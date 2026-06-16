<?php
/**
 * ============================================================================
 * api/equipo_posicion.php
 * ============================================================================
 * Actualiza pos_x y pos_y de un equipo. Solo admin.
 * Si pos_x o pos_y vienen como 'null', el equipo se quita del mapa.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/mapa_helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

if (!es_post() || !csrf_valido(input('_csrf'))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
    exit;
}

if (!tiene_permiso('administrar')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Solo administradores']);
    exit;
}

$equipo_id = (int) input('equipo_id', 0);
if ($equipo_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Equipo inválido']);
    exit;
}

$planta_id_raw = input('planta_id', null);
$pos_x_raw = input('pos_x', null);
$pos_y_raw = input('pos_y', null);

$planta_id = ($planta_id_raw === null || $planta_id_raw === '' || $planta_id_raw === '0' || $planta_id_raw === 'null')
    ? null : (int) $planta_id_raw;
$pos_x = ($pos_x_raw === null || $pos_x_raw === '' || $pos_x_raw === 'null') ? null : (float) $pos_x_raw;
$pos_y = ($pos_y_raw === null || $pos_y_raw === '' || $pos_y_raw === 'null') ? null : (float) $pos_y_raw;

try {
    actualizar_posicion_equipo($equipo_id, $planta_id, $pos_x, $pos_y);
    echo json_encode(['ok' => true, 'planta_id' => $planta_id, 'pos_x' => $pos_x, 'pos_y' => $pos_y]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
