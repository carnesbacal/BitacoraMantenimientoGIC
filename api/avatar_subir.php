<?php
/**
 * ============================================================================
 * api/avatar_subir.php
 * ============================================================================
 * Recibe una imagen via POST, valida tipo MIME real, recorta a cuadrado
 * centrado, redimensiona a 400x400 y guarda en assets/avatares/.
 *
 * Devuelve JSON: { ok: true, url: '...' } o { ok: false, error: '...' }
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

requerir_login();

header('Content-Type: application/json; charset=utf-8');

// ----------------------------------------------------------------------------
// Validar CSRF y método
// ----------------------------------------------------------------------------
if (!es_post() || !csrf_valido(input('_csrf'))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido']);
    exit;
}

$u = usuario_actual();

// ----------------------------------------------------------------------------
// El admin puede subir avatar de otros usuarios pasando ?usuario_id=N
// El usuario común solo puede subir el suyo
// ----------------------------------------------------------------------------
$usuario_id_target = (int) input('usuario_id', 0);
if ($usuario_id_target > 0 && $usuario_id_target !== (int) $u['id']) {
    if (!tiene_permiso('administrar')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sin permiso para modificar avatar ajeno']);
        exit;
    }
} else {
    $usuario_id_target = (int) $u['id'];
}

// ----------------------------------------------------------------------------
// Validar archivo
// ----------------------------------------------------------------------------
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $errores_upload = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamaño máximo del servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño máximo permitido.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'No se recibió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'No hay carpeta temporal disponible en el servidor.',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir en disco.',
    ];
    $cod = $_FILES['avatar']['error'] ?? 0;
    echo json_encode(['ok' => false, 'error' => $errores_upload[$cod] ?? 'Error al subir el archivo']);
    exit;
}

$archivo = $_FILES['avatar'];

// Tamaño máximo: 5 MB
if ($archivo['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'La imagen no debe exceder 5 MB']);
    exit;
}

// Validar MIME real
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $archivo['tmp_name']);
finfo_close($finfo);

$mimes_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $mimes_permitidos, true)) {
    echo json_encode(['ok' => false, 'error' => 'Solo se permiten imágenes JPG, PNG o WebP. Tipo detectado: ' . $mime]);
    exit;
}

// ----------------------------------------------------------------------------
// Cargar la imagen en GD
// ----------------------------------------------------------------------------
if (!function_exists('imagecreatefromjpeg')) {
    echo json_encode(['ok' => false, 'error' => 'Extensión GD no disponible en el servidor. Pide al administrador habilitarla en php.ini.']);
    exit;
}

try {
    $origen = null;
    switch ($mime) {
        case 'image/jpeg':
            $origen = imagecreatefromjpeg($archivo['tmp_name']);
            break;
        case 'image/png':
            $origen = imagecreatefrompng($archivo['tmp_name']);
            break;
        case 'image/webp':
            $origen = imagecreatefromwebp($archivo['tmp_name']);
            break;
    }
    if (!$origen) {
        echo json_encode(['ok' => false, 'error' => 'No se pudo procesar la imagen. ¿Está corrupta?']);
        exit;
    }

    // Dimensiones originales
    $ancho_orig = imagesx($origen);
    $alto_orig  = imagesy($origen);

    // Recorte cuadrado centrado (toma el lado más pequeño)
    $lado = min($ancho_orig, $alto_orig);
    $crop_x = (int) (($ancho_orig - $lado) / 2);
    $crop_y = (int) (($alto_orig - $lado) / 2);

    // Imagen de destino: 400x400 PNG
    $destino = imagecreatetruecolor(400, 400);

    // Si era PNG con transparencia, preservarla
    imagealphablending($destino, false);
    imagesavealpha($destino, true);
    $transparente = imagecolorallocatealpha($destino, 255, 255, 255, 127);
    imagefill($destino, 0, 0, $transparente);

    // Hacer el recorte + resize
    imagecopyresampled(
        $destino, $origen,
        0, 0, $crop_x, $crop_y,
        400, 400, $lado, $lado
    );

    imagedestroy($origen);

    // ----------------------------------------------------------------------------
    // Guardar al disco
    // ----------------------------------------------------------------------------
    $dir_destino = __DIR__ . '/../assets/avatares';
    if (!is_dir($dir_destino)) {
        if (!@mkdir($dir_destino, 0755, true)) {
            imagedestroy($destino);
            echo json_encode(['ok' => false, 'error' => 'No se pudo crear la carpeta de avatares.']);
            exit;
        }
    }

    // Nombre único por usuario (con sufijo aleatorio para forzar refresco de caché)
    $sufijo = bin2hex(random_bytes(4));
    $nombre_archivo = 'usuario_' . $usuario_id_target . '_' . $sufijo . '.png';
    $ruta_disco = $dir_destino . '/' . $nombre_archivo;
    $ruta_db    = 'assets/avatares/' . $nombre_archivo;

    if (!imagepng($destino, $ruta_disco, 8)) {
        imagedestroy($destino);
        echo json_encode(['ok' => false, 'error' => 'No se pudo escribir la imagen procesada.']);
        exit;
    }
    imagedestroy($destino);

    // ----------------------------------------------------------------------------
    // Eliminar avatar anterior (si existía)
    // ----------------------------------------------------------------------------
    $anterior = db_one("SELECT avatar_url FROM usuarios WHERE id = :id", ['id' => $usuario_id_target]);
    if ($anterior && !empty($anterior['avatar_url'])) {
        $ruta_anterior = __DIR__ . '/../' . $anterior['avatar_url'];
        if (file_exists($ruta_anterior) && strpos($anterior['avatar_url'], 'assets/avatares/') === 0) {
            @unlink($ruta_anterior);
        }
    }

    // ----------------------------------------------------------------------------
    // Actualizar BD
    // ----------------------------------------------------------------------------
    db_exec("UPDATE usuarios SET avatar_url = :url WHERE id = :id",
        ['url' => $ruta_db, 'id' => $usuario_id_target]);

    // Si es el avatar del usuario actual, actualizar la sesión
    if ($usuario_id_target === (int) $u['id']) {
        $_SESSION['usuario']['avatar_url'] = $ruta_db;
    }

    registrar_auditoria(
        'subir_avatar',
        'usuarios',
        $usuario_id_target,
        $usuario_id_target === (int) $u['id']
            ? 'Subió su foto de perfil'
            : "Subió avatar para usuario $usuario_id_target"
    );

    echo json_encode([
        'ok' => true,
        'url' => url($ruta_db),
        'ruta_db' => $ruta_db,
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Error procesando imagen: ' . $e->getMessage()]);
}
