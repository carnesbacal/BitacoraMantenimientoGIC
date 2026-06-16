<?php
/**
 * ============================================================================
 * cron/notificar_mantenimientos.php
 * ============================================================================
 * Script para tarea programada que:
 *   1. Actualiza estados (programado→próximo→vencido)
 *   2. Notifica a técnicos de mantenimientos próximos (3 días antes y el día)
 *   3. Notifica vencidos
 *
 * Configuración en Windows Task Scheduler:
 *   Programa:    C:\xampp\php\php.exe
 *   Argumentos:  "C:\xampp\htdocs\UtilidadesBacal\BitacoraSistemas\cron\notificar_mantenimientos.php"
 *   Frecuencia:  Diaria a las 8:00 AM (recomendado)
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Solo CLI.\n");
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/mantenimientos_helpers.php';
require_once __DIR__ . '/../config/notificaciones_helpers.php';

$inicio = microtime(true);
echo "============================================================\n";
echo "Notificación de mantenimientos\n";
echo "Inicio: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

// 1. Actualizar estados
echo "Paso 1: Actualizando estados...\n";
actualizar_estados_mantenimientos();
echo "  ✓ Estados actualizados\n\n";

// 2. Notificar mantenimientos próximos (estado = proximo, no notificados en últimas 24h)
echo "Paso 2: Notificando mantenimientos próximos...\n";
$proximos = db_all(
    "SELECT m.*, e.codigo_inventario, e.nombre equipo_nombre
     FROM mantenimientos m
     INNER JOIN equipos e ON m.equipo_id = e.id
     WHERE m.estado = 'proximo' AND m.asignado_a_id IS NOT NULL"
);

$notif_proximos = 0;
foreach ($proximos as $m) {
    $dias = (strtotime($m['fecha_programada']) - strtotime(date('Y-m-d'))) / 86400;
    $cuando = $dias <= 0 ? 'hoy' : ($dias == 1 ? 'mañana' : "en " . (int) $dias . " días");

    $creada = crear_notificacion(
        (int) $m['asignado_a_id'],
        'mantenimiento_proximo',
        "Mantenimiento $cuando: {$m['titulo']}",
        "Equipo {$m['codigo_inventario']} · {$m['equipo_nombre']}",
        url_relativa('mantenimiento_ver.php?id=' . $m['id']),
        'mantenimientos',
        (int) $m['id']
    );
    if ($creada) $notif_proximos++;
}
echo "  ✓ $notif_proximos notificación(es) de próximos\n\n";

// 3. Notificar vencidos
echo "Paso 3: Notificando mantenimientos vencidos...\n";
$vencidos = db_all(
    "SELECT m.*, e.codigo_inventario, e.nombre equipo_nombre
     FROM mantenimientos m
     INNER JOIN equipos e ON m.equipo_id = e.id
     WHERE m.estado = 'vencido' AND m.asignado_a_id IS NOT NULL"
);

$notif_vencidos = 0;
foreach ($vencidos as $m) {
    $dias_vencido = (strtotime(date('Y-m-d')) - strtotime($m['fecha_programada'])) / 86400;

    $creada = crear_notificacion(
        (int) $m['asignado_a_id'],
        'mantenimiento_vencido',
        "Mantenimiento vencido: {$m['titulo']}",
        "Equipo {$m['codigo_inventario']} · Vencido hace " . (int) $dias_vencido . " día(s)",
        url_relativa('mantenimiento_ver.php?id=' . $m['id']),
        'mantenimientos',
        (int) $m['id']
    );
    if ($creada) $notif_vencidos++;
}
echo "  ✓ $notif_vencidos notificación(es) de vencidos\n\n";

$duracion = round(microtime(true) - $inicio, 2);
echo "============================================================\n";
echo "Completado en {$duracion}s\n";
echo "Resumen: $notif_proximos próximos, $notif_vencidos vencidos\n";
echo "============================================================\n";

exit(0);
