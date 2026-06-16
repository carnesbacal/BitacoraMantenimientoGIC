<?php
/**
 * ============================================================================
 * api/notificacion_marcar_leida.php
 * ============================================================================
 * Marca una notificación como leída cuando el usuario hace clic en ella
 * desde el dropdown del campanita.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/notificaciones_helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

if (!es_post() || !csrf_valido(input('_csrf'))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF']);
    exit;
}

$u = usuario_actual();
$id = (int) input('id', 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

marcar_notificacion_leida($id, (int) $u['id']);

echo json_encode([
    'ok' => true,
    'no_leidas_restantes' => contar_no_leidas((int) $u['id']),
]);
