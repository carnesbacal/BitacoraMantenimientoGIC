<?php
/**
 * ============================================================================
 * config/inteligencia_helpers.php
 * ============================================================================
 * Funciones de inteligencia para el sistema:
 *   - Sugerir plantillas similares por texto del título
 *   - Sugerir soluciones de incidencias previas similares
 *   - Sugerir técnicos expertos en categoría/tipo
 *   - Evaluar reglas de auto-asignación
 *   - Encontrar técnico con menos carga
 *   - Detectar equipos con fallas recurrentes
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ============================================================================
// Búsqueda de palabras clave en texto (utilidad)
// ============================================================================

/**
 * Extrae palabras clave significativas de un texto (>3 caracteres, sin stopwords).
 */
function extraer_palabras_clave(string $texto): array {
    $stopwords = ['para','este','esta','esto','sobre','desde','como','pero','aunque',
                  'porque','cuando','donde','hace','hacer','tiene','tener','ningun',
                  'ninguna','nada','algo','alguien','algún','alguna','muchos','muchas',
                  'todos','todas','siempre','nunca','ahora','luego','antes','después',
                  'también','sino','aún','solo','solamente','muy','más','menos','mejor',
                  'peor','grande','pequeño','que','del','las','los','una','uno','con',
                  'por','sin','les','sus','nos','les'];

    $texto = mb_strtolower($texto, 'UTF-8');
    // Quitar acentos para hacer match más flexible
    $sin_acentos = strtr($texto, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
    ]);
    // Tokenizar
    $palabras = preg_split('/[^a-z0-9]+/', $sin_acentos, -1, PREG_SPLIT_NO_EMPTY);

    return array_values(array_unique(array_filter($palabras, fn($p) => mb_strlen($p) > 3 && !in_array($p, $stopwords, true))));
}


// ============================================================================
// SUGERENCIAS al crear incidencia
// ============================================================================

/**
 * Sugiere plantillas relevantes según el texto del título.
 * Retorna top 3 con score basado en palabras coincidentes.
 */
function sugerir_plantillas_por_texto(string $titulo, int $limit = 3): array {
    $titulo = trim($titulo);
    if (mb_strlen($titulo) < 3) return [];

    $palabras = extraer_palabras_clave($titulo);
    if (empty($palabras)) return [];

    // Construir WHERE con LIKE para cada palabra
    $where_parts = [];
    $params = [];
    foreach ($palabras as $i => $palabra) {
        $where_parts[] = "(LOWER(p.nombre) LIKE :p$i OR LOWER(p.descripcion) LIKE :pd$i OR LOWER(p.titulo) LIKE :pt$i)";
        $params["p$i"]  = "%$palabra%";
        $params["pd$i"] = "%$palabra%";
        $params["pt$i"] = "%$palabra%";
    }
    $where_or = implode(' OR ', $where_parts);

    // Calcular score
    $score_parts = [];
    foreach ($palabras as $i => $palabra) {
        $score_parts[] = "(CASE WHEN LOWER(p.nombre) LIKE :ps$i THEN 3 ELSE 0 END)";
        $score_parts[] = "(CASE WHEN LOWER(p.titulo) LIKE :pst$i THEN 2 ELSE 0 END)";
        $score_parts[] = "(CASE WHEN LOWER(p.descripcion) LIKE :psd$i THEN 1 ELSE 0 END)";
        $params["ps$i"]  = "%$palabra%";
        $params["pst$i"] = "%$palabra%";
        $params["psd$i"] = "%$palabra%";
    }
    $score_sql = implode(' + ', $score_parts);

    return db_all(
        "SELECT p.id, p.nombre, p.descripcion, p.icono, p.color,
                p.titulo, p.descripcion_inc, p.area_id, p.categoria_id,
                p.tipo_trabajo_id, p.severidad_id, p.origen_reporte_id, p.solucion_sugerida,
                ($score_sql) AS score
         FROM plantillas_incidencias p
         WHERE p.activo = 1 AND ($where_or)
         HAVING score > 0
         ORDER BY score DESC, p.usos DESC
         LIMIT $limit",
        $params
    );
}


/**
 * Sugiere soluciones aplicadas en incidencias resueltas similares.
 */
