<?php
/**
 * admin/notificaciones_config.php - Panel de configuración de canales de notificación
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/notificaciones_canales.php';
requerir_login();

if (!tiene_permiso('administrar')) {
    flash_set('error', 'Acceso denegado.');
    header('Location: ' . url('dashboard.php')); exit;
}

$u = usuario_actual();

// ── POST — ANTES del header para poder redirigir ──────────────────────────────
$resultado_prueba = null;
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        flash_set('error','Token inválido');
        header('Location:'.url('admin/notificaciones_config.php')); exit;
    }
    $op = (string) input('op','');

    if ($op === 'guardar') {
        $smtp_host     = trim((string) input('smtp_host','')) ?: null;
        $smtp_port     = (int) input('smtp_port', 587);
        $smtp_seg      = in_array(input('smtp_seguridad'),['tls','ssl','none']) ? input('smtp_seguridad') : 'tls';
        $smtp_user     = trim((string) input('smtp_usuario','')) ?: null;
        $smtp_pass     = (string) input('smtp_password','');
        $smtp_from     = trim((string) input('smtp_from_email','')) ?: null;
        $smtp_nombre   = trim((string) input('smtp_from_nombre','Bitácora Mantenimiento'));
        $smtp_activo   = input('smtp_activo') ? 1 : 0;
        $tg_token      = trim((string) input('telegram_bot_token','')) ?: null;
        $tg_activo     = input('telegram_activo') ? 1 : 0;

        // Si la contraseña viene vacía, conservar la actual
        $cfg_actual = db_one("SELECT smtp_password FROM configuracion_notificaciones WHERE id=1");
        if ($smtp_pass === '') $smtp_pass = $cfg_actual['smtp_password'] ?? null;

        db_exec(
            "UPDATE configuracion_notificaciones SET
             smtp_host=:h, smtp_port=:p, smtp_seguridad=:sg, smtp_usuario=:u,
             smtp_password=:pw, smtp_from_email=:fe, smtp_from_nombre=:fn,
             smtp_activo=:sa, telegram_bot_token=:tt, telegram_activo=:ta, actualizado_por=:ap
             WHERE id=1",
            ['h'=>$smtp_host,'p'=>$smtp_port,'sg'=>$smtp_seg,'u'=>$smtp_user,'pw'=>$smtp_pass,
             'fe'=>$smtp_from,'fn'=>$smtp_nombre,'sa'=>$smtp_activo,'tt'=>$tg_token,'ta'=>$tg_activo,'ap'=>$u['id']]
        );
        flash_set('success','Configuración guardada.');
        header('Location:'.url('admin/notificaciones_config.php')); exit;
    }

    if ($op === 'test_email') {
        $dest = trim((string) input('test_email_dest',''));
        $resultado_prueba = ['canal'=>'email', 'dest'=>$dest] + nc_test_canal('email', $dest);
    }
    if ($op === 'test_telegram') {
        $dest = trim((string) input('test_tg_chatid',''));
        $resultado_prueba = ['canal'=>'telegram', 'dest'=>$dest] + nc_test_canal('telegram', $dest);
    }
}

// ── Datos ─────────────────────────────────────────────────────────────────────
$cfg = db_one("SELECT * FROM configuracion_notificaciones WHERE id=1") ?? [];
$envios = db_all(
    "SELECT ne.*, u.nombre_completo usuario_nombre
     FROM notificacion_envios ne
     LEFT JOIN usuarios u ON u.id=ne.usuario_id
     ORDER BY ne.enviado_en DESC LIMIT 30"
);
$fm = flash_get();

$titulo_pagina = 'Configuración de Notificaciones';
$pagina_activa = 'admin_notificaciones_config';
require_once __DIR__ . '/../config/header.php';
?>

<div class="space-y-6 animate-fade-in max-w-4xl mx-auto">

<?php foreach ($fm as $f): ?>
<div class="px-4 py-3 rounded-lg text-sm <?= $f['tipo']==='success'?'bg-emerald-50 text-emerald-800 border border-emerald-200':'bg-red-50 text-red-800 border border-red-200' ?>">
    <?= e($f['mensaje']) ?>
</div>
<?php endforeach; ?>

<?php if ($resultado_prueba): ?>
<div class="px-4 py-3 rounded-lg text-sm <?= $resultado_prueba['ok']?'bg-emerald-50 text-emerald-800 border border-emerald-200':'bg-red-50 text-red-800 border border-red-200' ?>">
    <?php if ($resultado_prueba['ok']): ?>
    <i data-lucide="check-circle-2" class="w-4 h-4 inline -mt-0.5 mr-1"></i>
    Prueba de <?= e($resultado_prueba['canal']) ?> enviada correctamente a <strong><?= e($resultado_prueba['dest']) ?></strong>.
    <?php else: ?>
    <i data-lucide="x-circle" class="w-4 h-4 inline -mt-0.5 mr-1"></i>
    Error al enviar por <?= e($resultado_prueba['canal']) ?>: <?= e($resultado_prueba['error'] ?? 'Error desconocido') ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" class="space-y-6">
<?= csrf_input() ?>
<input type="hidden" name="op" value="guardar">

<!-- ── Sección SMTP ─────────────────────────────────────────────────────── -->
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-bold text-zinc-800 flex items-center gap-2">
            <i data-lucide="mail" class="w-4 h-4 text-zinc-500"></i> Email (SMTP)
        </h2>
        <label class="flex items-center gap-2 cursor-pointer">
            <span class="text-xs text-zinc-600">Activar</span>
            <input type="checkbox" name="smtp_activo" value="1" <?= !empty($cfg['smtp_activo'])?'checked':'' ?> class="w-4 h-4 rounded">
        </label>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2 md:col-span-1">
            <label class="block text-xs font-bold text-zinc-700 mb-1">Servidor SMTP</label>
            <input type="text" name="smtp_host" value="<?= e($cfg['smtp_host'] ?? '') ?>" placeholder="mail.granodeoro.com.mx"
                   class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            <p class="text-[10px] text-zinc-400 mt-1">cPanel: mail.tudominio.com.mx</p>
        </div>
        <div>
            <label class="block text-xs font-bold text-zinc-700 mb-1">Puerto</label>
            <input type="number" name="smtp_port" value="<?= (int)($cfg['smtp_port'] ?? 465) ?>"
                   class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
        </div>
        <div>
            <label class="block text-xs font-bold text-zinc-700 mb-1">Seguridad</label>
            <select name="smtp_seguridad" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                <option value="ssl"  <?= ($cfg['smtp_seguridad']??'ssl')==='ssl'?'selected':'' ?>>SSL (puerto 465 — cPanel)</option>
                <option value="tls"  <?= ($cfg['smtp_seguridad']??'')==='tls'?'selected':'' ?>>TLS / STARTTLS (puerto 587)</option>
                <option value="none" <?= ($cfg['smtp_seguridad']??'')==='none'?'selected':'' ?>>Sin cifrado</option>
            </select>
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-bold text-zinc-700 mb-1">Usuario (email de la cuenta SMTP)</label>
            <input type="email" name="smtp_usuario" value="<?= e($cfg['smtp_usuario'] ?? '') ?>"
                   class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-bold text-zinc-700 mb-1">Contraseña</label>
            <input type="password" name="smtp_password" placeholder="(dejar vacío para no cambiar)"
                   class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
        </div>
        <div>
            <label class="block text-xs font-bold text-zinc-700 mb-1">Email remitente (From)</label>
            <input type="email" name="smtp_from_email" value="<?= e($cfg['smtp_from_email'] ?? '') ?>"
                   class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
        </div>
        <div>
            <label class="block text-xs font-bold text-zinc-700 mb-1">Nombre remitente</label>
            <input type="text" name="smtp_from_nombre" value="<?= e($cfg['smtp_from_nombre'] ?? 'Bitácora Mantenimiento') ?>"
                   class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
        </div>
    </div>
    <div class="bg-zinc-50 rounded-lg px-4 py-3 text-xs text-zinc-600 border border-zinc-100">
        <strong>Configuración rápida cPanel:</strong> Servidor = mail.granodeoro.com.mx · Puerto = 465 · Seguridad = SSL
    </div>
</div>

<!-- ── Sección Telegram ─────────────────────────────────────────────────── -->
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-bold text-zinc-800 flex items-center gap-2">
            <i data-lucide="send" class="w-4 h-4 text-zinc-500"></i> Telegram
        </h2>
        <label class="flex items-center gap-2 cursor-pointer">
            <span class="text-xs text-zinc-600">Activar</span>
            <input type="checkbox" name="telegram_activo" value="1" <?= !empty($cfg['telegram_activo'])?'checked':'' ?> class="w-4 h-4 rounded">
        </label>
    </div>
    <div>
        <label class="block text-xs font-bold text-zinc-700 mb-1">Token del Bot</label>
        <input type="text" name="telegram_bot_token" value="<?= e($cfg['telegram_bot_token'] ?? '') ?>" placeholder="123456789:ABCdef..."
               class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm font-mono focus:outline-none focus:border-bacal-700">
    </div>
    <div class="bg-zinc-50 rounded-lg px-4 py-3 text-xs text-zinc-600 border border-zinc-100 space-y-1">
        <p class="font-bold text-zinc-700 mb-2">Cómo crear el bot en 6 pasos:</p>
        <p><span class="font-semibold">1.</span> Abre Telegram y busca <code class="bg-zinc-200 px-1 rounded">@BotFather</code></p>
        <p><span class="font-semibold">2.</span> Envía el comando <code class="bg-zinc-200 px-1 rounded">/newbot</code> y sigue las instrucciones</p>
        <p><span class="font-semibold">3.</span> BotFather te dará un token — cópialo y pégalo aquí</p>
        <p><span class="font-semibold">4.</span> Cada usuario debe iniciar conversación con el bot (<code class="bg-zinc-200 px-1 rounded">/start</code>) antes de poder recibir mensajes</p>
        <p><span class="font-semibold">5.</span> Para obtener el Chat ID, el usuario debe enviar un mensaje a <code class="bg-zinc-200 px-1 rounded">@userinfobot</code></p>
        <p><span class="font-semibold">6.</span> <em>Importante:</em> el usuario DEBE enviar un mensaje al bot antes del primer envío; sin ese mensaje inicial, Telegram bloqueará los envíos.</p>
    </div>
</div>

<div class="flex justify-end">
    <button type="submit" class="px-6 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
        Guardar configuración
    </button>
</div>
</form>

<!-- ── Pruebas ───────────────────────────────────────────────────────────── -->
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
    <h2 class="text-sm font-bold text-zinc-800">Enviar prueba</h2>
    <div class="grid md:grid-cols-2 gap-4">
        <form method="POST" class="flex gap-2 items-end">
            <?= csrf_input() ?><input type="hidden" name="op" value="test_email">
            <div class="flex-1">
                <label class="block text-xs font-bold text-zinc-700 mb-1">Email de destino</label>
                <input type="email" name="test_email_dest" placeholder="tu@correo.com" required
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-white text-sm">
                <i data-lucide="send" class="w-4 h-4 inline -mt-0.5"></i> Enviar
            </button>
        </form>
        <form method="POST" class="flex gap-2 items-end">
            <?= csrf_input() ?><input type="hidden" name="op" value="test_telegram">
            <div class="flex-1">
                <label class="block text-xs font-bold text-zinc-700 mb-1">Chat ID de Telegram</label>
                <input type="text" name="test_tg_chatid" placeholder="123456789" required
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm font-mono focus:outline-none focus:border-bacal-700">
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-white text-sm">
                <i data-lucide="send" class="w-4 h-4 inline -mt-0.5"></i> Enviar
            </button>
        </form>
    </div>
</div>

<!-- ── Log de envíos ─────────────────────────────────────────────────────── -->
<?php if (!empty($envios)): ?>
<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-zinc-100">
        <h2 class="text-sm font-bold text-zinc-800">Últimos 30 envíos externos</h2>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-zinc-50 border-b border-zinc-200">
            <tr class="text-left text-xs font-semibold text-zinc-500 uppercase tracking-wide">
                <th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Usuario</th>
                <th class="px-4 py-3">Canal</th><th class="px-4 py-3">Tipo</th>
                <th class="px-4 py-3">Estado</th><th class="px-4 py-3">Detalle</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            <?php foreach ($envios as $en): ?>
            <tr class="hover:bg-zinc-50">
                <td class="px-4 py-2.5 text-zinc-600 text-xs"><?= fmt_fecha($en['enviado_en']) ?></td>
                <td class="px-4 py-2.5 text-zinc-700"><?= e($en['usuario_nombre'] ?? '#'.$en['usuario_id']) ?></td>
                <td class="px-4 py-2.5">
                    <span class="text-xs px-2 py-0.5 rounded-full <?= $en['canal']==='email'?'bg-blue-100 text-blue-800':'bg-sky-100 text-sky-800' ?>">
                        <?= e($en['canal']) ?>
                    </span>
                </td>
                <td class="px-4 py-2.5 text-zinc-600 text-xs"><?= e($en['tipo'] ?? '—') ?></td>
                <td class="px-4 py-2.5">
                    <span class="text-xs px-2 py-0.5 rounded-full <?= $en['estado']==='ok'?'bg-emerald-100 text-emerald-800':'bg-red-100 text-red-800' ?>">
                        <?= e($en['estado']) ?>
                    </span>
                </td>
                <td class="px-4 py-2.5 text-xs text-zinc-500 max-w-xs truncate"><?= e($en['error_detalle'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
