<?php
/**
 * ============================================================================
 * config/comunicacion_helpers.php
 * ============================================================================
 * Funciones para:
 *   - Tablero de anuncios (publicación + audiencia + lecturas)
 *   - Recordatorios programados (encolar para envío futuro)
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ============================================================================
// TIPOS DE ANUNCIO
// ============================================================================

const ANUNCIO_TIPOS = [
    'info'    => ['nombre' => 'Información', 'color' => '#0EA5E9', 'icono' => 'info'],
    'aviso'   => ['nombre' => 'Aviso',       'color' => '#D97706', 'icono' => 'bell'],
    'urgente' => ['nombre' => 'Urgente',     'color' => '#DC2626', 'icono' => 'alert-triangle'],
    'exito'   => ['nombre' => 'Bien hecho',  'color' => '#16A34A', 'icono' => 'check-circle-2'],
];


// ============================================================================
// ANUNCIOS - LISTADO Y FILTRADO POR AUDIENCIA
// ============================================================================

/**
 * Lista los anuncios visibles para un usuario:
 *   - Activos y dentro de vigencia (hoy entre fecha_inicio y fecha_fin)
 *   - Audiencia coincide (todos / su sucursal / su rol)
 *   - Que el usuario NO haya cerrado todavía (a menos que sea fijado)
 */
function anuncios_visibles(int $usuario_id, ?int $sucursal_id, int $rol_id, bool $incluir_leidos = false): array {
    $hoy = date('Y-m-d');

    $params = [
        'uid'  => $usuario_id,
        'hoy1' => $hoy,
        'hoy2' => $hoy,
        'sid'  => $sucursal_id,
        'rid'  => $rol_id,
    ];

    $where_leido = $incluir_leidos ? '' : "AND (a.fijado = 1 OR l.id IS NULL)";

    return db_all(
        "SELECT a.*,
                u.nombre_completo creado_por_nombre,
                CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END AS leido
         FROM anuncios a
         LEFT JOIN usuarios u ON a.creado_por_id = u.id
         LEFT JOIN anuncios_lecturas l ON l.anuncio_id = a.id AND l.usuario_id = :uid
         WHERE a.activo = 1
           AND a.fecha_inicio <= :hoy1
           AND (a.fecha_fin IS NULL OR a.fecha_fin >= :hoy2)
           AND (a.sucursal_id IS NULL OR a.sucursal_id = :sid)
           AND (a.rol_id IS NULL OR a.rol_id = :rid)
           $where_leido
         ORDER BY a.fijado DESC, a.tipo = 'urgente' DESC, a.creado_en DESC",
        $params
    );
}


/**
 * Marca un anuncio como leído por un usuario.
 * Si el anuncio está fijado, no lo oculta sino solo registra que lo vio.
 */
function marcar_anuncio_leido(int $anuncio_id, int $usuario_id): void {
    db_exec(
        "INSERT IGNORE INTO anuncios_lecturas (anuncio_id, usuario_id)
         VALUES (:aid, :uid)",
        ['aid' => $anuncio_id, 'uid' => $usuario_id]
    );
}


/**
 * Cuenta los anuncios NO leídos por el usuario (para mostrar badge).
 */
function contar_anuncios_no_leidos(int $usuario_id, ?int $sucursal_id, int $rol_id): int {
    $hoy = date('Y-m-d');
    $r = db_one(
        "SELECT COUNT(*) c
         FROM anuncios a
         LEFT JOIN anuncios_lecturas l ON l.anuncio_id = a.id AND l.usuario_id = :uid
         WHERE a.activo = 1
           AND a.fecha_inicio <= :hoy1
           AND (a.fecha_fin IS NULL OR a.fecha_fin >= :hoy2)
           AND (a.sucursal_id IS NULL OR a.sucursal_id = :sid)
           AND (a.rol_id IS NULL OR a.rol_id = :rid)
           AND l.id IS NULL",
        [
            'uid'  => $usuario_id,
            'hoy1' => $hoy,
            'hoy2' => $hoy,
            'sid'  => $sucursal_id,
            'rid'  => $rol_id,
        ]
    );
    return (int) ($r['c'] ?? 0);
}


/**
 * ¿Quién puede administrar anuncios? Solo admin.
 */