function sugerir_soluciones_por_texto(string $titulo, int $limit = 3): array {
    $titulo = trim($titulo);
    if (mb_strlen($titulo) < 3) return [];

    $palabras = extraer_palabras_clave($titulo);
    if (empty($palabras)) return [];

    $where_parts = [];
    $params = [];
    foreach ($palabras as $i => $palabra) {
        $where_parts[] = "(LOWER(i.titulo) LIKE :p$i OR LOWER(i.descripcion) LIKE :pd$i)";
        $params["p$i"]  = "%$palabra%";
        $params["pd$i"] = "%$palabra%";
    }
    $where_or = implode(' OR ', $where_parts);

    $score_parts = [];
    foreach ($palabras as $i => $palabra) {
        $score_parts[] = "(CASE WHEN LOWER(i.titulo) LIKE :ps$i THEN 3 ELSE 0 END)";
        $score_parts[] = "(CASE WHEN LOWER(i.descripcion) LIKE :psd$i THEN 1 ELSE 0 END)";
        $params["ps$i"]  = "%$palabra%";
        $params["psd$i"] = "%$palabra%";
    }
    $score_sql = implode(' + ', $score_parts);

    return db_all(
        "SELECT i.id, i.folio, i.titulo, i.solucion, i.fecha_resolucion,
                u.nombre_completo resuelto_por_nombre,
                ($score_sql) AS score
         FROM incidencias i
         LEFT JOIN usuarios u ON i.resuelto_por_id = u.id
         WHERE i.solucion IS NOT NULL
           AND i.solucion <> ''
           AND ($where_or)
         HAVING score > 0
         ORDER BY score DESC, i.fecha_resolucion DESC
         LIMIT $limit",
        $params
    );
}


/**
 * Sugiere técnicos que han resuelto incidencias en la misma categoría/tipo.
 */
function sugerir_tecnicos_expertos(?int $categoria_id, ?int $tipo_trabajo_id, ?int $area_id, int $limit = 3): array {
    if (!$categoria_id && !$tipo_trabajo_id && !$area_id) return [];

    $where = ['1=1'];
    $params = [];
    if ($categoria_id) { $where[] = "i.categoria_id = :cid"; $params['cid'] = $categoria_id; }
    if ($tipo_trabajo_id) { $where[] = "i.tipo_trabajo_id = :tid"; $params['tid'] = $tipo_trabajo_id; }
    if ($area_id) { $where[] = "i.area_id = :aid"; $params['aid'] = $area_id; }

    $where_sql = implode(' AND ', $where);

    return db_all(
        "SELECT u.id, u.nombre_completo, u.avatar_url, u.email,
                COUNT(i.id) AS resueltas,
                AVG(i.tiempo_resolucion_min) AS tiempo_promedio
         FROM incidencias i
         INNER JOIN usuarios u ON i.resuelto_por_id = u.id
         INNER JOIN estados e ON i.estado_id = e.id
         WHERE e.es_final = 1
           AND u.activo = 1
           AND $where_sql
         GROUP BY u.id
         ORDER BY resueltas DESC
         LIMIT $limit",
        $params
    );
}


// ============================================================================
// REGLAS de auto-asignación
// ============================================================================

/**
 * Evalúa las reglas activas y retorna el técnico a asignar, o null si ninguna aplica.
 * Las reglas se evalúan en orden de prioridad (menor primero).
 */
function evaluar_reglas_asignacion(
    int $sucursal_id,
    ?int $area_id,
    ?int $categoria_id,
    ?int $tipo_trabajo_id,
    ?int $severidad_id
): ?array {
    $reglas = db_all(
        "SELECT r.*, u.nombre_completo asignado_nombre
         FROM reglas_asignacion r
         INNER JOIN usuarios u ON r.asignar_a_id = u.id
         WHERE r.activa = 1 AND u.activo = 1
         ORDER BY r.prioridad ASC, r.id ASC"
    );

    foreach ($reglas as $r) {
        // Para que una regla aplique, todas sus condiciones no-NULL deben coincidir
        if ($r['sucursal_id']     !== null && (int) $r['sucursal_id']     !== $sucursal_id)     continue;
        if ($r['area_id']         !== null && (int) $r['area_id']         !== (int) $area_id)         continue;
        if ($r['categoria_id']    !== null && (int) $r['categoria_id']    !== (int) $categoria_id)    continue;
        if ($r['tipo_trabajo_id'] !== null && (int) $r['tipo_trabajo_id'] !== (int) $tipo_trabajo_id) continue;
        if ($r['severidad_id']    !== null && (int) $r['severidad_id']    !== (int) $severidad_id)    continue;

        // Esta regla aplica
        return [
            'regla_id'        => (int) $r['id'],
            'regla_nombre'    => $r['nombre'],
            'asignar_a_id'    => (int) $r['asignar_a_id'],
            'asignado_nombre' => $r['asignado_nombre'],
        ];
    }

    return null;
}


/**
 * Incrementa el contador de uso de una regla.
 */
function registrar_uso_regla(int $regla_id): void {
    db_exec("UPDATE reglas_asignacion SET veces_aplicada = veces_aplicada + 1 WHERE id = :id",
        ['id' => $regla_id]);
}


/**
 * Construye una descripción legible de las condiciones de una regla.
 */
