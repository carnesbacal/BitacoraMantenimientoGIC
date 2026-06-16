<?php
/**
 * ============================================================================
 * api/preview_folio.php
 * ============================================================================
 * Devuelve un resumen ligero de una incidencia por folio o ID.
 * Se usa para mostrar tooltip al hacer hover sobre un folio.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

$folio = trim((string) input('folio', ''));
$id = (int) input('id', 0);

if ($folio === '' && $id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Folio o ID requerido']);
    exit;
}

$where = $id > 0 ? "i.id = :v" : "i.folio = :v";
$valor = $id > 0 ? $id : $folio;

$inc = db_one(
    "SELECT i.id, i.folio, i.titulo, i.creado_en, i.archivada, i.fecha_resolucion,
            est.nombre AS estado_nombre, est.color AS estado_color, est.es_final,
            sv.nombre AS severidad_nombre, sv.color AS severidad_color, sv.nivel AS severidad_nivel,
            s.codigo AS sucursal_codigo,
            cat.nombre AS categoria_nombre,
            tt.nombre AS tipo_trabajo_nombre,
            ua.nombre_completo AS asignado_nombre, ua.avatar_url AS asignado_avatar,
            ur.nombre_completo AS reportante_nombre
     FROM incidencias i
     INNER JOIN estados est ON i.estado_id = est.id
     INNER JOIN severidades sv ON i.severidad_id = sv.id
     INNER JOIN sucursales s ON i.sucursal_id = s.id
     LEFT JOIN categorias cat ON i.categoria_id = cat.id
     LEFT JOIN tipos_trabajo tt ON i.tipo_trabajo_id = tt.id
     LEFT JOIN usuarios ua ON i.asignado_a_id = ua.id
     LEFT JOIN usuarios ur ON i.reportado_por_id = ur.id
     WHERE $where
     LIMIT 1",
    ['v' => $valor]
);

if (!$inc) {
    echo json_encode(['ok' => false, 'error' => 'No encontrada']);
    exit;
}

// Verificar permisos: si no puede ver_todas_sucursales, solo puede ver su sucursal
$u = usuario_actual();
if (!tiene_permiso('ver_todas_sucursales')) {
    $suc_user = db_one("SELECT codigo FROM sucursales WHERE id = :id", ['id' => $u['sucursal_id']]);
    if ($suc_user && $inc['sucursal_codigo'] !== $suc_user['codigo']) {
        echo json_encode(['ok' => false, 'error' => 'Sin permiso para esta sucursal']);
        exit;
    }
}

// Tiempo abierta (si no es final)
$tiempo_str = '';
if (!$inc['es_final']) {
    $diff = time() - strtotime($inc['creado_en']);
    if ($diff < 3600) $tiempo_str = round($diff / 60) . ' min abierta';
    elseif ($diff < 86400) $tiempo_str = round($diff / 3600) . 'h abierta';
    else $tiempo_str = round($diff / 86400) . ' días abierta';
} else {
    $tiempo_str = 'Resuelta ' . fmt_tiempo_relativo($inc['fecha_resolucion'] ?: $inc['creado_en']);
}

echo json_encode([
    'ok' => true,
    'incidencia' => [
        'id' => (int) $inc['id'],
        'folio' => $inc['folio'],
        'titulo' => $inc['titulo'],
        'estado' => [
            'nombre' => $inc['estado_nombre'],
            'color' => $inc['estado_color'],
            'es_final' => (int) $inc['es_final'] === 1,
        ],
        'severidad' => [
            'nombre' => $inc['severidad_nombre'],
            'color' => $inc['severidad_color'],
            'nivel' => (int) $inc['severidad_nivel'],
        ],
        'sucursal' => $inc['sucursal_codigo'],
        'categoria' => $inc['categoria_nombre'],
        'tipo_trabajo' => $inc['tipo_trabajo_nombre'],
        'asignado' => $inc['asignado_nombre'],
        'asignado_avatar' => $inc['asignado_avatar'] ? url($inc['asignado_avatar']) : null,
        'reportante' => $inc['reportante_nombre'],
        'archivada' => (int) $inc['archivada'] === 1,
        'tiempo_str' => $tiempo_str,
        'creado_relativo' => fmt_tiempo_relativo($inc['creado_en']),
        'url' => url_relativa('incidencia_ver.php?id=' . $inc['id']),
    ],
], JSON_UNESCAPED_UNICODE);
