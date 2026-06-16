<?php
/**
 * ============================================================================
 * api/equipo_fotos_subir.php
 * ============================================================================
 * Recibe múltiples fotos para la galería de un equipo.
 * Valida MIME real, redimensiona si excede 1600px, guarda en assets/equipos/.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/equipos_helpers.php';

requerir_login();

header('Content-Type: application/json; charset=utf-8');

if (!es_post() || !csrf_valido(input('_csrf'))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido']);
    exit;
}

if (!puede_administrar_equipos()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso para subir fotos']);
    exit;
}

$u = usuario_actual();
$equipo_id = (int) input('equipo_id', 0);

$equipo = db_one("SELECT id FROM equipos WHERE id = :id", ['id' => $equipo_id]);
if (!$equipo) {
    echo json_encode(['ok' => false, 'error' => 'Equipo no encontrado']);
    exit;
}

// Verificar límite global
$actuales = db_one("SELECT COUNT(*) c FROM equipo_fotos WHERE equipo_id = :id", ['id' => $equipo_id]);
$ya_tiene = (int) ($actuales['c'] ?? 0);

if (!isset($_FILES['fotos']) || empty($_FILES['fotos']['name'][0])) {
    echo json_encode(['ok' => false, 'error' => 'No se recibieron archivos']);
    exit;
}

$total_recibidos = count($_FILES['fotos']['name']);
if ($ya_tiene + $total_recibidos > 20) {
    echo json_encode(['ok' => false, 'error' => 'Excedes el máximo de 20 fotos por equipo']);
    exit;
}

// Crear carpeta destino
$dir_destino = __DIR__ . '/../assets/equipos/' . $equipo_id;
if (!is_dir($dir_destino)) {
    if (!@mkdir($dir_destino, 0755, true)) {
        echo json_encode(['ok' => false, 'error' => 'No se pudo crear la carpeta de destino']);
        exit;
    }
}

$mimes_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
$exitosos = 0;
$errores = [];

for ($i = 0; $i < $total_recibidos; $i++) {
    $nombre_archivo_orig = $_FILES['fotos']['name'][$i];
    $tmp_path = $_FILES['fotos']['tmp_name'][$i];
    $err = $_FILES['fotos']['error'][$i];
    $size = $_FILES['fotos']['size'][$i];

    if ($err !== UPLOAD_ERR_OK) {
        $errores[] = "$nombre_archivo_orig: error de subida (código $err)";
        continue;
    }
    if ($size > 5 * 1024 * 1024) {
        $errores[] = "$nombre_archivo_orig: excede 5 MB";
        continue;
    }

    // Validar MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp_path);
    finfo_close($finfo);

    if (!in_array($mime, $mimes_permitidos, true)) {
        $errores[] = "$nombre_archivo_orig: tipo no permitido ($mime)";
        continue;
    }

    if (!function_exists('imagecreatefromjpeg')) {
        $errores[] = 'Extensión GD no disponible';
        break;
    }

    // Cargar imagen
    try {
        switch ($mime) {
            case 'image/jpeg': $img = imagecreatefromjpeg($tmp_path); break;
            case 'image/png':  $img = imagecreatefrompng($tmp_path); break;
            case 'image/webp': $img = imagecreatefromwebp($tmp_path); break;
            default: $img = null;
        }
        if (!$img) {
            $errores[] = "$nombre_archivo_orig: no se pudo procesar";
            continue;
        }

        $w = imagesx($img);
        $h = imagesy($img);

        // Redimensionar si excede 1600px en algún lado
        $max = 1600;
        if ($w > $max || $h > $max) {
            $ratio = min($max / $w, $max / $h);
            $new_w = (int) ($w * $ratio);
            $new_h = (int) ($h * $ratio);

            $img_nueva = imagecreatetruecolor($new_w, $new_h);
            if ($mime === 'image/png') {
                imagealphablending($img_nueva, false);
                imagesavealpha($img_nueva, true);
            }
            imagecopyresampled($img_nueva, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);
            imagedestroy($img);
            $img = $img_nueva;
        }

        // Guardar
        $sufijo = bin2hex(random_bytes(4));
        $timestamp = date('Ymd_His');
        $nombre_final = "eq{$equipo_id}_{$timestamp}_{$sufijo}.jpg";
        $ruta_disco = $dir_destino . '/' . $nombre_final;
        $ruta_db = 'assets/equipos/' . $equipo_id . '/' . $nombre_final;

        // Todas se guardan como JPG (estándar para galerías)
        if (!imagejpeg($img, $ruta_disco, 85)) {
            $errores[] = "$nombre_archivo_orig: no se pudo escribir en disco";
            imagedestroy($img);
            continue;
        }
        imagedestroy($img);

        // Si es la primera foto del equipo, marcarla como portada
        $es_portada = $ya_tiene === 0 && $exitosos === 0 ? 1 : 0;

        db_exec(
            "INSERT INTO equipo_fotos (equipo_id, ruta, es_portada, subido_por_id, tamano_bytes)
             VALUES (:eid, :r, :p, :uid, :t)",
            [
                'eid' => $equipo_id, 'r' => $ruta_db, 'p' => $es_portada,
                'uid' => $u['id'], 't' => filesize($ruta_disco),
            ]
        );
        $exitosos++;
    } catch (Throwable $e) {
        $errores[] = "$nombre_archivo_orig: " . $e->getMessage();
    }
}

if ($exitosos > 0) {
    registrar_auditoria('subir_fotos_equipo', 'equipos', $equipo_id,
        "Subió $exitosos foto(s) al equipo $equipo_id");
}

echo json_encode([
    'ok' => $exitosos > 0,
    'exitosos' => $exitosos,
    'errores' => $errores,
    'error' => $exitosos === 0 ? ('No se subió ninguna foto. ' . implode('; ', $errores)) : null,
]);
