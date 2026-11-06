<?php
// Administração: todos os usuários do site. Só para o administrador do site.
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();
exigir_admin_do_site($usuario['id']);

$busca = trim($_GET['q'] ?? '');
$volta = 'admin-usuarios.php' . ($busca !== '' ? '?q=' . rawurlencode($busca) : '');
$erro  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? '';
    $alvo = (int) ($_POST['usuario_id'] ?? 0);

    $st = db()->prepare('SELECT id, nome, is_admin FROM usuarios WHERE id = ?');
    $st->execute([$alvo]);
    $pessoa = $st->fetch();

    if (!$pessoa) {
        $erro = 'Usuário não encontrado.';
    } elseif ($alvo === $usuario['id']) {
        // O site nunca pode ficar sem administrador
        $erro = 'Você não pode excluir nem tirar o administrador da sua própria conta por aqui.';
    } elseif ($acao === 'tornar_admin' || $acao === 'retirar_admin') {
        $novo = $acao === 'tornar_admin' ? 1 : 0;
        db()->prepare('UPDATE usuarios SET is_admin = ? WHERE id = ?')->execute([$novo, $alvo]);
        avisar($pessoa['nome'] . ($novo ? ' agora é administrador(a) do site.' : ' não é mais administrador(a) do site.'));
        header('Location: ' . $volta);
        exit;
    } elseif ($acao === 'excluir') {
        $st = db()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
        $st->execute([$usuario['id']]);
        if (!password_verify($_POST['senha_admin'] ?? '', (string) $st->fetchColumn())) {
            $erro = 'Senha da sua conta incorreta. ' . $pessoa['nome'] . ' não foi excluído(a).';
        } else {
            excluir_usuario($alvo);
            avisar('A conta de ' . $pessoa['nome'] . ' foi excluída. A administração das comunidades dela passou para o membro mais antigo.');
            header('Location: ' . $volta);
            exit;
        }
    }
}

$sql    = 'SELECT id, nome, telefone, uf, cidade, bairro, is_admin, criado_em FROM usuarios';
$params = [];
if ($busca !== '') {
    $sql   .= ' WHERE nome LIKE ? OR telefone LIKE ? OR cidade LIKE ? OR bairro LIKE ?';
    $like   = '%' . $busca . '%';
    $params = [$like, '%' . (limpar_telefone($busca) ?: $busca) . '%', $like, $like];
}
$st = db()->prepare($sql . ' ORDER BY nome');
$st->execute($params);
$usuarios = $st->fetchAll();

// Para excluir alguém, abre o formulário de confirmação com a senha do admin
$confirmar = null;
if (isset($_GET['excluir'])) {
    foreach ($usuarios as $u) {
        if ($u['id'] === (int) $_GET['excluir'] && $u['id'] !== $usuario['id']) $confirmar = $u;
    }
}
if (!$confirmar && $erro && ($_POST['acao'] ?? '') === 'excluir') {
    foreach ($usuarios as $u) {
        if ($u['id'] === (int) ($_POST['usuario_id'] ?? 0)) $confirmar = $u;
    }
}

$titulo = 'Usuários (admin)';
require __DIR__ . '/inc/topo.php';
?>
<h1 class="titulo-pagina">🛠 Usuários</h1>

<?php if ($erro): ?>
    <div class="flash flash-erro"><?= e($erro) ?></div>
<?php endif; ?>

<?php if ($confirmar): ?>
<section class="cartao formulario zona-perigo" id="confirmar">
    <h2>Excluir <?= e($confirmar['nome']) ?>?</h2>
    <div class="flash flash-erro">A conta e <b>todas as publicações</b> dessa pessoa serão apagadas para sempre.
        Nas comunidades que ela administra, a administração passa para o membro mais antigo
        (e a comunidade que ficar sem membros é apagada).</div>
    <form method="post" action="<?= e($volta) ?>">
        <?= campo_csrf() ?>
        <input type="hidden" name="usuario_id" value="<?= $confirmar['id'] ?>">
        <label>Digite a senha da <b>sua</b> conta para confirmar
            <input type="password" name="senha_admin" required autocomplete="current-password" autofocus>
        </label>
        <button name="acao" value="excluir" class="botao perigo-forte">Sim, excluir a conta</button>
    </form>
    <p class="centro"><a href="<?= e($volta) ?>">Cancelar</a></p>
</section>
<?php endif; ?>

<form class="busca" method="get">
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar por nome, telefone, cidade ou bairro…">
    <button type="submit" aria-label="Buscar">🔍</button>
</form>

<p class="sub"><?= count($usuarios) ?> <?= count($usuarios) == 1 ? 'usuário' : 'usuários' ?><?= $busca !== '' ? ' encontrado(s)' : '' ?></p>

<?php foreach ($usuarios as $u): $euMesmo = $u['id'] === $usuario['id']; ?>
<article class="cartao usuario-item">
    <div class="pub-cabeca">
        <?php if ($u['is_admin']): ?><span class="etiqueta admin">🛡 Admin do site</span><?php endif; ?>
        <?php if ($euMesmo): ?><span class="etiqueta">Você</span><?php endif; ?>
        <time>desde <?= e(data_br($u['criado_em'], false)) ?></time>
    </div>
    <h3><?= e($u['nome']) ?></h3>
    <p class="autor">
        📞 <?= e(formatar_telefone($u['telefone'])) ?> · 📍 <?= e(local_formatado($u)) ?>
    </p>
    <?php if (!$euMesmo): ?>
    <div class="pub-acoes">
        <form method="post" action="<?= e($volta) ?>">
            <?= campo_csrf() ?>
            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
            <?php if ($u['is_admin']): ?>
                <button name="acao" value="retirar_admin" class="botao secundario"
                        onclick="return confirm(<?= e(json_encode('Retirar o administrador do site de ' . $u['nome'] . '?')) ?>)">Retirar admin</button>
            <?php else: ?>
                <button name="acao" value="tornar_admin" class="botao secundario"
                        onclick="return confirm(<?= e(json_encode('Tornar ' . $u['nome'] . ' administrador(a) do site? A pessoa terá poder sobre todas as comunidades, usuários e eventos.')) ?>)">Tornar admin</button>
            <?php endif; ?>
            <a href="admin-usuarios.php?<?= e(http_build_query(array_filter(['q' => $busca, 'excluir' => $u['id']]))) ?>#confirmar" class="botao perigo">🗑 Excluir</a>
        </form>
    </div>
    <?php endif; ?>
</article>
<?php endforeach; ?>
<?php require __DIR__ . '/inc/rodape.php'; ?>
