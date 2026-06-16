<?php
/**
 * ============================================================================
 * admin/auditoria.php - Bitácora del sistema
 * ============================================================================
 * Muestra todas las acciones registradas en `auditoria_sistema` con filtros
 * por usuario, acción, fechas. Permite ver quién hizo qué y cuándo.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin_helpers.php';

// Filtros
$f_usuario = (int) input('usuario_id', 0);
$f_accion  = trim((string) input('accion', ''));
$f_desde   = trim((string) input('fecha_desde', ''));
$f_hasta   = trim((string) input('fecha_hasta', ''));
$f_q       = trim((string) input('q', ''));

$pagina = max(1, (int) input('p', 1));
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

$where = [];
$params = [];

if ($f_usuario > 0) { $where[] = "a.usuario_id = :uid"; $params['uid'] = $f_usuario; }
if ($f_accion !== '') { $where[] = "a.accion = :acc"; $params['acc'] = $f_accion; }
if ($f_desde !== '')  { $where[] = "DATE(a.creado_en) >= :fd"; $params['fd'] = $f_desde; }
if ($f_hasta !== '')  { $where[] = "DATE(a.creado_en) <= :fh"; $params['fh'] = $f_hasta; }
if ($f_q !== '')      {
    $where[] = "(a.descripcion LIKE :q1 OR u.usuario LIKE :q2 OR u.nombre_completo LIKE :q3)";
    $params['q1'] = "%$f_q%"; $params['q2'] = "%$f_q%"; $params['q3'] = "%$f_q%";
}
$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Conteo total
$total_row = db_one(
    "SELECT COUNT(*) c FROM auditoria_sistema a
     LEFT JOIN usuarios u ON a.usuario_id = u.id $where_sql",
    $params
);
$total = (int) $total_row['c'];
$total_paginas = max(1, (int) ceil($total / $por_pagina));
$pagina = min($pagina, $total_paginas);
$offset = ($pagina - 1) * $por_pagina;

// Registros
$registros = db_all(
    "SELECT a.*, u.usuario, u.nombre_completo
     FROM auditoria_sistema a
     LEFT JOIN usuarios u ON a.usuario_id = u.id
     $where_sql
     ORDER BY a.creado_en DESC
     LIMIT $por_pagina OFFSET $offset",
    $params
);

// Lista de usuarios y acciones únicas para los filtros
$usuarios_lista = db_all("SELECT id, usuario, nombre_completo FROM usuarios ORDER BY nombre_completo");
$acciones_unicas = db_all("SELECT DISTINCT accion FROM auditoria_sistema ORDER BY accion");

$hay_filtros = $f_usuario > 0 || $f_accion !== '' || $f_desde !== '' || $f_hasta !== '' || $f_q !== '';

// URL preservando filtros
function url_auditoria(array $cambios = []): string {
    $p = array_merge($_GET, $cambios);
    foreach ($p as $k => $v) {
        if ($v === '' || $v === 0 || $v === '0' || $v === null) unset($p[$k]);
    }
    return url('admin/auditoria.php') . (empty($p) ? '' : '?' . http_build_query($p));
}

// Iconos por tipo de acción
function icono_accion(string $accion): array {
    $map = [
        'login'             => ['log-in', '#16A34A'],
        'logout'            => ['log-out', '#6B7280'],
        'cambio_password'   => ['key', '#D97706'],
        'reset_password'    => ['key-round', '#DC2626'],
        'crear_usuario'     => ['user-plus', '#2563EB'],
        'editar_usuario'    => ['user-cog', '#7C3AED'],
        'crear_incidencia'  => ['file-plus', '#16A34A'],
        'editar_incidencia' => ['file-edit', '#D97706'],
        'exportar_bitacora' => ['download', '#0EA5E9'],
        'activar'           => ['toggle-right', '#16A34A'],
        'desactivar'        => ['toggle-left', '#6B7280'],
    ];
    foreach ($map as $patron => $icono) {
        if (str_starts_with($accion, $patron)) return $icono;
    }
    return ['activity', '#6B7280'];
}

$titulo_pagina = 'Auditoría';
$pagina_activa = 'admin_auditoria';
require_once __DIR__ . '/../config/header.php';
?>

<div class="animate-fade-in" x-data="{ panelFiltros: false }">

    <?php render_admin_header('Auditoría del sistema', "Registro completo de actividad · " . number_format($total) . ' evento(s)'); ?>

    <!-- Barra de búsqueda y filtros -->
    <div class="flex flex-col md:flex-row gap-2 mb-4">
        <form method="GET" class="relative flex-1 max-w-md">
            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400"></i>
            <input type="text" name="q" value="<?= e($f_q) ?>"
                   placeholder="Buscar descripción, usuario..."
                   class="w-full pl-9 pr-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
            <?php foreach (['usuario_id' => $f_usuario, 'accion' => $f_accion, 'fecha_desde' => $f_desde, 'fecha_hasta' => $f_hasta] as $k => $v):
                if ($v): ?>
                <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
            <?php endif; endforeach; ?>
        </form>

        <button @click="panelFiltros = !panelFiltros"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50"
                :class="panelFiltros ? 'border-bacal-700 text-bacal-700 bg-bacal-50' : ''">
            <i data-lucide="filter" class="w-4 h-4"></i>
            Filtros
            <?php if ($hay_filtros): ?>
            <span class="bg-bacal-700 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">●</span>
            <?php endif; ?>
        </button>

        <?php if ($hay_filtros): ?>
        <a href="<?= url('admin/auditoria.php') ?>" class="px-3 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm hover:bg-zinc-50">
            Limpiar filtros
        </a>
        <?php endif; ?>
    </div>

    <!-- Panel de filtros -->
    <div x-show="panelFiltros" x-cloak x-transition class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5 mb-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <?php if ($f_q !== ''): ?>
            <input type="hidden" name="q" value="<?= e($f_q) ?>">
            <?php endif; ?>
            <div>
                <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Usuario</label>
                <select name="usuario_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">— Todos —</option>
                    <?php foreach ($usuarios_lista as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $f_usuario == $u['id'] ? 'selected' : '' ?>>
                        <?= e($u['nombre_completo']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Acción</label>
                <select name="accion" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">— Todas —</option>
                    <?php foreach ($acciones_unicas as $a): ?>
                    <option value="<?= e($a['accion']) ?>" <?= $f_accion === $a['accion'] ? 'selected' : '' ?>>
                        <?= e($a['accion']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Desde</label>
                <input type="date" name="fecha_desde" value="<?= e($f_desde) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Hasta</label>
                <input type="date" name="fecha_hasta" value="<?= e($f_hasta) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div class="md:col-span-2 lg:col-span-4 flex justify-end gap-2 pt-2 border-t border-zinc-100">
                <a href="<?= url('admin/auditoria.php') ?>" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Limpiar</a>
                <button type="submit" class="px-4 py-2 rounded-lg bg-bacal-700 text-white text-sm font-semibold">Aplicar</button>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <?php if (empty($registros)): ?>
        <div class="py-20 text-center">
            <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                <i data-lucide="search-x" class="w-6 h-6 text-zinc-400"></i>
            </div>
            <p class="text-sm text-zinc-500">Sin eventos que coincidan.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 border-b border-zinc-200">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider w-44">Fecha</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider w-48">Usuario</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider w-44">Acción</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Descripción</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider w-32">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($registros as $r):
                        [$icono, $color] = icono_accion($r['accion']);
                    ?>
                    <tr class="hover:bg-zinc-50">
                        <td class="px-3 py-2 align-top">
                            <div class="text-xs text-zinc-700"><?= e(fmt_fecha($r['creado_en'])) ?></div>
                            <div class="text-[10px] text-zinc-400"><?= e(fmt_tiempo_relativo($r['creado_en'])) ?></div>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <?php if ($r['usuario_id']): ?>
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[9px] font-bold flex-shrink-0"
                                     style="background-color: <?= color_avatar($r['nombre_completo'] ?? 'X') ?>">
                                    <?= e(iniciales($r['nombre_completo'] ?? 'X')) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-medium text-zinc-900 truncate"><?= e($r['nombre_completo'] ?? '—') ?></div>
                                    <div class="text-[10px] text-zinc-400 font-mono"><?= e($r['usuario'] ?? '—') ?></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <span class="text-[11px] text-zinc-400 italic">sistema</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-semibold"
                                  style="background-color: <?= e($color) ?>15; color: <?= e($color) ?>; border: 1px solid <?= e($color) ?>40">
                                <i data-lucide="<?= e($icono) ?>" class="w-3 h-3"></i>
                                <?= e($r['accion']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <div class="text-xs text-zinc-700"><?= e($r['descripcion'] ?? '—') ?></div>
                            <?php if ($r['entidad']): ?>
                            <div class="text-[10px] text-zinc-400 mt-0.5">
                                <span class="font-mono"><?= e($r['entidad']) ?></span>
                                <?php if ($r['entidad_id']): ?>
                                #<?= $r['entidad_id'] ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 align-top">
                            <span class="font-mono text-[10px] text-zinc-500"><?= e($r['ip'] ?? '—') ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="border-t border-zinc-200 px-4 py-3 flex items-center justify-between flex-wrap gap-2">
            <div class="text-xs text-zinc-500">
                <strong class="text-zinc-700"><?= ($offset + 1) ?>-<?= min($offset + $por_pagina, $total) ?></strong> de <strong class="text-zinc-700"><?= number_format($total) ?></strong>
            </div>
            <div class="flex items-center gap-1">
                <a href="<?= $pagina > 1 ? url_auditoria(['p' => $pagina - 1]) : '#' ?>"
                   class="px-2.5 py-1.5 rounded-md text-xs font-medium border <?= $pagina > 1 ? 'border-zinc-300 text-zinc-700 hover:bg-zinc-50' : 'border-zinc-200 text-zinc-300 pointer-events-none' ?>">
                    <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i>
                </a>
                <span class="px-3 py-1.5 text-xs font-semibold text-zinc-700">
                    Página <?= $pagina ?> de <?= $total_paginas ?>
                </span>
                <a href="<?= $pagina < $total_paginas ? url_auditoria(['p' => $pagina + 1]) : '#' ?>"
                   class="px-2.5 py-1.5 rounded-md text-xs font-medium border <?= $pagina < $total_paginas ? 'border-zinc-300 text-zinc-700 hover:bg-zinc-50' : 'border-zinc-200 text-zinc-300 pointer-events-none' ?>">
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