function puede_administrar_anuncios(): bool {
    return tiene_permiso('administrar');
}


// ============================================================================
// RECORDATORIOS - CREAR Y PROCESAR
// ============================================================================

/**
 * Crea un recordatorio que será enviado en fecha_envio.
 */
function crear_recordatorio(
    int $usuario_id,
    string $titulo,
    ?string $mensaje,
    string $fecha_envio,
    ?string $enlace = null,
    ?string $entidad = null,
    ?int $entidad_id = null,
    ?int $creado_por_id = null
): int {
    db_exec(
        "INSERT INTO recordatorios
         (usuario_id, titulo, mensaje, fecha_envio, enlace, entidad, entidad_id, creado_por_id)
         VALUES (:uid, :t, :m, :f, :e, :ent, :eid, :cid)",
        [
            'uid' => $usuario_id,
            't'   => mb_substr($titulo, 0, 200),
            'm'   => $mensaje ? mb_substr($mensaje, 0, 500) : null,
            'f'   => $fecha_envio,
            'e'   => $enlace,
            'ent' => $entidad,
            'eid' => $entidad_id,
            'cid' => $creado_por_id,
        ]
    );
    return (int) db_last_id();
}


/**
 * Lista los recordatorios pendientes (futuros) y recientes de un usuario.
 */
function listar_recordatorios_usuario(int $usuario_id, int $limite = 30): array {
    return db_all(
        "SELECT r.*, u.nombre_completo creado_por_nombre
         FROM recordatorios r
         LEFT JOIN usuarios u ON r.creado_por_id = u.id
         WHERE r.usuario_id = :uid
         ORDER BY
            CASE WHEN r.enviado = 0 THEN 0 ELSE 1 END ASC,
            r.fecha_envio ASC
         LIMIT $limite",
        ['uid' => $usuario_id]
    );
}


/**
 * Lista recordatorios pendientes de enviar (para el cron).
 */
function recordatorios_por_enviar(int $limit = 100): array {
    return db_all(
        "SELECT * FROM recordatorios
         WHERE enviado = 0 AND fecha_envio <= NOW()
         ORDER BY fecha_envio ASC
         LIMIT $limit"
    );
}


/**
 * Marca un recordatorio como enviado.
 */
function marcar_recordatorio_enviado(int $recordatorio_id): void {
    db_exec(
        "UPDATE recordatorios SET enviado = 1, enviado_en = NOW() WHERE id = :id",
        ['id' => $recordatorio_id]
    );
}


/**
 * Elimina un recordatorio (solo si pertenece al usuario o es admin).
 */
function eliminar_recordatorio(int $recordatorio_id, int $usuario_id, bool $es_admin = false): bool {
    $r = db_one("SELECT usuario_id FROM recordatorios WHERE id = :id", ['id' => $recordatorio_id]);
    if (!$r) return false;
    if (!$es_admin && (int) $r['usuario_id'] !== $usuario_id) return false;

    db_exec("DELETE FROM recordatorios WHERE id = :id", ['id' => $recordatorio_id]);
    return true;
}


// ============================================================================
// MENCIONES Y REACCIONES (Fase 16 - Bloque 3)
// ============================================================================

/**
 * Cuenta recordatorios pendientes para el usuario (próximos 24h).
 */
function contar_recordatorios_proximos(int $usuario_id): int {
    $r = db_one(
        "SELECT COUNT(*) c FROM recordatorios
         WHERE usuario_id = :uid AND enviado = 0
           AND fecha_envio BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)",
        ['uid' => $usuario_id]
    );
    return (int) ($r['c'] ?? 0);
}


/**
 * Emojis permitidos para reacciones (los 6 más útiles para trabajo técnico).
 */
const EMOJIS_REACCION = ['👍', '✓', '🔧', '❤️', '😄', '👀'];


/**
 * Extrae las menciones (@usuario) de un texto.
 * Retorna lista de logins encontrados (sin el @).
 *
 * Reglas: el login puede contener letras, números, punto, guion bajo (no espacios).
 */
function extraer_menciones(string $texto): array {
    if (preg_match_all('/(?:^|\s)@([a-zA-Z0-9._-]+)/u', $texto, $matches)) {
        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }
    return [];
}


/**
 * Dado un texto con menciones, busca a los usuarios reales y retorna sus IDs.
 */
