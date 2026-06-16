<?php
/**
 * ============================================================================
 * config/importacion_helpers.php
 * ============================================================================
 * Lógica para importar datos masivamente desde CSV:
 *   - Parser robusto (detecta separador: coma o punto y coma)
 *   - Detecta codificación UTF-8 vs Windows-1252 y convierte
 *   - Validación de cada fila antes de insertar
 *   - Procesadores específicos para usuarios, equipos, incidencias
 *   - Registro en tabla `importaciones` para auditoría
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ============================================================================
// Constantes de columnas esperadas por tipo
// ============================================================================

const IMPORTAR_COLUMNAS = [
    'usuarios' => [
        'usuario'         => ['requerido' => true,  'max' => 50,  'desc' => 'Nombre de login único'],
        'nombre_completo' => ['requerido' => true,  'max' => 150, 'desc' => 'Nombre completo'],
        'email'           => ['requerido' => false, 'max' => 100, 'desc' => 'Email (opcional)'],
        'telefono'        => ['requerido' => false, 'max' => 30,  'desc' => 'Teléfono (opcional)'],
        'rol'             => ['requerido' => true,  'max' => 50,  'desc' => 'Nombre del rol (ej. ingeniero, gerente, jefe_area)'],
        'sucursal'        => ['requerido' => false, 'max' => 50,  'desc' => 'Código o nombre de sucursal'],
    ],
    'equipos' => [
        'codigo_inventario' => ['requerido' => true,  'max' => 50,  'desc' => 'Código único'],
        'nombre'           => ['requerido' => true,  'max' => 150, 'desc' => 'Nombre del equipo'],
        'tipo'             => ['requerido' => false, 'max' => 100, 'desc' => 'Tipo (ej. Báscula, Impresora)'],
        'marca'            => ['requerido' => false, 'max' => 100, 'desc' => 'Marca'],
        'modelo'           => ['requerido' => false, 'max' => 100, 'desc' => 'Modelo'],
        'numero_serie'     => ['requerido' => false, 'max' => 100, 'desc' => 'Número de serie'],
        'sucursal'         => ['requerido' => true,  'max' => 50,  'desc' => 'Código o nombre de sucursal'],
        'area'             => ['requerido' => false, 'max' => 100, 'desc' => 'Nombre del área'],
        'ubicacion'        => ['requerido' => false, 'max' => 200, 'desc' => 'Ubicación física (ej. Caja 2)'],
        'fecha_compra'     => ['requerido' => false, 'max' => 20,  'desc' => 'YYYY-MM-DD'],
        'costo_compra'     => ['requerido' => false, 'max' => 20,  'desc' => 'Número decimal (ej. 15000.00)'],
    ],
    'incidencias' => [
        'titulo'        => ['requerido' => true,  'max' => 255, 'desc' => 'Título'],
        'descripcion'   => ['requerido' => false, 'max' => 5000,'desc' => 'Descripción'],
        'sucursal'      => ['requerido' => true,  'max' => 50,  'desc' => 'Código o nombre'],
        'area'          => ['requerido' => false, 'max' => 100, 'desc' => 'Nombre del área'],
        'categoria'     => ['requerido' => false, 'max' => 100, 'desc' => 'Nombre de la categoría'],
        'tipo_trabajo'  => ['requerido' => false, 'max' => 100, 'desc' => 'Nombre del tipo'],
        'severidad'     => ['requerido' => true,  'max' => 50,  'desc' => 'Nombre (ej. Crítica, Alta, Media, Baja)'],
        'origen'        => ['requerido' => false, 'max' => 50,  'desc' => 'Nombre del origen (ej. Presencial)'],
        'estado'        => ['requerido' => true,  'max' => 50,  'desc' => 'Nombre del estado (ej. Resuelta, Cerrada)'],
        'reportante'    => ['requerido' => false, 'max' => 150, 'desc' => 'Nombre de quien reportó'],
        'puesto'        => ['requerido' => false, 'max' => 100, 'desc' => 'Puesto del reportante'],
        'fecha_evento'  => ['requerido' => true,  'max' => 20,  'desc' => 'YYYY-MM-DD HH:MM (cuándo ocurrió)'],
        'solucion'      => ['requerido' => false, 'max' => 5000,'desc' => 'Solución aplicada (si está resuelta)'],
    ],
];


// ============================================================================
// PARSEO DE CSV
// ============================================================================

/**
 * Parsea un archivo CSV detectando automáticamente:
 *  - Separador: , o ;
 *  - Codificación: UTF-8 o Windows-1252 (típico de Excel español)
 *  - BOM al inicio
 *
 * Retorna ['headers' => [...], 'filas' => [[...], [...]]]
 */
