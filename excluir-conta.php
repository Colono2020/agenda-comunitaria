<?php
// Exclusão da própria conta (com confirmação pela senha)
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $st = db()->prepare('SELECT senha_hash, telefone FROM usuarios WHERE id = ?');
    $st->execute([$usuario['id']]);
    $u = $st->fetch();

    if ($u && password_verify($_POST['senha'] ?? '', $u['senha_hash'])) {
        // Sai das comunidades (passando a administração adiante), apaga as publicações e a conta
        excluir_usuario($usuario['id']);

        // Logout: limpa a sessão e troca o ID, mantendo só o aviso para a página de login
        $_SESSION = [];
        session_regenerate_id(true);
        avisar('Sua conta foi excluída.');
        header('Location: login.php');
        exit;
    }

    if (!$u) {
        // Conta já não existe (ex.: excluída em outra aba)
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: login.php');
        exit;
    }
    $erro = 'Senha incorreta. Nada foi excluído.';
}

$titulo = 'Excluir minha conta';
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao formulario">
    <h1>Excluir minha conta</h1>

    <div class="flash flash-erro">
        Sua conta e <b>todas as suas publicações</b> serão apagadas para sempre.
        Isso não pode ser desfeito.
    </div>
    <p class="sub">Nas comunidades que você administra, a administração passa para o membro mais antigo.
        Se você for o único membro, a comunidade também será apagada.</p>

    <?php if ($erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <label>Digite sua senha para confirmar
            <input type="password" name="senha" required autocomplete="current-password" autofocus>
        </label>
        <button type="submit" class="botao perigo-forte">Sim, excluir minha conta</button>
    </form>
    <p class="centro"><a href="conta.php">Cancelar</a></p>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