function describir_regla(array $regla): string {
    $partes = [];

    if ($regla['sucursal_id']) {
        $r = db_one("SELECT nombre FROM sucursales WHERE id = :id", ['id' => $regla['sucursal_id']]);
        if ($r) $partes[] = "Sucursal: <strong>{$r['nombre']}</strong>";
    }
    if ($regla['area_id']) {
        $r = db_one("SELECT nombre FROM areas WHERE id = :id", ['id' => $regla['area_id']]);
        if ($r) $partes[] = "Área: <strong>{$r['nombre']}</strong>";
    }
    if ($regla['categoria_id']) {
        $r = db_one("SELECT nombre FROM categorias WHERE id = :id", ['id' => $regla['categoria_id']]);
        if ($r) $partes[] = "Categoría: <strong>{$r['nombre']}</strong>";
    }
    if ($regla['tipo_trabajo_id']) {
        $r = db_one("SELECT nombre FROM tipos_trabajo WHERE id = :id", ['id' => $regla['tipo_trabajo_id']]);
        if ($r) $partes[] = "Tipo: <strong>{$r['nombre']}</strong>";
    }
    if ($regla['severidad_id']) {
        $r = db_one("SELECT nombre FROM severidades WHERE id = :id", ['id' => $regla['severidad_id']]);
        if ($r) $partes[] = "Severidad: <strong>{$r['nombre']}</strong>";
    }

    if (empty($partes)) return '<em class="text-zinc-500">Sin condiciones (aplica a TODO)</em>';
    return implode(' · ', $partes);
}


// ============================================================================
// BALANCEO DE CARGA
// ============================================================================

/**
 * Retorna el técnico con MENOS incidencias abiertas en este momento.
 * Si hay empate, prefiere quien tenga acceso a la sucursal.
 */
function tecnico_menos_cargado(?int $sucursal_id = null): ?array {
    $params = [];
    $where_suc = '';
    if ($sucursal_id) {
        // Preferir técnicos de esta sucursal o sin sucursal asignada (admin global)
        $where_suc = "AND (u.sucursal_id IS NULL OR u.sucursal_id = :sid)";
        $params['sid'] = $sucursal_id;
    }

    return db_one(
        "SELECT u.id, u.nombre_completo, u.avatar_url,
                COUNT(i.id) AS abiertas
         FROM usuarios u
         INNER JOIN roles r ON u.rol_id = r.id
         LEFT JOIN incidencias i ON i.asignado_a_id = u.id AND i.estado_id IN (
            SELECT id FROM estados WHERE es_final = 0
         )
         WHERE r.puede_resolver = 1 AND u.activo = 1 $where_suc
         GROUP BY u.id
         ORDER BY abiertas ASC, u.nombre_completo ASC
         LIMIT 1",
        $params
    );
}


// ============================================================================
// DETECCIÓN DE FALLAS RECURRENTES
// ============================================================================

/**
 * Equipos con fallas frecuentes en los últimos 30/90 días.
 * Umbrales: 2+ atención, 3+ preocupante, 4+ urgente (en 30 días)
 *           6+ urgente (en 90 días)
 */
function equipos_problematicos(?int $sucursal_id = null, int $limit = 10): array {
    $params = [];
    $where_suc = '';
    if ($sucursal_id) {
        $where_suc = "AND e.sucursal_id = :sid";
        $params['sid'] = $sucursal_id;
    }

    return db_all(
        "SELECT e.id, e.codigo_inventario, e.nombre, e.marca, e.modelo,
                s.nombre sucursal_nombre,
                a.nombre area_nombre,
                COUNT(DISTINCT CASE
                    WHEN i.fecha_evento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    THEN i.id END) AS inc_30d,
                COUNT(DISTINCT CASE
                    WHEN i.fecha_evento >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    THEN i.id END) AS inc_90d,
                MAX(i.fecha_evento) AS ultima_falla
         FROM equipos e
         INNER JOIN incidencias i ON i.equipo_id = e.id
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         LEFT JOIN areas a ON e.area_id = a.id
         WHERE e.activo = 1
           AND e.estado_vida != 'dado_de_baja'
           AND i.fecha_evento >= DATE_SUB(NOW(), INTERVAL 90 DAY)
           $where_suc
         GROUP BY e.id
         HAVING inc_30d >= 2 OR inc_90d >= 4
         ORDER BY inc_30d DESC, inc_90d DESC
         LIMIT $limit",
        $params
    );
}


/**
 * Clasifica el nivel de gravedad de un equipo según el conteo de fallas.
 * Retorna ['nivel' => 'atencion|preocupante|urgente', 'color' => '...', 'icono' => '...']
 */
function clasificar_problema_equipo(int $inc_30d, int $inc_90d): array {
    if ($inc_30d >= 4 || $inc_90d >= 6) {
        return ['nivel' => 'urgente', 'color' => '#DC2626', 'etiqueta' => 'Urgente', 'icono' => 'flame'];
    }
    if ($inc_30d >= 3) {
        return ['nivel' => 'preocupante', 'color' => '#D97706', 'etiqueta' => 'Preocupante', 'icono' => 'alert-triangle'];
    }
    return ['nivel' => 'atencion', 'color' => '#0EA5E9', 'etiqueta' => 'Atención', 'icono' => 'info'];
}
