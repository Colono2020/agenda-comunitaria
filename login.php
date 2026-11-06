<?php
require_once __DIR__ . '/inc/funcoes.php';

if (usuario_logado()) {
    header('Location: home.php');
    exit;
}

$erro = '';
$telefone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $telefone = limpar_telefone($_POST['telefone'] ?? '');
    $senha    = $_POST['senha'] ?? '';

    $st = db()->prepare('SELECT * FROM usuarios WHERE telefone = ?');
    $st->execute([$telefone]);
    $u = $st->fetch();

    if ($u && password_verify($senha, $u['senha_hash'])) {
        iniciar_sessao($u);
        ir_para_volta(); // vai para o convite, se veio de um; senão, para a Home
    }
    $erro = 'Telefone ou senha incorretos.';
}

$titulo = 'Entrar';
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao formulario">
    <h1>Entrar</h1>
    <p class="sub">Avisos, produtos, empréstimos e eventos da comunidade rural.</p>

    <?php if (!empty($_SESSION['voltar_para'])): ?>
        <div class="flash flash-aviso">Entre ou crie sua conta para aceitar o convite da comunidade.</div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <label>Telefone (com DDD)
            <input type="tel" name="telefone" value="<?= e($telefone) ?>" required inputmode="numeric" placeholder="49 99999-0000" autocomplete="tel">
        </label>
        <label>Senha
            <input type="password" name="senha" required autocomplete="current-password">
        </label>
        <button type="submit" class="botao">Entrar</button>
    </form>
    <p class="centro"><a href="esqueci-senha.php">Esqueci minha senha</a></p>
    <p class="centro">Ainda não tem conta? <a href="cadastro.php">Cadastre-se</a></p>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
