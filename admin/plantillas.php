<?php
/**
 * ============================================================================
 * admin/plantillas.php - Gestión de plantillas de incidencias
 * ============================================================================
 * Permite administrar plantillas pre-rellenadas para acelerar el registro
 * de incidencias comunes (reset password, falta tinta, internet caído...).
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin_helpers.php';

$accion = (string) input('accion', 'listar');
$id     = (int) input('id', 0);

$plantilla_edit = null;
if (in_array($accion, ['editar', 'toggle'], true) && $id > 0) {
    $plantilla_edit = db_one("SELECT * FROM plantillas_incidencias WHERE id = :id", ['id' => $id]);
    if (!$plantilla_edit) {
        flash_set('error', 'Plantilla no encontrada.');
        header('Location: ' . url('admin/plantillas.php'));
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
                $datos = [
                    'nombre'            => trim((string) input('nombre', '')),
                    'descripcion'       => trim((string) input('descripcion', '')) ?: null,
                    'icono'             => trim((string) input('icono', 'file-text')) ?: 'file-text',
                    'color'             => (string) input('color', '#6B7280'),
                    'titulo'            => trim((string) input('titulo', '')) ?: null,
                    'descripcion_inc'   => trim((string) input('descripcion_inc', '')) ?: null,
                    'area_id'           => (int) input('area_id', 0) ?: null,
                    'categoria_id'      => (int) input('categoria_id', 0) ?: null,
                    'subcategoria_id'   => (int) input('subcategoria_id', 0) ?: null,
                    'tipo_trabajo_id'   => (int) input('tipo_trabajo_id', 0) ?: null,
                    'severidad_id'      => (int) input('severidad_id', 0) ?: null,
                    'origen_reporte_id' => (int) input('origen_reporte_id', 0) ?: null,
                    'solucion_sugerida' => trim((string) input('solucion_sugerida', '')) ?: null,
                ];

                if ($datos['nombre'] === '') $errores[] = 'El nombre es obligatorio.';

                if (empty($errores)) {
                    if ($op === 'crear') {
                        $u = usuario_actual();
                        $datos['creado_por_id'] = $u['id'];
                        $datos['activo'] = 1;
                        $cols = implode(', ', array_keys($datos));
                        $params = ':' . implode(', :', array_keys($datos));
                        db_exec("INSERT INTO plantillas_incidencias ($cols) VALUES ($params)", $datos);
                        $new_id = db_last_id();
                        registrar_auditoria('crear_plantilla', 'plantillas_incidencias', $new_id, "Plantilla {$datos['nombre']}");
                        flash_set('success', "Plantilla \"{$datos['nombre']}\" creada.");
                    } else {
                        $sets = [];
                        foreach (array_keys($datos) as $k) $sets[] = "$k = :$k";
                        $datos['id'] = $plantilla_edit['id'];
                        db_exec("UPDATE plantillas_incidencias SET " . implode(', ', $sets) . " WHERE id = :id", $datos);
                        registrar_auditoria('editar_plantilla', 'plantillas_incidencias', $plantilla_edit['id'], "Plantilla {$datos['nombre']}");
                        flash_set('success', 'Plantilla actualizada.');
                    }
                    header('Location: ' . url('admin/plantillas.php'));
                    exit;
                }
            } elseif ($op === 'toggle' && $plantilla_edit) {
                admin_toggle_activo('plantillas_incidencias', $plantilla_edit['id'], "Plantilla {$plantilla_edit['nombre']}");
                header('Location: ' . url('admin/plantillas.php'));
                exit;
            } elseif ($op === 'eliminar' && $plantilla_edit) {
                db_exec("DELETE FROM plantillas_incidencias WHERE id = :id", ['id' => $plantilla_edit['id']]);
                registrar_auditoria('eliminar_plantilla', 'plantillas_incidencias', $plantilla_edit['id'], "Eliminó plantilla {$plantilla_edit['nombre']}");
                flash_set('success', 'Plantilla eliminada.');
                header('Location: ' . url('admin/plantillas.php'));
                exit;
            }
        } catch (Throwable $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Catálogos para selects
$areas       = db_all("SELECT id, nombre FROM areas WHERE activo=1 ORDER BY nombre");
$categorias  = db_all("SELECT id, nombre FROM categorias WHERE activo=1 ORDER BY nombre");
$tipos       = db_all("SELECT id, nombre FROM tipos_trabajo WHERE activo=1 ORDER BY nombre");
$severidades = db_all("SELECT id, nombre FROM severidades WHERE activo=1 ORDER BY nivel");
$origenes    = db_all("SELECT id, nombre FROM origenes_reporte WHERE activo=1 ORDER BY id");

// Iconos sugeridos para selección
$iconos_sugeridos = [
    'file-text', 'key', 'printer', 'wifi-off', 'monitor-x', 'cpu', 'mail-x',
    'wrench', 'scale', 'phone', 'mouse', 'keyboard', 'hard-drive', 'lock',
    'shield-alert', 'database', 'cloud-off', 'router', 'usb', 'battery-warning',
];

$titulo_pagina = 'Plantillas';
$pagina_activa = 'admin_plantillas';
require_once __DIR__ . '/../config/header.php';

if ($accion === 'nuevo' || ($accion === 'editar' && $plantilla_edit)):
    $es_edicion = ($accion === 'editar');
    $p = $plantilla_edit;
?>
<div class="max-w-3xl mx-auto animate-fade-in" x-data="{
    icono: '<?= e($es_edicion ? $p['icono'] : 'file-text') ?>',
    color: '<?= e($es_edicion ? $p['color'] : '#6B7280') ?>',
    categoriaId: '<?= e((string) ($es_edicion ? $p['categoria_id'] : '')) ?>',
    subcategoriaId: '<?= e((string) ($es_edicion ? $p['subcategoria_id'] : '')) ?>',
    subcategorias: [],

    async cargarSubcategorias() {
        if (!this.categoriaId) { this.subcategorias = []; return; }
        try {
            const resp = await fetch('<?= url('api/buscar_reincidencias.php') ?>?area=1&categoria=' + this.categoriaId);
            // Reutilizamos el endpoint solo para cargar subcategorías directo de BD
        } catch(e) { console.error(e); }
    }
}">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= url('admin/plantillas.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h2 class="font-display text-2xl font-extrabold text-zinc-900">
            <?= $es_edicion ? 'Editar plantilla' : 'Nueva plantilla' ?>
        </h2>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="mb-5 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs"><?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="<?= $es_edicion ? 'editar' : 'crear' ?>">
        <input type="hidden" name="icono" :value="icono">
        <input type="hidden" name="color" :value="color">

        <!-- Apariencia -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4">Identificación visual</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre de la plantilla *</label>
                    <input type="text" name="nombre" required maxlength="150"
                           value="<?= e($es_edicion ? $p['nombre'] : (string) input('nombre', '')) ?>"
                           placeholder="ej. Reset de contraseña"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Descripción corta</label>
                    <input type="text" name="descripcion" maxlength="255"
                           value="<?= e($es_edicion ? (string) $p['descripcion'] : (string) input('descripcion', '')) ?>"
                           placeholder="Cuándo usar esta plantilla"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Color</label>
                    <?= render_color_picker('color_disp', $es_edicion ? $p['color'] : '#6B7280') ?>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Ícono</label>
                    <div class="grid grid-cols-10 gap-1.5">
                        <?php foreach ($iconos_sugeridos as $ic): ?>
                        <button type="button" @click="icono = '<?= e($ic) ?>'"
                                class="w-8 h-8 rounded-md flex items-center justify-center transition-all border-2"
                                :class="icono === '<?= e($ic) ?>' ? 'border-zinc-900 bg-zinc-100' : 'border-transparent bg-zinc-50 hover:bg-zinc-100'">
                            <i data-lucide="<?= e($ic) ?>" class="w-4 h-4 text-zinc-700"></i>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <div class="bg-zinc-50 border border-zinc-200 rounded-lg p-3 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" :style="`background-color: ${color}15`">
                            <i :data-lucide="icono" class="w-5 h-5" :style="`color: ${color}`"></i>
                        </div>
                        <div class="text-xs text-zinc-500">Vista previa de cómo se verá la plantilla en el formulario de nueva incidencia.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Datos pre-rellenados -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-1">Datos pre-rellenados</h3>
            <p class="text-xs text-zinc-500 mb-4">Estos campos se copiarán automáticamente al formulario de nueva incidencia cuando se use esta plantilla. Todo es opcional.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Título sugerido</label>
                    <input type="text" name="titulo" maxlength="255"
                           value="<?= e($es_edicion ? (string) $p['titulo'] : (string) input('titulo', '')) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Descripción sugerida (plantilla)</label>
                    <textarea name="descripcion_inc" rows="4"
                              placeholder="Puedes incluir placeholders como 'Usuario: ____' para que se completen al usar la plantilla."
                              class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e($es_edicion ? (string) $p['descripcion_inc'] : (string) input('descripcion_inc', '')) ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Área típica</label>
                    <select name="area_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin pre-seleccionar —</option>
                        <?php foreach ($areas as $a):
                            $sel = $es_edicion ? $p['area_id'] : (string) input('area_id', '');
                        ?>
                        <option value="<?= $a['id'] ?>" <?= (string) $sel === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Categoría</label>
                    <select name="categoria_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin pre-seleccionar —</option>
                        <?php foreach ($categorias as $c):
                            $sel = $es_edicion ? $p['categoria_id'] : (string) input('categoria_id', '');
                        ?>
                        <option value="<?= $c['id'] ?>" <?= (string) $sel === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Tipo de trabajo</label>
                    <select name="tipo_trabajo_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin pre-seleccionar —</option>
                        <?php foreach ($tipos as $t):
                            $sel = $es_edicion ? $p['tipo_trabajo_id'] : (string) input('tipo_trabajo_id', '');
                        ?>
                        <option value="<?= $t['id'] ?>" <?= (string) $sel === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Severidad típica</label>
                    <select name="severidad_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin pre-seleccionar —</option>
                        <?php foreach ($severidades as $s):
                            $sel = $es_edicion ? $p['severidad_id'] : (string) input('severidad_id', '');
                        ?>
                        <option value="<?= $s['id'] ?>" <?= (string) $sel === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Origen del reporte</label>
                    <select name="origen_reporte_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin pre-seleccionar —</option>
                        <?php foreach ($origenes as $o):
                            $sel = $es_edicion ? $p['origen_reporte_id'] : (string) input('origen_reporte_id', '');
                        ?>
                        <option value="<?= $o['id'] ?>" <?= (string) $sel === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Solución sugerida</label>
                    <textarea name="solucion_sugerida" rows="5"
                              placeholder="Pasos típicos para resolver este tipo de problema (los técnicos los verán como guía al usar la plantilla)"
                              class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e($es_edicion ? (string) $p['solucion_sugerida'] : (string) input('solucion_sugerida', '')) ?></textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= url('admin/plantillas.php') ?>" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                <?= $es_edicion ? 'Guardar cambios' : 'Crear plantilla' ?>
            </button>
        </div>
    </form>
</div>

<?php else:
    $plantillas = db_all(
        "SELECT p.*, a.nombre area_nombre, c.nombre cat_nombre, sev.nombre sev_nombre, sev.color sev_color
         FROM plantillas_incidencias p
         LEFT JOIN areas a ON p.area_id = a.id
         LEFT JOIN categorias c ON p.categoria_id = c.id
         LEFT JOIN severidades sev ON p.severidad_id = sev.id
         ORDER BY p.activo DESC, p.usos DESC, p.nombre ASC"
    );
?>

<?php render_admin_header('Plantillas de incidencias', count($plantillas) . ' plantilla(s) configurada(s)', url('admin/plantillas.php?accion=nuevo'), 'Nueva plantilla'); ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($plantillas as $p): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5 <?= !$p['activo'] ? 'opacity-50' : '' ?>">
        <div class="flex items-start gap-3 mb-3">
            <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0"
                 style="background-color: <?= e($p['color']) ?>15">
                <i data-lucide="<?= e($p['icono']) ?>" class="w-5 h-5" style="color: <?= e($p['color']) ?>"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm text-zinc-900 truncate"><?= e($p['nombre']) ?></div>
                <?php if ($p['descripcion']): ?>
                <div class="text-xs text-zinc-500 line-clamp-2 mt-0.5"><?= e($p['descripcion']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap mb-3 min-h-[24px]">
            <?php if ($p['area_nombre']): ?>
            <span class="text-[10px] font-medium text-zinc-600 bg-zinc-100 px-1.5 py-0.5 rounded"><?= e($p['area_nombre']) ?></span>
            <?php endif; ?>
            <?php if ($p['cat_nombre']): ?>
            <span class="text-[10px] font-medium text-zinc-600 bg-zinc-100 px-1.5 py-0.5 rounded"><?= e($p['cat_nombre']) ?></span>
            <?php endif; ?>
            <?php if ($p['sev_nombre']): ?>
            <span class="inline-flex items-center text-[10px] font-medium px-1.5 py-0.5 rounded"
                  style="background-color: <?= e($p['sev_color']) ?>20; color: <?= e($p['sev_color']) ?>">
                <?= e($p['sev_nombre']) ?>
            </span>
            <?php endif; ?>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-zinc-100">
            <div class="text-[10px] text-zinc-500">
                Usada <strong class="text-zinc-700"><?= $p['usos'] ?></strong> vez/veces
            </div>
            <div class="flex gap-1">
                <a href="<?= url('admin/plantillas.php?accion=editar&id=' . $p['id']) ?>"
                   class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700">
                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                </a>
                <form method="POST" action="<?= url('admin/plantillas.php?accion=toggle&id=' . $p['id']) ?>">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="toggle">
                    <button type="submit" class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100"
                            title="<?= $p['activo'] ? 'Desactivar' : 'Activar' ?>">
                        <i data-lucide="<?= $p['activo'] ? 'power' : 'power-off' ?>" class="w-4 h-4"></i>
                    </button>
                </form>
                <form method="POST" action="<?= url('admin/plantillas.php?accion=toggle&id=' . $p['id']) ?>"
                      onsubmit="return confirm('¿Eliminar permanentemente esta plantilla?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="eliminar">
                    <button type="submit" class="p-1.5 rounded text-zinc-500 hover:bg-bacal-50 hover:text-bacal-700"
                            title="Eliminar permanentemente">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($plantillas)): ?>
    <div class="col-span-full bg-white rounded-xl border border-zinc-200 shadow-sm p-12 text-center">
        <div class="w-14 h-14 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
            <i data-lucide="layout-template" class="w-7 h-7 text-zinc-400"></i>
        </div>
        <p class="text-sm font-medium text-zinc-700">Aún no hay plantillas configuradas</p>
        <p class="text-xs text-zinc-500 mt-1 mb-4">Las plantillas aceleran el registro de incidencias comunes.</p>
        <a href="<?= url('admin/plantillas.php?accion=nuevo') ?>"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
            <i data-lucide="plus" class="w-4 h-4"></i> Crear primera plantilla
        </a>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
