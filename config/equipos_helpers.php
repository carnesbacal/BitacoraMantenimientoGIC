<?php
/**
 * ============================================================================
 * config/equipos_helpers.php
 * ============================================================================
 * Funciones de utilidad para gestión de equipos:
 *   - Cálculo de depreciación lineal
 *   - Días/meses transcurridos desde compra
 *   - Estados de vida y badges
 *   - Detección de equipos próximos a cumplir años
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ============================================================================
// Estados de vida
// ============================================================================

const EQUIPO_ESTADOS_VIDA = [
    'nuevo'          => ['nombre' => 'Nuevo',           'color' => '#16A34A', 'icono' => 'sparkles'],
    'en_uso'         => ['nombre' => 'En uso',          'color' => '#0EA5E9', 'icono' => 'check-circle-2'],
    'en_reparacion'  => ['nombre' => 'En reparación',   'color' => '#D97706', 'icono' => 'wrench'],
    'dado_de_baja'   => ['nombre' => 'Dado de baja',    'color' => '#6B7280', 'icono' => 'archive'],
];

function badge_estado_vida(string $estado): string {
    $cfg = EQUIPO_ESTADOS_VIDA[$estado] ?? EQUIPO_ESTADOS_VIDA['en_uso'];
    return sprintf(
        '<span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded" style="background-color: %s15; color: %s">' .
        '<i data-lucide="%s" class="w-3 h-3"></i> %s</span>',
        htmlspecialchars($cfg['color'], ENT_QUOTES),
        htmlspecialchars($cfg['color'], ENT_QUOTES),
        htmlspecialchars($cfg['icono'], ENT_QUOTES),
        htmlspecialchars($cfg['nombre'], ENT_QUOTES)
    );
}


// ============================================================================
// Cálculo de depreciación
// ============================================================================

/**
 * Calcula depreciación lineal del equipo.
 * Retorna array con detalles o null si no hay datos suficientes.
 *
 * Modelo: depreciación lineal con valor de rescate 10%.
 *   - Si tiene fecha_compra Y costo_compra Y vida_util_meses → calcula completo
 *   - Si falta alguno → retorna null
 */
function calcular_depreciacion(array $equipo): ?array {
    $costo = $equipo['costo_compra'] ?? null;
    $fecha = $equipo['fecha_compra'] ?? null;
    $vida_meses = $equipo['vida_util_meses'] ?? null;

    if (!$costo || !$fecha || !$vida_meses || $costo <= 0 || $vida_meses <= 0) {
        return null;
    }

    $costo = (float) $costo;
    $vida_meses = (int) $vida_meses;
    $valor_rescate = $costo * 0.10; // 10% de valor residual

    // Meses transcurridos desde la compra
    $hoy = new DateTime();
    $compra = new DateTime($fecha);
    $diff = $compra->diff($hoy);
    $meses_transcurridos = ($diff->y * 12) + $diff->m;
    if ($diff->d >= 15) $meses_transcurridos++; // redondeo

    // Si ya pasó la vida útil, queda en el valor de rescate
    if ($meses_transcurridos >= $vida_meses) {
        return [
            'costo_compra' => $costo,
            'valor_actual' => $valor_rescate,
            'depreciacion_total' => $costo - $valor_rescate,
            'porcentaje_depreciado' => 100,
            'meses_transcurridos' => $meses_transcurridos,
            'meses_vida_util' => $vida_meses,
            'meses_restantes' => 0,
            'porcentaje_vida_usada' => 100,
            'agotado' => true,
        ];
    }

    $depreciacion_mensual = ($costo - $valor_rescate) / $vida_meses;
    $depreciacion_total = $depreciacion_mensual * $meses_transcurridos;
    $valor_actual = $costo - $depreciacion_total;
    $porcentaje_depreciado = ($depreciacion_total / ($costo - $valor_rescate)) * 100;
    $porcentaje_vida_usada = ($meses_transcurridos / $vida_meses) * 100;

    return [
        'costo_compra' => $costo,
        'valor_actual' => $valor_actual,
        'depreciacion_total' => $depreciacion_total,
        'porcentaje_depreciado' => round($porcentaje_depreciado, 1),
        'meses_transcurridos' => $meses_transcurridos,
        'meses_vida_util' => $vida_meses,
        'meses_restantes' => $vida_meses - $meses_transcurridos,
        'porcentaje_vida_usada' => round($porcentaje_vida_usada, 1),
        'agotado' => false,
    ];
}


