<?php
// Página da comunidade: membros, sair e (para o admin da comunidade ou do site) convite, senha, remover membros e excluir
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();

$id = (int) ($_GET['id'] ?? ($_SESSION['comunidade_id'] ?? 0));
$st = db()->prepare('SELECT * FROM comunidades WHERE id = ?');
$st->execute([$id]);
$comunidade = $st->fetch();

$papel     = $comunidade ? papel_na_comunidade($id, $usuario['id']) : null; // null = não é membro
$adminSite = admin_do_site($usuario['id']);
if (!$comunidade || (!$papel && !$adminSite)) {
    avisar('Você não participa dessa comunidade.', 'erro');
    header('Location: comunidades.php');
    exit;
}
// Poderes de administrador: admin da comunidade ou administrador do site (conferidos no banco)
$admin = $papel === 'admin' || $adminSite;

$erro = '';
$aqui = 'comunidade.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'sair' && $papel) {
        $resultado = remover_da_comunidade($id, $usuario['id']);
        if ($resultado === null) {
            avisar('Você saiu e a comunidade ' . $comunidade['nome'] . ' foi apagada, porque não tinha mais membros.');
        } elseif ($resultado !== '') {
            avisar('Você saiu da comunidade ' . $comunidade['nome'] . '. ' . $resultado . ' agora é o(a) administrador(a).');
        } else {
            avisar('Você saiu da comunidade ' . $comunidade['nome'] . '.');
        }
        header('Location: comunidades.php');
        exit;
    }

    // Daqui para baixo, só o administrador (conferido no banco)
    if (!$admin) {
        http_response_code(403);
        exit('Somente o administrador da comunidade ou do site pode fazer isso.');
    }

    switch ($acao) {
        case 'novo_codigo':
            db()->prepare('UPDATE comunidades SET codigo = ? WHERE id = ?')->execute([gerar_codigo_comunidade(), $id]);
            avisar('Novo link gerado. O link antigo não funciona mais.');
            header('Location: ' . $aqui);
            exit;

        case 'trocar_senha':
            $senha  = $_POST['senha'] ?? '';
            $senha2 = $_POST['senha2'] ?? '';
            if (strlen($senha) < 6)   { $erro = 'A nova senha precisa ter pelo menos 6 caracteres.'; break; }
            if ($senha !== $senha2)   { $erro = 'As senhas não conferem.'; break; }
            db()->prepare('UPDATE comunidades SET senha_hash = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $id]);
            avisar('Senha da comunidade alterada.');
            header('Location: ' . $aqui);
            exit;

        case 'remover_membro':
            $alvo = (int) ($_POST['usuario_id'] ?? 0);
            if ($alvo === $usuario['id']) { $erro = 'Para sair da comunidade, use o botão "Sair da comunidade".'; break; }
            if (papel_na_comunidade($id, $alvo)) {
                $resultado = remover_da_comunidade($id, $alvo);
                if ($resultado === null) {
                    avisar('Membro removido. A comunidade ficou sem membros e foi apagada.');
                    header('Location: ' . ($papel ? 'comunidades.php' : 'admin-comunidades.php'));
                    exit;
                }
                avisar('Membro removido. As publicações dele nesta comunidade foram apagadas.'
                    . ($resultado !== '' ? ' ' . $resultado . ' agora é o(a) administrador(a).' : ''));
            }
            header('Location: ' . $aqui);
            exit;

        case 'excluir':
            $st = db()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
            $st->execute([$usuario['id']]);
            if (!password_verify($_POST['senha_conta'] ?? '', (string) $st->fetchColumn())) {
                $erro = 'Senha da sua conta incorreta. A comunidade não foi excluída.';
                break;
            }
            // Membros e publicações saem junto (ON DELETE CASCADE)
            db()->prepare('DELETE FROM comunidades WHERE id = ?')->execute([$id]);
            avisar('A comunidade ' . $comunidade['nome'] . ' foi excluída.');
            header('Location: ' . ($papel ? 'comunidades.php' : 'admin-comunidades.php'));
            exit;
    }
}

$st = db()->prepare('SELECT u.id, u.nome, u.localidade, m.papel, m.entrou_em
                     FROM membros m JOIN usuarios u ON u.id = m.usuario_id
                     WHERE m.comunidade_id = ?
                     ORDER BY m.papel = \'admin\' DESC, m.entrou_em, u.id');
$st->execute([$id]);
$membros = $st->fetchAll();

// Quem vira admin se o admin da comunidade sair (o membro mais antigo)
$sucessor = null;
if ($papel === 'admin') {
    foreach ($membros as $m) {
        if ($m['id'] !== $usuario['id'] && $m['papel'] !== 'admin') {
            $antigo = $sucessor === null || [$m['entrou_em'], $m['id']] < [$sucessor['entrou_em'], $sucessor['id']];
            if ($antigo) $sucessor = $m;
        }
    }
}
$avisoSair = $papel !== 'admin' ? 'Sair desta comunidade? Suas publicações nela serão apagadas.'
    : ($sucessor ? 'Sair desta comunidade? Suas publicações nela serão apagadas e ' . $sucessor['nome'] . ' vira administrador(a).'
                 : 'Você é o único membro: ao sair, a comunidade será apagada. Continuar?');

$link = link_convite($comunidade['codigo']);
$mensagemConvite = 'Olá! Você foi convidado(a) para a comunidade "' . $comunidade['nome'] . '" na Agenda Comunitária. '
    . 'Entre pelo link: ' . $link . ' (a senha eu te passo à parte).';

