<?php
require_once __DIR__ . '/inc/funcoes.php';

if (usuario_logado()) {
    header('Location: home.php');
    exit;
}

$erros = [];
$dados = ['nome' => '', 'telefone' => '', 'localidade' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $dados['nome']       = trim($_POST['nome'] ?? '');
    $dados['telefone']   = limpar_telefone($_POST['telefone'] ?? '');
    $dados['localidade'] = trim($_POST['localidade'] ?? '');
    $senha               = $_POST['senha'] ?? '';
    $senha2              = $_POST['senha2'] ?? '';

    if (mb_strlen($dados['nome']) < 3)                  $erros[] = 'Informe seu nome.';
    if (!in_array(strlen($dados['telefone']), [10, 11])) $erros[] = 'Informe o telefone com DDD (ex.: 49 99999-0000).';
    if ($dados['localidade'] === '')                     $erros[] = 'Informe sua comunidade ou localidade.';
    if (strlen($senha) < 6)                              $erros[] = 'A senha precisa ter pelo menos 6 caracteres.';
    if ($senha !== $senha2)                              $erros[] = 'As senhas não conferem.';

    if (!$erros) {
        $st = db()->prepare('SELECT id FROM usuarios WHERE telefone = ?');
        $st->execute([$dados['telefone']]);
        if ($st->fetch()) {
            $erros[] = 'Já existe uma conta com este telefone. Faça login.';
        }
    }

    if (!$erros) {
        // Apaga cadastros pendentes abandonados (vencidos há mais de 1 dia)
        db()->prepare('DELETE FROM verificacoes WHERE expira_em < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);

        // A conta só é criada depois de confirmar o código (verificar.php)
        $st = db()->prepare('INSERT INTO verificacoes (telefone, nome, localidade, senha_hash) VALUES (?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE nome = VALUES(nome), localidade = VALUES(localidade), senha_hash = VALUES(senha_hash)');
        $st->execute([$dados['telefone'], $dados['nome'], $dados['localidade'], password_hash($senha, PASSWORD_DEFAULT)]);
        $_SESSION['verificar_telefone'] = $dados['telefone'];

        if (espera_reenvio(buscar_verificacao($dados['telefone'])) > 0) {
            // Um código foi enviado há menos de 60 segundos e continua valendo
            avisar('Já enviamos um código há pouco. Digite-o abaixo.');
        } elseif (!enviar_novo_codigo($dados['telefone'])) {
            avisar('Não foi possível enviar o código pelo WhatsApp. Toque em "Reenviar código".', 'erro');
        }
        header('Location: verificar.php');
        exit;
    }
} elseif (!empty($_SESSION['verificar_telefone']) && ($pendente = buscar_verificacao($_SESSION['verificar_telefone']))) {
    // Voltou para corrigir os dados: preenche com o cadastro pendente
    $dados = ['nome' => $pendente['nome'], 'telefone' => $pendente['telefone'], 'localidade' => $pendente['localidade']];
}

$titulo = 'Criar conta';
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao formulario">
    <h1>Criar conta</h1>
    <p class="sub">Participe do mural da sua comunidade.</p>

    <?php foreach ($erros as $erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <label>Nome
            <input type="text" name="nome" value="<?= e($dados['nome']) ?>" required maxlength="100" autocomplete="name">
        </label>
        <label>Telefone / WhatsApp (com DDD)
            <input type="tel" name="telefone" value="<?= e($dados['telefone']) ?>" required inputmode="numeric" placeholder="49 99999-0000" autocomplete="tel">
        </label>
        <label>Comunidade / Localidade
            <input type="text" name="localidade" value="<?= e($dados['localidade']) ?>" required maxlength="100" placeholder="Ex.: Linha São José">
        </label>
        <label>Senha
            <input type="password" name="senha" required minlength="6" autocomplete="new-password">
        </label>
        <label>Repita a senha
            <input type="password" name="senha2" required minlength="6" autocomplete="new-password">
        </label>
        <button type="submit" class="botao">Continuar</button>
        <p class="sub centro dica">Vamos enviar um código pelo WhatsApp para confirmar seu telefone.</p>
    </form>
    <p class="centro">Já tem conta? <a href="login.php">Entrar</a></p>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
