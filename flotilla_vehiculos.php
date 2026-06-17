<?php
/**
 * flotilla_vehiculos.php - Catálogo de vehículos de la flotilla
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
requerir_login();

$u = usuario_actual();
$ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursal_filtro = $ver_todas ? (int) input('sucursal', 0) : (int) $u['sucursal_id'];

// ── POST ────────────────────────────────────────────────────────────────────
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) { flash_set('error','Token inválido'); header('Location:'.url('flotilla_vehiculos.php')); exit; }
    $op = (string) input('op','');

    if ($op === 'crear' || $op === 'editar') {
        $alias   = trim((string) input('alias',''));
        $marca   = trim((string) input('marca',''));
        $modelo  = trim((string) input('modelo',''));
        $anio    = (int) input('anio', date('Y'));
        $placas  = trim((string) input('placas',''));
        $tipo_id = (int) input('tipo_id',0);
        $suc_id  = $ver_todas ? (int) input('sucursal_id',0) : (int)$u['sucursal_id'];
        $estado  = in_array(input('estado'), ['activo','taller','inactivo','baja']) ? input('estado') : 'activo';
        $color   = trim((string) input('color',''));
        $comb    = in_array(input('combustible_tipo'), ['gasolina','diesel','gas','electrico','hibrido']) ? input('combustible_tipo') : 'diesel';
        $km_ini  = (int) input('km_inicial',0);
        $notas   = trim((string) input('notas',''));

        if (!$marca || !$modelo || !$placas || !$tipo_id) {
            flash_set('error','Marca, modelo, placas y tipo son obligatorios.');
        } else {
            if ($op === 'crear') {
                db_exec(
                    "INSERT INTO flotilla_vehiculos (tipo_id,sucursal_id,alias,marca,modelo,anio,color,placas,combustible_tipo,km_inicial,km_actual,estado,notas,creado_por)
                     VALUES (:t,:s,:al,:ma,:mo,:an,:co,:pl,:cb,:ki,:ki2,:est,:no,:cp)",
                    ['t'=>$tipo_id,'s'=>$suc_id?:null,'al'=>$alias?:null,'ma'=>$marca,'mo'=>$modelo,
                     'an'=>$anio,'co'=>$color?:null,'pl'=>$placas,'cb'=>$comb,'ki'=>$km_ini,'ki2'=>$km_ini,
                     'est'=>$estado,'no'=>$notas?:null,'cp'=>$u['id']]
                );
                flash_set('success','Vehículo registrado.');
            } else {
                $vid = (int) input('id',0);
                db_exec(
                    "UPDATE flotilla_vehiculos SET tipo_id=:t,sucursal_id=:s,alias=:al,marca=:ma,modelo=:mo,anio=:an,color=:co,placas=:pl,combustible_tipo=:cb,km_inicial=:ki,estado=:est,notas=:no WHERE id=:id",
                    ['t'=>$tipo_id,'s'=>$suc_id?:null,'al'=>$alias?:null,'ma'=>$marca,'mo'=>$modelo,
                     'an'=>$anio,'co'=>$color?:null,'pl'=>$placas,'cb'=>$comb,'ki'=>$km_ini,
                     'est'=>$estado,'no'=>$notas?:null,'id'=>$vid]
                );
                flash_set('success','Vehículo actualizado.');
            }
        }
        header('Location:'.url('flotilla_vehiculos.php?vista='.input('vista','tarjetas'))); exit;
    }

    if ($op === 'eliminar') {
        $vid = (int) input('id',0);
        db_exec("UPDATE flotilla_vehiculos SET activo=0 WHERE id=:id",['id'=>$vid]);
        flash_set('success','Vehículo desactivado.');
        header('Location:'.url('flotilla_vehiculos.php')); exit;
    }
}

$titulo_pagina = 'Flotilla — Vehículos';
$pagina_activa = 'flotilla_vehiculos';
require_once __DIR__ . '/config/header.php';
require_once __DIR__ . '/config/flotilla_nav.php';

// ── Datos ────────────────────────────────────────────────────────────────────
$f_busqueda = trim((string) input('q',''));
$f_estado   = (string) input('estado','');
$f_tipo     = (int) input('tipo',0);
$vista      = in_array(input('vista'),['tarjetas','lista']) ? input('vista') : 'tarjetas';

$where = "WHERE v.activo=1";
$params = [];
if ($sucursal_filtro) { $where .= " AND v.sucursal_id=:sid"; $params['sid']=$sucursal_filtro; }
if ($f_busqueda) { $where .= " AND (v.placas LIKE :q OR v.marca LIKE :q OR v.modelo LIKE :q OR v.alias LIKE :q)"; $params['q']="%$f_busqueda%"; }
if ($f_estado) { $where .= " AND v.estado=:est"; $params['est']=$f_estado; }
if ($f_tipo) { $where .= " AND v.tipo_id=:tip"; $params['tip']=$f_tipo; }

$vehiculos = db_all(
    "SELECT v.*, tv.nombre tipo_nombre, s.nombre sucursal_nombre, c.nombre_completo conductor_nombre
     FROM flotilla_vehiculos v
     LEFT JOIN flotilla_tipos_vehiculo tv ON tv.id=v.tipo_id
     LEFT JOIN sucursales s ON s.id=v.sucursal_id
     LEFT JOIN flotilla_conductores c ON c.id=v.conductor_asignado_id
     $where ORDER BY v.marca, v.modelo",
    $params
);

$tipos_vehiculo = db_all("SELECT id,nombre FROM flotilla_tipos_vehiculo ORDER BY nombre");
$conductores    = db_all("SELECT id,nombre_completo FROM flotilla_conductores WHERE activo=1 ORDER BY nombre_completo");
$sucursales     = $ver_todas ? db_all("SELECT id,nombre FROM sucursales WHERE activo=1 ORDER BY nombre") : [];

$editar_id = (int) input('editar',0);
$editar_v  = $editar_id ? db_one("SELECT * FROM flotilla_vehiculos WHERE id=:id AND activo=1",['id'=>$editar_id]) : null;

$fm = flash_get();
$estado_colores = [
    'activo'   => 'bg-emerald-100 text-emerald-800',
    'taller'   => 'bg-amber-100 text-amber-800',
    'inactivo' => 'bg-zinc-100 text-zinc-600',
    'baja'     => 'bg-red-100 text-red-800',
];
?>

<div class="space-y-4 animate-fade-in">

<?php foreach ($fm as $f): ?>
<div class="px-4 py-3 rounded-lg text-sm font-medium <?= $f['tipo']==='success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
    <?= e($f['mensaje']) ?>
</div>
<?php endforeach; ?>

<!-- Encabezado + botón agregar -->
<div class="flex items-center justify-between">
    <h1 class="text-xl font-display font-bold text-zinc-900">Vehículos</h1>
    <button onclick="document.getElementById('modal-vehiculo').classList.remove('hidden')"
            class="flex items-center gap-2 px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
        <i data-lucide="plus" class="w-4 h-4"></i> Agregar vehículo
    </button>
</div>

<!-- Filtros + toggle vista -->
<form method="GET" class="flex flex-wrap items-center gap-2">
    <input type="hidden" name="vista" value="<?= e($vista) ?>">
    <input type="text" name="q" value="<?= e($f_busqueda) ?>" placeholder="Buscar placas, marca, modelo…"
           class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700 w-56">
    <select name="estado" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
        <option value="">Todos los estados</option>
        <option value="activo"   <?= $f_estado==='activo'?'selected':'' ?>>Activo</option>
        <option value="taller"   <?= $f_estado==='taller'?'selected':'' ?>>En taller</option>
        <option value="inactivo" <?= $f_estado==='inactivo'?'selected':'' ?>>Inactivo</option>
        <option value="baja"     <?= $f_estado==='baja'?'selected':'' ?>>Baja</option>
    </select>
    <select name="tipo" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
        <option value="">Todos los tipos</option>
        <?php foreach ($tipos_vehiculo as $t): ?>
        <option value="<?= $t['id'] ?>" <?= $f_tipo==$t['id']?'selected':'' ?>><?= e($t['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($ver_todas): ?>
    <select name="sucursal" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
        <option value="">Todas las sucursales</option>
        <?php foreach ($sucursales as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $sucursal_filtro==$s['id']?'selected':'' ?>><?= e($s['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button type="submit" class="px-4 py-2 rounded-lg bg-zinc-800 text-white text-sm font-medium hover:bg-zinc-700">Filtrar</button>
    <a href="<?= url('flotilla_vehiculos.php') ?>" class="px-3 py-2 rounded-lg border border-zinc-300 text-zinc-600 text-sm hover:bg-zinc-50">Limpiar</a>

    <!-- Toggle vista -->
    <div class="ml-auto flex items-center gap-1 bg-zinc-100 rounded-lg p-1">
        <a href="<?= url('flotilla_vehiculos.php?'.http_build_query(array_merge($_GET,['vista'=>'tarjetas']))) ?>"
           class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors <?= $vista==='tarjetas' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-700' ?>">
            <i data-lucide="layout-grid" class="w-4 h-4 inline -mt-0.5"></i>
        </a>
        <a href="<?= url('flotilla_vehiculos.php?'.http_build_query(array_merge($_GET,['vista'=>'lista']))) ?>"
           class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors <?= $vista==='lista' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-700' ?>">
            <i data-lucide="list" class="w-4 h-4 inline -mt-0.5"></i>
        </a>
    </div>
</form>

<!-- Contador -->
<p class="text-sm text-zinc-500"><?= count($vehiculos) ?> vehículo(s) encontrado(s)</p>

<?php if (empty($vehiculos)): ?>
<div class="text-center py-16 text-zinc-400">
    <i data-lucide="car" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
    <p class="text-sm">No hay vehículos registrados.</p>
</div>

<?php elseif ($vista === 'tarjetas'): ?>
<!-- VISTA TARJETAS -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php foreach ($vehiculos as $v): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm hover:shadow-md transition-shadow p-4 flex flex-col gap-3">
        <!-- Estado + tipo -->
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $estado_colores[$v['estado']] ?? 'bg-zinc-100 text-zinc-600' ?>">
                <?= ucfirst($v['estado']) ?>
            </span>
            <span class="text-xs text-zinc-400"><?= e($v['tipo_nombre'] ?? '—') ?></span>
        </div>
        <!-- Ícono + datos -->
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-bacal-50 flex items-center justify-center flex-shrink-0">
                <i data-lucide="car" class="w-5 h-5 text-bacal-700"></i>
            </div>
            <div class="min-w-0">
                <p class="font-bold text-zinc-900 text-sm truncate"><?= e($v['alias'] ?: ($v['marca'].' '.$v['modelo'])) ?></p>
                <p class="text-xs text-zinc-500 truncate"><?= e($v['marca']) ?> <?= e($v['modelo']) ?> <?= $v['anio'] ?></p>
                <p class="text-xs font-mono text-zinc-700 mt-0.5"><?= e($v['placas']) ?></p>
            </div>
        </div>
        <!-- KM -->
        <div class="flex items-center justify-between text-xs text-zinc-500 border-t border-zinc-100 pt-2">
            <span><i data-lucide="gauge" class="w-3 h-3 inline -mt-0.5"></i> <?= number_format($v['km_actual']) ?> km</span>
            <span class="truncate max-w-[100px]"><?= e($v['sucursal_nombre'] ?? '—') ?></span>
        </div>
        <!-- Acciones -->
        <div class="flex gap-2 mt-auto">
            <a href="?editar=<?= $v['id'] ?>&vista=tarjetas"
               class="flex-1 text-center px-2 py-1.5 rounded-lg border border-zinc-200 text-xs text-zinc-600 hover:bg-zinc-50">
                <i data-lucide="pencil" class="w-3 h-3 inline -mt-0.5"></i> Editar
            </a>
            <form method="POST" onsubmit="return confirm('¿Desactivar este vehículo?')" class="flex-none">
                <?= csrf_input() ?><input type="hidden" name="op" value="eliminar"><input type="hidden" name="id" value="<?= $v['id'] ?>">
                <button type="submit" class="px-2 py-1.5 rounded-lg border border-zinc-200 text-xs text-red-500 hover:bg-red-50">
                    <i data-lucide="trash-2" class="w-3 h-3"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- VISTA LISTA -->
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200">
            <tr class="text-left text-xs font-semibold text-zinc-500 uppercase tracking-wide">
                <th class="px-4 py-3">Vehículo</th>
                <th class="px-4 py-3">Placas</th>
                <th class="px-4 py-3">Tipo</th>
                <th class="px-4 py-3">KM actual</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3">Sucursal</th>
                <th class="px-4 py-3">Conductor</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            <?php foreach ($vehiculos as $v): ?>
            <tr class="hover:bg-zinc-50 transition-colors">
                <td class="px-4 py-3">
                    <div class="font-semibold text-zinc-900"><?= e($v['alias'] ?: ($v['marca'].' '.$v['modelo'])) ?></div>
                    <div class="text-xs text-zinc-500"><?= e($v['marca']) ?> <?= e($v['modelo']) ?> <?= $v['anio'] ?></div>
                </td>
                <td class="px-4 py-3 font-mono text-zinc-700"><?= e($v['placas']) ?></td>
                <td class="px-4 py-3 text-zinc-600"><?= e($v['tipo_nombre'] ?? '—') ?></td>
                <td class="px-4 py-3 text-zinc-700"><?= number_format($v['km_actual']) ?> km</td>
                <td class="px-4 py-3">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $estado_colores[$v['estado']] ?? 'bg-zinc-100 text-zinc-600' ?>">
                        <?= ucfirst($v['estado']) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-zinc-600 text-xs"><?= e($v['sucursal_nombre'] ?? '—') ?></td>
                <td class="px-4 py-3 text-zinc-600 text-xs"><?= e($v['conductor_nombre'] ?? '—') ?></td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <a href="?editar=<?= $v['id'] ?>&vista=lista" class="text-zinc-500 hover:text-bacal-700">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </a>
                        <form method="POST" onsubmit="return confirm('¿Desactivar?')" class="inline">
                            <?= csrf_input() ?><input type="hidden" name="op" value="eliminar"><input type="hidden" name="id" value="<?= $v['id'] ?>">
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

<!-- Modal Crear/Editar vehículo -->
<?php
$mv = $editar_v ?? [];
$modal_titulo = $editar_v ? 'Editar vehículo' : 'Agregar vehículo';
$modal_op     = $editar_v ? 'editar' : 'crear';
?>
<div id="modal-vehiculo" class="<?= $editar_v ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-5 border-b border-zinc-200">
            <h2 class="text-base font-bold text-zinc-900"><?= $modal_titulo ?></h2>
            <button onclick="document.getElementById('modal-vehiculo').classList.add('hidden')" class="text-zinc-400 hover:text-zinc-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="<?= $modal_op ?>">
            <input type="hidden" name="vista" value="<?= e($vista) ?>">
            <?php if ($editar_v): ?><input type="hidden" name="id" value="<?= $mv['id'] ?>"><?php endif; ?>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Marca *</label>
                    <input type="text" name="marca" value="<?= e($mv['marca'] ?? '') ?>" required
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Modelo *</label>
                    <input type="text" name="modelo" value="<?= e($mv['modelo'] ?? '') ?>" required
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Año *</label>
                    <input type="number" name="anio" value="<?= $mv['anio'] ?? date('Y') ?>" min="2000" max="<?= date('Y')+1 ?>" required
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Placas *</label>
                    <input type="text" name="placas" value="<?= e($mv['placas'] ?? '') ?>" required
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Alias / Nombre operativo</label>
                    <input type="text" name="alias" value="<?= e($mv['alias'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Color</label>
                    <input type="text" name="color" value="<?= e($mv['color'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Tipo de vehículo *</label>
                    <select name="tipo_id" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">Seleccionar…</option>
                        <?php foreach ($tipos_vehiculo as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($mv['tipo_id']??0)==$t['id']?'selected':'' ?>><?= e($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Combustible</label>
                    <select name="combustible_tipo" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        <?php foreach (['gasolina'=>'Gasolina','diesel'=>'Diésel','gas'=>'Gas','electrico'=>'Eléctrico','hibrido'=>'Híbrido'] as $val=>$lbl): ?>
                        <option value="<?= $val ?>" <?= ($mv['combustible_tipo']??'diesel')===$val?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">KM inicial</label>
                    <input type="number" name="km_inicial" value="<?= $mv['km_inicial'] ?? 0 ?>" min="0"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Estado</label>
                    <select name="estado" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        <?php foreach (['activo'=>'Activo','taller'=>'En taller','inactivo'=>'Inactivo','baja'=>'Baja'] as $val=>$lbl): ?>
                        <option value="<?= $val ?>" <?= ($mv['estado']??'activo')===$val?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($ver_todas): ?>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Sucursal</label>
                    <select name="sucursal_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">Sin sucursal</option>
                        <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($mv['sucursal_id']??0)==$s['id']?'selected':'' ?>><?= e($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1">Notas</label>
                <textarea name="notas" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e($mv['notas'] ?? '') ?></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-3 border-t border-zinc-100">
                <button type="button" onclick="document.getElementById('modal-vehiculo').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>
