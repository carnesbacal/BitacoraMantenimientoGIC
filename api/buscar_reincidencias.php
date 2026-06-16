<?php
/**
 * ============================================================================
 * api/buscar_reincidencias.php
 * ============================================================================
 * Busca incidencias similares en los últimos 30 días para detectar reincidencias.
 * Devuelve hasta 10 candidatas en JSON.
 * Usado por incidencia_nueva.php / incidencia_editar.php en tiempo real.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/incidencias_helpers.php';

requerir_login();

header('Content-Type: application/json; charset=utf-8');

$area_id      = (int) input('area', 0);
$equipo_id    = (int) input('equipo', 0) ?: null;
$categoria_id = (int) input('categoria', 0) ?: null;
$excluir_id   = (int) input('excluir', 0);

if ($area_id <= 0) {
    echo json_encode([]);
    exit;
}

$candidatas = buscar_reincidencias_similares($area_id, $equipo_id, $categoria_id, $excluir_id, 30);

// Agregar campo "dias_atras" para mostrar en UI
foreach ($candidatas as &$c) {
    $dias = (int) floor((time() - strtotime($c['fecha_evento'])) / 86400);
    $c['dias_atras'] = max(0, $dias);
}

echo json_encode($candidatas, JSON_UNESCAPED_UNICODE);
