<?php
/**
 * config/notificaciones_canales.php
 * Canales externos de notificación: Email (SMTP nativo) y Telegram (Bot API).
 * Sin composer, sin extensiones extra. Requiere: fsockopen, file_get_contents con HTTPS.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// ── Configuración global ──────────────────────────────────────────────────────

function _nc_config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    try {
        $cfg = db_one("SELECT * FROM configuracion_notificaciones WHERE id = 1") ?? [];
    } catch (Throwable $e) {
        $cfg = [];
    }
    return $cfg;
}

// ── Preferencias por usuario ──────────────────────────────────────────────────

function _nc_preferencias(int $usuario_id, string $tipo): array
{
    static $cache = [];
    $key = $usuario_id . ':' . $tipo;
    if (isset($cache[$key])) return $cache[$key];

    try {
        $row = db_one(
            "SELECT canal_email, canal_telegram FROM notificacion_preferencias WHERE usuario_id = :uid AND tipo = :tipo",
            ['uid' => $usuario_id, 'tipo' => $tipo]
        );
        if ($row) {
            $cache[$key] = $row;
            return $row;
        }
    } catch (Throwable $e) { /* tabla puede no existir aún */ }

    // Defaults: email=1 para asignacion y mencion, 0 para el resto
    $default_email = in_array($tipo, ['asignacion', 'mencion']) ? 1 : 0;
    $cache[$key] = ['canal_email' => $default_email, 'canal_telegram' => 0];
    return $cache[$key];
}

// ── Dispatcher principal ──────────────────────────────────────────────────────

function dispatch_notificacion(
    int $usuario_id,
    string $tipo,
    string $titulo,
    string $mensaje,
    ?string $url,
    ?int $notif_id
): void {
    if ($usuario_id <= 0) return;

    $cfg   = _nc_config();
    $prefs = _nc_preferencias($usuario_id, $tipo);

    // Datos del usuario (email + telegram_chat_id)
    $usuario = null;
    try {
        $usuario = db_one(
            "SELECT email, telegram_chat_id FROM usuarios WHERE id = :id AND activo = 1",
            ['id' => $usuario_id]
        );
    } catch (Throwable $e) { return; }
    if (!$usuario) return;

    // ── Email ────────────────────────────────────────────────────────────────
    if (!empty($cfg['smtp_activo']) && !empty($prefs['canal_email']) && !empty($usuario['email'])) {
        $res = _nc_enviar_email($cfg, $usuario['email'], $titulo, $mensaje, $url);
        _nc_log_envio($notif_id, $usuario_id, 'email', $tipo, $titulo, $res['ok'] ? 'ok' : 'error', $res['error'] ?? null);
    }

    // ── Telegram ─────────────────────────────────────────────────────────────
    if (!empty($cfg['telegram_activo']) && !empty($prefs['canal_telegram']) && !empty($usuario['telegram_chat_id']) && !empty($cfg['telegram_bot_token'])) {
        $res = _nc_enviar_telegram($cfg['telegram_bot_token'], $usuario['telegram_chat_id'], $titulo, $mensaje, $url);
        _nc_log_envio($notif_id, $usuario_id, 'telegram', $tipo, $titulo, $res['ok'] ? 'ok' : 'error', $res['error'] ?? null);
    }
}

// ── Email SMTP nativo ─────────────────────────────────────────────────────────

