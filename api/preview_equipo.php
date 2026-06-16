<?php
/**
 * ============================================================================
 * api/preview_equipo.php
 * ============================================================================
 * Resumen ligero de un equipo por código o ID.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

$codigo = trim((string) input('codigo', ''));
$id = (int) input('id', 0);

if ($codigo === '' && $id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Código o ID requerido']);
    exit;
}

$where = $id > 0 ? "e.id = :v" : "e.codigo_inventario = :v";
$valor = $id > 0 ? $id : $codigo;

$eq = db_one(
    "SELECT e.id, e.codigo_inventario, e.nombre, e.tipo, e.marca, e.modelo,
            e.estado_vida, e.ubicacion, e.fecha_compra,
            s.codigo AS sucursal_codigo, s.nombre AS sucursal_nombre,
            a.nombre AS area_nombre, a.color AS area_color,
            (SELECT COUNT(*) FROM incidencias i
             INNER JOIN estados es ON i.estado_id = es.id
             WHERE i.equipo_id = e.id AND es.es_final = 0) AS incidencias_abiertas,
            (SELECT COUNT(*) FROM incidencias i
             WHERE i.equipo_id = e.id) AS incidencias_totales
     FROM equipos e
     LEFT JOIN sucursales s ON e.sucursal_id = s.id
     LEFT JOIN areas a ON e.area_id = a.id
     WHERE $where AND e.activo = 1
     LIMIT 1",
    ['v' => $valor]
);

if (!$eq) {
    echo json_encode(['ok' => false, 'error' => 'Equipo no encontrado']);
    exit;
}

// Verificar permisos de sucursal
$u = usuario_actual();
if (!tiene_permiso('ver_todas_sucursales')) {
    $suc_user = db_one("SELECT codigo FROM sucursales WHERE id = :id", ['id' => $u['sucursal_id']]);
    if ($suc_user && $eq['sucursal_codigo'] !== $suc_user['codigo']) {
        echo json_encode(['ok' => false, 'error' => 'Sin permiso para esta sucursal']);
        exit;
    }
}

$estado_label = match ($eq['estado_vida']) {
    'en_uso' => ['nombre' => 'En uso', 'color' => '#16A34A'],
    'en_mantenimiento' => ['nombre' => 'En mantenimiento', 'color' => '#F59E0B'],
    'baja' => ['nombre' => 'Dado de baja', 'color' => '#71717a'],
    default => ['nombre' => $eq['estado_vida'] ?: '?', 'color' => '#0EA5E9'],
};

echo json_encode([
    'ok' => true,
    'equipo' => [
        'id' => (int) $eq['id'],
        'codigo' => $eq['codigo_inventario'],
        'nombre' => $eq['nombre'],
        'tipo' => $eq['tipo'],
        'marca' => $eq['marca'],
        'modelo' => $eq['modelo'],
        'estado' => $estado_label,
        'sucursal' => $eq['sucursal_codigo'],
        'sucursal_nombre' => $eq['sucursal_nombre'],
        'area' => $eq['area_nombre'],
        'area_color' => $eq['area_color'],
        'ubicacion' => $eq['ubicacion'],
        'incidencias_abiertas' => (int) $eq['incidencias_abiertas'],
        'incidencias_totales' => (int) $eq['incidencias_totales'],
        'url' => url_relativa('equipo_ver.php?id=' . $eq['id']),
    ],
], JSON_UNESCAPED_UNICODE);
