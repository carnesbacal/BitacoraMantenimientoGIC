<?php
/**
 * ============================================================================
 * admin/sucursales.php - Gestión de sucursales
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin_helpers.php';

$accion = (string) input('accion', 'listar');
$id     = (int) input('id', 0);

$sucursal_edit = null;
if (in_array($accion, ['editar', 'toggle'], true) && $id > 0) {
    $sucursal_edit = db_one("SELECT * FROM sucursales WHERE id = :id", ['id' => $id]);
    if (!$sucursal_edit) {
        flash_set('error', 'Sucursal no encontrada.');
        header('Location: ' . url('admin/sucursales.php'));
        exit;
    }
}

$errores = [];

if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token de seguridad inválido.';
    } else {
        $op = (string) input('op', '');
        try {
            if ($op === 'crear' || $op === 'editar') {
                $nombre = trim((string) input('nombre', ''));
                $codigo = strtoupper(trim((string) input('codigo', '')));
                $dir    = trim((string) input('direccion', ''));
                $tel    = trim((string) input('telefono', ''));
                $resp   = trim((string) input('responsable', ''));

                if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
                if ($codigo === '') $errores[] = 'El código es obligatorio.';
                if (!preg_match('/^[A-Z0-9]{2,10}$/', $codigo)) $errores[] = 'El código debe ser de 2-10 caracteres en mayúsculas (letras/números).';

                $check_id = $op === 'editar' ? (int) $sucursal_edit['id'] : 0;
                $dup = db_one("SELECT id FROM sucursales WHERE (nombre = :n OR codigo = :c) AND id <> :id",
                    ['n' => $nombre, 'c' => $codigo, 'id' => $check_id]);
                if ($dup) $errores[] = 'Ya existe una sucursal con ese nombre o código.';

                if (empty($errores)) {
                    if ($op === 'crear') {
                        db_exec(
                            "INSERT INTO sucursales (nombre, codigo, direccion, telefono, responsable, activo)
                             VALUES (:n, :c, :d, :t, :r, 1)",
                            ['n' => $nombre, 'c' => $codigo, 'd' => $dir ?: null, 't' => $tel ?: null, 'r' => $resp ?: null]
                        );
                        $new_id = db_last_id();
                        registrar_auditoria('crear_sucursal', 'sucursales', $new_id, "Sucursal $nombre");
                        flash_set('success', "Sucursal \"$nombre\" creada.");
                    } else {
                        db_exec(
                            "UPDATE sucursales SET nombre=:n, codigo=:c, direccion=:d, telefono=:t, responsable=:r WHERE id=:id",
                            ['n' => $nombre, 'c' => $codigo, 'd' => $dir ?: null, 't' => $tel ?: null, 'r' => $resp ?: null, 'id' => $sucursal_edit['id']]
                        );
                        registrar_auditoria('editar_sucursal', 'sucursales', $sucursal_edit['id'], "Sucursal $nombre");
                        flash_set('success', 'Sucursal actualizada.');
                    }
                    header('Location: ' . url('admin/sucursales.php'));
                    exit;
                }
            } elseif ($op === 'toggle' && $sucursal_edit) {
                admin_toggle_activo('sucursales', $sucursal_edit['id'], "Sucursal {$sucursal_edit['nombre']}");
                header('Location: ' . url('admin/sucursales.php'));
                exit;
            }
        } catch (Throwable $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

$titulo_pagina = 'Sucursales';
$pagina_activa = 'admin_sucursales';
require_once __DIR__ . '/../config/header.php';

if ($accion === 'nuevo' || ($accion === 'editar' && $sucursal_edit)):
    $es_edicion = ($accion === 'editar');
    $s = $sucursal_edit;
?>
<div class="max-w-2xl mx-auto animate-fade-in">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= url('admin/sucursales.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h2 class="font-display text-2xl font-extrabold text-zinc-900"><?= $es_edicion ? 'Editar sucursal' : 'Nueva sucursal' ?></h2>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="mb-5 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs"><?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="<?= $es_edicion ? 'editar' : 'crear' ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre *</label>
                <input type="text" name="nombre" required maxlength="100"
                       value="<?= e($es_edicion ? $s['nombre'] : (string) input('nombre', '')) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Código *</label>
                <input type="text" name="codigo" required maxlength="10" pattern="[A-Z0-9]{2,10}"
                       value="<?= e($es_edicion ? $s['codigo'] : (string) input('codigo', '')) ?>"
                       placeholder="ej. BAC, FER"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm font-mono uppercase focus:outline-none focus:border-bacal-700"
                       style="text-transform: uppercase;">
                <p class="text-[10px] text-zinc-500 mt-1">2-10 caracteres. Se usa en los folios (INC-XXX-...)</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Teléfono</label>
                <input type="text" name="telefono" maxlength="50"
                       value="<?= e($es_edicion ? (string) $s['telefono'] : (string) input('telefono', '')) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Dirección</label>
                <input type="text" name="direccion" maxlength="255"
                       value="<?= e($es_edicion ? (string) $s['direccion'] : (string) input('direccion', '')) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Responsable</label>
                <input type="text" name="responsable" maxlength="150"
                       value="<?= e($es_edicion ? (string) $s['responsable'] : (string) input('responsable', '')) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-zinc-100">
            <a href="<?= url('admin/sucursales.php') ?>" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                <?= $es_edicion ? 'Guardar' : 'Crear' ?>
            </button>
        </div>
    </form>
</div>

<?php else:
    $sucursales = db_all(
        "SELECT s.*,
                (SELECT COUNT(*) FROM usuarios WHERE sucursal_id = s.id AND activo=1) AS usuarios_count,
                (SELECT COUNT(*) FROM equipos WHERE sucursal_id = s.id AND activo=1) AS equipos_count,
                (SELECT COUNT(*) FROM incidencias WHERE sucursal_id = s.id) AS incidencias_count
         FROM sucursales s ORDER BY s.activo DESC, s.nombre ASC"
    );
?>

<?php render_admin_header('Sucursales', count($sucursales) . ' sucursal(es) registrada(s)', url('admin/sucursales.php?accion=nuevo'), 'Nueva sucursal'); ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($sucursales as $s): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5 <?= !$s['activo'] ? 'opacity-50' : '' ?>">
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2">
                <div class="w-10 h-10 rounded-lg bg-bacal-700 text-white flex items-center justify-center font-display font-bold">
                    <?= e(substr($s['codigo'], 0, 1)) ?>
                </div>
                <div>
                    <div class="font-semibold text-zinc-900"><?= e($s['nombre']) ?></div>
                    <div class="font-mono text-[10px] text-zinc-500"><?= e($s['codigo']) ?></div>
                </div>
            </div>
            <div class="flex gap-1">
                <a href="<?= url('admin/sucursales.php?accion=editar&id=' . $s['id']) ?>"
                   class="p-1.5 rounded text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700">
                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                </a>
                <form method="POST" action="<?= url('admin/sucursales.php?accion=toggle&id=' . $s['id']) ?>"
                      onsubmit="return confirm('¿<?= $s['activo'] ? 'Desactivar' : 'Activar' ?> esta sucursal?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="toggle">
                    <button type="submit" class="p-1.5 rounded text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700"
                            title="<?= $s['activo'] ? 'Desactivar' : 'Activar' ?>">
                        <i data-lucide="<?= $s['activo'] ? 'power' : 'power-off' ?>" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>

        <?php if ($s['direccion']): ?>
        <div class="text-xs text-zinc-600 mb-1 flex items-start gap-1.5">
            <i data-lucide="map-pin" class="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-zinc-400"></i>
            <span><?= e($s['direccion']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($s['telefono']): ?>
        <div class="text-xs text-zinc-600 mb-3 flex items-center gap-1.5">
            <i data-lucide="phone" class="w-3.5 h-3.5 text-zinc-400"></i>
            <span><?= e($s['telefono']) ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-3 gap-2 pt-3 border-t border-zinc-100 text-center">
            <div>
                <div class="font-display text-lg font-bold text-zinc-900"><?= $s['usuarios_count'] ?></div>
                <div class="text-[10px] text-zinc-500 uppercase tracking-wide">Usuarios</div>
            </div>
            <div>
                <div class="font-display text-lg font-bold text-zinc-900"><?= $s['equipos_count'] ?></div>
                <div class="text-[10px] text-zinc-500 uppercase tracking-wide">Equipos</div>
            </div>
            <div>
                <div class="font-display text-lg font-bold text-zinc-900"><?= $s['incidencias_count'] ?></div>
                <div class="text-[10px] text-zinc-500 uppercase tracking-wide">Incidencias</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
