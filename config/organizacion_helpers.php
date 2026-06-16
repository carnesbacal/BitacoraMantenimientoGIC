<?php
/**
 * ============================================================================
 * config/organizacion_helpers.php
 * ============================================================================
 * Funciones para:
 *   - Sugerir categoría según palabras clave del título/descripción
 *   - Archivar incidencias resueltas hace más de 1 año
 *   - Listar palabras clave y estadísticas
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ============================================================================
// SUGERENCIA DE CATEGORÍA
// ============================================================================

/**
 * Normaliza un texto: lowercase, sin acentos.
 * Para comparar con las palabras clave guardadas (que también están normalizadas).
 */
function normalizar_texto(string $texto): string {
    $texto = mb_strtolower($texto, 'UTF-8');
    return strtr($texto, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
    ]);
}


/**
 * Sugiere las top N categorías más probables según el texto proporcionado.
 *
 * @param string $texto  Título y/o descripción de la incidencia
 * @param int $limit Cuántas sugerencias retornar
 * @return array  Lista de ['categoria_id', 'categoria_nombre', 'score', 'palabras_coincidentes']
 */
function sugerir_categorias_por_texto(string $texto, int $limit = 3): array {
    $texto_norm = normalizar_texto($texto);
    if (mb_strlen($texto_norm) < 3) return [];

    // Cargar todas las palabras clave activas (cache: hay pocas, no hay paginación)
    $palabras_clave = db_all(
        "SELECT k.categoria_id, k.palabra, k.peso, c.nombre categoria_nombre, c.color
         FROM categorias_palabras_clave k
         INNER JOIN categorias c ON k.categoria_id = c.id
         WHERE c.activo = 1"
    );

    if (empty($palabras_clave)) return [];

    // Acumular scores por categoría
    $scores = [];
    foreach ($palabras_clave as $kw) {
        $palabra = mb_strtolower($kw['palabra'], 'UTF-8');
        // La palabra debe aparecer en el texto normalizado
        if (mb_strpos($texto_norm, $palabra) !== false) {
            $cid = (int) $kw['categoria_id'];
            if (!isset($scores[$cid])) {
                $scores[$cid] = [
                    'categoria_id' => $cid,
                    'categoria_nombre' => $kw['categoria_nombre'],
                    'color' => $kw['color'],
                    'score' => 0,
                    'palabras_coincidentes' => [],
                ];
            }
            $scores[$cid]['score'] += (int) $kw['peso'];
            $scores[$cid]['palabras_coincidentes'][] = $kw['palabra'];
        }
    }

    if (empty($scores)) return [];

    // Ordenar por score descendente
    usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

    return array_slice($scores, 0, $limit);
}


/**
 * Retorna la categoría más probable (score más alto). Útil para auto-asignar.
 */
function categoria_mas_probable(string $texto): ?array {
    $sugerencias = sugerir_categorias_por_texto($texto, 1);
    return $sugerencias[0] ?? null;
}


// ============================================================================
// GESTIÓN DE PALABRAS CLAVE
// ============================================================================

function listar_palabras_clave_de_categoria(int $categoria_id): array {
    return db_all(
        "SELECT id, palabra, peso, creado_en
         FROM categorias_palabras_clave
         WHERE categoria_id = :cid
         ORDER BY peso DESC, palabra ASC",
        ['cid' => $categoria_id]
    );
}

function agregar_palabra_clave(int $categoria_id, string $palabra, int $peso = 1): bool {
    $palabra_norm = normalizar_texto(trim($palabra));
    if (mb_strlen($palabra_norm) < 2 || mb_strlen($palabra_norm) > 60) return false;

    try {
        db_exec(
            "INSERT INTO categorias_palabras_clave (categoria_id, palabra, peso)
             VALUES (:cid, :p, :w)",
            ['cid' => $categoria_id, 'p' => $palabra_norm, 'w' => max(1, min(5, $peso))]
        );
        return true;
    } catch (Throwable $e) {
        // Probablemente duplicada (UNIQUE KEY)
        return false;
    }
}

