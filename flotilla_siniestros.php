<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
requerir_login();
$titulo_pagina = 'Flotilla — Siniestros';
$pagina_activa = 'flotilla_siniestros';
require_once __DIR__ . '/config/header.php';
require_once __DIR__ . '/config/flotilla_nav.php';

$u = usuario_actual();
$ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursal_filtro = $ver_todas ? (int) input('sucursal', 0) : (int) $u['sucursal_id'];

$suc_join  = $sucursal_filtro ? " AND v.sucursal_id=:sid " : "";
$suc_param = $sucursal_filtro ? ['sid'=>$sucursal_filtro] : [];

$siniestros = db_all(
    "SELECT s.*, COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) vehiculo_nombre, v.placas
     FROM flotilla_siniestros s
     INNER JOIN flotilla_vehiculos v ON v.id=s.vehiculo_id $suc_join
     ORDER BY s.fecha DESC",
    $suc_param
);
$fm = flash_get();
?>
<div class="space-y-4 animate-fade-in">
<?php foreach ($fm as $f): ?><div class="px-4 py-3 rounded-lg text-sm <?= $f['tipo']==='success'?'bg-emerald-50 text-emerald-800 border border-emerald-200':'bg-red-50 text-red-800 border border-red-200' ?>"><?= e($f['mensaje']) ?></div><?php endforeach; ?>
<h1 class="text-xl font-display font-bold text-zinc-900">Siniestros</h1>
<?php if (empty($siniestros)): ?>
<div class="text-center py-20 text-zinc-400">
    <i data-lucide="shield-alert" class="w-14 h-14 mx-auto mb-3 opacity-25"></i>
    <p class="text-base font-medium text-zinc-500">Sin siniestros registrados</p>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
<table class="w-full text-sm">
<thead class="bg-zinc-50 border-b border-zinc-200"><tr class="text-left text-xs font-semibold text-zinc-500 uppercase tracking-wide">
<th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Vehículo</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Costo</th>
</tr></thead>
<tbody class="divide-y divide-zinc-100">
<?php foreach ($siniestros as $s): ?>
<tr class="hover:bg-zinc-50">
<td class="px-4 py-2.5 text-zinc-700"><?= fmt_fecha($s['fecha'] ?? '',false) ?></td>
<td class="px-4 py-2.5 font-medium text-zinc-900"><?= e($s['vehiculo_nombre']) ?><br><span class="text-xs text-zinc-500 font-mono"><?= e($s['placas']) ?></span></td>
<td class="px-4 py-2.5 text-zinc-600"><?= e($s['tipo'] ?? '—') ?></td>
<td class="px-4 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full <?= ($s['estado']??'')!=='cerrado'?'bg-amber-100 text-amber-800':'bg-zinc-100 text-zinc-600' ?>"><?= e($s['estado'] ?? '—') ?></span></td>
<td class="px-4 py-2.5 text-right"><?= isset($s['monto_reparacion']) && $s['monto_reparacion'] ? '$'.number_format($s['monto_reparacion'],2) : '—' ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/config/footer.php'; ?>
