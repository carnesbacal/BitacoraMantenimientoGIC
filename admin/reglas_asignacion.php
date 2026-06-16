<?php
/**
 * ============================================================================
 * admin/reglas_asignacion.php - Reglas de auto-asignación de incidencias
 * ============================================================================
 * Permite al admin definir reglas que asignan automáticamente un técnico
 * a las incidencias nuevas según condiciones.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin_helpers.php';
require_once __DIR__ . '/../config/inteligencia_helpers.php';

$u = usuario_actual();

$accion = (string) input('accion', 'listar');
$id     = (int) input('id', 0);

$regla_edit = null;
if (in_array($accion, ['editar','toggle','eliminar'], true) && $id > 0) {
    $regla_edit = db_one("SELECT * FROM reglas_asignacion WHERE id = :id", ['id' => $id]);
    if (!$regla_edit) {
        flash_set('error', 'Regla no encontrada.');
        header('Location: ' . url('admin/reglas_asignacion.php'));
        exit;
    }
}

$errores = [];

// ----------------------------------------------------------------------------
// Procesar POST
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token inválido.';
    } else {
        $op = (string) input('op', '');

        try {
            if ($op === 'crear' || $op === 'editar') {
                $datos = [
                    'nombre'          => trim((string) input('nombre', '')),
                    'descripcion'     => trim((string) input('descripcion', '')) ?: null,
                    'sucursal_id'     => (int) input('sucursal_id', 0) ?: null,
                    'area_id'         => (int) input('area_id', 0) ?: null,
                    'categoria_id'    => (int) input('categoria_id', 0) ?: null,
                    'tipo_trabajo_id' => (int) input('tipo_trabajo_id', 0) ?: null,
                    'severidad_id'    => (int) input('severidad_id', 0) ?: null,
                    'asignar_a_id'    => (int) input('asignar_a_id', 0),
                    'prioridad'       => (int) input('prioridad', 100),
                ];

                if ($datos['nombre'] === '') $errores[] = 'El nombre es obligatorio.';
                if ($datos['asignar_a_id'] <= 0) $errores[] = 'Debes seleccionar a quién asignar.';

                // Validar que al menos una condición sea no-NULL para evitar regla "captura todo"
                $tiene_condicion = $datos['sucursal_id'] || $datos['area_id'] || $datos['categoria_id'] ||
                                   $datos['tipo_trabajo_id'] || $datos['severidad_id'];
                if (!$tiene_condicion) {
                    $errores[] = 'Debes especificar al menos una condición. Una regla sin condiciones aplicaría a TODO.';
                }

                if (empty($errores)) {
                    if ($op === 'crear') {
                        $datos['creado_por_id'] = $u['id'];
                        $datos['activa'] = 1;
                        $cols = implode(', ', array_keys($datos));
                        $params = ':' . implode(', :', array_keys($datos));
                        db_exec("INSERT INTO reglas_asignacion ($cols) VALUES ($params)", $datos);
                        $new_id = db_last_id();
                        registrar_auditoria('crear_regla', 'reglas_asignacion', $new_id, "Regla {$datos['nombre']}");
                        flash_set('success', "Regla \"{$datos['nombre']}\" creada.");
                    } else {
                        $sets = [];
                        foreach (array_keys($datos) as $k) $sets[] = "$k = :$k";
                        $datos['id'] = $regla_edit['id'];
                        db_exec("UPDATE reglas_asignacion SET " . implode(', ', $sets) . " WHERE id = :id", $datos);
                        registrar_auditoria('editar_regla', 'reglas_asignacion', $regla_edit['id'], "Regla {$datos['nombre']}");
                        flash_set('success', 'Regla actualizada.');
                    }
                    header('Location: ' . url('admin/reglas_asignacion.php'));
                    exit;
                }
            } elseif ($op === 'toggle' && $regla_edit) {
                $nuevo = (int) $regla_edit['activa'] === 1 ? 0 : 1;
                db_exec("UPDATE reglas_asignacion SET activa = :a WHERE id = :id",
                    ['a' => $nuevo, 'id' => $regla_edit['id']]);
                registrar_auditoria($nuevo ? 'activar_regla' : 'desactivar_regla',
                    'reglas_asignacion', (int) $regla_edit['id'], "Regla {$regla_edit['nombre']}");
                flash_set('success', "Regla " . ($nuevo ? 'activada' : 'desactivada') . ".");
                header('Location: ' . url('admin/reglas_asignacion.php'));
                exit;
            } elseif ($op === 'eliminar' && $regla_edit) {
                db_exec("DELETE FROM reglas_asignacion WHERE id = :id", ['id' => $regla_edit['id']]);
                registrar_auditoria('eliminar_regla', 'reglas_asignacion', (int) $regla_edit['id'],
                    "Eliminó regla {$regla_edit['nombre']}");
                flash_set('success', 'Regla eliminada.');
                header('Location: ' . url('admin/reglas_asignacion.php'));
                exit;
            }
        } catch (Throwable $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

// ----------------------------------------------------------------------------
// Catálogos
// ----------------------------------------------------------------------------
$sucursales  = db_all("SELECT id, nombre FROM sucursales WHERE activo=1 ORDER BY nombre");
$areas       = db_all("SELECT id, nombre FROM areas WHERE activo=1 ORDER BY nombre");
$categorias  = db_all("SELECT id, nombre FROM categorias WHERE activo=1 ORDER BY nombre");
$tipos       = db_all("SELECT id, nombre FROM tipos_trabajo WHERE activo=1 ORDER BY nombre");
$severidades = db_all("SELECT id, nombre FROM severidades WHERE activo=1 ORDER BY nivel");
$tecnicos    = db_all(
    "SELECT u.id, u.nombre_completo FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE u.activo = 1 AND r.puede_resolver = 1
     ORDER BY u.nombre_completo"
);

$titulo_pagina = 'Reglas de auto-asignación';
$pagina_activa = 'admin_reglas';
require_once __DIR__ . '/../config/header.php';

// ============================================================================
// VISTA: FORMULARIO
// ============================================================================
if ($accion === 'nuevo' || ($accion === 'editar' && $regla_edit)):
    $es_edicion = ($accion === 'editar');
    $r = $regla_edit;
?>
<div class="max-w-3xl mx-auto animate-fade-in">

    <div class="flex items-center gap-3 mb-6">
        <a href="<?= url('admin/reglas_asignacion.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">
                <?= $es_edicion ? 'Editar regla' : 'Nueva regla de auto-asignación' ?>
            </h2>
            <p class="text-xs text-zinc-500">Si una incidencia cumple TODAS las condiciones, se asignará automáticamente al técnico indicado.</p>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="mb-5 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs">
            <?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="<?= $es_edicion ? 'editar' : 'crear' ?>">

        <!-- Información básica -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-bacal-700"></i> Información
            </h3>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre de la regla *</label>
                <input type="text" name="nombre" required maxlength="150"
                       value="<?= e($es_edicion ? $r['nombre'] : (string) input('nombre', '')) ?>"
                       placeholder="ej. POS de Bacal va a Carlos, Críticas a Abraham"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Descripción</label>
                <input type="text" name="descripcion" maxlength="255"
                       value="<?= e($es_edicion ? (string) $r['descripcion'] : (string) input('descripcion', '')) ?>"
                       placeholder="Por qué existe esta regla, contexto..."
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Prioridad</label>
                <input type="number" name="prioridad" min="1" max="999"
                       value="<?= e($es_edicion ? (string) $r['prioridad'] : '100') ?>"
                       class="w-32 px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                <p class="text-[10px] text-zinc-500 mt-1">Menor número = se evalúa antes (1 = máxima prioridad). Usa esto para resolver conflictos entre reglas.</p>
            </div>
        </div>

        <!-- Condiciones -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="filter" class="w-4 h-4 text-bacal-700"></i> Condiciones
            </h3>
            <p class="text-xs text-zinc-500">La regla se aplica SOLO si la incidencia cumple TODAS las condiciones especificadas. Deja vacío lo que no quieras filtrar.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Sucursal</label>
                    <select name="sucursal_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Cualquier sucursal —</option>
                        <?php foreach ($sucursales as $s):
                            $sel = $es_edicion ? $r['sucursal_id'] : (string) input('sucursal_id', '');
                        ?>
                        <option value="<?= $s['id'] ?>" <?= (string) $sel === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Área</label>
                    <select name="area_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Cualquier área —</option>
                        <?php foreach ($areas as $a):
                            $sel = $es_edicion ? $r['area_id'] : (string) input('area_id', '');
                        ?>
                        <option value="<?= $a['id'] ?>" <?= (string) $sel === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Categoría</label>
                    <select name="categoria_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Cualquier categoría —</option>
                        <?php foreach ($categorias as $c):
                            $sel = $es_edicion ? $r['categoria_id'] : (string) input('categoria_id', '');
                        ?>
                        <option value="<?= $c['id'] ?>" <?= (string) $sel === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Tipo de trabajo</label>
                    <select name="tipo_trabajo_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Cualquier tipo —</option>
                        <?php foreach ($tipos as $t):
                            $sel = $es_edicion ? $r['tipo_trabajo_id'] : (string) input('tipo_trabajo_id', '');
                        ?>
                        <option value="<?= $t['id'] ?>" <?= (string) $sel === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Severidad</label>
                    <select name="severidad_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Cualquier severidad —</option>
                        <?php foreach ($severidades as $sv):
                            $sel = $es_edicion ? $r['severidad_id'] : (string) input('severidad_id', '');
                        ?>
                        <option value="<?= $sv['id'] ?>" <?= (string) $sel === (string) $sv['id'] ? 'selected' : '' ?>><?= e($sv['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Acción -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="user-check" class="w-4 h-4 text-bacal-700"></i> Asignar a
            </h3>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Técnico * </label>
                <select name="asignar_a_id" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">— Selecciona técnico —</option>
                    <?php foreach ($tecnicos as $t):
                        $sel = $es_edicion ? $r['asignar_a_id'] : (string) input('asignar_a_id', '');
                    ?>
                    <option value="<?= $t['id'] ?>" <?= (string) $sel === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['nombre_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= url('admin/reglas_asignacion.php') ?>" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                <?= $es_edicion ? 'Guardar cambios' : 'Crear regla' ?>
            </button>
        </div>
    </form>
</div>

<?php
// ============================================================================
// VISTA: LISTADO
// ============================================================================
else:
    $reglas = db_all(
        "SELECT r.*, u.nombre_completo asignado_nombre, u.avatar_url asignado_avatar
         FROM reglas_asignacion r
         INNER JOIN usuarios u ON r.asignar_a_id = u.id
         ORDER BY r.activa DESC, r.prioridad ASC, r.id ASC"
    );
?>

<?php render_admin_header(
    'Reglas de auto-asignación',
    'Asigna técnicos automáticamente al crear incidencias según condiciones. ' . count($reglas) . ' regla(s).',
    url('admin/reglas_asignacion.php?accion=nuevo'),
    'Nueva regla'
); ?>

<!-- Cómo funciona -->
<div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5 flex items-start gap-3">
    <i data-lucide="lightbulb" class="w-5 h-5 text-blue-700 flex-shrink-0 mt-0.5"></i>
    <div class="text-xs text-blue-900 flex-1">
        <strong>Cómo funcionan las reglas:</strong>
        <ol class="list-decimal list-inside mt-1 space-y-0.5">
            <li>Al crear una nueva incidencia (manual o por plantilla), el sistema evalúa las reglas activas en orden de prioridad.</li>
            <li>La primera regla cuyas condiciones se cumplan, aplica.</li>
            <li>Si el usuario ya seleccionó un técnico manualmente, la regla NO sobreescribe.</li>
            <li>Si ninguna regla aplica, la incidencia queda sin asignar.</li>
        </ol>
    </div>
</div>

<?php if (empty($reglas)): ?>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-12 text-center">
    <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
        <i data-lucide="settings-2" class="w-8 h-8 text-zinc-400"></i>
    </div>
    <p class="text-sm font-medium text-zinc-700 mb-1">Aún no hay reglas configuradas</p>
    <p class="text-xs text-zinc-500 mb-4">Crea reglas para que las incidencias se asignen automáticamente a tu equipo.</p>
    <a href="<?= url('admin/reglas_asignacion.php?accion=nuevo') ?>"
       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
        <i data-lucide="plus" class="w-4 h-4"></i> Crear primera regla
    </a>
</div>
<?php else: ?>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200">
            <tr>
                <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase">#</th>
                <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase">Nombre</th>
                <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase">Condiciones</th>
                <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase">Asigna a</th>
                <th class="px-4 py-2.5 text-center text-[10px] font-bold text-zinc-500 uppercase">Aplicada</th>
                <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            <?php foreach ($reglas as $r): ?>
            <tr class="<?= !$r['activa'] ? 'opacity-50' : '' ?>">
                <td class="px-4 py-3 text-center">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-zinc-100 text-zinc-700 text-xs font-bold">
                        <?= (int) $r['prioridad'] ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <div class="font-semibold text-sm text-zinc-900"><?= e($r['nombre']) ?></div>
                    <?php if ($r['descripcion']): ?>
                    <div class="text-[11px] text-zinc-500 mt-0.5"><?= e($r['descripcion']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                    <div class="text-xs text-zinc-700 leading-relaxed"><?= describir_regla($r) ?></div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <?= render_avatar(['nombre_completo' => $r['asignado_nombre'], 'avatar_url' => $r['asignado_avatar']], 'w-7 h-7') ?>
                        <span class="text-sm text-zinc-900 font-medium"><?= e($r['asignado_nombre']) ?></span>
                    </div>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="text-sm font-bold text-zinc-700"><?= (int) $r['veces_aplicada'] ?></span>
                    <div class="text-[10px] text-zinc-500">veces</div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-1">
                        <a href="<?= url('admin/reglas_asignacion.php?accion=editar&id=' . $r['id']) ?>"
                           class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700" title="Editar">
                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                        </a>
                        <form method="POST" action="<?= url('admin/reglas_asignacion.php?accion=toggle&id=' . $r['id']) ?>">
                            <?= csrf_input() ?>
                            <input type="hidden" name="op" value="toggle">
                            <button type="submit" class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100"
                                    title="<?= $r['activa'] ? 'Desactivar' : 'Activar' ?>">
                                <i data-lucide="<?= $r['activa'] ? 'power' : 'power-off' ?>" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                        <form method="POST" action="<?= url('admin/reglas_asignacion.php?accion=eliminar&id=' . $r['id']) ?>"
                              onsubmit="return confirm('¿Eliminar permanentemente esta regla?');">
                            <?= csrf_input() ?>
                            <input type="hidden" name="op" value="eliminar">
                            <button type="submit" class="p-1.5 rounded text-zinc-500 hover:bg-bacal-50 hover:text-bacal-700" title="Eliminar">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
