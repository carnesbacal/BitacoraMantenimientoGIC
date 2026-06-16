<?php
/**
 * ============================================================================
 * cron/enviar_recordatorios.php
 * ============================================================================
 * Procesa recordatorios programados cuya fecha de envío ya llegó.
 * Los convierte en notificaciones in-app.
 *
 * Configuración Windows Task Scheduler:
 *   Programa:    C:\xampp\php\php.exe
 *   Argumentos:  "C:\xampp\htdocs\UtilidadesBacal\BitacoraSistemas\cron\enviar_recordatorios.php"
 *   Frecuencia:  Cada 5 minutos (recomendado para precisión)
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Solo CLI.\n");
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/comunicacion_helpers.php';
require_once __DIR__ . '/../config/notificaciones_helpers.php';

$inicio = microtime(true);
$fecha = date('Y-m-d H:i:s');

echo "============================================================\n";
echo "Envío de recordatorios programados\n";
echo "Inicio: $fecha\n";
echo "============================================================\n";

$pendientes = recordatorios_por_enviar(100);
$enviados = 0;
$fallidos = 0;

if (empty($pendientes)) {
    echo "No hay recordatorios pendientes.\n";
} else {
    echo "Procesando " . count($pendientes) . " recordatorio(s)...\n\n";

    foreach ($pendientes as $r) {
        try {
            crear_notificacion(
                (int) $r['usuario_id'],
                'sistema',
                '⏰ ' . $r['titulo'],
                $r['mensaje'] ?: 'Tienes un recordatorio',
                $r['enlace'],
                $r['entidad'],
                $r['entidad_id'] ? (int) $r['entidad_id'] : null
            );
            marcar_recordatorio_enviado((int) $r['id']);
            $enviados++;
            echo "  ✓ Recordatorio #{$r['id']} → usuario {$r['usuario_id']}: {$r['titulo']}\n";
        } catch (Throwable $e) {
            $fallidos++;
            echo "  ✗ Recordatorio #{$r['id']}: " . $e->getMessage() . "\n";
        }
    }
}

$duracion = round(microtime(true) - $inicio, 2);
echo "\n============================================================\n";
echo "Resumen: $enviados enviado(s), $fallidos fallido(s) en {$duracion}s\n";
echo "============================================================\n";

exit($fallidos > 0 ? 1 : 0);
