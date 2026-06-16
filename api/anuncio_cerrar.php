<?php
/**
 * ============================================================================
 * api/anuncio_cerrar.php
 * ============================================================================
 * Marca un anuncio como leído por el usuario actual.
 * Si está fijado, se registra como leído pero seguirá apareciendo.
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
$anuncio_id = (int) input('anuncio_id', 0);

if ($anuncio_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

marcar_anuncio_leido($anuncio_id, (int) $u['id']);
echo json_encode(['ok' => true]);
