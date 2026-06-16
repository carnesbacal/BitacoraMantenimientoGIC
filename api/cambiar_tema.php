<?php
/**
 * ============================================================================
 * api/cambiar_tema.php
 * ============================================================================
 * Persiste la preferencia de tema (claro/oscuro/auto) del usuario actual.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

if (!es_post() || !csrf_valido(input('_csrf'))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
    exit;
}

$tema = (string) input('tema', 'auto');
if (!in_array($tema, ['auto', 'claro', 'oscuro'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Tema inválido']);
    exit;
}

$u = usuario_actual();

try {
    db_exec("UPDATE usuarios SET tema_preferido = :t WHERE id = :id",
        ['t' => $tema, 'id' => $u['id']]);

    // Actualizar también la sesión
    $_SESSION['usuario']['tema_preferido'] = $tema;

    echo json_encode(['ok' => true, 'tema' => $tema]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