$titulo = $comunidade['nome'];
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao">
    <h1><?= e($comunidade['nome']) ?></h1>
    <?php if ($comunidade['descricao']): ?><p class="sub"><?= e($comunidade['descricao']) ?></p><?php endif; ?>
    <p class="info-linha">
        <?php if ($papel === 'admin'): ?><span class="etiqueta admin">Você é administrador(a)</span>
        <?php elseif ($papel): ?><span class="etiqueta">Você é membro</span><?php endif; ?>
        <?php if ($adminSite): ?><span class="etiqueta admin">🛡 Administrador(a) do site</span><?php endif; ?>
        <span class="etiqueta"><?= count($membros) ?> <?= count($membros) == 1 ? 'membro' : 'membros' ?></span>
    </p>
    <?php if ((int) ($_SESSION['comunidade_id'] ?? 0) !== $id): ?>
        <a href="mural.php?comunidade=<?= $id ?>" class="botao secundario">Ver o mural desta comunidade</a>
    <?php endif; ?>
    <?php if (!$papel): ?>
        <div class="flash flash-aviso">Você está vendo como administrador do site (você não é membro desta comunidade).</div>
    <?php endif; ?>
</section>

<?php if ($erro): ?>
    <div class="flash flash-erro"><?= e($erro) ?></div>
<?php endif; ?>

<?php if ($admin): ?>
<section class="cartao formulario">
    <h2>Convidar pessoas</h2>
    <p class="sub">Envie o link e passe a senha à parte (por exemplo, pessoalmente ou numa ligação).</p>
    <label>Link de convite
        <input type="text" id="link-convite" value="<?= e($link) ?>" readonly onclick="this.select()">
    </label>
    <p class="sub dica-codigo">Ou use o código: <b><?= e($comunidade['codigo']) ?></b></p>
    <div class="pilha-botoes">
        <button type="button" class="botao secundario" id="copiar-link">📋 Copiar link</button>
        <a class="botao whatsapp" target="_blank" rel="noopener" href="https://wa.me/?text=<?= e(rawurlencode($mensagemConvite)) ?>">💬 Enviar pelo WhatsApp</a>
    </div>
    <form method="post" class="form-linha">
        <?= campo_csrf() ?>
        <button name="acao" value="novo_codigo" class="botao perigo"
                onclick="return confirm('Gerar um novo link? O link e o código atuais vão parar de funcionar.')">↻ Gerar novo link</button>
    </form>
</section>
<?php endif; ?>

<section class="cartao">
    <h2>Membros</h2>
    <ul class="lista-membros">
        <?php foreach ($membros as $m): ?>
        <li>
            <div>
                <b><?= e($m['nome']) ?></b>
                <?php if ($m['papel'] === 'admin'): ?><span class="etiqueta admin">Admin</span><?php endif; ?>
                <small>📍 <?= e($m['localidade']) ?> · desde <?= e(data_br($m['entrou_em'], false)) ?></small>
            </div>
            <?php if ($admin && $m['id'] !== $usuario['id']): ?>
                <form method="post">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="usuario_id" value="<?= $m['id'] ?>">
                    <button name="acao" value="remover_membro" class="botao perigo botao-pequeno"
                            onclick="return confirm(<?= e(json_encode('Remover ' . $m['nome'] . ' da comunidade? As publicações dele aqui serão apagadas.')) ?>)">Remover</button>
                </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($admin): ?>
<section class="cartao formulario">
    <h2>Trocar a senha da comunidade</h2>
    <p class="sub">Quem já é membro continua na comunidade. A nova senha vale para os próximos que entrarem.</p>
    <form method="post">
        <?= campo_csrf() ?>
        <label>Nova senha
            <input type="password" name="senha" required minlength="6" autocomplete="new-password">
        </label>
        <label>Repita a nova senha
            <input type="password" name="senha2" required minlength="6" autocomplete="new-password">
        </label>
        <button name="acao" value="trocar_senha" class="botao">Salvar nova senha</button>
    </form>
</section>
<?php endif; ?>

<?php if ($papel): ?>
<section class="cartao">
    <form method="post">
        <?= campo_csrf() ?>
        <button name="acao" value="sair" class="botao perigo" onclick="return confirm(<?= e(json_encode($avisoSair)) ?>)">🚪 Sair da comunidade</button>
    </form>
</section>
<?php endif; ?>

<?php if ($admin): ?>
<section class="cartao formulario zona-perigo">
    <h2>Excluir comunidade</h2>
    <div class="flash flash-erro">A comunidade, a lista de membros e <b>todas as publicações</b> dela serão apagadas para sempre. Isso não pode ser desfeito.</div>
    <form method="post">
        <?= campo_csrf() ?>
        <label>Digite a senha da sua conta para confirmar
            <input type="password" name="senha_conta" required autocomplete="current-password">
        </label>
        <button name="acao" value="excluir" class="botao perigo-forte">Excluir comunidade</button>
    </form>
</section>
<?php endif; ?>

<?php if ($admin): ?>
<script>
// Copiar link: usa a área de transferência moderna quando há HTTPS e, sem HTTPS, o jeito antigo
document.getElementById('copiar-link').addEventListener('click', function () {
    var botao = this, campo = document.getElementById('link-convite');
    function pronto() {
        botao.textContent = '✔ Link copiado';
        setTimeout(function () { botao.textContent = '📋 Copiar link'; }, 2000);
    }
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(campo.value).then(pronto);
        return;
    }
    campo.select();
    campo.setSelectionRange(0, 99999);
    try { if (document.execCommand('copy')) pronto(); } catch (e) {}
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/inc/rodape.php'; ?>
