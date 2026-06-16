<?php
/**
 * ============================================================================
 * api/sugerencias_incidencia.php
 * ============================================================================
 * Devuelve sugerencias en vivo mientras el usuario escribe el título.
 * Llamado con debounce de 500ms desde incidencia_nueva.php
 *
 * Parámetros GET:
 *   - titulo: texto del título (mínimo 3 caracteres)
 *   - categoria_id: opcional, para sugerir técnicos expertos
 *   - tipo_trabajo_id: opcional
 *   - area_id: opcional
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/inteligencia_helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

$titulo = trim((string) input('titulo', ''));
$categoria_id = (int) input('categoria_id', 0) ?: null;
$tipo_trabajo_id = (int) input('tipo_trabajo_id', 0) ?: null;
$area_id = (int) input('area_id', 0) ?: null;

// Si el título es muy corto, devolver vacío
if (mb_strlen($titulo) < 3) {
    echo json_encode([
        'plantillas' => [],
        'soluciones' => [],
        'tecnicos' => [],
    ]);
    exit;
}

// Sugerencias
$plantillas = sugerir_plantillas_por_texto($titulo, 3);
$soluciones = sugerir_soluciones_por_texto($titulo, 3);
$tecnicos   = sugerir_tecnicos_expertos($categoria_id, $tipo_trabajo_id, $area_id, 3);

// Enriquecer técnicos con URL de avatar
foreach ($tecnicos as &$t) {
    $t['tiempo_promedio_min'] = $t['tiempo_promedio'] ? (int) round($t['tiempo_promedio']) : null;
    if ($t['avatar_url']) {
        $t['avatar_full_url'] = url($t['avatar_url']);
    }
}
unset($t);

// Truncar soluciones para no enviar demasiado
foreach ($soluciones as &$s) {
    if (mb_strlen($s['solucion']) > 200) {
        $s['solucion'] = mb_substr($s['solucion'], 0, 200) . '...';
    }
}
unset($s);

echo json_encode([
    'plantillas' => $plantillas,
    'soluciones' => $soluciones,
    'tecnicos' => $tecnicos,
], JSON_UNESCAPED_UNICODE);
