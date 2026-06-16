<?php
/**
 * ============================================================================
 * api/usuarios_buscar.php
 * ============================================================================
 * Búsqueda de usuarios para autocompletado de menciones @usuario.
 * Recibe parámetro `q` (texto parcial del login) y devuelve hasta 8 coincidencias.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

$q = trim((string) input('q', ''));

if (mb_strlen($q) < 1) {
    echo json_encode(['usuarios' => []]);
    exit;
}

// Buscar por usuario (login) o nombre, case-insensitive
$usuarios = db_all(
    "SELECT id, usuario, nombre_completo, avatar_url
     FROM usuarios
     WHERE activo = 1
       AND (LOWER(usuario) LIKE :q1 OR LOWER(nombre_completo) LIKE :q2)
     ORDER BY
        CASE WHEN LOWER(usuario) LIKE :q3 THEN 0 ELSE 1 END,
        usuario ASC
     LIMIT 8",
    [
        'q1' => strtolower($q) . '%',
        'q2' => '%' . strtolower($q) . '%',
        'q3' => strtolower($q) . '%',
    ]
);

// Enriquecer con URL completa de avatar
foreach ($usuarios as &$u) {
    if (!empty($u['avatar_url'])) {
        $u['avatar_full_url'] = url($u['avatar_url']);
    }
    // Iniciales para placeholder
    $partes = explode(' ', $u['nombre_completo']);
    $u['iniciales'] = strtoupper(
        ($partes[0][0] ?? '') . ($partes[1][0] ?? '')
    );
}
unset($u);

echo json_encode(['usuarios' => $usuarios], JSON_UNESCAPED_UNICODE);