function parsear_csv(string $ruta_archivo): array {
    if (!file_exists($ruta_archivo)) {
        throw new RuntimeException('Archivo no encontrado');
    }

    $contenido = file_get_contents($ruta_archivo);
    if ($contenido === false) {
        throw new RuntimeException('No se pudo leer el archivo');
    }

    // Quitar BOM
    if (substr($contenido, 0, 3) === "\xEF\xBB\xBF") {
        $contenido = substr($contenido, 3);
    }

    // Detectar codificación
    $codif = mb_detect_encoding($contenido, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
    if ($codif !== 'UTF-8' && $codif !== false) {
        $contenido = mb_convert_encoding($contenido, 'UTF-8', $codif);
    }

    // Normalizar saltos de línea
    $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);

    // Detectar separador: contar , vs ; en la primera línea
    $primera = strtok($contenido, "\n");
    $sep = (substr_count($primera, ';') > substr_count($primera, ',')) ? ';' : ',';

    // Parsear con fgetcsv (más robusto que explode)
    $lineas = explode("\n", $contenido);
    $headers = null;
    $filas = [];

    foreach ($lineas as $idx => $linea) {
        if (trim($linea) === '') continue;
        $campos = str_getcsv($linea, $sep, '"');
        $campos = array_map(fn($v) => trim((string) $v), $campos);

        if ($headers === null) {
            // Primera línea: headers (normalizar: lowercase, sin espacios extra)
            $headers = array_map(fn($h) => strtolower(trim($h)), $campos);
        } else {
            // Asegurar que cada fila tenga el mismo número de columnas
            while (count($campos) < count($headers)) $campos[] = '';
            $filas[] = $campos;
        }
    }

    return ['headers' => $headers, 'filas' => $filas, 'separador' => $sep];
}


/**
 * Verifica que el CSV tenga las columnas requeridas para el tipo.
 * Retorna lista de errores (vacía si todo bien).
 */
function validar_columnas_csv(array $headers, string $tipo): array {
    if (!isset(IMPORTAR_COLUMNAS[$tipo])) {
        return ['Tipo de importación inválido: ' . $tipo];
    }

    $columnas_esperadas = IMPORTAR_COLUMNAS[$tipo];
    $errores = [];

    // Verificar columnas requeridas
    foreach ($columnas_esperadas as $col => $cfg) {
        if ($cfg['requerido'] && !in_array($col, $headers, true)) {
            $errores[] = "Falta la columna obligatoria: <code>$col</code>";
        }
    }

    // Detectar columnas desconocidas (no fatal, solo aviso)
    $desconocidas = array_diff($headers, array_keys($columnas_esperadas));
    if (!empty($desconocidas)) {
        $errores[] = '⚠ Columnas desconocidas que serán ignoradas: ' .
                     implode(', ', array_map(fn($c) => "<code>$c</code>", $desconocidas));
    }

    return $errores;
}


// ============================================================================
// RESOLUCIÓN DE CATÁLOGOS (cache estático)
// ============================================================================

