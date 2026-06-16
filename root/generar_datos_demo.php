<?php
/**
 * ============================================================================
 * generar_datos_demo.php - Genera datos de demostración
 * ============================================================================
 * USO:
 * 1. Coloca este archivo en la raíz del proyecto.
 * 2. Abre: http://localhost/UtilidadesBacal/BitacoraSistemas/generar_datos_demo.php
 * 3. ¡ELIMÍNALO después de generar los datos!
 *
 * Crea:
 * - 8 usuarios de prueba (ingenieros, gerentes, jefes de área)
 * - ~12 equipos por sucursal
 * - ~80 incidencias variadas distribuidas en los últimos 30 días
 *   con diferentes estados, severidades, reincidencias y tiempos resueltos
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

// Protección: solo accesible si no hay incidencias previas
$existentes = db_one("SELECT COUNT(*) c FROM incidencias")['c'];
if ($existentes > 5 && !isset($_GET['forzar'])) {
    die("<pre style='font-family:sans-serif;padding:30px;background:#fef2f2;color:#991b1b'>
⚠️ Ya hay {$existentes} incidencias en la base de datos.
Si quieres GENERAR de todos modos, agrega ?forzar=1 a la URL.
Si quieres BORRAR TODO antes (recomendado para empezar limpio), agrega ?reset=1
</pre>");
}

if (isset($_GET['reset'])) {
    db_exec("SET FOREIGN_KEY_CHECKS=0");
    db_exec("TRUNCATE TABLE incidencias_adjuntos");
    db_exec("TRUNCATE TABLE incidencias_comentarios");
    db_exec("TRUNCATE TABLE incidencias_historial");
    db_exec("TRUNCATE TABLE incidencias_etiquetas");
    db_exec("TRUNCATE TABLE incidencias");
    db_exec("TRUNCATE TABLE equipos");
    db_exec("DELETE FROM usuarios WHERE usuario != 'admin'");
    db_exec("SET FOREIGN_KEY_CHECKS=1");
    echo "<p style='color:#16a34a;font-weight:bold;padding:10px'>✓ Datos limpiados. Recarga sin ?reset para generar nuevos datos.</p>";
    exit;
}

echo "<!DOCTYPE html><html><head><title>Generando datos demo</title>
<style>body{font-family:system-ui;padding:30px;background:#fafafa;max-width:800px;margin:auto}
h1{color:#C8102E}h2{color:#18181b;margin-top:30px;border-bottom:2px solid #e4e4e7;padding-bottom:5px}
.ok{color:#16a34a}.warn{color:#d97706}.info{color:#2563eb}
.box{background:white;border:1px solid #e4e4e7;border-radius:8px;padding:20px;margin:15px 0}
code{background:#f4f4f5;padding:2px 6px;border-radius:4px;font-size:13px}
.btn{display:inline-block;padding:10px 20px;background:#C8102E;color:white;text-decoration:none;border-radius:6px;font-weight:bold;margin-top:10px}
</style></head><body>";

echo "<h1>🎲 Generando datos de demostración</h1>";

// ============================================================
// 1. USUARIOS DE PRUEBA
// ============================================================
echo "<h2>Usuarios</h2>";

$rol_admin    = db_one("SELECT id FROM roles WHERE nombre='Administrador'")['id'];
$rol_ing      = db_one("SELECT id FROM roles WHERE nombre='Ingeniero en Sistemas'")['id'];
$rol_gerente  = db_one("SELECT id FROM roles WHERE nombre='Gerente'")['id'];
$rol_jefe     = db_one("SELECT id FROM roles WHERE nombre='Jefe de Área'")['id'];

$sucursal_bacal  = db_one("SELECT id FROM sucursales WHERE codigo='BAC'")['id'];
$sucursal_ferias = db_one("SELECT id FROM sucursales WHERE codigo='FER'")['id'];

$area_cajas      = db_one("SELECT id FROM areas WHERE nombre='Cajas'")['id'];
$area_contab     = db_one("SELECT id FROM areas WHERE nombre='Contabilidad'")['id'];
$area_carniceria = db_one("SELECT id FROM areas WHERE nombre='Carnicería'")['id'];
$area_oficina    = db_one("SELECT id FROM areas WHERE nombre='Oficina'")['id'];
$area_almacen    = db_one("SELECT id FROM areas WHERE nombre='Almacén'")['id'];

$pass = password_hash('demo1234', PASSWORD_DEFAULT);

$usuarios_demo = [
    // Ingenieros (acceso a todas las sucursales)
    ['abraham',  'Abraham García',     $rol_ing,     null,             null,            'Ing. Sistemas'],
    ['carlos',   'Carlos Martínez',    $rol_ing,     null,             null,            'Ing. Sistemas'],
    ['diana',    'Diana López',        $rol_ing,     null,             null,            'Ing. Sistemas'],
    // Gerentes
    ['gerente1', 'Roberto Hernández',  $rol_gerente, $sucursal_bacal,  null,            'Gerente Bacal'],
    ['gerente2', 'Laura Sánchez',      $rol_gerente, $sucursal_ferias, null,            'Gerente Ferias'],
    // Jefes de área
    ['jefe_cajas', 'Beatriz Ramírez',  $rol_jefe,    $sucursal_bacal,  $area_cajas,     'Jefe de Cajas'],
    ['jefe_carn',  'Pedro Morales',    $rol_jefe,    $sucursal_bacal,  $area_carniceria,'Jefe Carnicería'],
    ['jefe_alm',   'Nadia Guerrero',   $rol_jefe,    $sucursal_ferias, $area_almacen,   'Jefe Almacén'],
];

$user_ids = [];
foreach ($usuarios_demo as $du) {
    $existe = db_one("SELECT id FROM usuarios WHERE usuario = :u", ['u' => $du[0]]);
    if ($existe) {
        $user_ids[$du[0]] = $existe['id'];
        echo "<div class='info'>↻ Usuario <code>{$du[0]}</code> ya existía.</div>";
        continue;
    }
    db_exec(
        "INSERT INTO usuarios (usuario, password_hash, nombre_completo, rol_id, sucursal_id, area_id, puesto, debe_cambiar_password)
         VALUES (:u, :p, :n, :r, :s, :a, :pu, 0)",
        ['u' => $du[0], 'p' => $pass, 'n' => $du[1], 'r' => $du[2],
         's' => $du[3], 'a' => $du[4], 'pu' => $du[5]]
    );
    $user_ids[$du[0]] = db_last_id();
    echo "<div class='ok'>✓ Usuario <code>{$du[0]}</code> creado ({$du[1]})</div>";
}

// ============================================================
// 2. EQUIPOS DE PRUEBA
// ============================================================
echo "<h2>Equipos</h2>";

$tipos_equipos = ['PC', 'Laptop', 'Impresora', 'Cámara IP', 'Punto de Venta', 'Switch', 'Router', 'Teléfono IP'];
$marcas = ['HP', 'Dell', 'Lenovo', 'Epson', 'Brother', 'Cisco', 'TP-Link', 'Yealink'];

$equipos_creados = 0;
foreach ([$sucursal_bacal => 'BAC', $sucursal_ferias => 'FER'] as $sid => $cod) {
    for ($i = 1; $i <= 12; $i++) {
        $tipo = $tipos_equipos[array_rand($tipos_equipos)];
        $marca = $marcas[array_rand($marcas)];
        $codigo = "{$cod}-" . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        $existe = db_one("SELECT id FROM equipos WHERE codigo_inventario = :c", ['c' => $codigo]);
        if ($existe) continue;

        $area_eq = [$area_cajas, $area_contab, $area_carniceria, $area_oficina, $area_almacen][array_rand([0,1,2,3,4])];
        $nombre_eq = "{$tipo} " . $cod . "-" . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        db_exec(
            "INSERT INTO equipos (codigo_inventario, nombre, tipo, marca, modelo, sucursal_id, area_id, ubicacion)
             VALUES (:c, :n, :t, :m, :mo, :s, :a, :u)",
            ['c' => $codigo, 'n' => $nombre_eq, 't' => $tipo, 'm' => $marca,
             'mo' => 'Modelo ' . rand(2018, 2024), 's' => $sid, 'a' => $area_eq,
             'u' => 'Sucursal ' . $cod . ' · Área ' . rand(1, 5)]
        );
        $equipos_creados++;
    }
}
echo "<div class='ok'>✓ {$equipos_creados} equipos generados</div>";

// ============================================================
// 3. INCIDENCIAS DE PRUEBA
// ============================================================
echo "<h2>Incidencias</h2>";

// Cargar catálogos
$todas_areas      = db_all("SELECT id FROM areas WHERE activo=1");
$todas_cat        = db_all("SELECT id FROM categorias WHERE activo=1");
$todos_tipos      = db_all("SELECT id FROM tipos_trabajo WHERE activo=1");
$todas_sev        = db_all("SELECT id, nivel, sla_horas FROM severidades ORDER BY nivel");
$todos_estados    = db_all("SELECT id, es_final, es_inicial FROM estados WHERE activo=1");
$estado_inicial   = db_one("SELECT id FROM estados WHERE es_inicial=1 LIMIT 1")['id'];
$estado_completada = db_one("SELECT id FROM estados WHERE nombre='Completada' LIMIT 1")['id'];
$estado_proceso   = db_one("SELECT id FROM estados WHERE nombre='En proceso' LIMIT 1")['id'];
$todos_origenes   = db_all("SELECT id FROM origenes_reporte WHERE activo=1");

// Equipos por sucursal
$equipos_bacal  = db_all("SELECT id FROM equipos WHERE sucursal_id = :s", ['s' => $sucursal_bacal]);
$equipos_ferias = db_all("SELECT id FROM equipos WHERE sucursal_id = :s", ['s' => $sucursal_ferias]);

// Plantillas realistas de incidencias
$plantillas = [
    ['Soporte de contraseña de caja', 'Personal de caja no recuerda su contraseña de acceso al sistema de punto de venta.',
     'Se brindó apoyo al personal proporcionando una nueva contraseña temporal. Se verificó el acceso exitoso.',
     'Implementar política de cambio de contraseña cada 90 días y entrenar al personal en el uso de gestores.'],
    ['Falla en impresora de tickets', 'La impresora de tickets de la caja 2 no imprime, muestra error de papel aunque sí tiene rollo.',
     'Se realizó limpieza del cabezal, recalibración y cambio de rodillo. Funciona correctamente.',
     'Programar mantenimiento preventivo trimestral de impresoras de tickets.'],
    ['Sin acceso a internet en oficinas', 'No hay acceso a internet en el área administrativa, todos los equipos sin conexión.',
     'Se identificó problema en el switch principal. Se reinició y se reemplazó el cable patch dañado.',
     'Revisar redundancia del switch principal y considerar respaldo de internet.'],
    ['Cámara CCTV sin señal', 'La cámara de seguridad del área de carga no transmite video al DVR desde anoche.',
     'Se sustituyó el cable BNC dañado y se reconfiguró el canal. Imagen restaurada.',
     'Inspección mensual de cableado de cámaras y limpieza de conectores.'],
    ['Alarma activándose sola', 'La alarma contra incendios se activa sin causa aparente varias veces al día.',
     'Se identificó polvo en el sensor de humo del área de cocina. Se limpiaron todos los sensores.',
     'Programar limpieza trimestral de sensores y revisión anual del sistema de alarma.'],
    ['PC de contabilidad muy lenta', 'La computadora de contabilidad se traba constantemente al abrir el sistema.',
     'Se realizó limpieza de archivos temporales, desfragmentación y actualización del antivirus. Velocidad mejorada.',
     'Considerar aumento de RAM y migración a SSD. Mantenimiento preventivo cada 6 meses.'],
    ['Punto de venta no factura', 'El sistema de punto de venta en la caja principal no permite generar facturas, error 503.',
     'Se reinició el servicio de facturación y se actualizó el certificado SAT. Facturación restaurada.',
     'Configurar alertas automáticas para vencimiento de certificados fiscales.'],
    ['Solicitud de instalación de Office', 'Nuevo equipo en oficina requiere instalación de paquetería Office y configuración de correo.',
     'Se instaló Office 365, se configuró Outlook con la cuenta corporativa y OneDrive.',
     'Mantener inventario de licencias y agilizar entrega para nuevos ingresos.'],
    ['Cuenta de usuario bloqueada', 'Usuario reporta que no puede acceder al sistema, mensaje de cuenta bloqueada.',
     'Se desbloqueó la cuenta y se reseteó la contraseña. Acceso restaurado.',
     'Recordar al personal evitar múltiples intentos fallidos.'],
    ['Báscula sin conexión al sistema', 'La báscula de carnicería no envía las lecturas de peso al sistema de punto de venta.',
     'Se reconfiguró el puerto COM y se actualizó el driver. Comunicación restablecida.',
     'Hacer respaldo de la configuración de drivers de básculas.'],
    ['Teléfono IP sin tono', 'El teléfono IP de recepción no tiene tono de marcado, no se pueden hacer ni recibir llamadas.',
     'Se reinició el dispositivo y se verificó la configuración SIP. Servicio restaurado.',
     'Documentar configuración SIP de todos los teléfonos para recuperación rápida.'],
    ['Servidor de archivos sin respuesta', 'Nadie puede acceder a la carpeta compartida del servidor de archivos.',
     'Se reinició el servicio SMB y se liberó memoria del servidor. Acceso restaurado.',
     'Programar reinicios automáticos del servidor cada domingo a las 3 AM.'],
    ['Lector de código de barras dañado', 'El lector de barras de la caja 3 ya no lee productos, parece dañado físicamente.',
     'Se reemplazó el lector por uno nuevo del stock. Caja operativa.',
     'Mantener al menos 2 lectores de respaldo en almacén de TI.'],
    ['Solicitud de creación de usuario', 'Nuevo empleado de contabilidad requiere acceso al sistema ERP y cuenta de correo.',
     'Se creó la cuenta con los permisos correspondientes y se configuró el correo corporativo.',
     'Implementar flujo formal de altas con RH para que llegue completo el requerimiento.'],
    ['Disco duro casi lleno en servidor', 'Alerta automática indica que el disco del servidor de respaldos está al 95%.',
     'Se eliminaron respaldos antiguos según política de retención y se expandió la partición.',
     'Configurar respaldos en almacenamiento adicional o nube.'],
];

$reportantes = ['Beatriz Cajera', 'Nadia Guerrero', 'Ana Contable', 'Marcos Almacenista',
                'Sofía Recepción', 'Jorge Carnicero', 'Lucía Oficina', 'Raúl Reparto'];

$ingenieros = [$user_ids['abraham'], $user_ids['carlos'], $user_ids['diana']];
$jefes_arr  = [$user_ids['jefe_cajas'], $user_ids['jefe_carn'], $user_ids['jefe_alm']];

$total_creadas = 0;
$incidencias_por_grupo = []; // para crear reincidencias

for ($n = 0; $n < 80; $n++) {
    $plantilla = $plantillas[array_rand($plantillas)];
    $sucursal_id = rand(0, 1) ? $sucursal_bacal : $sucursal_ferias;
    $equipos_pool = $sucursal_id == $sucursal_bacal ? $equipos_bacal : $equipos_ferias;
    $equipo_id = !empty($equipos_pool) && rand(0, 100) < 80 ? $equipos_pool[array_rand($equipos_pool)]['id'] : null;
    $area_id = $todas_areas[array_rand($todas_areas)]['id'];
    $categoria_id = $todas_cat[array_rand($todas_cat)]['id'];
    $tipo_id = $todos_tipos[array_rand($todos_tipos)]['id'];

    // Distribución realista de severidades: pocos críticos, más medios
    $sev_pesos = [1, 2, 2, 3, 3, 3, 3, 4, 4]; // nivel
    $sev_nivel = $sev_pesos[array_rand($sev_pesos)];
    $sev_row = null;
    foreach ($todas_sev as $s) if ($s['nivel'] == $sev_nivel) { $sev_row = $s; break; }
    $severidad_id = $sev_row['id'];
    $sla_horas = (int) $sev_row['sla_horas'];

    // Fecha distribuida en los últimos 30 días
    $dias_atras = rand(0, 29);
    $hora = rand(7, 19);
    $minuto = rand(0, 59);
    $fecha_evento = date('Y-m-d H:i:s', strtotime("-$dias_atras days $hora:$minuto:00"));
    $fecha_creado = $fecha_evento;

    // Estado: 65% completadas, 15% en proceso, 20% otras abiertas
    $r = rand(1, 100);
    if ($r <= 65) {
        $estado_id = $estado_completada;
        $es_final = 1;
    } elseif ($r <= 80) {
        $estado_id = $estado_proceso;
        $es_final = 0;
    } else {
        // Algún otro estado abierto
        $abiertos = array_filter($todos_estados, fn($e) => !$e['es_final']);
        $estado_id = $abiertos[array_rand($abiertos)]['id'];
        $es_final = 0;
    }

    // ¿Es reincidencia? (15% si ya hay grupo similar previo)
    $clave_grupo = "$area_id-$equipo_id-$categoria_id";
    $es_reincidencia = 0;
    $incidencia_padre_id = null;
    if (isset($incidencias_por_grupo[$clave_grupo]) && rand(0, 100) < 50) {
        $es_reincidencia = 1;
        $incidencia_padre_id = $incidencias_por_grupo[$clave_grupo];
    }

    // Reportante y asignación
    $reportado_por_id = rand(0, 1) ? $jefes_arr[array_rand($jefes_arr)] : $ingenieros[array_rand($ingenieros)];
    $asignado_a_id = rand(0, 100) < 90 ? $ingenieros[array_rand($ingenieros)] : null;
    $resuelto_por_id = $es_final ? $asignado_a_id : null;

    // Tiempos
    $tiempo_respuesta = rand(5, 60); // minutos
    $fecha_atencion = $asignado_a_id ? date('Y-m-d H:i:s', strtotime($fecha_evento) + $tiempo_respuesta * 60) : null;
    $tiempo_resolucion = null;
    $fecha_resolucion = null;
    $fecha_cierre = null;
    $sla_cumplido = null;
    $fecha_limite_sla = date('Y-m-d H:i:s', strtotime($fecha_evento) + $sla_horas * 3600);

    if ($es_final) {
        $tiempo_resolucion = rand(15, $sla_horas * 60 + rand(-60, 120)); // a veces excede el SLA
        $fecha_resolucion = date('Y-m-d H:i:s', strtotime($fecha_atencion) + $tiempo_resolucion * 60);
        $fecha_cierre = date('Y-m-d H:i:s', strtotime($fecha_resolucion) + rand(0, 7200));
        $sla_cumplido = strtotime($fecha_resolucion) <= strtotime($fecha_limite_sla) ? 1 : 0;
    }

    // Generar folio
    $sc = db_one("SELECT codigo FROM sucursales WHERE id = :id", ['id' => $sucursal_id]);
    $anio = (int) date('Y', strtotime($fecha_creado));
    $row_count = db_one(
        "SELECT COUNT(*) n FROM incidencias WHERE sucursal_id = :sid AND YEAR(creado_en) = :a",
        ['sid' => $sucursal_id, 'a' => $anio]
    );
    $consecutivo = str_pad((string)((int)$row_count['n'] + 1), 4, '0', STR_PAD_LEFT);
    $folio = "INC-" . $sc['codigo'] . "-{$anio}-{$consecutivo}";

    db_exec(
        "INSERT INTO incidencias
         (folio, titulo, descripcion, sucursal_id, area_id, categoria_id, tipo_trabajo_id, severidad_id, estado_id,
          equipo_id, reportado_por_id, reportante_nombre, asignado_a_id, resuelto_por_id,
          solucion, recomendaciones, es_reincidencia, incidencia_padre_id,
          fecha_evento, fecha_atencion, fecha_resolucion, fecha_cierre,
          tiempo_respuesta_min, tiempo_resolucion_min, sla_cumplido, fecha_limite_sla,
          creado_en, creado_por_id)
         VALUES
         (:folio, :tit, :desc, :sid, :aid, :cid, :ttid, :sevid, :eid_est,
          :eqid, :rep, :repn, :asig, :res,
          :sol, :rec, :reinc, :padre,
          :fe, :fa, :fr, :fc,
          :tr, :tres, :sla, :fsla,
          :creado, :cpid)",
        [
            'folio' => $folio, 'tit' => $plantilla[0], 'desc' => $plantilla[1],
            'sid' => $sucursal_id, 'aid' => $area_id, 'cid' => $categoria_id,
            'ttid' => $tipo_id, 'sevid' => $severidad_id, 'eid_est' => $estado_id,
            'eqid' => $equipo_id, 'rep' => $reportado_por_id,
            'repn' => $reportantes[array_rand($reportantes)],
            'asig' => $asignado_a_id, 'res' => $resuelto_por_id,
            'sol' => $es_final ? $plantilla[2] : null,
            'rec' => $es_final ? $plantilla[3] : null,
            'reinc' => $es_reincidencia, 'padre' => $incidencia_padre_id,
            'fe' => $fecha_evento, 'fa' => $fecha_atencion,
            'fr' => $fecha_resolucion, 'fc' => $fecha_cierre,
            'tr' => $tiempo_respuesta, 'tres' => $tiempo_resolucion,
            'sla' => $sla_cumplido, 'fsla' => $fecha_limite_sla,
            'creado' => $fecha_creado, 'cpid' => $reportado_por_id,
        ]
    );
    $id_inc = db_last_id();
    $total_creadas++;

    // Guardar para futuras reincidencias
    if (!isset($incidencias_por_grupo[$clave_grupo])) {
        $incidencias_por_grupo[$clave_grupo] = $id_inc;
    }
}

echo "<div class='ok'>✓ {$total_creadas} incidencias generadas con datos realistas</div>";

// ============================================================
// FINAL
// ============================================================
?>

<div class="box">
    <h2 style="margin-top:0">🎉 Datos demo generados</h2>
    <p>Ahora puedes ver el dashboard con datos reales. Probá los siguientes usuarios para ver diferentes vistas:</p>

    <table style="border-collapse:collapse;width:100%;margin-top:10px">
        <tr style="background:#f4f4f5">
            <th style="padding:8px;text-align:left;border:1px solid #e4e4e7">Usuario</th>
            <th style="padding:8px;text-align:left;border:1px solid #e4e4e7">Contraseña</th>
            <th style="padding:8px;text-align:left;border:1px solid #e4e4e7">Rol / Vista</th>
        </tr>
        <tr><td style="padding:8px;border:1px solid #e4e4e7"><code>admin</code></td><td style="padding:8px;border:1px solid #e4e4e7">(la que tú definiste)</td><td style="padding:8px;border:1px solid #e4e4e7">Admin · Ve TODO</td></tr>
        <tr><td style="padding:8px;border:1px solid #e4e4e7"><code>abraham</code></td><td style="padding:8px;border:1px solid #e4e4e7"><code>demo1234</code></td><td style="padding:8px;border:1px solid #e4e4e7">Ingeniero · Ve todas las sucursales + su trabajo pendiente</td></tr>
        <tr><td style="padding:8px;border:1px solid #e4e4e7"><code>gerente1</code></td><td style="padding:8px;border:1px solid #e4e4e7"><code>demo1234</code></td><td style="padding:8px;border:1px solid #e4e4e7">Gerente Bacal · Solo ve su sucursal</td></tr>
        <tr><td style="padding:8px;border:1px solid #e4e4e7"><code>gerente2</code></td><td style="padding:8px;border:1px solid #e4e4e7"><code>demo1234</code></td><td style="padding:8px;border:1px solid #e4e4e7">Gerente Ferias · Solo ve su sucursal</td></tr>
        <tr><td style="padding:8px;border:1px solid #e4e4e7"><code>jefe_cajas</code></td><td style="padding:8px;border:1px solid #e4e4e7"><code>demo1234</code></td><td style="padding:8px;border:1px solid #e4e4e7">Jefe de Cajas · Crea solicitudes</td></tr>
    </table>

    <p style="color:#dc2626;margin-top:20px"><strong>⚠️ IMPORTANTE:</strong> Elimina este archivo (<code>generar_datos_demo.php</code>) después de usarlo.</p>

    <a href="<?= url('dashboard.php') ?>" class="btn">Ir al dashboard →</a>
</div>

</body></html>
