<?php
/**
 * ============================================================================
 * admin/importar_historial.php - Historial de importaciones masivas
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin_helpers.php';
require_once __DIR__ . '/../config/importacion_helpers.php';

$u = usuario_actual();

$importaciones = listar_importaciones(50);

// Stats
$stats = db_one(
    "SELECT
        COUNT(*) AS total,
        SUM(exitosos) AS total_exitosos,
        SUM(fallidos) AS total_fallidos,
        MAX(creado_en) AS ultima
     FROM importaciones"
);

$titulo_pagina = 'Historial de importaciones';
$pagina_activa = 'admin_importar';
require_once __DIR__ . '/../config/header.php';
?>

<div class="max-w-6xl mx-auto animate-fade-in space-y-5">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="<?= url('admin/importar.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h2 class="font-display text-2xl font-extrabold text-zinc-900">Historial de importaciones</h2>
                <p class="text-xs text-zinc-500 mt-0.5">Registro de todas las cargas masivas realizadas.</p>
            </div>
        </div>
        <a href="<?= url('admin/importar.php') ?>" class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
            <i data-lucide="upload" class="w-4 h-4"></i> Nueva importación
        </a>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Total cargas</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= (int) ($stats['total'] ?? 0) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-emerald-700 uppercase tracking-wider font-bold mb-1">Registros exitosos</div>
            <div class="font-display text-2xl font-extrabold text-emerald-700"><?= number_format((int) ($stats['total_exitosos'] ?? 0)) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-bacal-700 uppercase tracking-wider font-bold mb-1">Registros fallidos</div>
            <div class="font-display text-2xl font-extrabold text-bacal-700"><?= number_format((int) ($stats['total_fallidos'] ?? 0)) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Última carga</div>
            <div class="font-display text-sm font-extrabold text-zinc-900">
                <?= $stats['ultima'] ? e(fmt_tiempo_relativo($stats['ultima'])) : '<span class="text-zinc-400">Nunca</span>' ?>
            </div>
        </div>
    </div>

    <!-- Listado -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <?php if (empty($importaciones)): ?>
        <div class="px-5 py-12 text-center">
            <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                <i data-lucide="upload-cloud" class="w-8 h-8 text-zinc-400"></i>
            </div>
            <p class="text-sm font-medium text-zinc-700 mb-1">Sin importaciones aún</p>
            <p class="text-xs text-zinc-500">Cuando realices una carga masiva, aparecerá aquí su registro.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 border-b border-zinc-200">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase">Fecha</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase">Tipo</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase">Archivo</th>
                        <th class="px-4 py-2.5 text-center text-[10px] font-bold text-zinc-500 uppercase">Total</th>
                        <th class="px-4 py-2.5 text-center text-[10px] font-bold text-zinc-500 uppercase">Exitosos</th>
                        <th class="px-4 py-2.5 text-center text-[10px] font-bold text-zinc-500 uppercase">Fallidos</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase">Por</th>
                        <th class="px-4 py-2.5 text-center text-[10px] font-bold text-zinc-500 uppercase">Errores</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($importaciones as $imp):
                        $errores = $imp['errores_json'] ? json_decode($imp['errores_json'], true) : [];
                    ?>
                    <tr class="hover:bg-zinc-50" x-data="{ abierto: false }">
                        <td class="px-4 py-3 text-xs text-zinc-700 whitespace-nowrap">
                            <?= e(fmt_fecha($imp['creado_en'])) ?>
                            <div class="text-[10px] text-zinc-400"><?= e(fmt_tiempo_relativo($imp['creado_en'])) ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <?php
                            $colores_tipo = [
                                'usuarios' => 'blue',
                                'equipos' => 'purple',
                                'incidencias' => 'amber',
                            ];
                            $color = $colores_tipo[$imp['tipo']] ?? 'zinc';
                            ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded
                                         bg-<?= $color ?>-100 text-<?= $color ?>-800"><?= e($imp['tipo']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-700 font-mono truncate max-w-xs" title="<?= e($imp['nombre_archivo']) ?>">
                            <?= e($imp['nombre_archivo']) ?>
                        </td>
                        <td class="px-4 py-3 text-center font-mono text-sm text-zinc-700"><?= (int) $imp['total_filas'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="font-mono text-sm font-bold text-emerald-700"><?= (int) $imp['exitosos'] ?></span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ((int) $imp['fallidos'] > 0): ?>
                            <span class="font-mono text-sm font-bold text-bacal-700"><?= (int) $imp['fallidos'] ?></span>
                            <?php else: ?>
                            <span class="text-zinc-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-700"><?= e($imp['realizado_por_nombre'] ?? 'Sistema') ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if (!empty($errores)): ?>
                            <button type="button" @click="abierto = !abierto"
                                    class="text-xs font-semibold text-bacal-700 hover:underline">
                                <span x-show="!abierto">Ver (<?= count($errores) ?>)</span>
                                <span x-show="abierto" x-cloak>Ocultar</span>
                            </button>
                            <?php else: ?>
                            <span class="text-zinc-400">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($errores)): ?>
                    <tr x-show="abierto" x-cloak>
                        <td colspan="8" class="px-4 py-3 bg-bacal-50/50 border-l-4 border-bacal-300">
                            <div class="text-xs text-bacal-900">
                                <strong class="block mb-2">Errores en esta importación:</strong>
                                <ul class="space-y-0.5 font-mono text-[11px] max-h-48 overflow-y-auto">
                                    <?php foreach ($errores as $err): ?>
                                    <li>• <?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
