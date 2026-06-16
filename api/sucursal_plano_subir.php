<?php
/**
 * ============================================================================
 * api/sucursal_plano_subir.php
 * ============================================================================
 * Sube el plano (imagen) de una sucursal. Solo admin.
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

$planta_id = (int) input('planta_id', 0);
if ($planta_id <= 0 || !isset($_FILES['plano'])) {
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
    exit;
}

try {
    $ruta = subir_plano_planta($planta_id, $_FILES['plano']);
    registrar_auditoria('subir_plano', 'sucursal_plantas', $planta_id, "Plano: $ruta");
    echo json_encode(['ok' => true, 'ruta' => $ruta]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
