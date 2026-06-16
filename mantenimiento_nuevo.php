<?php
/**
 * ============================================================================
 * mantenimiento_nuevo.php - Crear nuevo mantenimiento programado
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/mantenimientos_helpers.php';
require_once __DIR__ . '/config/notificaciones_helpers.php';

requerir_login();

if (!puede_administrar_mantenimientos()) {
    flash_set('error', 'No tienes permiso para programar mantenimientos.');
    header('Location: ' . url('mantenimientos.php'));
    exit;
}

$u = usuario_actual();
$equipo_id_pre = (int) input('equipo_id', 0);

$errores = [];

// ----------------------------------------------------------------------------
// Procesar POST
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token de seguridad inválido.';
    } else {
        $equipo_id    = (int) input('equipo_id', 0);
        $titulo       = trim((string) input('titulo', ''));
        $descripcion  = trim((string) input('descripcion', ''));
        $fecha        = (string) input('fecha_programada', '');
        $hora         = (string) input('hora_programada', '');
        $asignado_id  = (int) input('asignado_a_id', 0);
        $proveedor_id = (int) input('proveedor_id', 0);
        $es_recurrente = input('es_recurrente') ? 1 : 0;
        $recurrencia_tipo = (string) input('recurrencia_tipo', '');
        $recurrencia_valor = (int) input('recurrencia_valor', 0);

        // Validaciones
        if ($equipo_id <= 0) $errores[] = 'Selecciona un equipo.';
        if ($titulo === '') $errores[] = 'El título es obligatorio.';
        if ($fecha === '') $errores[] = 'La fecha programada es obligatoria.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $errores[] = 'La fecha no tiene formato válido.';

        if ($es_recurrente) {
            if (!in_array($recurrencia_tipo, ['dias','semanas','meses','anios'], true)) {
                $errores[] = 'Tipo de recurrencia inválido.';
            }
            if ($recurrencia_valor < 1) {
                $errores[] = 'El valor de recurrencia debe ser al menos 1.';
            }
        }

        if (empty($errores)) {
            // Si la fecha es <= 3 días, marcar como próximo
            $dias_hasta = (strtotime($fecha) - strtotime(date('Y-m-d'))) / 86400;
            $estado_inicial = $dias_hasta <= 3 ? 'proximo' : 'programado';
            if ($dias_hasta < 0) $estado_inicial = 'vencido';

            db_exec(
                "INSERT INTO mantenimientos
                 (equipo_id, titulo, descripcion, fecha_programada, hora_programada,
                  asignado_a_id, proveedor_id, estado,
                  es_recurrente, recurrencia_tipo, recurrencia_valor, creado_por_id)
                 VALUES (:eid, :tit, :desc, :fp, :hp, :aid, :pid, :est,
                         :rec, :rt, :rv, :cid)",
                [
                    'eid'  => $equipo_id,
                    'tit'  => mb_substr($titulo, 0, 200),
                    'desc' => $descripcion ?: null,
                    'fp'   => $fecha,
                    'hp'   => $hora ?: null,
                    'aid'  => $asignado_id ?: null,
                    'pid'  => $proveedor_id ?: null,
                    'est'  => $estado_inicial,
                    'rec'  => $es_recurrente,
                    'rt'   => $es_recurrente ? $recurrencia_tipo : null,
                    'rv'   => $es_recurrente ? $recurrencia_valor : null,
                    'cid'  => $u['id'],
                ]
            );
            $mant_id = (int) db_last_id();

            registrar_auditoria('crear_mantenimiento', 'mantenimientos', $mant_id, "Mantenimiento: $titulo");

            // Notificar al técnico asignado
            if ($asignado_id > 0 && $asignado_id !== (int) $u['id']) {
                $equipo_info = db_one("SELECT codigo_inventario FROM equipos WHERE id = :id", ['id' => $equipo_id]);
                crear_notificacion(
                    $asignado_id,
                    'asignacion',
                    "Mantenimiento asignado: $titulo",
                    "Equipo " . ($equipo_info['codigo_inventario'] ?? '') . " · " . date('d/m/Y', strtotime($fecha)),
                    url('mantenimiento_ver.php?id=' . $mant_id),
                    'mantenimientos',
                    $mant_id
                );
            }

            flash_set('success', "Mantenimiento programado para " . date('d/m/Y', strtotime($fecha)));
            header('Location: ' . url('mantenimiento_ver.php?id=' . $mant_id));
            exit;
        }
    }

    // Restaurar valor del equipo si hubo error
    $equipo_id_pre = (int) input('equipo_id', 0);
}

// ----------------------------------------------------------------------------
// Catálogos
// ----------------------------------------------------------------------------
$equipos_list = db_all(
    "SELECT e.id, e.codigo_inventario, e.nombre, s.nombre sucursal_nombre
     FROM equipos e
     INNER JOIN sucursales s ON e.sucursal_id = s.id
     WHERE e.activo = 1 AND e.estado_vida != 'dado_de_baja'
     ORDER BY s.nombre, e.codigo_inventario"
);

$tecnicos = db_all(
    "SELECT u.id, u.nombre_completo FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE u.activo = 1 AND r.puede_resolver = 1
     ORDER BY u.nombre_completo"
);

$proveedores = db_all("SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre");

$titulo_pagina = 'Nuevo mantenimiento';
$pagina_activa = 'mantenimientos';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-3xl mx-auto animate-fade-in">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= url('mantenimientos.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Nuevo mantenimiento</h2>
            <p class="text-xs text-zinc-500">Programa un mantenimiento preventivo o correctivo</p>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="mb-5 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs">
            <?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5"
          x-data="{ esRecurrente: <?= input('es_recurrente') ? 'true' : 'false' ?> }">
        <?= csrf_input() ?>

        <!-- Información básica -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-bacal-700"></i> Información del mantenimiento
            </h3>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Equipo *</label>
                <select name="equipo_id" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">— Selecciona un equipo —</option>
                    <?php foreach ($equipos_list as $e):
                        $sel = $equipo_id_pre;
                    ?>
                    <option value="<?= $e['id'] ?>" <?= $sel == $e['id'] ? 'selected' : '' ?>>
                        <?= e($e['codigo_inventario']) ?> · <?= e($e['nombre']) ?> (<?= e($e['sucursal_nombre']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Título *</label>
                <input type="text" name="titulo" required maxlength="200"
                       value="<?= e((string) input('titulo', '')) ?>"
                       placeholder="ej. Calibración trimestral, Cambio de tóner, Limpieza interna"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Descripción</label>
                <textarea name="descripcion" rows="3"
                          placeholder="Detalles del trabajo a realizar"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e((string) input('descripcion', '')) ?></textarea>
            </div>
        </div>

        <!-- Programación -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="calendar-clock" class="w-4 h-4 text-bacal-700"></i> Programación
            </h3>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Fecha *</label>
                    <input type="date" name="fecha_programada" required
                           value="<?= e((string) input('fecha_programada', '')) ?>"
                           min="<?= date('Y-m-d') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Hora (opcional)</label>
                    <input type="time" name="hora_programada"
                           value="<?= e((string) input('hora_programada', '')) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <!-- Recurrencia -->
            <div class="border-t border-zinc-100 pt-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="es_recurrente" value="1"
                           x-model="esRecurrente"
                           class="rounded text-bacal-700 focus:ring-bacal-500">
                    <span class="text-sm font-semibold text-zinc-700">Mantenimiento recurrente</span>
                </label>
                <p class="text-[10px] text-zinc-500 mt-1 ml-6">Cuando este mantenimiento se complete, el sistema generará automáticamente el siguiente.</p>

                <div x-show="esRecurrente" x-cloak x-transition class="mt-3 grid grid-cols-2 gap-4 pl-6">
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Cada</label>
                        <input type="number" name="recurrencia_valor" min="1" max="365"
                               value="<?= e((string) input('recurrencia_valor', '3')) ?>"
                               class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Unidad</label>
                        <select name="recurrencia_tipo" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <?php $rt = (string) input('recurrencia_tipo', 'meses'); ?>
                            <option value="dias" <?= $rt === 'dias' ? 'selected' : '' ?>>Días</option>
                            <option value="semanas" <?= $rt === 'semanas' ? 'selected' : '' ?>>Semanas</option>
                            <option value="meses" <?= $rt === 'meses' ? 'selected' : '' ?>>Meses</option>
                            <option value="anios" <?= $rt === 'anios' ? 'selected' : '' ?>>Años</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Asignación -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4 text-bacal-700"></i> Quién lo hace
            </h3>
            <p class="text-[11px] text-zinc-500">Puede ser un técnico interno o un proveedor externo (o ambos).</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Técnico asignado</label>
                    <select name="asignado_a_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= (string) input('asignado_a_id') === (string) $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['nombre_completo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Proveedor externo</label>
                    <select name="proveedor_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Ninguno —</option>
                        <?php foreach ($proveedores as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (string) input('proveedor_id') === (string) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= url('mantenimientos.php') ?>" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                <i data-lucide="calendar-plus" class="w-4 h-4"></i> Programar mantenimiento
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>
