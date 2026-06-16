<?php
/**
 * ============================================================================
 * api/notificaciones_no_leidas.php
 * ============================================================================
 * Endpoint AJAX: devuelve el conteo de no leídas + las 5 más recientes
 * para mostrar en un dropdown del campanita del header.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/notificaciones_helpers.php';

requerir_login();

header('Content-Type: application/json; charset=utf-8');

$u = usuario_actual();

$conteo = contar_no_leidas((int) $u['id']);
$recientes = listar_notificaciones((int) $u['id'], 5, false);

// Enriquecer con tipo, ícono y tiempo relativo
$out = [];
foreach ($recientes as $n) {
    $tipo_cfg = NOTIF_TIPOS[$n['tipo']] ?? NOTIF_TIPOS['sistema'];
    $out[] = [
        'id' => (int) $n['id'],
        'titulo' => $n['titulo'],
        'mensaje' => $n['mensaje'],
        'url' => $n['url'],
        'icono' => $tipo_cfg['icono'],
        'color' => $tipo_cfg['color'],
        'leida' => (int) $n['leida'],
        'tiempo_relativo' => fmt_tiempo_relativo($n['creado_en']),
    ];
}

echo json_encode([
    'no_leidas' => $conteo,
    'recientes' => $out,
], JSON_UNESCAPED_UNICODE);