/**
 * Texto humano para "X años Y meses".
 */
function fmt_meses_humano(int $meses): string {
    if ($meses < 1) return 'menos de 1 mes';
    if ($meses === 1) return '1 mes';
    if ($meses < 12) return "$meses meses";

    $anios = (int) floor($meses / 12);
    $meses_restantes = $meses % 12;

    $partes = [];
    $partes[] = $anios === 1 ? '1 año' : "$anios años";
    if ($meses_restantes > 0) {
        $partes[] = $meses_restantes === 1 ? '1 mes' : "$meses_restantes meses";
    }
    return implode(' y ', $partes);
}


// ============================================================================
// Equipos que cumplen aniversario este mes
// ============================================================================

/**
 * Lista equipos que cumplen X años este mes (alertas tipo "este equipo cumple 5 años").
 */
function equipos_aniversario_mes(): array {
    $mes_actual = (int) date('n');
    return db_all(
        "SELECT e.id, e.codigo_inventario, e.nombre, e.fecha_compra,
                e.vida_util_meses, s.nombre sucursal_nombre,
                TIMESTAMPDIFF(YEAR, e.fecha_compra, CURDATE()) AS anios
         FROM equipos e
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         WHERE e.activo = 1
           AND e.fecha_compra IS NOT NULL
           AND MONTH(e.fecha_compra) = :mes
           AND TIMESTAMPDIFF(YEAR, e.fecha_compra, CURDATE()) >= 1
         ORDER BY anios DESC, e.codigo_inventario
         LIMIT 20",
        ['mes' => $mes_actual]
    );
}


/**
 * Equipos próximos a agotar su vida útil (>= 80%).
 */
function equipos_vida_util_critica(): array {
    return db_all(
        "SELECT e.id, e.codigo_inventario, e.nombre, e.fecha_compra,
                e.vida_util_meses, s.nombre sucursal_nombre,
                TIMESTAMPDIFF(MONTH, e.fecha_compra, CURDATE()) AS meses_uso
         FROM equipos e
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         WHERE e.activo = 1
           AND e.estado_vida IN ('nuevo','en_uso')
           AND e.fecha_compra IS NOT NULL
           AND e.vida_util_meses IS NOT NULL
           AND e.vida_util_meses > 0
           AND TIMESTAMPDIFF(MONTH, e.fecha_compra, CURDATE()) >= (e.vida_util_meses * 0.8)
         ORDER BY (TIMESTAMPDIFF(MONTH, e.fecha_compra, CURDATE()) / e.vida_util_meses) DESC
         LIMIT 10"
    );
}


// ============================================================================
// Permisos contextuales para equipos
// ============================================================================

/**
 * ¿Puede el usuario editar/administrar equipos?
 * Admin + ingenieros (puede_resolver)
 */
function puede_administrar_equipos(): bool {
    return tiene_permiso('administrar') || tiene_permiso('resolver');
}


/**
 * ¿Puede el usuario ver el detalle de este equipo?
 * Todos los logueados pueden ver. Si el usuario es gerente/jefe restringido a su sucursal,
 * verificamos coincidencia.
 */
function puede_ver_equipo(array $equipo): bool {
    if (!esta_logueado()) return false;

    // Admin e ingenieros ven todos
    if (tiene_permiso('ver_todas_sucursales') || tiene_permiso('administrar')) return true;

    // Gerentes/jefes: solo equipos de su sucursal
    $u = usuario_actual();
    if ($u['sucursal_id'] && (int) $u['sucursal_id'] === (int) $equipo['sucursal_id']) return true;

    return false;
}
