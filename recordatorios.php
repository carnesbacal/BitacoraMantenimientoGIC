<?php
/**
 * ============================================================================
 * recordatorios.php - Mis recordatorios programados
 * ============================================================================
 * El usuario puede crear recordatorios para fechas futuras.
 * En la fecha programada, el cron los convierte en notificaciones in-app.
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/comunicacion_helpers.php';

requerir_login();
$u = usuario_actual();

$errores = [];

// ----------------------------------------------------------------------------
// Procesar POST
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token inválido.';
    } else {
        $op = (string) input('op', '');

        if ($op === 'crear') {
            $titulo = trim((string) input('titulo', ''));
            $mensaje = trim((string) input('mensaje', ''));
            $fecha = (string) input('fecha_envio', '');
            $enlace = trim((string) input('enlace', ''));

            if ($titulo === '') $errores[] = 'El título es obligatorio.';
            if ($fecha === '') $errores[] = 'La fecha de envío es obligatoria.';
            else {
                $ts = strtotime($fecha);
                if ($ts === false || $ts < time()) {
                    $errores[] = 'La fecha debe ser en el futuro.';
                }
            }

            if (empty($errores)) {
                $fecha_normalizada = date('Y-m-d H:i:s', strtotime($fecha));
                $rid = crear_recordatorio(
                    (int) $u['id'], $titulo, $mensaje ?: null,
                    $fecha_normalizada, $enlace ?: null,
                    null, null, (int) $u['id']
                );
                registrar_auditoria('crear_recordatorio', 'recordatorios', $rid, "Recordatorio: $titulo");
                flash_set('success', 'Recordatorio programado para ' . date('d/m/Y H:i', strtotime($fecha)));
                header('Location: ' . url('recordatorios.php'));
                exit;
            }
        } elseif ($op === 'eliminar') {
            $rid = (int) input('id', 0);
            if (eliminar_recordatorio($rid, (int) $u['id'], tiene_permiso('administrar'))) {
                flash_set('success', 'Recordatorio eliminado.');
            }
            header('Location: ' . url('recordatorios.php'));
            exit;
        }
    }
}

// Datos
$recordatorios = listar_recordatorios_usuario((int) $u['id'], 50);

// Separar pendientes vs enviados
$pendientes = array_filter($recordatorios, fn($r) => (int) $r['enviado'] === 0);
$enviados   = array_filter($recordatorios, fn($r) => (int) $r['enviado'] === 1);

$titulo_pagina = 'Mis recordatorios';
$pagina_activa = 'recordatorios';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-4xl mx-auto animate-fade-in space-y-5"
     x-data="{ mostrarForm: false }">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Mis recordatorios</h2>
            <p class="text-xs text-zinc-500 mt-0.5">Programa avisos para ti mismo en fechas futuras. Recibirás una notificación cuando lleguen.</p>
        </div>
        <button @click="mostrarForm = !mostrarForm"
                class="flex items-center gap-1.5 px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold shadow-sm">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span x-text="mostrarForm ? 'Cerrar' : 'Nuevo recordatorio'"></span>
        </button>
    </div>

    <!-- Errores -->
    <?php if (!empty($errores)): ?>
    <div class="px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs">
            <?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Formulario de nuevo recordatorio -->
    <div x-show="mostrarForm" x-cloak x-transition class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
        <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
            <i data-lucide="bell-plus" class="w-4 h-4 text-bacal-700"></i> Nuevo recordatorio
        </h3>

        <form method="POST" class="space-y-4">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="crear">

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Título *</label>
                <input type="text" name="titulo" required maxlength="200"
                       placeholder="ej. Llamar a proveedor, Revisar reporte mensual"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Mensaje (opcional)</label>
                <textarea name="mensaje" rows="3" maxlength="500"
                          placeholder="Detalles adicionales sobre el recordatorio"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Fecha y hora de envío *</label>
                    <input type="datetime-local" name="fecha_envio" required
                           min="<?= date('Y-m-d\TH:i', time() + 60) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Enlace (opcional)</label>
                    <input type="text" name="enlace" maxlength="255"
                           placeholder="incidencia_ver.php?id=15"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <!-- Atajos rápidos -->
            <div class="flex flex-wrap gap-2 pt-2 border-t border-zinc-100">
                <span class="text-[10px] text-zinc-500 font-bold uppercase mt-1">Atajos:</span>
                <button type="button"
                        onclick="document.querySelector('input[name=fecha_envio]').value = new Date(Date.now()+3600000).toISOString().slice(0,16)"
                        class="text-[11px] px-2 py-1 rounded border border-zinc-300 hover:bg-zinc-50">En 1 hora</button>
                <button type="button"
                        onclick="document.querySelector('input[name=fecha_envio]').value = new Date(Date.now()+86400000).toISOString().slice(0,16)"
                        class="text-[11px] px-2 py-1 rounded border border-zinc-300 hover:bg-zinc-50">Mañana</button>
                <button type="button"
                        onclick="const d=new Date(Date.now()+86400000); d.setHours(8,0,0,0); document.querySelector('input[name=fecha_envio]').value = d.toISOString().slice(0,16)"
                        class="text-[11px] px-2 py-1 rounded border border-zinc-300 hover:bg-zinc-50">Mañana 8 AM</button>
                <button type="button"
                        onclick="document.querySelector('input[name=fecha_envio]').value = new Date(Date.now()+7*86400000).toISOString().slice(0,16)"
                        class="text-[11px] px-2 py-1 rounded border border-zinc-300 hover:bg-zinc-50">En 1 semana</button>
            </div>

            <div class="flex justify-end gap-2 pt-3">
                <button type="button" @click="mostrarForm = false" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                    <i data-lucide="bell" class="w-4 h-4"></i> Programar
                </button>
            </div>
        </form>
    </div>

    <!-- Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
        <i data-lucide="info" class="w-5 h-5 text-blue-700 flex-shrink-0 mt-0.5"></i>
        <div class="text-xs text-blue-900 flex-1 leading-relaxed">
            Los recordatorios se procesan automáticamente con el cron <code class="font-mono bg-blue-100 px-1 rounded">cron/enviar_recordatorios.php</code>.
            Cuando llegue su fecha, recibirás una notificación en la <strong>campanita 🔔</strong> de arriba.
        </div>
    </div>

    <!-- Pendientes -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-100">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="clock" class="w-4 h-4 text-bacal-700"></i>
                Pendientes
                <span class="text-xs font-normal text-zinc-500">(<?= count($pendientes) ?>)</span>
            </h3>
        </div>

        <?php if (empty($pendientes)): ?>
        <div class="px-5 py-12 text-center">
            <i data-lucide="bell-off" class="w-10 h-10 mx-auto text-zinc-300 mb-2"></i>
            <p class="text-sm text-zinc-500">No tienes recordatorios pendientes.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-zinc-100">
            <?php foreach ($pendientes as $r):
                $ts = strtotime($r['fecha_envio']);
                $faltan = $ts - time();
                $faltan_str = $faltan < 3600 ? 'en menos de 1h'
                    : ($faltan < 86400 ? 'en ' . round($faltan / 3600) . 'h'
                    : 'en ' . round($faltan / 86400) . ' día(s)');
            ?>
            <div class="flex items-center gap-3 p-4 hover:bg-zinc-50">
                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="bell" class="w-5 h-5 text-amber-700"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-zinc-900 truncate"><?= e($r['titulo']) ?></div>
                    <?php if ($r['mensaje']): ?>
                    <div class="text-xs text-zinc-600 truncate"><?= e($r['mensaje']) ?></div>
                    <?php endif; ?>
                    <div class="text-[11px] text-zinc-500 mt-1 flex items-center gap-2">
                        <span><i data-lucide="calendar" class="w-3 h-3 inline -mt-0.5"></i> <?= e(date('d/M/Y H:i', $ts)) ?></span>
                        <span class="text-amber-700 font-semibold">· <?= e($faltan_str) ?></span>
                    </div>
                </div>
                <form method="POST" onsubmit="return confirm('¿Eliminar este recordatorio?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="p-2 rounded text-zinc-400 hover:text-bacal-700 hover:bg-zinc-100" title="Eliminar">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Enviados -->
    <?php if (!empty($enviados)): ?>
    <details class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <summary class="cursor-pointer px-5 py-3 border-b border-zinc-100 hover:bg-zinc-50">
            <h3 class="inline font-display text-base font-bold text-zinc-700">
                <i data-lucide="check-circle-2" class="w-4 h-4 inline text-emerald-600"></i>
                Enviados <span class="text-xs font-normal text-zinc-500">(<?= count($enviados) ?>)</span>
            </h3>
        </summary>
        <div class="divide-y divide-zinc-100">
            <?php foreach ($enviados as $r): ?>
            <div class="flex items-center gap-3 p-4">
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 flex-shrink-0"></i>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-zinc-700 truncate"><?= e($r['titulo']) ?></div>
                    <div class="text-[11px] text-zinc-500">Enviado <?= e(fmt_tiempo_relativo($r['enviado_en'])) ?></div>
                </div>
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="text-zinc-400 hover:text-bacal-700 p-1" title="Eliminar">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>
