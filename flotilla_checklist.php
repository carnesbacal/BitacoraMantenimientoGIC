<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
requerir_login();
$titulo_pagina = 'Flotilla — Checklist';
$pagina_activa = 'flotilla_checklist';
require_once __DIR__ . '/config/header.php';
require_once __DIR__ . '/config/flotilla_nav.php';
$fm = flash_get();
?>
<div class="space-y-4 animate-fade-in">
<?php foreach ($fm as $f): ?><div class="px-4 py-3 rounded-lg text-sm <?= $f['tipo']==='success'?'bg-emerald-50 text-emerald-800 border border-emerald-200':'bg-red-50 text-red-800 border border-red-200' ?>"><?= e($f['mensaje']) ?></div><?php endforeach; ?>
<h1 class="text-xl font-display font-bold text-zinc-900">Checklist</h1>
<div class="text-center py-20 text-zinc-400">
    <i data-lucide="clipboard-check" class="w-14 h-14 mx-auto mb-3 opacity-25"></i>
    <p class="text-base font-medium text-zinc-500">Módulo Checklist</p>
    <p class="text-sm mt-1">Próximamente disponible.</p>
</div>
</div>
<?php require_once __DIR__ . '/config/footer.php'; ?>
