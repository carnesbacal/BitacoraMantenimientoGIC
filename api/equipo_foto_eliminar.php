<?php
/**
 * ============================================================================
 * api/equipo_foto_eliminar.php
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
    flash_set('error', 'Sin permiso.');
    header('Location: ' . url('dashboard.php'));
    exit;
}

$id = (int) input('id', 0);
$foto = db_one("SELECT * FROM equipo_fotos WHERE id = :id", ['id' => $id]);

if ($foto) {
    $ruta_disco = __DIR__ . '/../' . $foto['ruta'];
    if (file_exists($ruta_disco) && strpos($foto['ruta'], 'assets/equipos/') === 0) {
        @unlink($ruta_disco);
    }
    db_exec("DELETE FROM equipo_fotos WHERE id = :id", ['id' => $id]);

    // Si era portada, marcar la siguiente foto como portada
    if ((int) $foto['es_portada'] === 1) {
        $siguiente = db_one(
            "SELECT id FROM equipo_fotos WHERE equipo_id = :eid ORDER BY creado_en DESC LIMIT 1",
            ['eid' => $foto['equipo_id']]
        );
        if ($siguiente) {
            db_exec("UPDATE equipo_fotos SET es_portada = 1 WHERE id = :id", ['id' => $siguiente['id']]);
        }
    }

    registrar_auditoria('eliminar_foto_equipo', 'equipos', (int) $foto['equipo_id'],
        "Eliminó foto del equipo {$foto['equipo_id']}");
    flash_set('success', 'Foto eliminada.');

    header('Location: ' . url('equipo_ver.php?id=' . $foto['equipo_id']));
    exit;
}

header('Location: ' . url('dashboard.php'));
exit;
