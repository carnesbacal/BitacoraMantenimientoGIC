<?php
/**
 * flotilla_mantenimiento.php - Mantenimientos programados e historial
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
requerir_login();

$u = usuario_actual();
$ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursal_filtro = $ver_todas ? (int) input('sucursal', 0) : (int) $u['sucursal_id'];

// ── POST ─────────────────────────────────────────────────────────────────────
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) { flash_set('error','Token inválido'); header('Location:'.url('flotilla_mantenimiento.php')); exit; }
    $op = (string) input('op','');

    if ($op === 'registrar') {
        $vid       = (int) input('vehiculo_id',0);
        $prog_id   = (int) input('programa_id',0) ?: null;
        $nombre    = trim((string) input('nombre',''));
        $desc      = trim((string) input('descripcion','')) ?: null;
        $fecha     = (string) input('fecha', date('Y-m-d'));
        $km        = (int) input('km_odometro',0);
        $taller    = trim((string) input('taller','')) ?: null;
        $tecnico   = trim((string) input('tecnico','')) ?: null;
        $costo     = (float) input('costo',0);
        $orden     = trim((string) input('numero_orden','')) ?: null;
        $prox_km   = (int) input('proximo_km',0) ?: null;
        $prox_fec  = (string) input('proxima_fecha','') ?: null;
        $notas     = trim((string) input('notas','')) ?: null;

        if (!$vid || !$nombre || !$fecha || !$km) {
            flash_set('error','Vehículo, nombre, fecha y KM son obligatorios.');
        } else {
            db_exec(
                "INSERT INTO flotilla_mant_historial
                 (vehiculo_id,programa_id,nombre,descripcion,fecha,km_odometro,taller,tecnico,costo,numero_orden,proximo_km,proxima_fecha,notas,creado_por)
                 VALUES (:vid,:prog,:nom,:des,:fec,:km,:tal,:tec,:cos,:ord,:pkm,:pfe,:not,:cp)",
                ['vid'=>$vid,'prog'=>$prog_id,'nom'=>$nombre,'des'=>$desc,'fec'=>$fecha,'km'=>$km,
                 'tal'=>$taller,'tec'=>$tecnico,'cos'=>$costo?:null,'ord'=>$orden,
                 'pkm'=>$prox_km,'pfe'=>$prox_fec,'not'=>$notas,'cp'=>$u['id']]
            );

            // Actualizar km_actual del vehículo si aplica
            if ($km > 0) {
                db_exec("UPDATE flotilla_vehiculos SET km_actual=:km WHERE id=:vid AND km_actual < :km2",
                        ['km'=>$km,'vid'=>$vid,'km2'=>$km]);
            }

            // Crear gasto si hay costo
            if ($costo > 0) {
                $cat = db_one("SELECT id FROM flotilla_categorias_gasto WHERE nombre LIKE '%Manteni%' LIMIT 1");
                if ($cat) {
                    db_exec(
                        "INSERT INTO flotilla_gastos (vehiculo_id,categoria_id,fecha,concepto,monto,km_odometro,proveedor,numero_factura,creado_por)
                         VALUES (:vid,:cat,:fec,:con,:monto,:km,:tal,:ord,:cp)",
                        ['vid'=>$vid,'cat'=>$cat['id'],'fec'=>$fecha,'con'=>'Mantenimiento: '.$nombre,
                         'monto'=>$costo,'km'=>$km,'tal'=>$taller,'ord'=>$orden,'cp'=>$u['id']]
                    );
                }
            }
            flash_set('success','Mantenimiento registrado.');
        }
        header('Location:'.url('flotilla_mantenimiento.php')); exit;
    }

    if ($op === 'eliminar') {
        $rid = (int) input('id',0);
        db_exec("DELETE FROM flotilla_mant_historial WHERE id=:id",['id'=>$rid]);
        flash_set('success','Registro eliminado.');
        header('Location:'.url('flotilla_mantenimiento.php')); exit;
    }
}

$titulo_pagina = 'Flotilla — Mantenimiento';
$pagina_activa = 'flotilla_mantenimiento';
require_once __DIR__ . '/config/header.php';
require_once __DIR__ . '/config/flotilla_nav.php';

// ── Datos ─────────────────────────────────────────────────────────────────────
$vista = in_array(input('vista'),['pendientes','historial']) ? input('vista') : 'pendientes';
$f_vid = (int) input('vehiculo_id',0);

$suc_join  = $sucursal_filtro ? " AND v.sucursal_id=:sid " : "";
$suc_param = $sucursal_filtro ? ['sid'=>$sucursal_filtro] : [];
$vid_where = $f_vid ? " AND v.id=:vid " : "";
$vid_param = $f_vid ? ['vid'=>$f_vid] : [];
$all_params = array_merge($suc_param, $vid_param);

// ── Vista Pendientes: último registro por vehículo+nombre de programa ─────────
$pendientes = [];
if ($vista === 'pendientes') {
    $programas = db_all("SELECT id,nombre,intervalo_km,intervalo_dias FROM flotilla_mant_programas WHERE activo=1 ORDER BY nombre");
    $vehiculos_list = db_all(
        "SELECT v.id,v.km_actual,COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) nombre,v.placas
         FROM flotilla_vehiculos v WHERE v.activo=1 $suc_join $vid_where ORDER BY v.marca,v.modelo",
        $all_params
    );

    foreach ($vehiculos_list as $veh) {
        foreach ($programas as $prog) {
            // Último mantenimiento de este programa para este vehículo
            $ultimo = db_one(
                "SELECT id,fecha,km_odometro,proximo_km,proxima_fecha FROM flotilla_mant_historial
                 WHERE vehiculo_id=:vid AND nombre=:nom ORDER BY fecha DESC, id DESC LIMIT 1",
                ['vid'=>$veh['id'],'nom'=>$prog['nombre']]
            );

            // Calcular urgencia
            $urgencia = 'ok'; // ok | proximo | vencido
            $proximo_km  = $ultimo['proximo_km']  ?? ($ultimo ? (int)$ultimo['km_odometro'] + (int)$prog['intervalo_km'] : null);
            $proxima_fec = $ultimo['proxima_fecha'] ?? ($ultimo && $prog['intervalo_dias']
                            ? date('Y-m-d', strtotime($ultimo['fecha'].' +'.$prog['intervalo_dias'].' days'))
                            : ($prog['intervalo_dias'] ? date('Y-m-d', strtotime('-'.$prog['intervalo_dias'].' days')) : null));

            if ($proximo_km && (int)$veh['km_actual'] >= $proximo_km) $urgencia = 'vencido';
            elseif ($proxima_fec && strtotime($proxima_fec) <= strtotime('+30 days')) $urgencia = ($urgencia==='vencido'?'vencido':'proximo');
            elseif ($proximo_km && (int)$veh['km_actual'] >= $proximo_km - 500) $urgencia = 'proximo';

            if (!$ultimo) $urgencia = 'proximo'; // Nunca hecho = pendiente

            $pendientes[] = [
                'vehiculo_id'  => $veh['id'],
                'vehiculo_nombre' => $veh['nombre'],
                'placas'       => $veh['placas'],
                'km_actual'    => $veh['km_actual'],
                'programa_nombre' => $prog['nombre'],
                'programa_id'  => $prog['id'],
                'ultimo'       => $ultimo,
                'proximo_km'   => $proximo_km,
                'proxima_fec'  => $proxima_fec,
                'urgencia'     => $urgencia,
            ];
        }
    }

    // Ordenar: vencidos primero, luego próximos, luego ok
    usort($pendientes, function($a,$b){
        $orden = ['vencido'=>0,'proximo'=>1,'ok'=>2];
        return ($orden[$a['urgencia']]??3) <=> ($orden[$b['urgencia']]??3);
    });
}

// ── Vista Historial ─────────────────────────────────────────────────────────
$historial = [];
if ($vista === 'historial') {
    $historial = db_all(
        "SELECT h.*,
                COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) vehiculo_nombre, v.placas
         FROM flotilla_mant_historial h
         INNER JOIN flotilla_vehiculos v ON v.id=h.vehiculo_id $suc_join $vid_where
         ORDER BY h.fecha DESC, h.id DESC",
        $all_params
    );
}

$vehiculos = db_all(
    "SELECT id, CONCAT(COALESCE(alias,CONCAT(marca,' ',modelo)),' (',placas,')') label, km_actual
     FROM flotilla_vehiculos WHERE activo=1 ".($sucursal_filtro?" AND sucursal_id=:sid ":"")."ORDER BY marca,modelo",
    $sucursal_filtro ? ['sid'=>$sucursal_filtro] : []
);
$programas_cat = db_all("SELECT id,nombre FROM flotilla_mant_programas WHERE activo=1 ORDER BY nombre");

$modal_vid  = (string) input('modal_vid','');
$modal_nom  = (string) input('modal_nom','');

$fm = flash_get();
?>

<div class="space-y-5 animate-fade-in" x-data="{
    abrirModalMant(vid, nombre) {
        document.getElementById('mant-vid').value = vid;
        document.getElementById('mant-nombre').value = nombre;
        // Pre-llenar km desde vehículo seleccionado
        const sel = document.getElementById('mant-vid-sel');
        for (let o of sel.options) {
            if (o.value == vid) { sel.value = vid; break; }
        }
        document.getElementById('modal-mant').classList.remove('hidden');
    }
}">

<?php foreach ($fm as $f): ?>
<div class="px-4 py-3 rounded-lg text-sm <?= $f['tipo']==='success'?'bg-emerald-50 text-emerald-800 border border-emerald-200':'bg-red-50 text-red-800 border border-red-200' ?>">
    <?= e($f['mensaje']) ?>
</div>
<?php endforeach; ?>

<!-- Encabezado -->
<div class="flex items-center justify-between">
    <h1 class="text-xl font-display font-bold text-zinc-900">Mantenimiento</h1>
    <button onclick="document.getElementById('modal-mant').classList.remove('hidden')"
            class="flex items-center gap-2 px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
        <i data-lucide="plus" class="w-4 h-4"></i> Registrar servicio
    </button>
</div>

<!-- Filtros + Toggle vista -->
<div class="flex flex-wrap items-center gap-2">
    <form method="GET" class="flex items-center gap-2">
        <input type="hidden" name="vista" value="<?= e($vista) ?>">
        <select name="vehiculo_id" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
            <option value="">Todos los vehículos</option>
            <?php foreach ($vehiculos as $v): ?>
            <option value="<?= $v['id'] ?>" <?= $f_vid==$v['id']?'selected':'' ?>><?= e($v['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 rounded-lg bg-zinc-800 text-white text-sm">Filtrar</button>
        <a href="<?= url('flotilla_mantenimiento.php?vista='.e($vista)) ?>" class="px-3 py-2 rounded-lg border border-zinc-300 text-zinc-600 text-sm">Limpiar</a>
    </form>

    <div class="ml-auto flex items-center gap-1 bg-zinc-100 rounded-lg p-1">
        <a href="<?= url('flotilla_mantenimiento.php?vista=pendientes'.($f_vid?"&vehiculo_id=$f_vid":'')) ?>"
           class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors <?= $vista==='pendientes'?'bg-white text-zinc-900 shadow-sm':'text-zinc-500 hover:text-zinc-700' ?>">
            <i data-lucide="clock" class="w-4 h-4 inline -mt-0.5"></i> Pendientes
        </a>
        <a href="<?= url('flotilla_mantenimiento.php?vista=historial'.($f_vid?"&vehiculo_id=$f_vid":'')) ?>"
           class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors <?= $vista==='historial'?'bg-white text-zinc-900 shadow-sm':'text-zinc-500 hover:text-zinc-700' ?>">
            <i data-lucide="history" class="w-4 h-4 inline -mt-0.5"></i> Historial
        </a>
    </div>
</div>

<?php if ($vista === 'pendientes'): ?>
<!-- ── VISTA PENDIENTES ────────────────────────────────────────────────── -->
<?php
$solo_vencidos  = array_filter($pendientes, fn($p) => $p['urgencia']==='vencido');
$solo_proximos  = array_filter($pendientes, fn($p) => $p['urgencia']==='proximo');
$count_alertas  = count($solo_vencidos) + count($solo_proximos);
?>
<?php if ($count_alertas > 0): ?>
<div class="px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    <i data-lucide="alert-triangle" class="w-4 h-4 inline -mt-0.5 mr-1"></i>
    <?= count($solo_vencidos) ?> vencido(s) · <?= count($solo_proximos) ?> próximo(s) requieren atención.
</div>
<?php endif; ?>

<?php if (empty($pendientes)): ?>
<div class="text-center py-12 text-zinc-400">
    <i data-lucide="wrench" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
    <p class="text-sm">Sin vehículos o programas registrados.</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($pendientes as $p):
        $colores = ['vencido'=>'border-red-300 bg-red-50','proximo'=>'border-amber-300 bg-amber-50','ok'=>'border-zinc-200 bg-white'];
        $badge   = ['vencido'=>'bg-red-100 text-red-800','proximo'=>'bg-amber-100 text-amber-800','ok'=>'bg-emerald-100 text-emerald-800'];
        $labels  = ['vencido'=>'Vencido','proximo'=>'Próximo','ok'=>'Al día'];
    ?>
    <div class="rounded-xl border p-4 shadow-sm <?= $colores[$p['urgencia']] ?>">
        <div class="flex items-start justify-between mb-2">
            <div>
                <p class="font-semibold text-zinc-900 text-sm"><?= e($p['programa_nombre']) ?></p>
                <p class="text-xs text-zinc-500"><?= e($p['vehiculo_nombre']) ?> · <?= e($p['placas']) ?></p>
            </div>
            <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $badge[$p['urgencia']] ?>"><?= $labels[$p['urgencia']] ?></span>
        </div>
        <div class="text-xs text-zinc-600 space-y-0.5 mb-3">
            <div><i data-lucide="gauge" class="w-3 h-3 inline -mt-0.5"></i> KM actual: <?= number_format($p['km_actual']) ?></div>
            <?php if ($p['proximo_km']): ?><div><i data-lucide="map-pin" class="w-3 h-3 inline -mt-0.5"></i> Próximo en: <?= number_format($p['proximo_km']) ?> km</div><?php endif; ?>
            <?php if ($p['proxima_fec']): ?><div><i data-lucide="calendar" class="w-3 h-3 inline -mt-0.5"></i> Próxima fecha: <?= fmt_fecha($p['proxima_fec'],false) ?></div><?php endif; ?>
            <?php if ($p['ultimo']): ?><div class="text-zinc-400">Último: <?= fmt_fecha($p['ultimo']['fecha'],false) ?></div><?php endif; ?>
        </div>
        <button type="button"
                @click="abrirModalMant(<?= $p['vehiculo_id'] ?>, <?= json_encode($p['programa_nombre']) ?>)"
                class="w-full px-3 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-medium">
            <i data-lucide="wrench" class="w-3 h-3 inline -mt-0.5 mr-1"></i> Registrar servicio
        </button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── VISTA HISTORIAL ────────────────────────────────────────────────── -->
<?php if (empty($historial)): ?>
<div class="text-center py-12 text-zinc-400">
    <i data-lucide="history" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
    <p class="text-sm">Sin historial de mantenimientos.</p>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200">
            <tr class="text-left text-xs font-semibold text-zinc-500 uppercase tracking-wide">
                <th class="px-4 py-3">Fecha</th>
                <th class="px-4 py-3">Vehículo</th>
                <th class="px-4 py-3">Servicio</th>
                <th class="px-4 py-3">Taller</th>
                <th class="px-4 py-3 text-right">KM</th>
                <th class="px-4 py-3 text-right">Costo</th>
                <th class="px-4 py-3">Próximo</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            <?php foreach ($historial as $h): ?>
            <tr class="hover:bg-zinc-50">
                <td class="px-4 py-2.5 text-zinc-700"><?= fmt_fecha($h['fecha'],false) ?></td>
                <td class="px-4 py-2.5">
                    <div class="font-medium text-zinc-900"><?= e($h['vehiculo_nombre']) ?></div>
                    <div class="text-xs text-zinc-500"><?= e($h['placas']) ?></div>
                </td>
                <td class="px-4 py-2.5 text-zinc-800"><?= e($h['nombre']) ?></td>
                <td class="px-4 py-2.5 text-zinc-500 text-xs"><?= e($h['taller'] ?? '—') ?></td>
                <td class="px-4 py-2.5 text-right text-zinc-700"><?= number_format($h['km_odometro']) ?></td>
                <td class="px-4 py-2.5 text-right font-medium text-zinc-900"><?= $h['costo'] ? '$'.number_format($h['costo'],2) : '—' ?></td>
                <td class="px-4 py-2.5 text-xs text-zinc-500">
                    <?= $h['proximo_km'] ? number_format($h['proximo_km']).' km' : '' ?>
                    <?= ($h['proximo_km'] && $h['proxima_fecha']) ? ' / ' : '' ?>
                    <?= $h['proxima_fecha'] ? fmt_fecha($h['proxima_fecha'],false) : '' ?>
                    <?= (!$h['proximo_km'] && !$h['proxima_fecha']) ? '—' : '' ?>
                </td>
                <td class="px-4 py-2.5">
                    <form method="POST" onsubmit="return confirm('¿Eliminar?')" class="inline">
                        <?= csrf_input() ?><input type="hidden" name="op" value="eliminar"><input type="hidden" name="id" value="<?= $h['id'] ?>">
                        <button type="submit" class="text-zinc-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endif; ?>

</div>

<!-- Modal Registrar mantenimiento -->
<div id="modal-mant" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-5 border-b border-zinc-200">
            <h2 class="text-base font-bold text-zinc-900">Registrar servicio</h2>
            <button onclick="document.getElementById('modal-mant').classList.add('hidden')" class="text-zinc-400 hover:text-zinc-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="registrar">

            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Vehículo *</label>
                    <select name="vehiculo_id" id="mant-vid-sel" required
                            class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"
                            onchange="this.form.km_odometro.value=this.options[this.selectedIndex].dataset.km||''">
                        <option value="">Seleccionar…</option>
                        <?php foreach ($vehiculos as $v): ?>
                        <option value="<?= $v['id'] ?>" data-km="<?= $v['km_actual'] ?>"><?= e($v['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" id="mant-vid" name="vehiculo_id_hidden">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Nombre del servicio *</label>
                    <input type="text" name="nombre" id="mant-nombre" list="programas-list" required
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    <datalist id="programas-list">
                        <?php foreach ($programas_cat as $p): ?>
                        <option value="<?= e($p['nombre']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Fecha *</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">KM odómetro *</label>
                    <input type="number" name="km_odometro" min="0" required
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Taller</label>
                    <input type="text" name="taller"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Técnico</label>
                    <input type="text" name="tecnico"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Costo</label>
                    <input type="number" name="costo" step="0.01" min="0"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">N° orden</label>
                    <input type="text" name="numero_orden"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Próximo KM</label>
                    <input type="number" name="proximo_km" min="0"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Próxima fecha</label>
                    <input type="date" name="proxima_fecha"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1">Descripción</label>
                <textarea name="descripcion" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1">Notas</label>
                <textarea name="notas" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-3 border-t border-zinc-100">
                <button type="button" onclick="document.getElementById('modal-mant').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Pre-llenar desde tarjetas de pendientes vía Alpine
document.addEventListener('DOMContentLoaded', function() {
    // Si se abre el modal con datos pre-cargados, sincronizar el select
    const hiddenVid = document.getElementById('mant-vid');
    const selVid    = document.getElementById('mant-vid-sel');
    if (hiddenVid && selVid && hiddenVid.value) {
        selVid.value = hiddenVid.value;
    }
});
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>
