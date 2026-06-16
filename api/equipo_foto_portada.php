<?php
/**
 * ============================================================================
 * api/equipo_foto_portada.php
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/equipos_helpers.php';

requerir_login();

if (!es_post() || !csrf_valido(input('_csrf')) || !puede_administrar_equipos()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$id = (int) input('id', 0);
$foto = db_one("SELECT equipo_id FROM equipo_fotos WHERE id = :id", ['id' => $id]);

if ($foto) {
    db()->beginTransaction();
    db_exec("UPDATE equipo_fotos SET es_portada = 0 WHERE equipo_id = :eid", ['eid' => $foto['equipo_id']]);
    db_exec("UPDATE equipo_fotos SET es_portada = 1 WHERE id = :id", ['id' => $id]);
    db()->commit();

    flash_set('success', 'Foto marcada como portada.');
    header('Location: ' . url('equipo_ver.php?id=' . $foto['equipo_id']));
    exit;
}

header('Location: ' . url('dashboard.php'));
exit;
