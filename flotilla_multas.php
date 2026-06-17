<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
requerir_login();
$titulo_pagina = 'Flotilla — Multas';
$pagina_activa = 'flotilla_multas';
require_once __DIR__ . '/config/header.php';
require_once __DIR__ . '/config/flotilla_nav.php';

$u = usuario_actual();
$ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursal_filtro = $ver_todas ? (int) input('sucursal', 0) : (int) $u['sucursal_id'];
$suc_join  = $sucursal_filtro ? " AND v.sucursal_id=:sid " : "";
$suc_param = $sucursal_filtro ? ['sid'=>$sucursal_filtro] : [];

$multas = db_all(
    "SELECT m.*, COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) vehiculo_nombre, v.placas,
            c.nombre_completo conductor_nombre
     FROM flotilla_multas m
     INNER JOIN flotilla_vehiculos v ON v.id=m.vehiculo_id $suc_join
     LEFT JOIN flotilla_conductores c ON c.id=m.conductor_id
     ORDER BY m.fecha_infraccion DESC",
    $suc_param
);
$total_pendiente = array_sum(array_map(fn($m) => ($m['estado']??'') !== 'pagada' ? ($m['monto_original']??0) : 0, $multas));
$fm = flash_get();
?>
<div class="space-y-4 animate-fade-in">
<?php foreach ($fm as $f): ?><div class="px-4 py-3 rounded-lg text-sm <?= $f['tipo']==='success'?'bg-emerald-50 text-emerald-800 border border-emerald-200':'bg-red-50 text-red-800 border border-red-200' ?>"><?= e($f['mensaje']) ?></div><?php endforeach; ?>
<div class="flex items-center justify-between">
    <h1 class="text-xl font-display font-bold text-zinc-900">Multas</h1>
    <?php if ($total_pendiente > 0): ?>
    <span class="px-3 py-1.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm font-semibold">
        Pendiente por pagar: $<?= number_format($total_pendiente,2) ?>
    </span>
    <?php endif; ?>
</div>
<?php if (empty($multas)): ?>
<div class="text-center py-20 text-zinc-400">
    <i data-lucide="ticket-x" class="w-14 h-14 mx-auto mb-3 opacity-25"></i>
    <p class="text-base font-medium text-zinc-500">Sin multas registradas</p>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
<table class="w-full text-sm">
<thead class="bg-zinc-50 border-b border-zinc-200"><tr class="text-left text-xs font-semibold text-zinc-500 uppercase tracking-wide">
<th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Vehículo</th><th class="px-4 py-3">Conductor</th><th class="px-4 py-3">Concepto</th><th class="px-4 py-3 text-right">Monto</th><th class="px-4 py-3">Estado</th>
</tr></thead>
<tbody class="divide-y divide-zinc-100">
<?php foreach ($multas as $m): ?>
<tr class="hover:bg-zinc-50 <?= ($m['estado']??'')==='pendiente'?'bg-red-50':'' ?>">
<td class="px-4 py-2.5 text-zinc-700"><?= fmt_fecha($m['fecha_infraccion']??'',false) ?></td>
<td class="px-4 py-2.5 font-medium text-zinc-900"><?= e($m['vehiculo_nombre']) ?><br><span class="text-xs font-mono text-zinc-500"><?= e($m['placas']) ?></span></td>
<td class="px-4 py-2.5 text-zinc-500 text-xs"><?= e($m['conductor_nombre'] ?? '—') ?></td>
<td class="px-4 py-2.5 text-zinc-700"><?= e($m['motivo'] ?? '—') ?></td>
<td class="px-4 py-2.5 text-right font-semibold"><?= isset($m['monto_original']) ? '$'.number_format($m['monto_original'],2) : '—' ?></td>
<td class="px-4 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full <?= ($m['estado']??'')==='pagada'?'bg-emerald-100 text-emerald-800':'bg-red-100 text-red-800' ?>"><?= e(ucfirst($m['estado']??'—')) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/config/footer.php'; ?>
