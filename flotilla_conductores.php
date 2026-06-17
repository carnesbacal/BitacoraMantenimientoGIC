<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
requerir_login();

$u = usuario_actual();
$ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursal_filtro = $ver_todas ? (int) input('sucursal', 0) : (int) $u['sucursal_id'];

if (es_post() && csrf_valido(input('_csrf'))) {
    $op = (string) input('op','');
    if ($op === 'crear' || $op === 'editar') {
        $nombre   = trim((string) input('nombre_completo',''));
        $num_emp  = trim((string) input('numero_empleado','')) ?: null;
        $tel      = trim((string) input('telefono','')) ?: null;
        $email    = trim((string) input('email','')) ?: null;
        $suc_id   = $ver_todas ? (int) input('sucursal_id',0) ?: null : ($u['sucursal_id'] ?: null);
        $lic_num  = trim((string) input('licencia_numero','')) ?: null;
        $lic_tipo = trim((string) input('licencia_tipo','')) ?: null;
        $lic_vence= (string) input('licencia_vence','') ?: null;
        $notas    = trim((string) input('notas','')) ?: null;
        if (!$nombre) { flash_set('error','Nombre obligatorio.'); }
        else {
            if ($op === 'crear') {
                db_exec("INSERT INTO flotilla_conductores (nombre_completo,numero_empleado,telefono,email,sucursal_id,licencia_numero,licencia_tipo,licencia_vence,notas) VALUES (:n,:ne,:t,:e,:s,:ln,:lt,:lv,:no)",
                    ['n'=>$nombre,'ne'=>$num_emp,'t'=>$tel,'e'=>$email,'s'=>$suc_id,'ln'=>$lic_num,'lt'=>$lic_tipo,'lv'=>$lic_vence,'no'=>$notas]);
                flash_set('success','Conductor registrado.');
            } else {
                $cid = (int) input('id',0);
                db_exec("UPDATE flotilla_conductores SET nombre_completo=:n,numero_empleado=:ne,telefono=:t,email=:e,sucursal_id=:s,licencia_numero=:ln,licencia_tipo=:lt,licencia_vence=:lv,notas=:no WHERE id=:id",
                    ['n'=>$nombre,'ne'=>$num_emp,'t'=>$tel,'e'=>$email,'s'=>$suc_id,'ln'=>$lic_num,'lt'=>$lic_tipo,'lv'=>$lic_vence,'no'=>$notas,'id'=>$cid]);
                flash_set('success','Conductor actualizado.');
            }
        }
    } elseif ($op === 'eliminar') {
        db_exec("UPDATE flotilla_conductores SET activo=0 WHERE id=:id",['id'=>(int)input('id',0)]);
        flash_set('success','Conductor desactivado.');
    }
    header('Location:'.url('flotilla_conductores.php')); exit;
}

$titulo_pagina = 'Flotilla — Conductores';
$pagina_activa = 'flotilla_conductores';
require_once __DIR__ . '/config/header.php';
require_once __DIR__ . '/config/flotilla_nav.php';

$conductores = db_all(
    "SELECT c.*, s.nombre sucursal_nombre FROM flotilla_conductores c LEFT JOIN sucursales s ON s.id=c.sucursal_id WHERE c.activo=1".($sucursal_filtro?" AND c.sucursal_id=:sid":"")." ORDER BY c.nombre_completo",
    $sucursal_filtro ? ['sid'=>$sucursal_filtro] : []
);
$sucursales = $ver_todas ? db_all("SELECT id,nombre FROM sucursales WHERE activo=1 ORDER BY nombre") : [];
$editar_id = (int) input('editar',0);
$editar_c  = $editar_id ? db_one("SELECT * FROM flotilla_conductores WHERE id=:id",['id'=>$editar_id]) : null;
$fm = flash_get();
?>
<div class="space-y-4 animate-fade-in">
<?php foreach ($fm as $f): ?>
<div class="px-4 py-3 rounded-lg text-sm <?= $f['tipo']==='success'?'bg-emerald-50 text-emerald-800 border border-emerald-200':'bg-red-50 text-red-800 border border-red-200' ?>"><?= e($f['mensaje']) ?></div>
<?php endforeach; ?>
<div class="flex items-center justify-between">
    <h1 class="text-xl font-display font-bold text-zinc-900">Conductores</h1>
    <button onclick="document.getElementById('modal-cond').classList.remove('hidden')" class="flex items-center gap-2 px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
        <i data-lucide="plus" class="w-4 h-4"></i> Agregar conductor
    </button>