function resolver_menciones(string $texto): array {
    $logins = extraer_menciones($texto);
    if (empty($logins)) return [];

    $placeholders = [];
    $params = [];
    foreach ($logins as $i => $login) {
        $placeholders[] = ":u$i";
        $params["u$i"] = $login;
    }

    $rows = db_all(
        "SELECT id, usuario, nombre_completo
         FROM usuarios
         WHERE activo = 1 AND LOWER(usuario) IN (" . implode(',', $placeholders) . ")",
        $params
    );

    return $rows;
}


/**
 * Notifica a los usuarios mencionados en un texto.
 * No notifica al autor (sería raro mencionarse a sí mismo y recibir la notificación).
 */
function notificar_menciones(string $texto, int $autor_id, string $titulo_notif, string $url, string $entidad, int $entidad_id): int {
    $usuarios = resolver_menciones($texto);
    $notificados = 0;

    foreach ($usuarios as $u) {
        $uid = (int) $u['id'];
        if ($uid === $autor_id) continue;

        $ok = crear_notificacion(
            $uid,
            'mencion',
            $titulo_notif,
            mb_substr($texto, 0, 150),
            $url,
            $entidad,
            $entidad_id
        );
        if ($ok) $notificados++;
    }

    return $notificados;
}


/**
 * Convierte las menciones @usuario en HTML clickeable (para mostrar comentarios).
 * Las menciones de usuarios existentes se colorean; las que no, se dejan como texto plano.
 */
function renderizar_menciones(string $texto): string {
    $texto_esc = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');

    return preg_replace_callback(
        '/(^|\s)@([a-zA-Z0-9._-]+)/u',
        function ($m) {
            $login = $m[2];
            // Verificar si el usuario existe (consulta cache estática para no spamear)
            static $cache = [];
            $login_lower = strtolower($login);
            if (!isset($cache[$login_lower])) {
                $u = db_one("SELECT id, nombre_completo FROM usuarios WHERE LOWER(usuario) = :u LIMIT 1",
                    ['u' => $login_lower]);
                $cache[$login_lower] = $u ?: false;
            }
            if ($cache[$login_lower]) {
                return $m[1] . '<span class="inline-flex items-center px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 font-semibold text-xs" title="' .
                       htmlspecialchars($cache[$login_lower]['nombre_completo'], ENT_QUOTES) . '">@' . $login . '</span>';
            }
            return $m[1] . '<span class="text-zinc-500">@' . $login . '</span>';
        },
        $texto_esc
    );
}


// ============================================================================
// REACCIONES A COMENTARIOS
// ============================================================================

/**
 * Toggle de reacción: si ya existe, la quita; si no, la agrega.
 * Retorna ['estado' => 'agregada'|'eliminada', 'nuevo_total' => N]
 */
function toggle_reaccion_comentario(int $comentario_id, int $usuario_id, string $emoji): array {
    // Validar emoji permitido
    if (!in_array($emoji, EMOJIS_REACCION, true)) {
        return ['estado' => 'error', 'mensaje' => 'Emoji no permitido'];
    }

    // Verificar si ya existe
    $existe = db_one(
        "SELECT id FROM comentario_reacciones
         WHERE comentario_id = :cid AND usuario_id = :uid AND emoji = :e",
        ['cid' => $comentario_id, 'uid' => $usuario_id, 'e' => $emoji]
    );

    if ($existe) {
        db_exec("DELETE FROM comentario_reacciones WHERE id = :id", ['id' => $existe['id']]);
        $estado = 'eliminada';
    } else {
        db_exec(
            "INSERT INTO comentario_reacciones (comentario_id, usuario_id, emoji)
             VALUES (:cid, :uid, :e)",
            ['cid' => $comentario_id, 'uid' => $usuario_id, 'e' => $emoji]
        );
        $estado = 'agregada';
    }

    // Contar total
    $total = db_one(
        "SELECT COUNT(*) c FROM comentario_reacciones
         WHERE comentario_id = :cid AND emoji = :e",
        ['cid' => $comentario_id, 'e' => $emoji]
    );

    return [
        'estado' => $estado,
        'nuevo_total' => (int) ($total['c'] ?? 0),
        'emoji' => $emoji,
    ];
}