function resolver_sucursal_id(?string $referencia): ?int {
    if (empty($referencia)) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all("SELECT id, codigo, nombre FROM sucursales WHERE activo=1") as $s) {
            $cache[strtolower($s['codigo'])] = (int) $s['id'];
            $cache[strtolower($s['nombre'])] = (int) $s['id'];
        }
    }
    return $cache[strtolower(trim($referencia))] ?? null;
}

function resolver_area_id(?string $nombre): ?int {
    if (empty($nombre)) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all("SELECT id, nombre FROM areas WHERE activo=1") as $a) {
            $cache[strtolower($a['nombre'])] = (int) $a['id'];
        }
    }
    return $cache[strtolower(trim($nombre))] ?? null;
}

function resolver_categoria_id(?string $nombre): ?int {
    if (empty($nombre)) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all("SELECT id, nombre FROM categorias WHERE activo=1") as $c) {
            $cache[strtolower($c['nombre'])] = (int) $c['id'];
        }
    }
    return $cache[strtolower(trim($nombre))] ?? null;
}

function resolver_tipo_trabajo_id(?string $nombre): ?int {
    if (empty($nombre)) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all("SELECT id, nombre FROM tipos_trabajo WHERE activo=1") as $t) {
            $cache[strtolower($t['nombre'])] = (int) $t['id'];
        }
    }
    return $cache[strtolower(trim($nombre))] ?? null;
}

function resolver_severidad_id(?string $nombre): ?int {
    if (empty($nombre)) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all("SELECT id, nombre, nivel FROM severidades WHERE activo=1") as $s) {
            $cache[strtolower($s['nombre'])] = (int) $s['id'];
            $cache[(string) $s['nivel']] = (int) $s['id'];
        }
    }
    return $cache[strtolower(trim($nombre))] ?? null;
}

function resolver_estado_id(?string $nombre): ?int {
    if (empty($nombre)) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all("SELECT id, nombre FROM estados WHERE activo=1") as $e) {
            $cache[strtolower($e['nombre'])] = (int) $e['id'];
        }
    }
    return $cache[strtolower(trim($nombre))] ?? null;
}

function resolver_origen_id(?string $nombre): ?int {
    if (empty($nombre)) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all("SELECT id, nombre FROM origenes_reporte WHERE activo=1") as $o) {
            $cache[strtolower($o['nombre'])] = (int) $o['id'];
        }
    }
    return $cache[strtolower(trim($nombre))] ?? null;
}

function resolver_rol_id(?string $nombre): ?int {
    if (empty($nombre)) return null;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all("SELECT id, nombre FROM roles WHERE activo=1") as $r) {
            $cache[strtolower($r['nombre'])] = (int) $r['id'];
        }
    }
    return $cache[strtolower(trim($nombre))] ?? null;
}


// ============================================================================
// PROCESADORES POR TIPO
// ============================================================================

/**
 * Importa usuarios. Retorna ['exitosos' => N, 'fallidos' => N, 'errores' => [...]]
 *
 * Cada usuario importado tiene una contraseña temporal "demo1234"
 * con flag debe_cambiar_password=1.
 */
