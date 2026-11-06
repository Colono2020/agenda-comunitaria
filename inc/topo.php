<?php
// Cabeçalho comum. Defina $titulo antes de incluir.
$usuario = usuario_logado();
$pagina  = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2e7d32">
    <title><?= e($titulo ?? 'Agenda Comunitária') ?></title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" href="assets/icone.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icone-192.png" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <?php // ?v= muda quando o estilo.css muda: o navegador baixa a versão nova em vez de usar a guardada ?>
    <link rel="stylesheet" href="assets/estilo.css?v=<?= @filemtime(__DIR__ . '/../assets/estilo.css') ?: 1 ?>">
    <script>
    // Service worker (app instalável e página "Sem internet"). Caminho relativo: funciona em subpasta ou na raiz.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () { navigator.serviceWorker.register('sw.js'); });
    }
    </script>
</head>
<body>
<header class="topo">
    <a href="home.php" class="marca">🌱 Agenda Comunitária</a>
    <?php if ($usuario): ?>
        <a href="sair.php" class="sair">Sair</a>
    <?php endif; ?>
</header>
<main class="conteudo">
<?php if ($usuario && array_key_exists('email', $usuario) && $usuario['email'] === null): ?>
    <div class="flash flash-aviso aviso-email"><a href="conta.php#email">📧 Cadastre seu e-mail para poder recuperar sua conta.</a></div>
<?php endif; ?>
<?= mostrar_aviso() ?>
<?= mostrar_email_teste() ?>
