<?php
/**
 * config/flotilla_nav.php
 * Sub-navegación horizontal del módulo Flotilla.
 * Incluir DESPUÉS de config/header.php en cada página de flotilla.
 */

$_fnav = [
    'flotilla_vehiculos'    => ['flotilla_vehiculos.php',    'car',              'Vehículos'],
    'flotilla_conductores'  => ['flotilla_conductores.php',  'user-check',       'Conductores'],
    'flotilla_combustible'  => ['flotilla_combustible.php',  'fuel',             'Combustible'],
    'flotilla_mantenimiento'=> ['flotilla_mantenimiento.php','wrench',           'Mantenimiento'],
    'flotilla_documentos'   => ['flotilla_documentos.php',   'file-check',       'Documentos'],
    'flotilla_checklist'    => ['flotilla_checklist.php',    'clipboard-check',  'Checklist'],
    'flotilla_siniestros'   => ['flotilla_siniestros.php',   'shield-alert',     'Siniestros'],
    'flotilla_multas'       => ['flotilla_multas.php',       'ticket-x',         'Multas'],
    'flotilla_reportes'     => ['flotilla_reportes.php',     'bar-chart-2',      'Reportes'],
];
?>
<div class="bg-white border-b border-zinc-200 mb-6 -mt-2">
    <div class="max-w-full overflow-x-auto">
        <nav class="flex items-center gap-0 px-2">
            <?php foreach ($_fnav as $key => [$archivo, $icono, $label]): ?>
            <a href="<?= url($archivo) ?>"
               class="flex items-center gap-1.5 px-3 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                      <?= $pagina_activa === $key
                            ? 'border-bacal-700 text-bacal-700'
                            : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' ?>">
                <i data-lucide="<?= $icono ?>" class="w-4 h-4 flex-shrink-0"></i>
                <span><?= $label ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
