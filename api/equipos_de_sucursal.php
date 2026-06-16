<?php
/**
 * ============================================================================
 * api/equipos_de_sucursal.php
 * ============================================================================
 * Devuelve en JSON los equipos activos de una sucursal específica.
 * Usado por incidencia_nueva.php / incidencia_editar.php para poblar el select
 * de equipos dinámicamente cuando cambia la sucursal.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requerir_login();

header('Content-Type: application/json; charset=utf-8');

$sucursal_id = (int) input('sucursal', 0);

if ($sucursal_id <= 0) {
    echo json_encode([]);
    exit;
}

$equipos = db_all(
    "SELECT id, codigo_inventario, nombre, tipo, marca, modelo
     FROM equipos
     WHERE sucursal_id = :sid AND activo = 1
     ORDER BY codigo_inventario ASC",
    ['sid' => $sucursal_id]
);

echo json_encode($equipos, JSON_UNESCAPED_UNICODE);
