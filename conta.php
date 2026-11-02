<?php
// Minha conta: dados da conta e troca do nome (o telefone é o login e não muda aqui)
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();

$erro = '';
$nome = $usuario['nome'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $nome = trim($_POST['nome'] ?? '');

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
        $erro = 'O nome precisa ter entre 3 e 100 caracteres.';
    } else {
        db()->prepare('UPDATE usuarios SET nome = ? WHERE id = ?')->execute([$nome, $usuario['id']]);
        $_SESSION['usuario']['nome'] = $nome; // o "Olá, ..." e o resto do app mudam na hora
        avisar('Nome atualizado!');
        header('Location: conta.php');
        exit;
    }
}

$titulo = 'Minha conta';
require __DIR__ . '/inc/topo.php';
?>
<h1 class="titulo-pagina">Minha conta</h1>

<section class="cartao formulario">
    <?php if ($erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <label>Nome
            <input type="text" name="nome" value="<?= e($nome) ?>" required minlength="3" maxlength="100" autocomplete="name">
        </label>
        <button type="submit" class="botao">Salvar nome</button>
    </form>
</section>

<section class="cartao conta">
    <p>📞 <?= e(formatar_telefone($usuario['telefone'])) ?></p>
    <p class="sub dica-conta">O telefone é usado para entrar no app e foi confirmado pelo WhatsApp, por isso não pode ser alterado aqui.</p>
    <p>📍 <?= e($usuario['localidade']) ?></p>
</section>

<section class="cartao conta">
    <a href="sair.php" class="botao secundario">Sair do app</a>
    <a href="excluir-conta.php" class="botao perigo">🗑 Excluir minha conta</a>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
