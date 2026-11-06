<?php
require_once __DIR__ . '/inc/funcoes.php';

if (usuario_logado()) {
    header('Location: home.php');
    exit;
}

$erros = [];
$dados = ['nome' => '', 'telefone' => '', 'email' => '', 'uf' => '', 'cidade' => '', 'bairro' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $dados['nome']       = trim($_POST['nome'] ?? '');
    $dados['telefone']   = limpar_telefone($_POST['telefone'] ?? '');
    $dados['email']      = trim($_POST['email'] ?? '');
    $email               = normalizar_email($dados['email']);
    $senha               = $_POST['senha'] ?? '';
    $senha2              = $_POST['senha2'] ?? '';
    [$local, $errosLocal] = ler_localizacao($_POST);
    $dados = array_merge($dados, $local);

    if (mb_strlen($dados['nome']) < 3)                  $erros[] = 'Informe seu nome.';
    if (!in_array(strlen($dados['telefone']), [10, 11])) $erros[] = 'Informe o telefone com DDD (ex.: 49 99999-0000).';
    if ($email === '')                                   $erros[] = 'Informe um e-mail válido (ex.: maria@gmail.com).';
    array_push($erros, ...$errosLocal);
    if (strlen($senha) < 6)                              $erros[] = 'A senha precisa ter pelo menos 6 caracteres.';
    if ($senha !== $senha2)                              $erros[] = 'As senhas não conferem.';

    if (!$erros) {
        $st = db()->prepare('SELECT id FROM usuarios WHERE telefone = ?');
        $st->execute([$dados['telefone']]);
        if ($st->fetch()) {
            $erros[] = 'Já existe uma conta com este telefone. Faça login.';
        }
        if (email_em_uso($email)) {
            $erros[] = 'Já existe uma conta com este e-mail. Use outro e-mail ou faça login.';
        }
    }

    if (!$erros) {
        // Apaga cadastros pendentes abandonados (vencidos há mais de 1 dia)
        db()->prepare('DELETE FROM verificacoes WHERE expira_em < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);

        // A conta só é criada depois de confirmar o código (verificar.php)
        $st = db()->prepare('INSERT INTO verificacoes (telefone, nome, uf, cidade, bairro, email, senha_hash) VALUES (?, ?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE nome = VALUES(nome), uf = VALUES(uf), cidade = VALUES(cidade), bairro = VALUES(bairro),
                                                     email = VALUES(email), senha_hash = VALUES(senha_hash)');
        $st->execute([$dados['telefone'], $dados['nome'], $dados['uf'], $dados['cidade'], $dados['bairro'], $email, password_hash($senha, PASSWORD_DEFAULT)]);
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
    $dados = ['nome' => $pendente['nome'], 'telefone' => $pendente['telefone'], 'email' => $pendente['email'],
              'uf' => (string) $pendente['uf'], 'cidade' => $pendente['cidade'], 'bairro' => $pendente['bairro']];
}
// Estado ainda não escolhido: sugere o do DDD do telefone (se já foi digitado)
if (!uf_valida($dados['uf']) && $dados['telefone'] !== '' && uf_valida(uf_pelo_ddd($dados['telefone']))) {
    $dados['uf'] = uf_pelo_ddd($dados['telefone']);
}
// Tabela DDD -> estado para o JavaScript marcar o estado ao digitar o telefone
$dddUf = [];
for ($ddd = 11; $ddd <= 99; $ddd++) {
    if (uf_valida($u = uf_pelo_ddd((string) $ddd))) $dddUf[$ddd] = $u;
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
        <label>E-mail
            <input type="email" name="email" value="<?= e($dados['email']) ?>" required maxlength="190" placeholder="maria@gmail.com" autocomplete="email" inputmode="email">
            <small class="dica-campo">Usado só para recuperar sua conta se você esquecer a senha ou trocar de número.</small>
        </label>
        <span class="rotulo rotulo-secao">📍 Onde você mora</span>
        <?= campos_localizacao($dados) ?>
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
<script>
// Ao digitar o telefone, marca o estado pelo DDD (só se a pessoa ainda não escolheu um estado)
(function () {
    var dddUf = <?= json_encode($dddUf) ?>;
    var tel = document.querySelector('input[name=telefone]');
    var uf  = document.querySelector('[data-local-uf]');
    var escolhido = uf.value !== '';
    uf.addEventListener('change', function () { escolhido = true; });
    tel.addEventListener('input', function () {
        var ddd = tel.value.replace(/\D/g, '').slice(0, 2);
        if (!escolhido && dddUf[ddd] && uf.value !== dddUf[ddd]) {
            uf.value = dddUf[ddd];
            uf.dispatchEvent(new Event('change')); // carrega as cidades do estado
            escolhido = false;                     // o change acima não conta como escolha da pessoa
        }
    });
})();
</script>
<?php require __DIR__ . '/inc/rodape.php'; ?>
