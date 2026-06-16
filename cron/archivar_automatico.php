<?php
/**
 * ============================================================================
 * cron/archivar_automatico.php
 * ============================================================================
 * Archiva incidencias resueltas hace más de 365 días automáticamente.
 *
 * Configuración en Windows Task Scheduler:
 *   Programa:    C:\xampp\php\php.exe
 *   Argumentos:  "C:\xampp\htdocs\UtilidadesBacal\BitacoraSistemas\cron\archivar_automatico.php"
 *   Frecuencia:  Diaria a las 3:00 AM
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Solo CLI.\n");
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/organizacion_helpers.php';

$inicio = microtime(true);
$fecha = date('Y-m-d H:i:s');

echo "============================================================\n";
echo "Archivado automático de incidencias antiguas\n";
echo "Inicio: $fecha\n";
echo "============================================================\n\n";

$dias = 365;

// Conteo previo
$conteos = contar_incidencias_archivables($dias);
echo "Por archivar (resueltas hace >$dias días): {$conteos['por_archivar']}\n";
echo "Ya archivadas: {$conteos['ya_archivadas']}\n\n";

// Ejecutar
echo "Ejecutando archivado...\n";
$archivadas = archivar_incidencias_antiguas($dias);

$duracion = round(microtime(true) - $inicio, 2);

if ($archivadas > 0) {
    echo "✓ ÉXITO: $archivadas incidencia(s) archivada(s)\n";
} else {
    echo "✓ Nada que archivar\n";
}

echo "Tiempo: {$duracion}s\n";
echo "============================================================\n";

exit(0);
