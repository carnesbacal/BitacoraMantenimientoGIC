<?php
/**
 * ============================================================================
 * api/sugerir_categoria.php
 * ============================================================================
 * Sugiere las categorías más probables según el título y descripción de la incidencia.
 * Se llama desde incidencia_nueva.php con debounce.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/organizacion_helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

$titulo = trim((string) input('titulo', ''));
$descripcion = trim((string) input('descripcion', ''));

// Combinar título y descripción (el título pesa el doble)
$texto = $titulo . ' ' . $titulo . ' ' . $descripcion;

if (mb_strlen(trim($texto)) < 3) {
    echo json_encode(['sugerencias' => []]);
    exit;
}

$sugerencias = sugerir_categorias_por_texto($texto, 3);

// Mantener solo lo necesario para el front
$resultado = [];
foreach ($sugerencias as $s) {
    $resultado[] = [
        'categoria_id' => $s['categoria_id'],
        'categoria_nombre' => $s['categoria_nombre'],
        'color' => $s['color'],
        'score' => $s['score'],
        'palabras_coincidentes' => array_values(array_unique($s['palabras_coincidentes'])),
    ];
}

echo json_encode(['sugerencias' => $resultado], JSON_UNESCAPED_UNICODE);
