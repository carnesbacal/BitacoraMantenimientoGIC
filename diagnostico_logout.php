<?php
/**
 * diagnostico_logout.php - Herramienta temporal de diagnóstico
 *
 * USO:
 * 1. Cópialo a la raíz del proyecto (junto a login.php)
 * 2. Estando logueado como admin o gerente1, abre:
 *    http://localhost/UtilidadesBacal/BitacoraSistemas/diagnostico_logout.php
 * 3. Comparte conmigo la información que muestre
 * 4. ELIMÍNALO después de usar
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html><head>
<title>Diagnóstico Logout</title>
<style>
body { font-family: ui-monospace, monospace; padding: 20px; background: #18181b; color: #e4e4e7; max-width: 1000px; margin: auto; line-height: 1.6; }
h1 { color: #f87171; border-bottom: 2px solid #f87171; padding-bottom: 10px; }
h2 { color: #fbbf24; margin-top: 30px; border-bottom: 1px solid #3f3f46; padding-bottom: 5px; }
.box { background: #27272a; border-left: 4px solid #6b7280; padding: 15px; margin: 10px 0; border-radius: 4px; }
.ok { border-color: #22c55e; } .ok h3 { color: #22c55e; }
.err { border-color: #ef4444; } .err h3 { color: #ef4444; }
.warn { border-color: #fbbf24; } .warn h3 { color: #fbbf24; }
.info { border-color: #3b82f6; } .info h3 { color: #60a5fa; }
pre { background: #18181b; padding: 12px; border-radius: 4px; overflow: auto; font-size: 12px; }
.tag { display: inline-block; padding: 2px 8px; background: #3f3f46; border-radius: 4px; font-size: 11px; margin-right: 5px; }
a.btn { display: inline-block; padding: 10px 20px; background: #C8102E; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 10px 5px 0 0; }
a.btn:hover { background: #991B1B; }
</style></head><body>
<h1>🔍 Diagnóstico del sistema de logout</h1>

<?php
// ============================================================
// 1. Estado de la sesión PHP
// ============================================================
?>
<h2>1. Estado de la sesión PHP</h2>
<div class="box info">
<h3>session_status</h3>
<?php
$estados = [PHP_SESSION_DISABLED => 'DISABLED', PHP_SESSION_NONE => 'NONE', PHP_SESSION_ACTIVE => 'ACTIVE'];
echo "Estado: <strong>" . $estados[session_status()] . "</strong><br>";
echo "session_id(): <code>" . session_id() . "</code><br>";
echo "session_name(): <code>" . session_name() . "</code><br>";
echo "save_path: <code>" . ini_get('session.save_path') . "</code><br>";
?>
</div>

<?php
// ============================================================
// 2. Contenido completo de $_SESSION
// ============================================================
?>
<h2>2. Contenido de $_SESSION</h2>
<div class="box">
<pre><?php print_r($_SESSION); ?></pre>
</div>

<?php
// ============================================================
// 3. Verificación de auth.php
// ============================================================
?>
<h2>3. Verificación de funciones de auth</h2>
<?php
$logueado = esta_logueado();
$u = usuario_actual();
?>
<div class="box <?= $logueado ? 'ok' : 'err' ?>">
<h3>esta_logueado(): <?= $logueado ? '✓ TRUE' : '✗ FALSE' ?></h3>
<?php if ($u): ?>
<strong>Usuario actual:</strong>
<pre><?php print_r($u); ?></pre>
<?php else: ?>
<p>No hay usuario actual cargado.</p>
<?php endif; ?>
</div>

<?php
// ============================================================
// 4. Verificación de constantes y configuración
// ============================================================
?>
<h2>4. Configuración</h2>
<div class="box info">
<strong>APP_URL:</strong> <code><?= APP_URL ?></code><br>
<strong>SESSION_VERSION:</strong> <code><?= defined('SESSION_VERSION') ? SESSION_VERSION : 'NO DEFINIDA' ?></code><br>
<strong>session.cookie_path:</strong> <code><?= ini_get('session.cookie_path') ?></code><br>
<strong>session.cookie_domain:</strong> <code><?= ini_get('session.cookie_domain') ?: '(vacío)' ?></code><br>
<strong>session.cookie_httponly:</strong> <code><?= ini_get('session.cookie_httponly') ?></code><br>
<strong>session.use_strict_mode:</strong> <code><?= ini_get('session.use_strict_mode') ?></code><br>
</div>

<?php
// ============================================================
// 5. Verificar que logout.php existe y es legible
// ============================================================
?>
<h2>5. Archivo logout.php</h2>
<?php
$logout_path = __DIR__ . '/logout.php';
$existe = file_exists($logout_path);
$leible = $existe && is_readable($logout_path);
$tamano = $existe ? filesize($logout_path) : 0;
?>
<div class="box <?= ($existe && $leible) ? 'ok' : 'err' ?>">
<h3>logout.php</h3>
<strong>Existe:</strong> <?= $existe ? '✓ Sí' : '✗ NO' ?><br>
<strong>Legible:</strong> <?= $leible ? '✓ Sí' : '✗ NO' ?><br>
<strong>Tamaño:</strong> <?= $tamano ?> bytes<br>
<strong>Ruta:</strong> <code><?= e($logout_path) ?></code><br>
<?php if ($existe): ?>
<br><strong>Contenido:</strong>
<pre><?php echo e(file_get_contents($logout_path)); ?></pre>
<?php endif; ?>
</div>

<?php
// ============================================================
// 6. URL que generaría el botón de logout
// ============================================================
?>
<h2>6. URL del botón logout</h2>
<div class="box info">
<strong>url('logout.php') devuelve:</strong><br>
<code><?= e(url('logout.php')) ?></code><br><br>

<strong>Tu URL actual:</strong><br>
<code><?= e(($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?></code>
</div>

<?php
// ============================================================
// 7. Cookies del navegador
// ============================================================
?>
<h2>7. Cookies que envía tu navegador</h2>
<div class="box">
<pre><?php print_r($_COOKIE); ?></pre>
</div>

<?php
// ============================================================
// 8. Pruebas en vivo
// ============================================================
?>
<h2>8. Pruebas en vivo</h2>

<div class="box warn">
<h3>Probar logout.php directamente</h3>
<p>Al hacer clic, deberías ser redirigido al login. Si te muestra una página en blanco o un error, ese es el problema.</p>
<a class="btn" href="<?= url('logout.php') ?>">Ir a logout.php</a>
<a class="btn" href="<?= url('login.php') ?>" style="background: #6b7280">Ir a login.php</a>
</div>

<div class="box info">
<h3>Comparte conmigo esta info</h3>
<p>Toma una captura completa de esta página y compártela. Especialmente las secciones <strong>2</strong>, <strong>3</strong>, <strong>5</strong> y <strong>6</strong>.</p>
<p><strong>⚠️ ELIMINA este archivo (<code>diagnostico_logout.php</code>) cuando termines.</strong></p>
</div>

</body></html>
