<?php
/**
 * flotilla_reportes_export.php - Exporta reporte de flotilla a Excel (6 hojas)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/xlsx_writer.php';
requerir_login();

$u = usuario_actual();
$ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursal_filtro = $ver_todas ? (int) input('sucursal', 0) : (int) $u['sucursal_id'];

$desde = (string) input('desde', date('Y-m-01'));
$hasta = (string) input('hasta', date('Y-m-d'));

$suc_join  = $sucursal_filtro ? " AND v.sucursal_id=:sid " : "";
$suc_param = $sucursal_filtro ? ['sid' => $sucursal_filtro] : [];
$p_rango   = array_merge($suc_param, ['desde' => $desde, 'hasta' => $hasta]);

// ── Datos ─────────────────────────────────────────────────────────────────────

// KPIs generales
$gasto_total = (float) db_one("SELECT COALESCE(SUM(g.monto),0) t FROM flotilla_gastos g INNER JOIN flotilla_vehiculos v ON v.id=g.vehiculo_id $suc_join WHERE g.fecha BETWEEN :desde AND :hasta", $p_rango)['t'];
$litros      = (float) db_one("SELECT COALESCE(SUM(litros),0) t FROM flotilla_combustible c INNER JOIN flotilla_vehiculos v ON v.id=c.vehiculo_id $suc_join WHERE DATE(c.fecha) BETWEEN :desde AND :hasta", $p_rango)['t'];
$servicios   = (int)   db_one("SELECT COUNT(*) t FROM flotilla_mant_historial h INNER JOIN flotilla_vehiculos v ON v.id=h.vehiculo_id $suc_join WHERE h.fecha BETWEEN :desde AND :hasta", $p_rango)['t'];
$vehiculos_act = (int) db_one("SELECT COUNT(*) t FROM flotilla_vehiculos v WHERE v.activo=1 $suc_join AND v.estado='activo'", $suc_param)['t'];

// Resumen por categoría
$por_cat = db_all(
    "SELECT cat.nombre categoria, COUNT(*) registros, SUM(g.monto) total
     FROM flotilla_gastos g
     INNER JOIN flotilla_vehiculos v ON v.id=g.vehiculo_id $suc_join
     INNER JOIN flotilla_categorias_gasto cat ON cat.id=g.categoria_id
     WHERE g.fecha BETWEEN :desde AND :hasta
     GROUP BY cat.nombre ORDER BY total DESC",
    $p_rango
);

// Por vehículo
$por_vehiculo = db_all(
    "SELECT COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) nombre, v.placas, v.estado,
            COALESCE(SUM(g.monto),0) gasto_total,
            COALESCE(SUM(CASE WHEN cat.nombre LIKE '%Combustible%' THEN g.monto ELSE 0 END),0) comb,
            COALESCE(SUM(CASE WHEN cat.nombre LIKE '%Manteni%' THEN g.monto ELSE 0 END),0) mant,
            COALESCE(SUM(CASE WHEN cat.nombre LIKE '%Multa%' THEN g.monto ELSE 0 END),0) multas
     FROM flotilla_vehiculos v $suc_join
     LEFT JOIN flotilla_gastos g ON g.vehiculo_id=v.id AND g.fecha BETWEEN :desde AND :hasta
     LEFT JOIN flotilla_categorias_gasto cat ON cat.id=g.categoria_id
     WHERE v.activo=1
     GROUP BY v.id, v.alias, v.marca, v.modelo, v.placas, v.estado
     ORDER BY gasto_total DESC",
    $p_rango
);

// Detalle gastos
$gastos_det = db_all(
    "SELECT g.fecha, COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) vehiculo, v.placas,
            cat.nombre categoria, g.concepto, g.monto, g.proveedor, g.numero_factura
     FROM flotilla_gastos g
     INNER JOIN flotilla_vehiculos v ON v.id=g.vehiculo_id $suc_join
     INNER JOIN flotilla_categorias_gasto cat ON cat.id=g.categoria_id
     WHERE g.fecha BETWEEN :desde AND :hasta
     ORDER BY g.fecha DESC, g.id DESC",
    $p_rango
);

// Combustible
$comb_det = db_all(
    "SELECT DATE(c.fecha) fecha, COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) vehiculo, v.placas,
            con.nombre_completo conductor, c.km_odometro, c.litros, c.precio_litro,
            ROUND(c.litros*c.precio_litro,2) total, c.rendimiento_kml, c.tipo_combustible, c.estacion
     FROM flotilla_combustible c
     INNER JOIN flotilla_vehiculos v ON v.id=c.vehiculo_id $suc_join
     LEFT JOIN flotilla_conductores con ON con.id=c.conductor_id
     WHERE DATE(c.fecha) BETWEEN :desde AND :hasta
     ORDER BY c.fecha DESC, c.id DESC",
    $p_rango
);

// Mantenimiento historial
$mant_det = db_all(
    "SELECT h.fecha, COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) vehiculo, v.placas,
            h.nombre servicio, h.taller, h.tecnico, h.km_odometro, h.costo,
            h.proximo_km, h.proxima_fecha, h.numero_orden
     FROM flotilla_mant_historial h
     INNER JOIN flotilla_vehiculos v ON v.id=h.vehiculo_id $suc_join
     WHERE h.fecha BETWEEN :desde AND :hasta
     ORDER BY h.fecha DESC, h.id DESC",
    $p_rango
);

// Documentos
$docs_det = db_all(
    "SELECT COALESCE(v.alias,CONCAT(v.marca,' ',v.modelo)) vehiculo, v.placas,
            c.nombre_completo conductor, td.nombre tipo,
            d.numero_documento, d.proveedor, d.fecha_inicio, d.fecha_vence, d.monto, d.estado
     FROM flotilla_documentos d
     INNER JOIN flotilla_tipos_documento td ON td.id=d.tipo_id
     LEFT JOIN flotilla_vehiculos v ON v.id=d.vehiculo_id $suc_join
     LEFT JOIN flotilla_conductores c ON c.id=d.conductor_id
     ORDER BY d.fecha_vence ASC",
    $suc_param
);

// ── Construir Excel ──────────────────────────────────────────────────────────
$xls = new XlsxWriter();

// ── Hoja 1: Resumen ──────────────────────────────────────────────────────────
$xls->addSheet('Resumen');
$xls->addHeaderRow(['REPORTE FLOTILLA VEHICULAR'], true);
$xls->addRow(['Período:', $desde . ' al ' . $hasta]);
$xls->addRow(['Generado:', date('d/m/Y H:i')]);
$xls->addBlankRow();
$xls->addHeaderRow(['KPI', 'Valor']);
$xls->addRow(['Vehículos activos', $vehiculos_act]);
$xls->addRow(['Gasto total', $gasto_total], 3);
$xls->addRow(['Litros cargados', $litros], 2);
$xls->addRow(['Servicios de mantenimiento', $servicios]);
$xls->addBlankRow();
$xls->addHeaderRow(['Categoría', 'Registros', 'Total $']);
foreach ($por_cat as $r) {
    $xls->addRow([$r['categoria'], $r['registros'], $r['total']], 3);
}

// ── Hoja 2: Por vehículo ──────────────────────────────────────────────────────
$xls->addSheet('Por Vehículo');
$xls->addHeaderRow(['Vehículo', 'Placas', 'Estado', 'Gasto Total', 'Combustible', 'Mantenimiento', 'Multas'], true);
foreach ($por_vehiculo as $r) {
    $xls->addRow([$r['nombre'], $r['placas'], $r['estado'], $r['gasto_total'], $r['comb'], $r['mant'], $r['multas']], 3);
}

// ── Hoja 3: Gastos ────────────────────────────────────────────────────────────
$xls->addSheet('Gastos');
$xls->addHeaderRow(['Fecha', 'Vehículo', 'Placas', 'Categoría', 'Concepto', 'Monto', 'Proveedor', 'N° Factura'], true);
foreach ($gastos_det as $r) {
    $xls->addRow([$r['fecha'], $r['vehiculo'], $r['placas'], $r['categoria'], $r['concepto'], $r['monto'], $r['proveedor']??'', $r['numero_factura']??''], 3);
}

// ── Hoja 4: Combustible ───────────────────────────────────────────────────────
$xls->addSheet('Combustible');
$xls->addHeaderRow(['Fecha', 'Vehículo', 'Placas', 'Conductor', 'KM', 'Litros', 'Precio/L', 'Total', 'km/L', 'Tipo', 'Estación'], true);
foreach ($comb_det as $r) {
    $xls->addRow([$r['fecha'], $r['vehiculo'], $r['placas'], $r['conductor']??'', $r['km_odometro'], $r['litros'], $r['precio_litro'], $r['total'], $r['rendimiento_kml']??'', $r['tipo_combustible'], $r['estacion']??''], 2);
}

// ── Hoja 5: Mantenimiento ─────────────────────────────────────────────────────
$xls->addSheet('Mantenimiento');
$xls->addHeaderRow(['Fecha', 'Vehículo', 'Placas', 'Servicio', 'Taller', 'Técnico', 'KM', 'Costo', 'Próximo KM', 'Próxima Fecha', 'N° Orden'], true);
foreach ($mant_det as $r) {
    $xls->addRow([$r['fecha'], $r['vehiculo'], $r['placas'], $r['servicio'], $r['taller']??'', $r['tecnico']??'', $r['km_odometro'], $r['costo']??'', $r['proximo_km']??'', $r['proxima_fecha']??'', $r['numero_orden']??''], 2);
}

// ── Hoja 6: Documentos ────────────────────────────────────────────────────────
$xls->addSheet('Documentos');
$xls->addHeaderRow(['Vehículo', 'Placas', 'Conductor', 'Tipo', 'N° Documento', 'Proveedor', 'Inicio', 'Vencimiento', 'Monto', 'Estado'], true);
foreach ($docs_det as $r) {
    $xls->addRow([$r['vehiculo']??'', $r['placas']??'', $r['conductor']??'', $r['tipo'], $r['numero_documento']??'', $r['proveedor']??'', $r['fecha_inicio']??'', $r['fecha_vence']??'', $r['monto']??'', $r['estado']], 2);
}

// ── Descargar ─────────────────────────────────────────────────────────────────
$fname = 'flotilla_reporte_' . str_replace('-','',$desde) . '_' . str_replace('-','',$hasta) . '.xlsx';
$xls->download($fname);
