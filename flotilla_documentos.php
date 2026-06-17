<?php
/**
 * flotilla_documentos.php - Documentos con fecha de vencimiento
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
requerir_login();

$u = usuario_actual();
$ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursal_filtro = $ver_todas ? (int) input('sucursal', 0) : (int) $u['sucursal_id'];

// ── Actualizar estados automáticamente ──────────────────────────────────────
function flotilla_actualizar_estado_documentos(): void {
    // Vencidos
    db_exec("UPDATE flotilla_documentos SET estado='vencido'
             WHERE estado IN ('vigente','por_vencer') AND fecha_vence IS NOT NULL AND fecha_vence < CURDATE()");
    // Por vencer (usando días_alerta de tipo)
    db_exec("UPDATE flotilla_documentos d
             INNER JOIN flotilla_tipos_documento t ON t.id = d.tipo_id
             SET d.estado = 'por_vencer'
             WHERE d.estado = 'vigente'
               AND d.fecha_vence IS NOT NULL
               AND d.fecha_vence >= CURDATE()
               AND d.fecha_vence <= DATE_ADD(CURDATE(), INTERVAL t.dias_alerta DAY)");
    // Vigentes (recuperar los que ya no aplica por_vencer)
    db_exec("UPDATE flotilla_documentos d
             INNER JOIN flotilla_tipos_documento t ON t.id = d.tipo_id
             SET d.estado = 'vigente'
             WHERE d.estado = 'por_vencer'
               AND d.fecha_vence IS NOT NULL
               AND d.fecha_vence > DATE_ADD(CURDATE(), INTERVAL t.dias_alerta DAY)");
}
flotilla_actualizar_estado_documentos();

// ── POST ─────────────────────────────────────────────────────────────────────
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) { flash_set('error','Token inválido'); header('Location:'.url('flotilla_documentos.php')); exit; }
    $op = (string) input('op','');

    if ($op === 'crear' || $op === 'editar') {
        $vid      = (int) input('vehiculo_id',0) ?: null;
        $cid      = (int) input('conductor_id',0) ?: null;
        $tipo_id  = (int) input('tipo_id',0);
        $numero   = trim((string) input('numero_documento','')) ?: null;
        $proveed  = trim((string) input('proveedor','')) ?: null;
        $f_ini    = (string) input('fecha_inicio','') ?: null;
        $f_vence  = (string) input('fecha_vence','') ?: null;
        $monto    = (float) input('monto',0) ?: null;
        $notas    = trim((string) input('notas','')) ?: null;
        $estado   = 'vigente';
        // calcular estado inicial
        if ($f_vence) {
            $tipo_row = db_one("SELECT dias_alerta FROM flotilla_tipos_documento WHERE id=:id",['id'=>$tipo_id]);
            $dias_alerta = (int)($tipo_row['dias_alerta'] ?? 30);
            if (strtotime($f_vence) < strtotime('today')) $estado = 'vencido';
            elseif (strtotime($f_vence) <= strtotime("+{$dias_alerta} days")) $estado = 'por_vencer';
        }

        if (!$tipo_id || (!$vid && !$cid)) {
            flash_set('error','Tipo de documento y vehículo o conductor son obligatorios.');
        } else {
            if ($op === 'crear') {
                db_exec(
                    "INSERT INTO flotilla_documentos (vehiculo_id,conductor_id,tipo_id,numero_documento,proveedor,fecha_inicio,fecha_vence,monto,estado,notas,creado_por)
                     VALUES (:vid,:cid,:tid,:num,:pro,:fi,:fv,:mon,:est,:not,:cp)",
                    ['vid'=>$vid,'cid'=>$cid,'tid'=>$tipo_id,'num'=>$numero,'pro'=>$proveed,
                     'fi'=>$f_ini,'fv'=>$f_vence,'mon'=>$monto,'est'=>$estado,'not'=>$notas,'cp'=>$u['id']]
                );
                flash_set('success','Documento registrado.');
            } else {
                $did = (int) input('id',0);
                db_exec(
                    "UPDATE flotilla_documentos SET vehiculo_id=:vid,conductor_id=:cid,tipo_id=:tid,numero_documento=:num,proveedor=:pro,fecha_inicio=:fi,fecha_vence=:fv,monto=:mon,estado=:est,notas=:not WHERE id=:id",
                    ['vid'=>$vid,'cid'=>$cid,'tid'=>$tipo_id,'num'=>$numero,'pro'=>$proveed,
                     'fi'=>$f_ini,'fv'=>$f_vence,'mon'=>$monto,'est'=>$estado,'not'=>$notas,'id'=>$did]
                );
                flash_set('success','Documento actualizado.');
            }
        }
        header('Location:'.url('flotilla_documentos.php')); exit;
    }

    if ($op === 'eliminar') {
        $did = (int) input('id',0);
        db_exec("DELETE FROM flotilla_documentos WHERE id=:id",['id'=>$did]);
        flash_set('success','Documento eliminado.');
        header('Location:'.url('flotilla_documentos.php')); exit;
    }
}

$titulo_pagina = 'Flotilla — Documentos';
$pagina_activa = 'flotilla_documentos';
require_once __DIR__ . '/config/header.php';
require_once __DIR__ . '/config/flotilla_nav.php';

// ── Datos ─────────────────────────────────────────────────────────────────────
$f_estado = (string) input('estado','');
$f_vid    = (int) input('vehiculo_id',0);
$editar_id = (int) input('editar',0);

$suc_join  = $sucursal_filtro ? " AND v.sucursal_id=:sid " : "";
$suc_param = $sucursal_filtro ? ['sid'=>$sucursal_filtro] : [];

$where = "WHERE 1=1";
$params = $suc_param;
if ($f_estado) { $where .= " AND d.estado=:est"; $params['est']=$f_estado; }
if ($f_vid)    { $where .= " AND d.vehiculo_id=:vid"; $params['vid']=$f_vid; }

$documentos = db_all(
    "SELECT d.*, td.nombre tipo_nombre, td.dias_alerta,
            COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) vehiculo_nombre, v.placas,
            c.nombre_completo conductor_nombre
     FROM flotilla_documentos d
     INNER JOIN flotilla_tipos_documento td ON td.id=d.tipo_id
     LEFT JOIN flotilla_vehiculos v ON v.id=d.vehiculo_id $suc_join
     LEFT JOIN flotilla_conductores c ON c.id=d.conductor_id
     $where
     ORDER BY d.fecha_vence ASC, d.id DESC",
    $params
);

// KPIs
$kpi_total     = (int)db_one("SELECT COUNT(*) c FROM flotilla_documentos d LEFT JOIN flotilla_vehiculos v ON v.id=d.vehiculo_id $suc_join WHERE 1=1", $suc_param)['c'];
$kpi_vigente   = (int)db_one("SELECT COUNT(*) c FROM flotilla_documentos d LEFT JOIN flotilla_vehiculos v ON v.id=d.vehiculo_id $suc_join WHERE d.estado='vigente'", $suc_param)['c'];
$kpi_por_vencer= (int)db_one("SELECT COUNT(*) c FROM flotilla_documentos d LEFT JOIN flotilla_vehiculos v ON v.id=d.vehiculo_id $suc_join WHERE d.estado='por_vencer'", $suc_param)['c'];
$kpi_vencido   = (int)db_one("SELECT COUNT(*) c FROM flotilla_documentos d LEFT JOIN flotilla_vehiculos v ON v.id=d.vehiculo_id $suc_join WHERE d.estado='vencido'", $suc_param)['c'];

$vehiculos   = db_all("SELECT id, CONCAT(COALESCE(alias,CONCAT(marca,' ',modelo)),' (',placas,')') label FROM flotilla_vehiculos WHERE activo=1 ".($sucursal_filtro?" AND sucursal_id=:sid ":"")."ORDER BY marca,modelo", $suc_param);
$conductores = db_all("SELECT id,nombre_completo FROM flotilla_conductores WHERE activo=1 ORDER BY nombre_completo");
$tipos_doc   = db_all("SELECT id,nombre,aplica_vehiculo,aplica_conductor FROM flotilla_tipos_documento WHERE activo=1 ORDER BY nombre");

$editar_doc = $editar_id ? db_one("SELECT * FROM flotilla_documentos WHERE id=:id",['id'=>$editar_id]) : null;

$fm = flash_get();
$estado_cls = [
    'vigente'   => 'bg-emerald-100 text-emerald-800',
    'por_vencer'=> 'bg-amber-100 text-amber-800',
    'vencido'   => 'bg-red-100 text-red-800',
    'cancelado' => 'bg-zinc-100 text-zinc-500',
];
$fila_cls = [
    'vigente'   => '',
    'por_vencer'=> 'bg-amber-50',
    'vencido'   => 'bg-red-50',
    'cancelado' => 'opacity-60',
];
?>

<div class="space-y-5 animate-fade-in">

<?php foreach ($fm as $f): ?>
<div class="px-4 py-3 rounded-lg text-sm <?= $f['tipo']==='success'?'bg-emerald-50 text-emerald-800 border border-emerald-200':'bg-red-50 text-red-800 border border-red-200' ?>">
    <?= e($f['mensaje']) ?>
</div>
<?php endforeach; ?>

<!-- Encabezado -->
<div class="flex items-center justify-between">
    <h1 class="text-xl font-display font-bold text-zinc-900">Documentos</h1>
    <button onclick="document.getElementById('modal-doc').classList.remove('hidden')"
            class="flex items-center gap-2 px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
        <i data-lucide="plus" class="w-4 h-4"></i> Agregar documento
    </button>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php
    $kpi_cards = [
        ['Total', $kpi_total, '', 'bg-zinc-50 border-zinc-200'],
        ['Vigentes', $kpi_vigente, 'vigente', 'bg-emerald-50 border-emerald-200'],
        ['Por vencer', $kpi_por_vencer, 'por_vencer', 'bg-amber-50 border-amber-200'],
        ['Vencidos', $kpi_vencido, 'vencido', 'bg-red-50 border-red-200'],
    ];
    foreach ($kpi_cards as [$lbl,$cnt,$est,$cls]):
        $href = url('flotilla_documentos.php'.($est?"?estado=$est":''));
    ?>
    <a href="<?= $href ?>" class="rounded-xl border p-4 shadow-sm hover:shadow-md transition-shadow <?= $cls ?>">
        <p class="text-xs text-zinc-500 mb-1"><?= $lbl ?></p>
        <p class="text-2xl font-bold text-zinc-900"><?= $cnt ?></p>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<form method="GET" class="flex flex-wrap items-center gap-2">
    <select name="vehiculo_id" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
        <option value="">Todos los vehículos</option>
        <?php foreach ($vehiculos as $v): ?>
        <option value="<?= $v['id'] ?>" <?= $f_vid==$v['id']?'selected':'' ?>><?= e($v['label']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="estado" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
        <option value="">Todos los estados</option>
        <option value="vigente"    <?= $f_estado==='vigente'?'selected':'' ?>>Vigentes</option>
        <option value="por_vencer" <?= $f_estado==='por_vencer'?'selected':'' ?>>Por vencer</option>
        <option value="vencido"    <?= $f_estado==='vencido'?'selected':'' ?>>Vencidos</option>
        <option value="cancelado"  <?= $f_estado==='cancelado'?'selected':'' ?>>Cancelados</option>
    </select>
    <button type="submit" class="px-4 py-2 rounded-lg bg-zinc-800 text-white text-sm">Filtrar</button>
    <a href="<?= url('flotilla_documentos.php') ?>" class="px-3 py-2 rounded-lg border border-zinc-300 text-zinc-600 text-sm">Limpiar</a>
</form>

<!-- Tabla -->
<?php if (empty($documentos)): ?>
<div class="text-center py-12 text-zinc-400">
    <i data-lucide="file-check" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
    <p class="text-sm">Sin documentos registrados.</p>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200">
            <tr class="text-left text-xs font-semibold text-zinc-500 uppercase tracking-wide">
                <th class="px-4 py-3">Tipo</th>
                <th class="px-4 py-3">Vehículo / Conductor</th>
                <th class="px-4 py-3">N° Documento</th>
                <th class="px-4 py-3">Proveedor</th>
                <th class="px-4 py-3">Vence</th>
                <th class="px-4 py-3 text-right">Monto</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            <?php foreach ($documentos as $d): ?>
            <tr class="hover:brightness-95 transition-all <?= $fila_cls[$d['estado']] ?? '' ?>">
                <td class="px-4 py-2.5 font-medium text-zinc-900"><?= e($d['tipo_nombre']) ?></td>
                <td class="px-4 py-2.5 text-zinc-600 text-xs">
                    <?= e($d['vehiculo_nombre'] ?? '') ?>
                    <?= ($d['vehiculo_nombre'] && $d['conductor_nombre']) ? ' / ' : '' ?>
                    <?= e($d['conductor_nombre'] ?? '') ?>
                    <?= (!$d['vehiculo_nombre'] && !$d['conductor_nombre']) ? '—' : '' ?>
                    <?php if ($d['placas']): ?><br><span class="font-mono text-zinc-500"><?= e($d['placas']) ?></span><?php endif; ?>
                </td>
                <td class="px-4 py-2.5 font-mono text-zinc-700 text-xs"><?= e($d['numero_documento'] ?? '—') ?></td>
                <td class="px-4 py-2.5 text-zinc-500 text-xs"><?= e($d['proveedor'] ?? '—') ?></td>
                <td class="px-4 py-2.5 text-zinc-700"><?= $d['fecha_vence'] ? fmt_fecha($d['fecha_vence'],false) : '—' ?></td>
                <td class="px-4 py-2.5 text-right text-zinc-700"><?= $d['monto'] ? '$'.number_format($d['monto'],2) : '—' ?></td>
                <td class="px-4 py-2.5">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $estado_cls[$d['estado']] ?? '' ?>">
                        <?= ucwords(str_replace('_',' ',$d['estado'])) ?>
                    </span>
                </td>
                <td class="px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <a href="?editar=<?= $d['id'] ?>" class="text-zinc-400 hover:text-bacal-700"><i data-lucide="pencil" class="w-4 h-4"></i></a>
                        <form method="POST" onsubmit="return confirm('¿Eliminar este documento?')" class="inline">
                            <?= csrf_input() ?><input type="hidden" name="op" value="eliminar"><input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button type="submit" class="text-zinc-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</div>

<!-- Modal Crear/Editar documento -->
<?php
$md = $editar_doc ?? [];
$modal_titulo_doc = $editar_doc ? 'Editar documento' : 'Agregar documento';
$modal_op_doc     = $editar_doc ? 'editar' : 'crear';
?>
<div id="modal-doc" class="<?= $editar_doc ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-5 border-b border-zinc-200">
            <h2 class="text-base font-bold text-zinc-900"><?= $modal_titulo_doc ?></h2>
            <button onclick="document.getElementById('modal-doc').classList.add('hidden')" class="text-zinc-400 hover:text-zinc-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="<?= $modal_op_doc ?>">
            <?php if ($editar_doc): ?><input type="hidden" name="id" value="<?= $md['id'] ?>"><?php endif; ?>

            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Tipo de documento *</label>
                    <select name="tipo_id" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">Seleccionar…</option>
                        <?php foreach ($tipos_doc as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($md['tipo_id']??0)==$t['id']?'selected':'' ?>><?= e($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Vehículo</label>
                    <select name="vehiculo_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">Sin vehículo</option>
                        <?php foreach ($vehiculos as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= ($md['vehiculo_id']??0)==$v['id']?'selected':'' ?>><?= e($v['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Conductor</label>
                    <select name="conductor_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">Sin conductor</option>
                        <?php foreach ($conductores as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($md['conductor_id']??0)==$c['id']?'selected':'' ?>><?= e($c['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">N° documento</label>
                    <input type="text" name="numero_documento" value="<?= e($md['numero_documento'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Proveedor / Aseguradora</label>
                    <input type="text" name="proveedor" value="<?= e($md['proveedor'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Fecha inicio</label>
                    <input type="date" name="fecha_inicio" value="<?= e($md['fecha_inicio'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Fecha vencimiento</label>
                    <input type="date" name="fecha_vence" value="<?= e($md['fecha_vence'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Monto</label>
                    <input type="number" name="monto" step="0.01" min="0" value="<?= $md['monto'] ?? '' ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1">Notas</label>
                <textarea name="notas" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e($md['notas'] ?? '') ?></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-3 border-t border-zinc-100">
                <button type="button" onclick="document.getElementById('modal-doc').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($editar_doc): ?>
<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('modal-doc').classList.remove('hidden'));</script>
<?php endif; ?>

<?php require_once __DIR__ . '/config/footer.php'; ?>