function eliminar_palabra_clave(int $id): void {
    db_exec("DELETE FROM categorias_palabras_clave WHERE id = :id", ['id' => $id]);
}


// ============================================================================
// ARCHIVADO AUTOMÁTICO
// ============================================================================

/**
 * Cuenta cuántas incidencias serían archivadas con los criterios actuales.
 * Útil para mostrar al admin antes de hacer el archivado masivo.
 */
function contar_incidencias_archivables(int $dias_antiguedad = 365): array {
    $umbral = date('Y-m-d', strtotime("-$dias_antiguedad days"));

    return db_one(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN i.archivada = 0 THEN 1 ELSE 0 END) AS por_archivar,
            SUM(CASE WHEN i.archivada = 1 THEN 1 ELSE 0 END) AS ya_archivadas
         FROM incidencias i
         INNER JOIN estados e ON i.estado_id = e.id
         WHERE e.es_final = 1
           AND i.fecha_resolucion IS NOT NULL
           AND DATE(i.fecha_resolucion) <= :umbral",
        ['umbral' => $umbral]
    ) ?: ['total' => 0, 'por_archivar' => 0, 'ya_archivadas' => 0];
}


/**
 * Ejecuta el archivado masivo de incidencias resueltas hace >X días.
 * Retorna cuántas fueron archivadas.
 */
function archivar_incidencias_antiguas(int $dias_antiguedad = 365): int {
    $umbral = date('Y-m-d', strtotime("-$dias_antiguedad days"));

    // Contar antes
    $antes = db_one(
        "SELECT COUNT(*) c FROM incidencias i
         INNER JOIN estados e ON i.estado_id = e.id
         WHERE i.archivada = 0
           AND e.es_final = 1
           AND i.fecha_resolucion IS NOT NULL
           AND DATE(i.fecha_resolucion) <= :umbral",
        ['umbral' => $umbral]
    );
    $total = (int) ($antes['c'] ?? 0);

    if ($total === 0) return 0;

    // Archivar en lote
    db_exec(
        "UPDATE incidencias i
         INNER JOIN estados e ON i.estado_id = e.id
         SET i.archivada = 1, i.fecha_archivado = NOW()
         WHERE i.archivada = 0
           AND e.es_final = 1
           AND i.fecha_resolucion IS NOT NULL
           AND DATE(i.fecha_resolucion) <= :umbral",
        ['umbral' => $umbral]
    );

    return $total;
}


/**
 * Desarchiva una incidencia específica (para si admin necesita volver a verla).
 */
function desarchivar_incidencia(int $incidencia_id): bool {
    db_exec(
        "UPDATE incidencias SET archivada = 0, fecha_archivado = NULL WHERE id = :id",
        ['id' => $incidencia_id]
    );
    return true;
}


// ============================================================================
// ESTADÍSTICAS
// ============================================================================

function stats_archivado(): array {
    $total = db_one("SELECT COUNT(*) c FROM incidencias");
    $archivadas = db_one("SELECT COUNT(*) c FROM incidencias WHERE archivada = 1");
    $resueltas = db_one(
        "SELECT COUNT(*) c FROM incidencias i
         INNER JOIN estados e ON i.estado_id = e.id
         WHERE e.es_final = 1"
    );

    return [
        'total' => (int) ($total['c'] ?? 0),
        'archivadas' => (int) ($archivadas['c'] ?? 0),
        'resueltas' => (int) ($resueltas['c'] ?? 0),
        'activas' => (int) ($total['c'] ?? 0) - (int) ($archivadas['c'] ?? 0),
    ];
}


function stats_palabras_clave(): array {
    $total = db_one("SELECT COUNT(*) c FROM categorias_palabras_clave");
    $cats_con_palabras = db_one(
        "SELECT COUNT(DISTINCT categoria_id) c FROM categorias_palabras_clave"
    );
    $cats_total = db_one("SELECT COUNT(*) c FROM categorias WHERE activo = 1");

    return [
        'total_palabras' => (int) ($total['c'] ?? 0),
        'categorias_con_palabras' => (int) ($cats_con_palabras['c'] ?? 0),
        'categorias_total' => (int) ($cats_total['c'] ?? 0),
    ];
}
