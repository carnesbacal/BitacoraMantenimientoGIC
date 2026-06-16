<?php
/**
 * ============================================================================
 * api/proveedores_listar.php
 * ============================================================================
 * Devuelve los proveedores activos en JSON para usar en selectores
 * (admin/equipos.php, incidencias, etc.)
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requerir_login();

header('Content-Type: application/json; charset=utf-8');

$proveedores = db_all(
    "SELECT p.id, p.nombre, p.servicio,
            (SELECT GROUP_CONCAT(DISTINCT tipo SEPARATOR ', ') FROM proveedor_tipos_equipo WHERE proveedor_id = p.id) tipos
     FROM proveedores p
     WHERE p.activo = 1
     ORDER BY p.nombre ASC"
);

echo json_encode($proveedores, JSON_UNESCAPED_UNICODE);