function importar_usuarios(array $headers, array $filas, int $usuario_id_actor): array {
    $exitosos = 0;
    $fallidos = 0;
    $errores = [];

    $idx_usuario = array_search('usuario', $headers, true);
    $idx_nombre  = array_search('nombre_completo', $headers, true);
    $idx_email   = array_search('email', $headers, true);
    $idx_tel     = array_search('telefono', $headers, true);
    $idx_rol     = array_search('rol', $headers, true);
    $idx_suc     = array_search('sucursal', $headers, true);

    $password_hash = password_hash('demo1234', PASSWORD_DEFAULT);

    foreach ($filas as $i => $fila) {
        $num_fila = $i + 2; // +2 porque header es fila 1
        $usuario = trim((string) ($fila[$idx_usuario] ?? ''));
        $nombre  = trim((string) ($fila[$idx_nombre] ?? ''));
        $rol_str = trim((string) ($fila[$idx_rol] ?? ''));

        if ($usuario === '') { $fallidos++; $errores[] = "Fila $num_fila: usuario vacío"; continue; }
        if ($nombre === '')  { $fallidos++; $errores[] = "Fila $num_fila: nombre vacío"; continue; }

        // Validar usuario único
        $existe = db_one("SELECT id FROM usuarios WHERE usuario = :u", ['u' => $usuario]);
        if ($existe) { $fallidos++; $errores[] = "Fila $num_fila: usuario '$usuario' ya existe"; continue; }

        // Resolver rol
        $rol_id = resolver_rol_id($rol_str);
        if (!$rol_id) { $fallidos++; $errores[] = "Fila $num_fila: rol '$rol_str' no encontrado"; continue; }

        // Resolver sucursal (opcional)
        $sucursal_id = null;
        if ($idx_suc !== false) {
            $suc_str = trim((string) ($fila[$idx_suc] ?? ''));
            if ($suc_str !== '') {
                $sucursal_id = resolver_sucursal_id($suc_str);
                if (!$sucursal_id) {
                    $fallidos++; $errores[] = "Fila $num_fila: sucursal '$suc_str' no encontrada"; continue;
                }
            }
        }

        $email = $idx_email !== false ? trim((string) ($fila[$idx_email] ?? '')) : '';
        $telefono = $idx_tel !== false ? trim((string) ($fila[$idx_tel] ?? '')) : '';

        try {
            db_exec(
                "INSERT INTO usuarios (usuario, password_hash, nombre_completo, email, telefono,
                  rol_id, sucursal_id, debe_cambiar_password, activo)
                 VALUES (:u, :ph, :nc, :e, :t, :rid, :sid, 1, 1)",
                [
                    'u' => $usuario, 'ph' => $password_hash, 'nc' => $nombre,
                    'e' => $email ?: null, 't' => $telefono ?: null,
                    'rid' => $rol_id, 'sid' => $sucursal_id,
                ]
            );
            $exitosos++;
        } catch (Throwable $e) {
            $fallidos++;
            $errores[] = "Fila $num_fila ($usuario): " . $e->getMessage();
        }
    }

    return ['exitosos' => $exitosos, 'fallidos' => $fallidos, 'errores' => $errores];
}


/**
 * Importa equipos.
 */