function _nc_enviar_email(array $cfg, string $dest_email, string $titulo, string $mensaje, ?string $url): array
{
    $host     = $cfg['smtp_host'] ?? '';
    $port     = (int) ($cfg['smtp_port'] ?? 587);
    $seg      = $cfg['smtp_seguridad'] ?? 'tls';
    $usuario  = $cfg['smtp_usuario'] ?? '';
    $password = $cfg['smtp_password'] ?? '';
    $from_email = $cfg['smtp_from_email'] ?? $usuario;
    $from_nombre = $cfg['smtp_from_nombre'] ?? 'Bitácora Mantenimiento';

    if (!$host || !$from_email) return ['ok' => false, 'error' => 'SMTP no configurado'];

    try {
        // Conectar
        $errno = 0; $errstr = '';
        $timeout = 15;
        if ($seg === 'ssl') {
            $socket = @fsockopen("ssl://$host", $port, $errno, $errstr, $timeout);
        } else {
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        }
        if (!$socket) return ['ok' => false, 'error' => "Conexión fallida: $errstr ($errno)"];

        $read = function() use ($socket) {
            $resp = '';
            while ($line = fgets($socket, 512)) {
                $resp .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $resp;
        };
        $send = function(string $cmd) use ($socket, $read) {
            fwrite($socket, $cmd . "\r\n");
            return $read();
        };

        $read(); // banner
        $send("EHLO " . gethostname());

        // STARTTLS para puerto 587
        if ($seg === 'tls') {
            $send("STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . gethostname());
        }

        // Autenticación
        if ($usuario && $password) {
            $send("AUTH LOGIN");
            $send(base64_encode($usuario));
            $auth_resp = $send(base64_encode($password));
            if (substr(trim($auth_resp), 0, 3) !== '235') {
                fclose($socket);
                return ['ok' => false, 'error' => 'Auth fallida: ' . trim($auth_resp)];
            }
        }

        $send("MAIL FROM:<$from_email>");
        $send("RCPT TO:<$dest_email>");
        $send("DATA");

        $html_body = _nc_email_html($titulo, $mensaje, $url, $from_nombre);
        $boundary  = '----=_Part_' . md5(uniqid());
        $subject_enc = '=?UTF-8?B?' . base64_encode($titulo) . '?=';
        $from_enc    = '=?UTF-8?B?' . base64_encode($from_nombre) . '?=';

        $headers  = "From: $from_enc <$from_email>\r\n";
        $headers .= "To: $dest_email\r\n";
        $headers .= "Subject: $subject_enc\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= strip_tags($mensaje) . ($url ? "\n\nVer: $url" : '') . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $html_body . "\r\n";
        $body .= "--$boundary--\r\n";

        $resp = $send($headers . "\r\n" . $body . "\r\n.");
        $send("QUIT");
        fclose($socket);

        $code = (int) substr(trim($resp), 0, 3);
        if ($code >= 200 && $code < 300) return ['ok' => true, 'error' => null];
        return ['ok' => false, 'error' => 'SMTP rechazó el mensaje: ' . trim($resp)];

    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ── Telegram Bot API ──────────────────────────────────────────────────────────

function _nc_enviar_telegram(string $bot_token, string $chat_id, string $titulo, string $mensaje, ?string $url): array
{
    if (!$bot_token || !$chat_id) return ['ok' => false, 'error' => 'Token o chat_id vacíos'];

    $text = "*" . preg_replace('/[_*\[\]()~`>#+\-=|{}.!\\\\]/', '\\\\$0', $titulo) . "*\n"
          . preg_replace('/[_*\[\]()~`>#+\-=|{}.!\\\\]/', '\\\\$0', $mensaje);

    if ($url) {
        $text .= "\n[Ver detalle](" . $url . ")";
    }

    $payload = json_encode([
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'MarkdownV2',
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
            'content' => $payload,
            'timeout' => 10,
            'ignore_errors' => true,
        ]
    ]);

    try {
        $resp = @file_get_contents(
            "https://api.telegram.org/bot{$bot_token}/sendMessage",
            false,
            $context
        );
        if ($resp === false) return ['ok' => false, 'error' => 'No se pudo conectar con Telegram'];
        $data = json_decode($resp, true);
        if (!empty($data['ok'])) return ['ok' => true, 'error' => null];
        return ['ok' => false, 'error' => $data['description'] ?? 'Error desconocido de Telegram'];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ── Prueba de canal ───────────────────────────────────────────────────────────

function nc_test_canal(string $canal, string $destino): array
{
    $cfg = _nc_config();
    if ($canal === 'email') {
        if (empty($cfg['smtp_activo'])) return ['ok' => false, 'error' => 'SMTP no está activado'];
        return _nc_enviar_email($cfg, $destino,
            'Prueba — Bitácora Mantenimiento',
            'Este es un mensaje de prueba del sistema de notificaciones.',
            null
        );
    }
    if ($canal === 'telegram') {
        if (empty($cfg['telegram_activo'])) return ['ok' => false, 'error' => 'Telegram no está activado'];
        if (empty($cfg['telegram_bot_token'])) return ['ok' => false, 'error' => 'Token de bot no configurado'];
        return _nc_enviar_telegram($cfg['telegram_bot_token'], $destino,
            'Prueba — Bitácora Mantenimiento',
            'Este es un mensaje de prueba del sistema de notificaciones.',
            null
        );
    }
    return ['ok' => false, 'error' => 'Canal desconocido'];
}

// ── Log de envíos ─────────────────────────────────────────────────────────────

function _nc_log_envio(?int $notif_id, int $usuario_id, string $canal, string $tipo, string $asunto, string $estado, ?string $error): void
{
    try {
        db_exec(
            "INSERT INTO notificacion_envios (notificacion_id,usuario_id,canal,tipo,asunto,estado,error_detalle)
             VALUES (:nid,:uid,:canal,:tipo,:asunto,:estado,:err)",
            ['nid'=>$notif_id,'uid'=>$usuario_id,'canal'=>$canal,'tipo'=>$tipo,
             'asunto'=>mb_substr($asunto,0,255),'estado'=>$estado,'err'=>$error]
        );
    } catch (Throwable $e) {
        error_log('_nc_log_envio fallido: ' . $e->getMessage());
    }
}

// ── HTML de email ─────────────────────────────────────────────────────────────

function _nc_email_html(string $titulo, string $mensaje, ?string $url, string $from_nombre): string
{
    $btn = $url
        ? '<p style="text-align:center;margin:24px 0"><a href="' . htmlspecialchars($url) . '" style="background:#92400e;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px">Ver detalle</a></p>'
        : '';

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f4f5;font-family:Inter,Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
  <tr><td style="background:#92400e;padding:24px 32px">
    <p style="color:#fff;font-size:18px;font-weight:700;margin:0">' . htmlspecialchars($from_nombre) . '</p>
  </td></tr>
  <tr><td style="padding:32px">
    <h2 style="font-size:20px;color:#18181b;margin:0 0 12px">' . htmlspecialchars($titulo) . '</h2>
    <p style="color:#52525b;font-size:15px;line-height:1.6;margin:0 0 16px">' . nl2br(htmlspecialchars($mensaje)) . '</p>
    ' . $btn . '
    <hr style="border:none;border-top:1px solid #e4e4e7;margin:24px 0">
    <p style="color:#a1a1aa;font-size:12px;margin:0">Esta notificación fue generada automáticamente por ' . htmlspecialchars($from_nombre) . '.</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>';
}
