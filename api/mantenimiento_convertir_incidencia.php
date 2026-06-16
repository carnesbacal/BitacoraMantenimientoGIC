<?php
/**
 * ============================================================================
 * api/mantenimiento_convertir_incidencia.php
 * ============================================================================
 * Convierte un mantenimiento programado en una incidencia.
 * Crea la incidencia, la vincula al equipo, y marca el mantenimiento.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/incidencias_helpers.php';
require_once __DIR__ . '/../config/mantenimientos_helpers.php';

requerir_login();

if (!es_post() || !csrf_valido(input('_csrf'))) {
    flash_set('error', 'Token inválido.');
    header('Location: ' . url('mantenimientos.php'));
    exit;
}

if (!puede_administrar_mantenimientos()) {
    flash_set('error', 'Sin permiso.');
    header('Location: ' . url('mantenimientos.php'));
    exit;
}

$u = usuario_actual();
$mant_id = (int) input('mantenimiento_id', 0);

$m = db_one(
    "SELECT m.*, e.sucursal_id equipo_sucursal_id, e.area_id equipo_area_id
     FROM mantenimientos m
     INNER JOIN equipos e ON m.equipo_id = e.id
     WHERE m.id = :id",
    ['id' => $mant_id]
);

if (!$m) {
    flash_set('error', 'Mantenimiento no encontrado.');
    header('Location: ' . url('mantenimientos.php'));
    exit;
}

if ($m['incidencia_generada_id']) {
    flash_set('error', 'Este mantenimiento ya fue convertido en incidencia.');
    header('Location: ' . url('mantenimiento_ver.php?id=' . $mant_id));
    exit;
}

// Catálogos por defecto para la incidencia generada
$severidad_media = db_one("SELECT id FROM severidades WHERE nivel = 3 LIMIT 1");
$tipo_preventivo = db_one("SELECT id FROM tipos_trabajo WHERE nombre LIKE '%Preventivo%' LIMIT 1");
$categoria_hw    = db_one("SELECT id FROM categorias WHERE nombre LIKE '%Hardware%' LIMIT 1");
$origen_sistema  = db_one("SELECT id FROM origenes_reporte LIMIT 1");
$estado_inicial  = db_one("SELECT id FROM estados WHERE es_inicial = 1 LIMIT 1");

if (!$estado_inicial || !$severidad_media) {
    flash_set('error', 'No hay severidades o estados configurados.');
    header('Location: ' . url('mantenimiento_ver.php?id=' . $mant_id));
    exit;
}

try {
    db()->beginTransaction();

    // Generar folio
    $sucursal = db_one("SELECT codigo FROM sucursales WHERE id = :id", ['id' => $m['equipo_sucursal_id']]);
    $folio = generar_folio_incidencia($sucursal['codigo'] ?? 'BAC');

    // Crear incidencia
    db_exec(
        "INSERT INTO incidencias
         (folio, sucursal_id, area_id, equipo_id,
          categoria_id, tipo_trabajo_id, severidad_id, origen_reporte_id, estado_id,
          titulo, descripcion,
          reportado_por_id, asignado_a_id,
          fecha_evento, fecha_reporte, creado_en)
         VALUES
         (:folio, :sid, :aid, :eid,
          :cat, :tt, :sev, :org, :est,
          :tit, :desc,
          :rid, :asgn,
          NOW(), NOW(), NOW())",
        [
            'folio' => $folio,
            'sid'   => $m['equipo_sucursal_id'],
            'aid'   => $m['equipo_area_id'],
            'eid'   => $m['equipo_id'],
            'cat'   => $categoria_hw['id'] ?? null,
            'tt'    => $tipo_preventivo['id'] ?? null,
            'sev'   => $severidad_media['id'],
            'org'   => $origen_sistema['id'] ?? null,
            'est'   => $estado_inicial['id'],
            'tit'   => 'Mantenimiento: ' . $m['titulo'],
            'desc'  => "Convertido desde mantenimiento programado #{$mant_id}.\n\n" . ($m['descripcion'] ?: ''),
            'rid'   => $u['id'],
            'asgn'  => $m['asignado_a_id'],
        ]
    );
    $incidencia_id = (int) db_last_id();

    // Marcar mantenimiento como convertido
    db_exec(
        "UPDATE mantenimientos SET estado = 'cancelado', incidencia_generada_id = :iid,
         resultado = CONCAT(COALESCE(resultado,''), 'Convertido a incidencia $folio')
         WHERE id = :mid",
        ['iid' => $incidencia_id, 'mid' => $mant_id]
    );

    registrar_auditoria('convertir_mant_incidencia', 'mantenimientos', $mant_id,
        "Convertido en incidencia $folio (ID $incidencia_id)");

    db()->commit();

    flash_set('success', "Mantenimiento convertido a incidencia $folio.");
    header('Location: ' . url('incidencia_ver.php?id=' . $incidencia_id));
    exit;
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    flash_set('error', 'Error: ' . $e->getMessage());
    header('Location: ' . url('mantenimiento_ver.php?id=' . $mant_id));
    exit;
}