function importar_equipos(array $headers, array $filas, int $usuario_id_actor): array {
    $exitosos = 0;
    $fallidos = 0;
    $errores = [];

    $i_codigo  = array_search('codigo_inventario', $headers, true);
    $i_nombre  = array_search('nombre', $headers, true);
    $i_tipo    = array_search('tipo', $headers, true);
    $i_marca   = array_search('marca', $headers, true);
    $i_modelo  = array_search('modelo', $headers, true);
    $i_serie   = array_search('numero_serie', $headers, true);
    $i_suc     = array_search('sucursal', $headers, true);
    $i_area    = array_search('area', $headers, true);
    $i_ubic    = array_search('ubicacion', $headers, true);
    $i_fcompra = array_search('fecha_compra', $headers, true);
    $i_costo   = array_search('costo_compra', $headers, true);

    foreach ($filas as $i => $fila) {
        $num_fila = $i + 2;
        $codigo = trim((string) ($fila[$i_codigo] ?? ''));
        $nombre = trim((string) ($fila[$i_nombre] ?? ''));
        $suc_str = trim((string) ($fila[$i_suc] ?? ''));

        if ($codigo === '') { $fallidos++; $errores[] = "Fila $num_fila: código vacío"; continue; }
        if ($nombre === '') { $fallidos++; $errores[] = "Fila $num_fila: nombre vacío"; continue; }

        // Validar único
        $existe = db_one("SELECT id FROM equipos WHERE codigo_inventario = :c", ['c' => $codigo]);
        if ($existe) { $fallidos++; $errores[] = "Fila $num_fila: código '$codigo' ya existe"; continue; }

        $sucursal_id = resolver_sucursal_id($suc_str);
        if (!$sucursal_id) { $fallidos++; $errores[] = "Fila $num_fila: sucursal '$suc_str' no encontrada"; continue; }

        $area_id = null;
        if ($i_area !== false) {
            $area_str = trim((string) ($fila[$i_area] ?? ''));
            if ($area_str !== '') $area_id = resolver_area_id($area_str);
        }

        $fecha_compra = null;
        if ($i_fcompra !== false) {
            $fc = trim((string) ($fila[$i_fcompra] ?? ''));
            if ($fc !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fc)) $fecha_compra = $fc;
        }

        $costo = null;
        if ($i_costo !== false) {
            $c = trim((string) ($fila[$i_costo] ?? ''));
            if ($c !== '' && is_numeric($c)) $costo = (float) $c;
        }

        try {
            db_exec(
                "INSERT INTO equipos (codigo_inventario, nombre, tipo, marca, modelo, numero_serie,
                  sucursal_id, area_id, ubicacion, fecha_compra, costo_compra, estado_vida, activo)
                 VALUES (:c, :n, :t, :m, :mo, :ns, :s, :a, :u, :fc, :cc, 'en_uso', 1)",
                [
                    'c' => $codigo, 'n' => $nombre,
                    't' => $i_tipo !== false ? (trim((string) ($fila[$i_tipo] ?? '')) ?: null) : null,
                    'm' => $i_marca !== false ? (trim((string) ($fila[$i_marca] ?? '')) ?: null) : null,
                    'mo' => $i_modelo !== false ? (trim((string) ($fila[$i_modelo] ?? '')) ?: null) : null,
                    'ns' => $i_serie !== false ? (trim((string) ($fila[$i_serie] ?? '')) ?: null) : null,
                    's' => $sucursal_id, 'a' => $area_id,
                    'u' => $i_ubic !== false ? (trim((string) ($fila[$i_ubic] ?? '')) ?: null) : null,
                    'fc' => $fecha_compra, 'cc' => $costo,
                ]
            );
            $exitosos++;
        } catch (Throwable $e) {
            $fallidos++;
            $errores[] = "Fila $num_fila ($codigo): " . $e->getMessage();
        }
    }

    return ['exitosos' => $exitosos, 'fallidos' => $fallidos, 'errores' => $errores];
}


/**
 * Importa incidencias históricas.
 */
