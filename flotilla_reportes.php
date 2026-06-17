<?php
/**
 * flotilla_reportes.php - Reportes y KPIs de la flotilla
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
requerir_login();

$titulo_pagina = 'Flotilla — Reportes';
$pagina_activa = 'flotilla_reportes';
require_once __DIR__ . '/config/header.php';
require_once __DIR__ . '/config/flotilla_nav.php';

$u = usuario_actual();
$ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursal_filtro = $ver_todas ? (int) input('sucursal', 0) : (int) $u['sucursal_id'];

// ── Periodo ───────────────────────────────────────────────────────────────────
$desde = (string) input('desde', date('Y-m-01'));
$hasta = (string) input('hasta', date('Y-m-d'));
// Botones de periodo rápido
$periodo = (string) input('periodo','');
if ($periodo === 'mes')       { $desde = date('Y-m-01'); $hasta = date('Y-m-d'); }
if ($periodo === 'mes_ant')   { $desde = date('Y-m-01', strtotime('first day of last month')); $hasta = date('Y-m-t', strtotime('last month')); }
if ($periodo === 'anio')      { $desde = date('Y-01-01'); $hasta = date('Y-m-d'); }
if ($periodo === 'ultimos90') { $desde = date('Y-m-d', strtotime('-90 days')); $hasta = date('Y-m-d'); }

$suc_join  = $sucursal_filtro ? " AND v.sucursal_id=:sid " : "";
$suc_param = $sucursal_filtro ? ['sid' => $sucursal_filtro] : [];

$params_rango = array_merge($suc_param, ['desde' => $desde, 'hasta' => $hasta]);

// ── KPIs ──────────────────────────────────────────────────────────────────────
$gasto_total = (float) db_one(
    "SELECT COALESCE(SUM(g.monto),0) tot FROM flotilla_gastos g INNER JOIN flotilla_vehiculos v ON v.id=g.vehiculo_id $suc_join
     WHERE g.fecha BETWEEN :desde AND :hasta", $params_rango
)['tot'];

$gasto_comb = (float) db_one(
    "SELECT COALESCE(SUM(g.monto),0) tot FROM flotilla_gastos g
     INNER JOIN flotilla_vehiculos v ON v.id=g.vehiculo_id $suc_join
     INNER JOIN flotilla_categorias_gasto cat ON cat.id=g.categoria_id
     WHERE g.fecha BETWEEN :desde AND :hasta AND cat.nombre LIKE '%Combustible%'", $params_rango
)['tot'];

$gasto_mant = (float) db_one(
    "SELECT COALESCE(SUM(g.monto),0) tot FROM flotilla_gastos g
     INNER JOIN flotilla_vehiculos v ON v.id=g.vehiculo_id $suc_join
     INNER JOIN flotilla_categorias_gasto cat ON cat.id=g.categoria_id
     WHERE g.fecha BETWEEN :desde AND :hasta AND cat.nombre LIKE '%Manteni%'", $params_rango
)['tot'];

$litros_total = (float) db_one(
    "SELECT COALESCE(SUM(c.litros),0) tot FROM flotilla_combustible c INNER JOIN flotilla_vehiculos v ON v.id=c.vehiculo_id $suc_join
     WHERE DATE(c.fecha) BETWEEN :desde AND :hasta", $params_rango
)['tot'];

$rend_prom = db_one(
    "SELECT AVG(c.rendimiento_kml) rend FROM flotilla_combustible c INNER JOIN flotilla_vehiculos v ON v.id=c.vehiculo_id $suc_join
     WHERE DATE(c.fecha) BETWEEN :desde AND :hasta AND c.rendimiento_kml > 0", $params_rango
)['rend'];

$servicios_mant = (int) db_one(
    "SELECT COUNT(*) cnt FROM flotilla_mant_historial h INNER JOIN flotilla_vehiculos v ON v.id=h.vehiculo_id $suc_join
     WHERE h.fecha BETWEEN :desde AND :hasta", $params_rango
)['cnt'];

$km_total = (int) db_one(
    "SELECT COALESCE(SUM(c.km_recorridos),0) tot FROM flotilla_combustible c INNER JOIN flotilla_vehiculos v ON v.id=c.vehiculo_id $suc_join
     WHERE DATE(c.fecha) BETWEEN :desde AND :hasta", $params_rango
)['tot'];

$costo_km = ($km_total > 0) ? round($gasto_total / $km_total, 2) : null;

// ── Tendencia mensual ────────────────────────────────────────────────────────
$tendencia = db_all(
    "SELECT DATE_FORMAT(g.fecha,'%Y-%m') mes,
            SUM(CASE WHEN cat.nombre LIKE '%Combustible%' THEN g.monto ELSE 0 END) comb,
            SUM(CASE WHEN cat.nombre LIKE '%Manteni%' THEN g.monto ELSE 0 END) mant,
            SUM(CASE WHEN cat.nombre NOT LIKE '%Combustible%' AND cat.nombre NOT LIKE '%Manteni%' THEN g.monto ELSE 0 END) otros
     FROM flotilla_gastos g
     INNER JOIN flotilla_vehiculos v ON v.id=g.vehiculo_id $suc_join
     INNER JOIN flotilla_categorias_gasto cat ON cat.id=g.categoria_id
     WHERE g.fecha BETWEEN :desde AND :hasta
     GROUP BY DATE_FORMAT(g.fecha,'%Y-%m')
     ORDER BY mes",
    $params_rango
);
$tend_labels = array_column($tendencia, 'mes');
$tend_comb   = array_map('floatval', array_column($tendencia, 'comb'));
$tend_mant   = array_map('floatval', array_column($tendencia, 'mant'));
$tend_otros  = array_map('floatval', array_column($tendencia, 'otros'));

// ── Por vehículo ──────────────────────────────────────────────────────────────
$por_vehiculo = db_all(
    "SELECT v.id, COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) nombre, v.placas,
            COALESCE(SUM(g.monto),0) gasto_total,
            COALESCE(SUM(CASE WHEN cat.nombre LIKE '%Combustible%' THEN g.monto ELSE 0 END),0) gasto_comb,
            COALESCE(SUM(CASE WHEN cat.nombre LIKE '%Manteni%' THEN g.monto ELSE 0 END),0) gasto_mant,
            COALESCE((SELECT SUM(c2.km_recorridos) FROM flotilla_combustible c2 WHERE c2.vehiculo_id=v.id AND DATE(c2.fecha) BETWEEN :desde AND :hasta),0) km_rec
     FROM flotilla_vehiculos v $suc_join
     LEFT JOIN flotilla_gastos g ON g.vehiculo_id=v.id AND g.fecha BETWEEN :desde2 AND :hasta2
     LEFT JOIN flotilla_categorias_gasto cat ON cat.id=g.categoria_id
     WHERE v.activo=1
     GROUP BY v.id, v.alias, v.marca, v.modelo, v.placas
     ORDER BY gasto_total DESC",
    array_merge($params_rango, ['desde2'=>$desde,'hasta2'=>$hasta])
);

// ── Alertas ──────────────────────────────────────────────────────────────────
$alertas_rep = [];

$docs_vencidos = (int) db_one(
    "SELECT COUNT(*) c FROM flotilla_documentos d LEFT JOIN flotilla_vehiculos v ON v.id=d.vehiculo_id $suc_join WHERE d.estado='vencido'",
    $suc_param
)['c'];
if ($docs_vencidos) $alertas_rep[] = ['tipo'=>'critica','icono'=>'file-x','msg'=>"$docs_vencidos documento(s) vencido(s)",'url'=>url('flotilla_documentos.php?estado=vencido')];

$docs_pv = (int) db_one(
    "SELECT COUNT(*) c FROM flotilla_documentos d LEFT JOIN flotilla_vehiculos v ON v.id=d.vehiculo_id $suc_join WHERE d.estado='por_vencer'",
    $suc_param
)['c'];
if ($docs_pv) $alertas_rep[] = ['tipo'=>'warning','icono'=>'file-clock','msg'=>"$docs_pv documento(s) por vencer",'url'=>url('flotilla_documentos.php?estado=por_vencer')];

$multas_pend = db_one(
    "SELECT COUNT(*) c, COALESCE(SUM(m.monto_original),0) tot FROM flotilla_multas m INNER JOIN flotilla_vehiculos v ON v.id=m.vehiculo_id $suc_join WHERE m.estado='pendiente'",
    $suc_param
);
if ((int)($multas_pend['c']??0)) $alertas_rep[] = ['tipo'=>'warning','icono'=>'ticket-x','msg'=>"{$multas_pend['c']} multa(s) sin pagar · \${$multas_pend['tot']}",'url'=>url('flotilla_multas.php')];

// Mantenimientos vencidos (km_actual >= proximo_km de último historial)
$mant_vencidos = (int) db_one(
    "SELECT COUNT(DISTINCT h.vehiculo_id) c
     FROM flotilla_mant_historial h
     INNER JOIN flotilla_vehiculos v ON v.id=h.vehiculo_id $suc_join
     INNER JOIN (SELECT vehiculo_id,nombre,MAX(id) last_id FROM flotilla_mant_historial GROUP BY vehiculo_id,nombre) ul ON ul.last_id=h.id
     WHERE h.proximo_km IS NOT NULL AND v.km_actual >= h.proximo_km",
    $suc_param
)['c'];
if ($mant_vencidos) $alertas_rep[] = ['tipo'=>'warning','icono'=>'wrench','msg'=>"$mant_vencidos servicio(s) de mantenimiento vencido(s)",'url'=>url('flotilla_mantenimiento.php?vista=pendientes')];

$siniestros_abiertos = (int) db_one(
    "SELECT COUNT(*) c FROM flotilla_siniestros s INNER JOIN flotilla_vehiculos v ON v.id=s.vehiculo_id $suc_join WHERE s.estado != 'cerrado'",
    $suc_param
)['c'];
if ($siniestros_abiertos) $alertas_rep[] = ['tipo'=>'critica','icono'=>'shield-alert','msg'=>"$siniestros_abiertos siniestro(s) sin cerrar",'url'=>url('flotilla_siniestros.php')];

$sucursales = $ver_todas ? db_all("SELECT id,nombre FROM sucursales WHERE activo=1 ORDER BY nombre") : [];
?>

<div class="space-y-6 animate-fade-in">
<div class="flex items-center justify-between">
    <h1 class="text-xl font-display font-bold text-zinc-900">Reportes de Flotilla</h1>
    <a href="<?= url('flotilla_reportes_export.php?desde='.urlencode($desde).'&hasta='.urlencode($hasta).($sucursal_filtro?"&sucursal=$sucursal_filtro":'')) ?>"
       class="flex items-center gap-2 px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm hover:bg-zinc-50">
        <i data-lucide="download" class="w-4 h-4"></i> Exportar Excel
    </a>
</div>

<!-- Filtros de periodo -->
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-4 space-y-3">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <?php if ($ver_todas): ?>
        <div>
            <label class="block text-xs font-bold text-zinc-600 mb-1">Sucursal</label>
            <select name="sucursal" class="px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                <option value="">Todas</option>
                <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $sucursal_filtro==$s['id']?'selected':'' ?>><?= e($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label class="block text-xs font-bold text-zinc-600 mb-1">Desde</label>
            <input type="date" name="desde" value="<?= e($desde) ?>" class="px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
        </div>
        <div>
            <label class="block text-xs font-bold text-zinc-600 mb-1">Hasta</label>
            <input type="date" name="hasta" value="<?= e($hasta) ?>" class="px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-zinc-800 text-white text-sm">Aplicar</button>
    </form>
    <!-- Botones rápidos -->
    <div class="flex flex-wrap gap-2">
        <?php
        $periodos = ['mes'=>'Este mes','mes_ant'=>'Mes anterior','anio'=>'Este año','ultimos90'=>'Últimos 90 días'];
        foreach ($periodos as $pkey=>$plabel):
            $qs = http_build_query(['periodo'=>$pkey]+($sucursal_filtro?['sucursal'=>$sucursal_filtro]:[]));
        ?>
        <a href="<?= url('flotilla_reportes.php?'.$qs) ?>"
           class="px-3 py-1.5 rounded-lg border text-xs font-medium <?= $periodo===$pkey?'border-bacal-700 bg-bacal-50 text-bacal-800':'border-zinc-300 text-zinc-600 hover:bg-zinc-50' ?>">
            <?= $plabel ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Alertas -->
<?php if (!empty($alertas_rep)): ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <?php foreach ($alertas_rep as $al):
        $cls = $al['tipo']==='critica' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-800';
    ?>
    <a href="<?= $al['url'] ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl border <?= $cls ?> hover:brightness-95 transition-all">
        <i data-lucide="<?= $al['icono'] ?>" class="w-5 h-5 flex-shrink-0"></i>
        <span class="text-sm font-medium"><?= e($al['msg']) ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php
    $kpis = [
        ['Gasto total', '$'.number_format($gasto_total,2), 'banknote', '#D97706'],
        ['Combustible', '$'.number_format($gasto_comb,2), 'fuel', '#F59E0B'],
        ['Mantenimiento', '$'.number_format($gasto_mant,2), 'wrench', '#2563EB'],
        ['Litros cargados', number_format($litros_total,1).' L', 'droplet', '#0EA5E9'],
        ['Rendimiento prom.', $rend_prom ? round($rend_prom,2).' km/L' : '—', 'gauge', '#7C3AED'],
        ['Servicios de mant.', $servicios_mant, 'clipboard-check', '#16A34A'],
        ['KM recorridos', number_format($km_total), 'map-pin', '#6B7280'],
        ['Costo por km', $costo_km ? '$'.number_format($costo_km,2) : '—', 'trending-up', '#DC2626'],
    ];
    foreach ($kpis as [$lbl,$val,$ico,$col]):
    ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-4">
        <div class="flex items-center gap-2 mb-1">
            <i data-lucide="<?= $ico ?>" class="w-4 h-4" style="color:<?= $col ?>"></i>
            <span class="text-xs text-zinc-500"><?= $lbl ?></span>
        </div>
        <p class="text-xl font-bold text-zinc-900"><?= $val ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Gráfica de tendencia mensual -->
<?php if (!empty($tendencia)): ?>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
    <h2 class="text-sm font-bold text-zinc-700 mb-4">Gasto mensual por categoría</h2>
    <div style="height:280px">
        <canvas id="chart-tendencia"></canvas>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    const ctx = document.getElementById('chart-tendencia');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($tend_labels) ?>,
            datasets: [
                { label:'Combustible', data: <?= json_encode($tend_comb) ?>,  backgroundColor:'#F59E0B', stack:'g' },
                { label:'Mantenimiento', data: <?= json_encode($tend_mant) ?>, backgroundColor:'#3B82F6', stack:'g' },
                { label:'Otros',  data: <?= json_encode($tend_otros) ?>, backgroundColor:'#9CA3AF', stack:'g' },
            ]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ position:'bottom' } },
            scales:{ x:{ stacked:true }, y:{ stacked:true, ticks:{ callback: v=>'$'+v.toLocaleString() } } }
        }
    });
})();
</script>
<?php endif; ?>

<!-- Rendimiento por vehículo -->
<?php if (!empty($por_vehiculo)): ?>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-zinc-100">
        <h2 class="text-sm font-bold text-zinc-700">Gasto por vehículo</h2>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-zinc-50">
            <tr class="text-left text-xs font-semibold text-zinc-500 uppercase tracking-wide">
                <th class="px-4 py-3">Vehículo</th>
                <th class="px-4 py-3 text-right">Gasto total</th>
                <th class="px-4 py-3 text-right">Combustible</th>
                <th class="px-4 py-3 text-right">Mantenimiento</th>
                <th class="px-4 py-3 text-right">KM recorridos</th>
                <th class="px-4 py-3 text-right">$/km</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            <?php foreach ($por_vehiculo as $pv):
                $cxkm = ($pv['km_rec']>0) ? round($pv['gasto_total']/$pv['km_rec'],2) : null;
            ?>
            <tr class="hover:bg-zinc-50">
                <td class="px-4 py-2.5 font-medium text-zinc-900"><?= e($pv['nombre']) ?><br><span class="text-xs text-zinc-500 font-mono"><?= e($pv['placas']) ?></span></td>
                <td class="px-4 py-2.5 text-right font-semibold text-zinc-900">$<?= number_format($pv['gasto_total'],2) ?></td>
                <td class="px-4 py-2.5 text-right text-amber-700">$<?= number_format($pv['gasto_comb'],2) ?></td>
                <td class="px-4 py-2.5 text-right text-blue-700">$<?= number_format($pv['gasto_mant'],2) ?></td>
                <td class="px-4 py-2.5 text-right text-zinc-600"><?= number_format($pv['km_rec']) ?></td>
                <td class="px-4 py-2.5 text-right text-zinc-600"><?= $cxkm ? '$'.number_format($cxkm,2) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>
