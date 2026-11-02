<?php
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();

$erros = [];
$dados = ['nome' => '', 'descricao' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $dados['nome']      = trim($_POST['nome'] ?? '');
    $dados['descricao'] = trim($_POST['descricao'] ?? '');
    $senha              = $_POST['senha'] ?? '';
    $senha2             = $_POST['senha2'] ?? '';

    if (mb_strlen($dados['nome']) < 3 || mb_strlen($dados['nome']) > 80) $erros[] = 'O nome precisa ter entre 3 e 80 caracteres.';
    if (mb_strlen($dados['descricao']) > 255)                            $erros[] = 'A descrição pode ter no máximo 255 caracteres.';
    if (strlen($senha) < 6)                                              $erros[] = 'A senha de acesso precisa ter pelo menos 6 caracteres.';
    if ($senha !== $senha2)                                              $erros[] = 'As senhas não conferem.';

    if (!$erros) {
        $pdo = db();
        $pdo->beginTransaction();
        $st = $pdo->prepare('INSERT INTO comunidades (nome, descricao, codigo, senha_hash, criador_id) VALUES (?, ?, ?, ?, ?)');
        $st->execute([$dados['nome'], $dados['descricao'], gerar_codigo_comunidade(), password_hash($senha, PASSWORD_DEFAULT), $usuario['id']]);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO membros (comunidade_id, usuario_id, papel) VALUES (?, ?, 'admin')")->execute([$id, $usuario['id']]);
        $pdo->commit();

        $_SESSION['comunidade_id'] = $id;
        avisar('Comunidade criada! Agora convide as pessoas com o link abaixo.');
        header('Location: comunidade.php?id=' . $id);
        exit;
    }
}

$titulo = 'Criar comunidade';
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao formulario">
    <h1>Criar comunidade</h1>
    <p class="sub">Você será o(a) administrador(a) e poderá convidar as pessoas por um link.</p>

    <?php foreach ($erros as $erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <label>Nome da comunidade
            <input type="text" name="nome" value="<?= e($dados['nome']) ?>" required maxlength="80" placeholder="Ex.: Produtores da Linha São José">
        </label>
        <label>Descrição curta (opcional)
            <textarea name="descricao" rows="2" maxlength="255" placeholder="Ex.: Avisos e trocas entre as famílias da linha."><?= e($dados['descricao']) ?></textarea>
        </label>
        <label>Senha de acesso
            <input type="password" name="senha" required minlength="6" autocomplete="new-password">
        </label>
        <label>Repita a senha
            <input type="password" name="senha2" required minlength="6" autocomplete="new-password">
        </label>
        <p class="sub dica">Quem receber o link de convite vai precisar desta senha para entrar.</p>
        <button type="submit" class="botao">Criar comunidade</button>
    </form>
    <p class="centro"><a href="comunidades.php">Cancelar</a></p>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