function importar_incidencias(array $headers, array $filas, int $usuario_id_actor): array {
    $exitosos = 0;
    $fallidos = 0;
    $errores = [];

    $i_tit = array_search('titulo', $headers, true);
    $i_desc = array_search('descripcion', $headers, true);
    $i_suc = array_search('sucursal', $headers, true);
    $i_area = array_search('area', $headers, true);
    $i_cat = array_search('categoria', $headers, true);
    $i_tt = array_search('tipo_trabajo', $headers, true);
    $i_sev = array_search('severidad', $headers, true);
    $i_org = array_search('origen', $headers, true);
    $i_est = array_search('estado', $headers, true);
    $i_rep = array_search('reportante', $headers, true);
    $i_pue = array_search('puesto', $headers, true);
    $i_fe = array_search('fecha_evento', $headers, true);
    $i_sol = array_search('solucion', $headers, true);

    foreach ($filas as $i => $fila) {
        $num_fila = $i + 2;
        $titulo = trim((string) ($fila[$i_tit] ?? ''));
        $suc_str = trim((string) ($fila[$i_suc] ?? ''));
        $sev_str = trim((string) ($fila[$i_sev] ?? ''));
        $est_str = trim((string) ($fila[$i_est] ?? ''));
        $fe_str = trim((string) ($fila[$i_fe] ?? ''));

        if ($titulo === '') { $fallidos++; $errores[] = "Fila $num_fila: título vacío"; continue; }

        $sucursal_id = resolver_sucursal_id($suc_str);
        if (!$sucursal_id) { $fallidos++; $errores[] = "Fila $num_fila: sucursal '$suc_str' no encontrada"; continue; }

        $severidad_id = resolver_severidad_id($sev_str);
        if (!$severidad_id) { $fallidos++; $errores[] = "Fila $num_fila: severidad '$sev_str' no encontrada"; continue; }

        $estado_id = resolver_estado_id($est_str);
        if (!$estado_id) { $fallidos++; $errores[] = "Fila $num_fila: estado '$est_str' no encontrado"; continue; }

        // Parsear fecha (acepta YYYY-MM-DD HH:MM o YYYY-MM-DD)
        if ($fe_str === '') { $fallidos++; $errores[] = "Fila $num_fila: fecha_evento vacía"; continue; }
        $ts = strtotime($fe_str);
        if ($ts === false) { $fallidos++; $errores[] = "Fila $num_fila: fecha '$fe_str' inválida"; continue; }
        $fecha_evento = date('Y-m-d H:i:s', $ts);

        // Resolver opcionales
        $area_id = $i_area !== false ? resolver_area_id(trim((string) ($fila[$i_area] ?? ''))) : null;
        $cat_id = $i_cat !== false ? resolver_categoria_id(trim((string) ($fila[$i_cat] ?? ''))) : null;
        $tt_id = $i_tt !== false ? resolver_tipo_trabajo_id(trim((string) ($fila[$i_tt] ?? ''))) : null;
        $org_id = $i_org !== false ? resolver_origen_id(trim((string) ($fila[$i_org] ?? ''))) : null;

        // Si el estado es final y hay solución, marcar resuelta
        $estado_es_final = db_one("SELECT es_final FROM estados WHERE id = :id", ['id' => $estado_id])['es_final'] ?? 0;
        $fecha_resolucion = (int) $estado_es_final === 1 ? $fecha_evento : null;

        // Generar folio
        $sucursal = db_one("SELECT codigo FROM sucursales WHERE id = :id", ['id' => $sucursal_id]);
        $codigo_suc = $sucursal['codigo'] ?? 'BAC';
        // Folio simplificado para importación: IMP-{COD}-{TIMESTAMP}-{INDEX}
        $folio = 'IMP-' . $codigo_suc . '-' . date('YmdHis') . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

        try {
            db_exec(
                "INSERT INTO incidencias
                 (folio, titulo, descripcion, sucursal_id, area_id, categoria_id,
                  tipo_trabajo_id, severidad_id, origen_reporte_id, estado_id,
                  reportado_por_id, reportante_nombre, reportante_puesto,
                  solucion, fecha_evento, fecha_reporte, fecha_resolucion, creado_en)
                 VALUES (:f, :t, :d, :s, :a, :c, :tt, :sv, :o, :es,
                  :rid, :rn, :rp, :sol, :fe, :fe2, :fr, NOW())",
                [
                    'f' => $folio, 't' => $titulo,
                    'd' => $i_desc !== false ? (trim((string) ($fila[$i_desc] ?? '')) ?: null) : null,
                    's' => $sucursal_id, 'a' => $area_id, 'c' => $cat_id,
                    'tt' => $tt_id, 'sv' => $severidad_id, 'o' => $org_id, 'es' => $estado_id,
                    'rid' => $usuario_id_actor,
                    'rn' => $i_rep !== false ? (trim((string) ($fila[$i_rep] ?? '')) ?: null) : null,
                    'rp' => $i_pue !== false ? (trim((string) ($fila[$i_pue] ?? '')) ?: null) : null,
                    'sol' => $i_sol !== false ? (trim((string) ($fila[$i_sol] ?? '')) ?: null) : null,
                    'fe' => $fecha_evento, 'fe2' => $fecha_evento, 'fr' => $fecha_resolucion,
                ]
            );
            $exitosos++;
        } catch (Throwable $e) {
            $fallidos++;
            $errores[] = "Fila $num_fila: " . $e->getMessage();
        }
    }

    return ['exitosos' => $exitosos, 'fallidos' => $fallidos, 'errores' => $errores];
}


