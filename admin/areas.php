<?php
/**
 * ============================================================================
 * admin/areas.php - Gestión de áreas/departamentos
 * ============================================================================
 * CRUD compacto. Las áreas se crean y editan inline desde la misma página.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin_helpers.php';

$errores = [];

if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token de seguridad inválido.';
    } else {
        $op = (string) input('op', '');
        try {
            if ($op === 'crear') {
                $nombre = trim((string) input('nombre', ''));
                $descripcion = trim((string) input('descripcion', ''));
                $color  = (string) input('color', '#6B7280');

                if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
                $dup = db_one("SELECT id FROM areas WHERE nombre = :n", ['n' => $nombre]);
                if ($dup) $errores[] = 'Ya existe un área con ese nombre.';

                if (empty($errores)) {
                    db_exec("INSERT INTO areas (nombre, descripcion, color, activo) VALUES (:n, :d, :c, 1)",
                        ['n' => $nombre, 'd' => $descripcion ?: null, 'c' => $color]);
                    registrar_auditoria('crear_area', 'areas', db_last_id(), "Área $nombre");
                    flash_set('success', "Área \"$nombre\" creada.");
                }
            } elseif ($op === 'editar') {
                $aid = (int) input('id', 0);
                $a = db_one("SELECT * FROM areas WHERE id=:id", ['id' => $aid]);
                if (!$a) throw new Exception('Área no encontrada');

                $nombre = trim((string) input('nombre', ''));
                $descripcion = trim((string) input('descripcion', ''));
                $color  = (string) input('color', '#6B7280');

                if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
                $dup = db_one("SELECT id FROM areas WHERE nombre = :n AND id <> :id", ['n' => $nombre, 'id' => $aid]);
                if ($dup) $errores[] = 'Ya existe otra área con ese nombre.';

                if (empty($errores)) {
                    db_exec("UPDATE areas SET nombre=:n, descripcion=:d, color=:c WHERE id=:id",
                        ['n' => $nombre, 'd' => $descripcion ?: null, 'c' => $color, 'id' => $aid]);
                    registrar_auditoria('editar_area', 'areas', $aid, "Área $nombre");
                    flash_set('success', "Área actualizada.");
                }
            } elseif ($op === 'toggle') {
                $aid = (int) input('id', 0);
                $a = db_one("SELECT nombre FROM areas WHERE id=:id", ['id' => $aid]);
                if ($a) admin_toggle_activo('areas', $aid, "Área {$a['nombre']}");
            }
        } catch (Throwable $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }

        if (empty($errores)) {
            header('Location: ' . url('admin/areas.php'));
            exit;
        }
    }
}

$areas = db_all(
    "SELECT a.*,
            (SELECT COUNT(*) FROM incidencias WHERE area_id = a.id) AS incidencias_count
     FROM areas a ORDER BY a.activo DESC, a.nombre ASC"
);

$titulo_pagina = 'Áreas';
$pagina_activa = 'admin_areas';
require_once __DIR__ . '/../config/header.php';
?>

<div class="max-w-4xl mx-auto animate-fade-in" x-data="{ editandoId: null, mostrarNueva: false }">

    <?php render_admin_header('Áreas / departamentos', count($areas) . ' área(s) registrada(s)'); ?>

    <?php if (!empty($errores)): ?>
    <div class="mb-4 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs"><?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <!-- Botón "Nueva área" -->
    <div class="mb-4">
        <button @click="mostrarNueva = !mostrarNueva"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold shadow-sm">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span x-text="mostrarNueva ? 'Cancelar' : 'Nueva área'"></span>
        </button>
    </div>

    <!-- Formulario nueva área -->
    <div x-show="mostrarNueva" x-cloak x-transition class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5 mb-4">
        <form method="POST" class="space-y-3">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="crear">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre *</label>
                    <input type="text" name="nombre" required maxlength="100" autofocus
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Color</label>
                    <?= render_color_picker('color', '#6B7280') ?>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Descripción</label>
                    <input type="text" name="descripcion" maxlength="255"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="mostrarNueva = false"
                        class="px-4 py-1.5 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                <button type="submit" class="px-4 py-1.5 rounded-lg bg-bacal-700 text-white text-sm font-semibold">Crear área</button>
            </div>
        </form>
    </div>

    <!-- Lista de áreas -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <?php foreach ($areas as $a):
            $datos = json_encode([
                'id' => $a['id'], 'nombre' => $a['nombre'],
                'descripcion' => $a['descripcion'] ?? '', 'color' => $a['color']
            ], JSON_UNESCAPED_UNICODE);
        ?>
        <div class="border-b border-zinc-100 last:border-b-0 <?= !$a['activo'] ? 'opacity-50' : '' ?>">
            <!-- Vista normal -->
            <div x-show="editandoId !== <?= $a['id'] ?>" class="px-4 py-3 flex items-center gap-3 group">
                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium"
                      style="background-color: <?= e($a['color']) ?>1f; color: <?= e($a['color']) ?>; border: 1px solid <?= e($a['color']) ?>40;">
                    <?= e($a['nombre']) ?>
                </span>
                <div class="flex-1 min-w-0">
                    <?php if ($a['descripcion']): ?>
                    <div class="text-xs text-zinc-500 truncate"><?= e($a['descripcion']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-[11px] text-zinc-500 hidden md:block">
                    <span class="font-semibold text-zinc-700"><?= (int) $a['incidencias_count'] ?></span> incidencia(s)
                </div>
                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button @click="editandoId = <?= $a['id'] ?>"
                            class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700">
                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                    </button>
                    <form method="POST" onsubmit="return confirm('¿<?= $a['activo'] ? 'Desactivar' : 'Activar' ?> esta área?');">
                        <?= csrf_input() ?>
                        <input type="hidden" name="op" value="toggle">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit" class="p-1.5 rounded text-zinc-500 hover:bg-bacal-50 hover:text-bacal-700"
                                title="<?= $a['activo'] ? 'Desactivar' : 'Activar' ?>">
                            <i data-lucide="<?= $a['activo'] ? 'power' : 'power-off' ?>" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Vista de edición -->
            <div x-show="editandoId === <?= $a['id'] ?>" x-cloak class="px-4 py-3 bg-zinc-50">
                <form method="POST" class="space-y-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="editar">
                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Nombre</label>
                            <input type="text" name="nombre" value="<?= e($a['nombre']) ?>" required maxlength="100"
                                   class="w-full px-3 py-1.5 rounded-md border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Color</label>
                            <?= render_color_picker('color', $a['color']) ?>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Descripción</label>
                            <input type="text" name="descripcion" value="<?= e((string) $a['descripcion']) ?>" maxlength="255"
                                   class="w-full px-3 py-1.5 rounded-md border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="editandoId = null"
                                class="px-3 py-1 rounded-md border border-zinc-300 text-zinc-700 text-xs">Cancelar</button>
                        <button type="submit" class="px-3 py-1 rounded-md bg-bacal-700 text-white text-xs font-semibold">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
