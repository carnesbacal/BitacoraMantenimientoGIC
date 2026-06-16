<?php
/**
 * ============================================================================
 * api/comentario_reaccionar.php
 * ============================================================================
 * Toggle de reacción de emoji a un comentario.
 * Si ya existe, la elimina. Si no, la agrega.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/comunicacion_helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

if (!es_post() || !csrf_valido(input('_csrf'))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
    exit;
}

$u = usuario_actual();
$comentario_id = (int) input('comentario_id', 0);
$emoji = (string) input('emoji', '');

if ($comentario_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

// Verificar que el comentario existe
$comentario = db_one("SELECT id, usuario_id FROM incidencias_comentarios WHERE id = :id", ['id' => $comentario_id]);
if (!$comentario) {
    echo json_encode(['ok' => false, 'error' => 'Comentario no encontrado']);
    exit;
}

$resultado = toggle_reaccion_comentario($comentario_id, (int) $u['id'], $emoji);

if ($resultado['estado'] === 'error') {
    echo json_encode(['ok' => false, 'error' => $resultado['mensaje']]);
    exit;
}

echo json_encode([
    'ok' => true,
    'estado' => $resultado['estado'],
    'emoji' => $resultado['emoji'],
    'nuevo_total' => $resultado['nuevo_total'],
]);