// ============================================================================
// REGISTRO Y LISTADO
// ============================================================================

function registrar_importacion(string $tipo, string $nombre_archivo, int $total, int $exitosos, int $fallidos, array $errores, int $usuario_id): int {
    db_exec(
        "INSERT INTO importaciones (tipo, nombre_archivo, total_filas, exitosos, fallidos, errores_json, realizado_por_id)
         VALUES (:t, :n, :tot, :e, :f, :err, :uid)",
        [
            't' => $tipo, 'n' => $nombre_archivo, 'tot' => $total,
            'e' => $exitosos, 'f' => $fallidos,
            'err' => !empty($errores) ? json_encode(array_slice($errores, 0, 100), JSON_UNESCAPED_UNICODE) : null,
            'uid' => $usuario_id,
        ]
    );
    return (int) db_last_id();
}

function listar_importaciones(int $limite = 50): array {
    return db_all(
        "SELECT i.*, u.nombre_completo realizado_por_nombre
         FROM importaciones i
         LEFT JOIN usuarios u ON i.realizado_por_id = u.id
         ORDER BY i.creado_en DESC
         LIMIT $limite"
    );
}


// ============================================================================
// CSV DE EJEMPLO POR TIPO
// ============================================================================

function generar_csv_ejemplo(string $tipo): string {
    if (!isset(IMPORTAR_COLUMNAS[$tipo])) return '';

    $headers = array_keys(IMPORTAR_COLUMNAS[$tipo]);

    // Filas de ejemplo según tipo
    $filas = [];
    switch ($tipo) {
        case 'usuarios':
            $filas = [
                ['juan.perez', 'Juan Pérez García', 'juan@ejemplo.com', '664-123-4567', 'ingeniero', 'BAC'],
                ['maria.lopez', 'María López Soto', 'maria@ejemplo.com', '', 'gerente', 'FER'],
                ['carlos.ruiz', 'Carlos Ruiz', '', '664-987-6543', 'jefe_area', 'BAC'],
            ];
            break;
        case 'equipos':
            $filas = [
                ['EQ-001', 'Báscula caja 1', 'Báscula', 'Toledo', 'IND-FW-15', 'SN12345', 'BAC', 'Cajas', 'Caja 1', '2024-03-15', '12500.00'],
                ['EQ-002', 'Impresora térmica', 'Impresora', 'Epson', 'TM-T20III', '', 'FER', 'Cajas', 'Caja 2', '2025-01-10', '4500.00'],
                ['EQ-003', 'PC mostrador', 'Computadora', 'Lenovo', 'ThinkCentre M70', 'PC-456', 'BAC', '', 'Mostrador', '', ''],
            ];
            break;
        case 'incidencias':
            $filas = [
                ['Falla en báscula', 'No marca peso correctamente', 'BAC', 'Cajas', 'Hardware', 'Correctivo', 'Alta', 'Presencial', 'Resuelta', 'Pedro Sánchez', 'Cajero', '2024-08-15 10:30', 'Se recalibró la báscula con pesa patrón'],
                ['Sin internet en sucursal', 'Toda la sucursal sin conexión', 'FER', '', 'Red', 'Correctivo', 'Crítica', 'Presencial', 'Cerrada', 'Encargado', 'Encargado', '2024-09-02 08:15', 'Se reinició router; cable suelto en panel'],
            ];
            break;
    }

    // Generar CSV con BOM UTF-8 para Excel
    $out = "\xEF\xBB\xBF";
    $out .= implode(',', array_map('csv_escape', $headers)) . "\n";
    foreach ($filas as $f) {
        $out .= implode(',', array_map('csv_escape', $f)) . "\n";
    }
    return $out;
}

function csv_escape(string $valor): string {
    if (strpos($valor, ',') !== false || strpos($valor, '"') !== false || strpos($valor, "\n") !== false) {
        return '"' . str_replace('"', '""', $valor) . '"';
    }
    return $valor;
}
