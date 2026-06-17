<?php
/**
 * flotilla_combustible.php - Registro de cargas de combustible
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
requerir_login();

$u = usuario_actual();
$ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursal_filtro = $ver_todas ? (int) input('sucursal', 0) : (int) $u['sucursal_id'];

// ── Helpers de sucursal ──────────────────────────────────────────────────────
function _flot_where_suc(int $sid, string $alias = 'v'): array {
    if ($sid) return [" AND {$alias}.sucursal_id = :sid ", ['sid' => $sid]];
    return ['', []];
}

// ── POST ─────────────────────────────────────────────────────────────────────
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) { flash_set('error','Token inválido'); header('Location:'.url('flotilla_combustible.php')); exit; }
    $op = (string) input('op','');

    if ($op === 'crear') {
        $vid        = (int) input('vehiculo_id',0);
        $cid        = (int) input('conductor_id',0) ?: null;
        $fecha      = (string) input('fecha', date('Y-m-d H:i:s'));
        $km         = (int) input('km_odometro',0);
        $litros     = (float) input('litros',0);
        $precio     = (float) input('precio_litro',0);
        $tipo       = in_array(input('tipo_combustible'), ['gasolina_regular','gasolina_premium','diesel','gas']) ? input('tipo_combustible') : 'diesel';
        $estacion   = trim((string) input('estacion','')) ?: null;
        $ticket     = trim((string) input('ticket_numero','')) ?: null;
        $lleno      = input('es_tanque_lleno') ? 1 : 0;
        $notas      = trim((string) input('notas','')) ?: null;

        if (!$vid || $litros <= 0 || $precio <= 0 || $km <= 0) {
            flash_set('error','Vehículo, KM, litros y precio son obligatorios.');
        } else {
            // Calcular km_recorridos vs última carga
            $ultima = db_one(
                "SELECT km_odometro FROM flotilla_combustible WHERE vehiculo_id=:vid ORDER BY fecha DESC, id DESC LIMIT 1",
                ['vid'=>$vid]
            );
            $km_rec = $ultima ? max(0, $km - (int)$ultima['km_odometro']) : null;
            $rendimiento = ($km_rec && $litros > 0 && $lleno) ? round($km_rec / $litros, 3) : null;

            db_exec(
                "INSERT INTO flotilla_combustible
                 (vehiculo_id,conductor_id,fecha,km_odometro,litros,precio_litro,tipo_combustible,estacion,ticket_numero,es_tanque_lleno,km_recorridos,rendimiento_kml,notas,creado_por)
                 VALUES (:vid,:cid,:fec,:km,:lit,:pre,:tip,:est,:tck,:lle,:kmr,:ren,:not,:cp)",
                ['vid'=>$vid,'cid'=>$cid,'fec'=>$fecha,'km'=>$km,'lit'=>$litros,'pre'=>$precio,
                 'tip'=>$tipo,'est'=>$estacion,'tck'=>$ticket,'lle'=>$lleno,
                 'kmr'=>$km_rec,'ren'=>$rendimiento,'not'=>$notas,'cp'=>$u['id']]
            );
            $nuevo_id = db()->lastInsertId();

            // Actualizar km_actual del vehículo si es mayor
            db_exec("UPDATE flotilla_vehiculos SET km_actual=:km WHERE id=:vid AND km_actual < :km2",
                    ['km'=>$km,'vid'=>$vid,'km2'=>$km]);

            // Crear registro en flotilla_gastos
            $cat = db_one("SELECT id FROM flotilla_categorias_gasto WHERE nombre LIKE '%Combustible%' LIMIT 1");
            if ($cat) {
                db_exec(
                    "INSERT INTO flotilla_gastos (vehiculo_id,categoria_id,conductor_id,fecha,concepto,monto,km_odometro,combustible_id,creado_por)
                     VALUES (:vid,:cat,:cid,:fec,:con,:monto,:km,:comb,:cp)",
                    ['vid'=>$vid,'cat'=>$cat['id'],'cid'=>$cid,'fec'=>date('Y-m-d',strtotime($fecha)),
                     'con'=>'Combustible - '.$tipo,'monto'=>round($litros*$precio,2),
                     'km'=>$km,'comb'=>$nuevo_id,'cp'=>$u['id']]
                );
            }
            flash_set('success','Carga registrada correctamente.');
        }
        header('Location:'.url('flotilla_combustible.php?'.http_build_query(['mes'=>input('mes',''),'vehiculo_id'=>$vid]))); exit;
    }

    if ($op === 'eliminar') {
        $rid = (int) input('id',0);
        db_exec("DELETE FROM flotilla_combustible WHERE id=:id",['id'=>$rid]);
        flash_set('success','Registro eliminado.');
        header('Location:'.url('flotilla_combustible.php')); exit;
    }
}

$titulo_pagina = 'Flotilla — Combustible';
$pagina_activa = 'flotilla_combustible';
require_once __DIR__ . '/config/header.php';
require_once __DIR__ . '/config/flotilla_nav.php';

// ── Filtros ───────────────────────────────────────────────────────────────────
$f_mes = (string) input('mes', date('Y-m'));
$f_vid = (int) input('vehiculo_id',0);
[$w_suc, $p_suc] = _flot_where_suc($sucursal_filtro);

$where = "WHERE 1=1";
$params = [];
if ($f_mes) {
    $where .= " AND DATE_FORMAT(c.fecha,'%Y-%m')=:mes";
    $params['mes'] = $f_mes;
}
if ($f_vid) { $where .= " AND c.vehiculo_id=:vid"; $params['vid']=$f_vid; }

// Aplicar filtro sucursal via JOIN vehículo
$join_suc = $sucursal_filtro ? " AND v.sucursal_id=:sid " : "";
if ($sucursal_filtro) $params['sid'] = $sucursal_filtro;

$registros = db_all(
    "SELECT c.*, v.placas, v.alias, v.marca, v.modelo,
            CONCAT(COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)),' (',v.placas,')') vehiculo_label,
            con.nombre_completo conductor_nombre
     FROM flotilla_combustible c
     INNER JOIN flotilla_vehiculos v ON v.id=c.vehiculo_id $join_suc
     LEFT JOIN flotilla_conductores con ON con.id=c.conductor_id
     $where
     ORDER BY c.fecha DESC, c.id DESC",
    $params
);

// ── KPIs ──────────────────────────────────────────────────────────────────────
$kpi_cargas    = count($registros);
$kpi_litros    = array_sum(array_column($registros, 'litros'));
$kpi_costo     = array_sum(array_map(fn($r) => $r['litros'] * $r['precio_litro'], $registros));
$rend_vals     = array_filter(array_column($registros,'rendimiento_kml'), fn($v) => $v > 0);
$kpi_rendimiento = $rend_vals ? round(array_sum($rend_vals)/count($rend_vals),2) : null;

// Vehículos para select
$vehiculos = db_all(
    "SELECT id, CONCAT(COALESCE(alias,CONCAT(marca,' ',modelo)),' (',placas,')') label, km_actual
     FROM flotilla_vehiculos WHERE activo=1 ".($sucursal_filtro?" AND sucursal_id=:sid ":"")."ORDER BY marca,modelo",
    $sucursal_filtro ? ['sid'=>$sucursal_filtro] : []
);
$conductores = db_all("SELECT id,nombre_completo FROM flotilla_conductores WHERE activo=1 ORDER BY nombre_completo");

$fm = flash_get();
?>

<div class="space-y-5 animate-fade-in">

<?php foreach ($fm as $f): ?>
<div class="px-4 py-3 rounded-lg text-sm <?= $f['tipo']==='success'?'bg-emerald-50 text-emerald-800 border border-emerald-200':'bg-red-50 text-red-800 border border-red-200' ?>">
    <?= e($f['mensaje']) ?>
</div>
<?php endforeach; ?>

<!-- Encabezado -->
<div class="flex items-center justify-between">
    <h1 class="text-xl font-display font-bold text-zinc-900">Combustible</h1>
    <button onclick="document.getElementById('modal-carga').classList.remove('hidden')"
            class="flex items-center gap-2 px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
        <i data-lucide="plus" class="w-4 h-4"></i> Registrar carga
    </button>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php
    $kpis = [
        ['Cargas', $kpi_cargas, 'fuel', '#D97706'],
        ['Litros', number_format($kpi_litros,1).' L', 'droplet', '#2563EB'],
        ['Costo total', '$'.number_format($kpi_costo,2), 'banknote', '#16A34A'],
        ['Rendimiento prom.', $kpi_rendimiento ? $kpi_rendimiento.' km/L' : '—', 'gauge', '#7C3AED'],
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

<!-- Filtros -->
<form method="GET" class="flex flex-wrap items-center gap-2">
    <input type="month" name="mes" value="<?= e($f_mes) ?>"
           class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
    <select name="vehiculo_id" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
        <option value="">Todos los vehículos</option>
        <?php foreach ($vehiculos as $v): ?>
        <option value="<?= $v['id'] ?>" <?= $f_vid==$v['id']?'selected':'' ?>><?= e($v['label']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="px-4 py-2 rounded-lg bg-zinc-800 text-white text-sm">Filtrar</button>
    <a href="<?= url('flotilla_combustible.php') ?>" class="px-3 py-2 rounded-lg border border-zinc-300 text-zinc-600 text-sm">Limpiar</a>
</form>

<!-- Tabla -->
<?php if (empty($registros)): ?>
<div class="text-center py-12 text-zinc-400">
    <i data-lucide="fuel" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
    <p class="text-sm">Sin registros para el período seleccionado.</p>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200">
            <tr class="text-left text-xs font-semibold text-zinc-500 uppercase tracking-wide">
                <th class="px-4 py-3">Fecha</th>
                <th class="px-4 py-3">Vehículo</th>
                <th class="px-4 py-3">Conductor</th>
                <th class="px-4 py-3 text-right">KM</th>
                <th class="px-4 py-3 text-right">Litros</th>
                <th class="px-4 py-3 text-right">$/L</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3 text-right">km/L</th>
                <th class="px-4 py-3">Tipo</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            <?php foreach ($registros as $r): ?>
            <tr class="hover:bg-zinc-50">
                <td class="px-4 py-2.5 text-zinc-700"><?= fmt_fecha($r['fecha'],false) ?></td>
                <td class="px-4 py-2.5 text-zinc-900 font-medium"><?= e($r['vehiculo_label']) ?></td>
                <td class="px-4 py-2.5 text-zinc-500 text-xs"><?= e($r['conductor_nombre'] ?? '—') ?></td>
                <td class="px-4 py-2.5 text-right text-zinc-700"><?= number_format($r['km_odometro']) ?></td>
                <td class="px-4 py-2.5 text-right font-mono text-zinc-900"><?= number_format($r['litros'],3) ?></td>
                <td class="px-4 py-2.5 text-right text-zinc-600">$<?= number_format($r['precio_litro'],3) ?></td>
                <td class="px-4 py-2.5 text-right font-semibold text-zinc-900">$<?= number_format($r['litros']*$r['precio_litro'],2) ?></td>
                <td class="px-4 py-2.5 text-right text-zinc-500"><?= $r['rendimiento_kml'] ? number_format($r['rendimiento_kml'],2) : '—' ?></td>
                <td class="px-4 py-2.5">
                    <span class="text-xs bg-zinc-100 text-zinc-600 px-2 py-0.5 rounded-full"><?= str_replace('_',' ',e($r['tipo_combustible'])) ?></span>
                    <?php if ($r['es_tanque_lleno']): ?><span class="ml-1 text-xs text-emerald-600" title="Tanque lleno">●</span><?php endif; ?>
                </td>
                <td class="px-4 py-2.5">
                    <form method="POST" onsubmit="return confirm('¿Eliminar este registro?')" class="inline">
                        <?= csrf_input() ?><input type="hidden" name="op" value="eliminar"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="text-zinc-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</div>

<!-- Modal Registrar carga -->
<div id="modal-carga" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-5 border-b border-zinc-200">
            <h2 class="text-base font-bold text-zinc-900">Registrar carga de combustible</h2>
            <button onclick="document.getElementById('modal-carga').classList.add('hidden')" class="text-zinc-400 hover:text-zinc-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" id="form-carga" class="p-5 space-y-4">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="crear">
            <input type="hidden" name="mes" value="<?= e($f_mes) ?>">

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1">Vehículo *</label>
                <select name="vehiculo_id" id="sel-vehiculo" required
                        class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"
                        onchange="preCargarKm(this)">
                    <option value="">Seleccionar…</option>
                    <?php foreach ($vehiculos as $v): ?>
                    <option value="<?= $v['id'] ?>" data-km="<?= $v['km_actual'] ?>"><?= e($v['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Fecha y hora *</label>
                    <input type="datetime-local" name="fecha" value="<?= date('Y-m-d\TH:i') ?>" required
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">KM odómetro *</label>
                    <input type="number" name="km_odometro" id="inp-km" min="0" required
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Litros *</label>
                    <input type="number" name="litros" id="inp-litros" step="0.001" min="0.001" required
                           oninput="calcTotal()"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Precio/litro *</label>
                    <input type="number" name="precio_litro" id="inp-precio" step="0.001" min="0.001" required
                           oninput="calcTotal()"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
            <div class="bg-zinc-50 rounded-lg px-4 py-2 text-sm text-zinc-700 flex items-center justify-between">
                <span>Total estimado:</span>
                <span id="total-display" class="font-bold text-zinc-900">$0.00</span>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Tipo combustible</label>
                    <select name="tipo_combustible" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        <option value="gasolina_regular">Gasolina regular</option>
                        <option value="gasolina_premium">Gasolina premium</option>
                        <option value="diesel" selected>Diésel</option>
                        <option value="gas">Gas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Conductor</label>
                    <select name="conductor_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">Sin asignar</option>
                        <?php foreach ($conductores as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Estación / gasolinera</label>
                    <input type="text" name="estacion"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">N° ticket</label>
                    <input type="text" name="ticket_numero"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-zinc-700 cursor-pointer">
                <input type="checkbox" name="es_tanque_lleno" value="1" checked class="rounded">
                Tanque lleno (para calcular rendimiento km/L)
            </label>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1">Notas</label>
                <textarea name="notas" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-3 border-t border-zinc-100">
                <button type="button" onclick="document.getElementById('modal-carga').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function calcTotal() {
    const lit = parseFloat(document.getElementById('inp-litros').value) || 0;
    const pre = parseFloat(document.getElementById('inp-precio').value) || 0;
    document.getElementById('total-display').textContent = '$' + (lit * pre).toFixed(2);
}
function preCargarKm(sel) {
    const opt = sel.options[sel.selectedIndex];
    const km  = opt.dataset.km;
    if (km) document.getElementById('inp-km').value = km;
}
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>
