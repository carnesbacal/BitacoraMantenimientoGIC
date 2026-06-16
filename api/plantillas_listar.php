<?php
/**
 * ============================================================================
 * api/plantillas_listar.php
 * ============================================================================
 * Devuelve las plantillas activas en JSON para el selector del formulario
 * de nueva incidencia.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requerir_login();

header('Content-Type: application/json; charset=utf-8');

$plantillas = db_all(
    "SELECT id, nombre, descripcion, icono, color,
            titulo, descripcion_inc, area_id, categoria_id, subcategoria_id,
            tipo_trabajo_id, severidad_id, origen_reporte_id, solucion_sugerida
     FROM plantillas_incidencias
     WHERE activo = 1
     ORDER BY usos DESC, nombre ASC"
);

echo json_encode($plantillas, JSON_UNESCAPED_UNICODE);