</div>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200">
            <tr class="text-left text-xs font-semibold text-zinc-500 uppercase tracking-wide">
                <th class="px-4 py-3">Nombre</th><th class="px-4 py-3">N° Emp.</th><th class="px-4 py-3">Teléfono</th>
                <th class="px-4 py-3">Licencia</th><th class="px-4 py-3">Vence lic.</th><th class="px-4 py-3">Sucursal</th><th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            <?php if (empty($conductores)): ?>
            <tr><td colspan="7" class="px-4 py-10 text-center text-zinc-400">Sin conductores registrados.</td></tr>
            <?php endif; ?>
            <?php foreach ($conductores as $c): ?>
            <tr class="hover:bg-zinc-50">
                <td class="px-4 py-2.5 font-medium text-zinc-900"><?= e($c['nombre_completo']) ?></td>
                <td class="px-4 py-2.5 text-zinc-600"><?= e($c['numero_empleado'] ?? '—') ?></td>
                <td class="px-4 py-2.5 text-zinc-600"><?= e($c['telefono'] ?? '—') ?></td>
                <td class="px-4 py-2.5 text-xs"><?= e($c['licencia_numero'] ?? '—') ?><?= $c['licencia_tipo'] ? ' ('.$c['licencia_tipo'].')' : '' ?></td>
                <td class="px-4 py-2.5 text-xs <?= ($c['licencia_vence'] && strtotime($c['licencia_vence']) < strtotime('+30 days')) ? 'text-red-600 font-semibold' : 'text-zinc-600' ?>">
                    <?= $c['licencia_vence'] ? fmt_fecha($c['licencia_vence'],false) : '—' ?>
                </td>
                <td class="px-4 py-2.5 text-zinc-500 text-xs"><?= e($c['sucursal_nombre'] ?? '—') ?></td>
                <td class="px-4 py-2.5">
                    <div class="flex gap-2">
                        <a href="?editar=<?= $c['id'] ?>" class="text-zinc-400 hover:text-bacal-700"><i data-lucide="pencil" class="w-4 h-4"></i></a>
                        <form method="POST" onsubmit="return confirm('¿Desactivar?')" class="inline">
                            <?= csrf_input() ?><input type="hidden" name="op" value="eliminar"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="text-zinc-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>

<?php $mc = $editar_c ?? []; ?>
<div id="modal-cond" class="<?= $editar_c ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-5 border-b border-zinc-200">
            <h2 class="text-base font-bold text-zinc-900"><?= $editar_c ? 'Editar' : 'Agregar' ?> conductor</h2>
            <button onclick="document.getElementById('modal-cond').classList.add('hidden')" class="text-zinc-400 hover:text-zinc-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="<?= $editar_c ? 'editar' : 'crear' ?>">
            <?php if ($editar_c): ?><input type="hidden" name="id" value="<?= $mc['id'] ?>"><?php endif; ?>
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2"><label class="block text-xs font-bold text-zinc-700 mb-1">Nombre completo *</label><input type="text" name="nombre_completo" value="<?= e($mc['nombre_completo']??'') ?>" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></div>
                <div><label class="block text-xs font-bold text-zinc-700 mb-1">N° Empleado</label><input type="text" name="numero_empleado" value="<?= e($mc['numero_empleado']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></div>
                <div><label class="block text-xs font-bold text-zinc-700 mb-1">Teléfono</label><input type="text" name="telefono" value="<?= e($mc['telefono']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></div>
                <div class="col-span-2"><label class="block text-xs font-bold text-zinc-700 mb-1">Email</label><input type="email" name="email" value="<?= e($mc['email']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></div>
                <div><label class="block text-xs font-bold text-zinc-700 mb-1">N° Licencia</label><input type="text" name="licencia_numero" value="<?= e($mc['licencia_numero']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></div>
                <div><label class="block text-xs font-bold text-zinc-700 mb-1">Tipo (A,B,C…)</label><input type="text" name="licencia_tipo" value="<?= e($mc['licencia_tipo']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></div>
                <div><label class="block text-xs font-bold text-zinc-700 mb-1">Vence licencia</label><input type="date" name="licencia_vence" value="<?= e($mc['licencia_vence']??'') ?>" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></div>
                <?php if ($ver_todas): ?>
                <div><label class="block text-xs font-bold text-zinc-700 mb-1">Sucursal</label><select name="sucursal_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><option value="">Sin sucursal</option><?php foreach ($sucursales as $s): ?><option value="<?= $s['id'] ?>" <?= ($mc['sucursal_id']??0)==$s['id']?'selected':'' ?>><?= e($s['nombre']) ?></option><?php endforeach; ?></select></div>
                <?php endif; ?>
            </div>
            <div><label class="block text-xs font-bold text-zinc-700 mb-1">Notas</label><textarea name="notas" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e($mc['notas']??'') ?></textarea></div>
            <div class="flex justify-end gap-3 pt-3 border-t border-zinc-100">
                <button type="button" onclick="document.getElementById('modal-cond').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Guardar</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/config/footer.php'; ?>
