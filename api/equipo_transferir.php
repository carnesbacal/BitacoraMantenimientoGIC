<?php
/**
 * ============================================================================
 * api/equipo_transferir.php
 * ============================================================================
 * Registra una transferencia y actualiza la sucursal/área del equipo.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/equipos_helpers.php';

requerir_login();

if (!es_post() || !csrf_valido(input('_csrf'))) {
    flash_set('error', 'Token inválido.');
    header('Location: ' . url('dashboard.php'));
    exit;
}

if (!puede_administrar_equipos()) {
    flash_set('error', 'Sin permiso para transferir equipos.');
    header('Location: ' . url('dashboard.php'));
    exit;
}

$u = usuario_actual();
$equipo_id = (int) input('equipo_id', 0);
$destino_sid = (int) input('sucursal_destino_id', 0);
$destino_aid = input('area_destino_id', '') !== '' ? (int) input('area_destino_id') : null;
$fecha = (string) input('fecha_transferencia', date('Y-m-d'));
$motivo = trim((string) input('motivo', ''));
$notas = trim((string) input('notas', ''));

$equipo = db_one("SELECT * FROM equipos WHERE id = :id", ['id' => $equipo_id]);
if (!$equipo) {
    flash_set('error', 'Equipo no encontrado.');
    header('Location: ' . url('dashboard.php'));
    exit;
}

if ($destino_sid === 0) {
    flash_set('error', 'Debes seleccionar una sucursal de destino.');
    header('Location: ' . url('equipo_ver.php?id=' . $equipo_id));
    exit;
}

if ($destino_sid === (int) $equipo['sucursal_id'] && $destino_aid === $equipo['area_id']) {
    flash_set('error', 'El destino es igual al origen actual. No hay transferencia que registrar.');
    header('Location: ' . url('equipo_ver.php?id=' . $equipo_id));
    exit;
}

try {
    db()->beginTransaction();

    // Registrar la transferencia
    db_exec(
        "INSERT INTO equipo_transferencias
         (equipo_id, sucursal_origen_id, sucursal_destino_id, area_origen_id, area_destino_id,
          motivo, notas, fecha_transferencia, realizado_por_id)
         VALUES (:eid, :so, :sd, :ao, :ad, :m, :n, :f, :uid)",
        [
            'eid' => $equipo_id,
            'so'  => $equipo['sucursal_id'],
            'sd'  => $destino_sid,
            'ao'  => $equipo['area_id'],
            'ad'  => $destino_aid,
            'm'   => $motivo ?: null,
            'n'   => $notas ?: null,
            'f'   => $fecha,
            'uid' => $u['id'],
        ]
    );

    // Actualizar el equipo
    db_exec(
        "UPDATE equipos SET sucursal_id = :s, area_id = :a WHERE id = :id",
        ['s' => $destino_sid, 'a' => $destino_aid, 'id' => $equipo_id]
    );

    registrar_auditoria('transferir_equipo', 'equipos', $equipo_id,
        "Transferencia: sucursal {$equipo['sucursal_id']} → $destino_sid" .
        ($motivo ? " · Motivo: $motivo" : ''));

    db()->commit();

    flash_set('success', 'Transferencia registrada correctamente.');
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    flash_set('error', 'Error: ' . $e->getMessage());
}

header('Location: ' . url('equipo_ver.php?id=' . $equipo_id));
exit;
