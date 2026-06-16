<?php
/**
 * ============================================================================
 * api/plantilla_usada.php
 * ============================================================================
 * Incrementa el contador de uso de una plantilla. Usado silenciosamente
 * cuando un usuario aplica una plantilla al crear una incidencia.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requerir_login();

header('Content-Type: application/json; charset=utf-8');

$id = (int) input('id', 0);
if ($id > 0) {
    db_exec("UPDATE plantillas_incidencias SET usos = usos + 1 WHERE id = :id AND activo = 1", ['id' => $id]);
}

echo json_encode(['ok' => true]);
