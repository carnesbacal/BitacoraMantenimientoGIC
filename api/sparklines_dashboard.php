<?php
/**
 * ============================================================================
 * api/sparklines_dashboard.php
 * ============================================================================
 * Devuelve series de últimos 7 días para los KPIs del dashboard.
 * Permite mostrar mini-gráficas inline de tendencia.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

$u = usuario_actual();
$ver_todas = tiene_permiso('ver_todas_sucursales');
$where_suc = $ver_todas ? '' : 'AND i.sucursal_id = :sid';
$params_base = $ver_todas ? [] : ['sid' => (int) $u['sucursal_id']];

// Generar las últimas 7 fechas (incluyendo hoy)
$fechas = [];
for ($i = 6; $i >= 0; $i--) {
    $fechas[] = date('Y-m-d', strtotime("-$i days"));
}

// Helper: dado un query con fecha agrupada, normaliza el array a las 7 fechas
function rellenar_serie(array $datos_brutos, array $fechas): array {
    $map = [];
    foreach ($datos_brutos as $r) {
        $map[$r['fecha']] = (int) $r['cantidad'];
    }
    $serie = [];
    foreach ($fechas as $f) {
        $serie[] = $map[$f] ?? 0;
    }
    return $serie;
}

// 1. Incidencias creadas por día
$creadas = db_all(
    "SELECT DATE(creado_en) AS fecha, COUNT(*) AS cantidad
     FROM incidencias i
     WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
       $where_suc
     GROUP BY DATE(creado_en)
     ORDER BY fecha ASC",
    $params_base
);

// 2. Incidencias resueltas por día
$resueltas = db_all(
    "SELECT DATE(fecha_resolucion) AS fecha, COUNT(*) AS cantidad
     FROM incidencias i
     WHERE fecha_resolucion IS NOT NULL
       AND fecha_resolucion >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
       $where_suc
     GROUP BY DATE(fecha_resolucion)
     ORDER BY fecha ASC",
    $params_base
);

// 3. Total abiertas al final de cada día (snapshot)
$abiertas_diario = [];
foreach ($fechas as $f) {
    $params_d = array_merge($params_base, ['fecha' => $f]);
    $r = db_one(
        "SELECT COUNT(*) c
         FROM incidencias i
         INNER JOIN estados est ON i.estado_id = est.id
         WHERE i.creado_en <= CONCAT(:fecha, ' 23:59:59')
           AND (i.fecha_resolucion IS NULL OR i.fecha_resolucion > CONCAT(:fecha, ' 23:59:59'))
           $where_suc",
        $params_d
    );
    $abiertas_diario[] = (int) ($r['c'] ?? 0);
}

echo json_encode([
    'ok' => true,
    'fechas' => $fechas,
    'series' => [
        'creadas' => rellenar_serie($creadas, $fechas),
        'resueltas' => rellenar_serie($resueltas, $fechas),
        'abiertas' => $abiertas_diario,
    ],
], JSON_UNESCAPED_UNICODE);
