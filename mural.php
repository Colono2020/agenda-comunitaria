<?php
// Mural comunitário: lista as publicações com filtros
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();

// Escolha/troca de comunidade (só aceita comunidades de que a pessoa é membro, ou qualquer uma
// para o administrador do site; 0 = voltar para a escolha)
if (isset($_GET['comunidade'])) {
    $escolhida = (int) $_GET['comunidade'];
    $_SESSION['comunidade_id'] = $escolhida;
    if ($escolhida && comunidade_atual($usuario['id'])) {
        $_SESSION['comunidade_id'] = $escolhida;
        header('Location: mural.php');
    } else {
        unset($_SESSION['comunidade_id']);
        header('Location: comunidades.php');
    }
    exit;
}

$filtroTipo = $_GET['tipo'] ?? '';
$busca      = trim($_GET['q'] ?? '');
$minhas     = !empty($_GET['minhas']);

// O mural precisa de uma comunidade escolhida (sem ela, vai para a tela de escolha).
// "Minhas publicações" funciona sempre, com as publicações de todas as minhas comunidades.
$atual       = $minhas ? comunidade_atual($usuario['id']) : exigir_comunidade($usuario['id']);
$comunidades = minhas_comunidades($usuario['id']);

$where  = [];
$params = [];

if ($minhas) {
    // Minhas publicações, só nas comunidades de que ainda participo
    $where[]  = 'p.usuario_id = ? AND p.comunidade_id IN (SELECT comunidade_id FROM membros WHERE usuario_id = ?)';
    array_push($params, $usuario['id'], $usuario['id']);
} else {
    // Mural: só a comunidade escolhida (exigir_comunidade() já conferiu que sou membro ou admin do site)
    $where[]  = 'p.ativo = 1 AND p.comunidade_id = ?';
    $params[] = $atual['id'];
}
if (isset(TIPOS[$filtroTipo])) {
    $where[]  = 'p.tipo = ?';
    $params[] = $filtroTipo;
}
if ($busca !== '') {
    $where[]  = '(p.titulo LIKE ? OR p.descricao LIKE ? OR u.localidade LIKE ?)';
    $like     = '%' . $busca . '%';
    array_push($params, $like, $like, $like);
}

$sql = 'SELECT p.*, u.nome, u.telefone, u.localidade, c.nome AS comunidade_nome,
               ' . SQL_SEG_EDITAR . ' AS seg_editar
        FROM publicacoes p
        JOIN usuarios u ON u.id = p.usuario_id
        JOIN comunidades c ON c.id = p.comunidade_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.criado_em DESC
        LIMIT 100';
$st = db()->prepare($sql);
$st->execute($params);
$publicacoes = $st->fetchAll();

// Próximos eventos (só no mural da comunidade, sem filtros)
$proximos = [];
if (!$minhas && $filtroTipo === '' && $busca === '') {
    $st = db()->prepare(
        "SELECT p.id, p.titulo, p.data_evento FROM publicacoes p
         WHERE p.comunidade_id = ? AND p.tipo = 'evento' AND p.ativo = 1 AND p.data_evento >= ?
         ORDER BY p.data_evento ASC LIMIT 3"
    );
    $st->execute([$atual['id'], date('Y-m-d H:i:s')]);
    $proximos = $st->fetchAll();
}
// Remove publicações dos outros: admin da comunidade ou administrador do site
$souAdmin   = $atual && ($atual['papel'] !== 'membro' || admin_do_site($usuario['id']));
$comoSite   = $atual && $atual['papel'] === 'site'; // vendo sem ser membro
if ($comoSite) $comunidades[] = $atual;

/** Monta link mantendo os filtros atuais. */
function link_filtro(array $mudar): string
{
    $q = array_merge(['tipo' => $_GET['tipo'] ?? '', 'q' => $_GET['q'] ?? '', 'minhas' => $_GET['minhas'] ?? ''], $mudar);
    return 'mural.php?' . http_build_query(array_filter($q, fn($v) => $v !== ''));
}

$titulo = $minhas ? 'Minhas publicações' : $atual['nome'];
require __DIR__ . '/inc/topo.php';
?>

<?php if ($minhas): ?>
    <h1 class="titulo-pagina">Minhas publicações</h1>
<?php else: ?>
    <div class="seletor-comunidade">
        <form method="get">
            <select name="comunidade" aria-label="Trocar de comunidade" onchange="this.form.submit()">
                <?php foreach ($comunidades as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] === $atual['id'] ? 'selected' : '' ?>>🏘️ <?= e($c['nome']) ?></option>
                <?php endforeach; ?>
                <option value="0">↩ Voltar para a escolha de comunidade</option>
            </select>
            <noscript><button type="submit" class="botao secundario">Trocar</button></noscript>
        </form>
        <a href="comunidade.php?id=<?= $atual['id'] ?>" class="link-comunidade" aria-label="Detalhes da comunidade">ℹ️</a>
    </div>
    <?php if ($comoSite): ?>
        <div class="flash flash-aviso">🛡 Você está vendo como administrador do site (você não é membro desta comunidade).</div>
    <?php else: ?>
        <a href="publicar.php" class="botao botao-publicar">＋ Publicar</a>
    <?php endif; ?>
<?php endif; ?>

<form class="busca" method="get">
    <?php if ($minhas): ?><input type="hidden" name="minhas" value="1"><?php endif; ?>
    <?php if ($filtroTipo): ?><input type="hidden" name="tipo" value="<?= e($filtroTipo) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar: tomate, trator, reunião…">
    <button type="submit" aria-label="Buscar">🔍</button>
</form>

<div class="filtros">
    <a href="<?= e(link_filtro(['tipo' => ''])) ?>" class="<?= $filtroTipo === '' ? 'ativo' : '' ?>">Todos</a>
    <?php foreach (TIPOS as $chave => [$nome, $icone]): ?>
        <a href="<?= e(link_filtro(['tipo' => $chave])) ?>" class="<?= $filtroTipo === $chave ? 'ativo' : '' ?>"><?= $icone ?> <?= $nome ?></a>
    <?php endforeach; ?>
</div>

<?php if ($proximos): ?>
<section class="proximos">
    <h2>📅 Próximos eventos</h2>
    <?php foreach ($proximos as $ev): ?>
        <a href="#pub-<?= $ev['id'] ?>"><b><?= e(data_br($ev['data_evento'])) ?></b> — <?= e($ev['titulo']) ?></a>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!$publicacoes): ?>
    <div class="vazio">
        <p>Nenhuma publicação encontrada.</p>
        <?php if ($atual && !$comoSite): ?><a href="publicar.php" class="botao">Fazer uma publicação</a><?php endif; ?>
    </div>
<?php endif; ?>

<?php foreach ($publicacoes as $p):
    [$nomeTipo, $icone] = TIPOS[$p['tipo']];
    $dono = (int) $p['usuario_id'] === $usuario['id'];
    // O autor pode editar até 24 horas depois de publicar (conferido de novo no editar.php)
    $podeEditar = $dono && (int) $p['seg_editar'] > 0;
?>
<article class="cartao pub tipo-<?= e($p['tipo']) ?> <?= $p['ativo'] ? '' : 'encerrada' ?>" id="pub-<?= $p['id'] ?>">
    <div class="pub-cabeca">
        <span class="etiqueta"><?= $icone ?> <?= $nomeTipo ?></span>
        <?php if ($p['intencao']): ?>
            <span class="etiqueta intencao-<?= e($p['intencao']) ?>"><?= $p['intencao'] === 'ofereco' ? 'Oferece' : 'Precisa' ?></span>
        <?php endif; ?>
        <?php if (!$p['ativo']): ?><span class="etiqueta cinza">Encerrada</span><?php endif; ?>
        <time><?= e(tempo_atras($p['criado_em'])) ?></time>
    </div>

    <h3><?= e($p['titulo']) ?></h3>
    <p class="descricao"><?= nl2br(e($p['descricao'])) ?></p>

    <?php if ($p['data_evento']): ?>
        <p class="data">🗓️ <?= e(data_br($p['data_evento'])) ?></p>
    <?php endif; ?>

    <p class="autor">👤 <?= e($p['nome']) ?> · 📍 <?= e($p['localidade']) ?>
        <?php if ($minhas): ?><br>🏘️ <?= e($p['comunidade_nome']) ?><?php endif; ?></p>

    <div class="pub-acoes">
        <?php if (!$dono): ?>
            <a class="botao whatsapp" target="_blank" rel="noopener"
               href="<?= e(link_whatsapp($p['telefone'], 'Olá ' . $p['nome'] . ', vi sua publicação "' . $p['titulo'] . '" na Agenda Comunitária.')) ?>">
                💬 WhatsApp
            </a>
            <a class="botao secundario" href="tel:<?= e(limpar_telefone($p['telefone'])) ?>">📞 Ligar</a>
        <?php else: ?>
            <form method="post" action="acao.php">
                <?= campo_csrf() ?>
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <?php if ($podeEditar): ?>
                    <a class="botao secundario" href="editar.php?<?= e(http_build_query(array_filter(['id' => $p['id'], 'voltar' => $minhas ? 'minhas' : '']))) ?>">✏️ Editar</a>
                <?php endif; ?>
                <?php if ($p['ativo']): ?>
                    <button name="acao" value="encerrar" class="botao secundario">✔ Encerrar</button>
                <?php else: ?>
                    <button name="acao" value="reabrir" class="botao secundario">↺ Reabrir</button>
                <?php endif; ?>
                <button name="acao" value="excluir" class="botao perigo"
                        onclick="return confirm('Excluir esta publicação?')">🗑 Excluir</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($p['editado_em']): ?>
        <p class="marca-editado">✏️ Alterado em <?= e(data_br($p['editado_em'])) ?></p>
    <?php endif; ?>

    <?php if (!$dono && !$minhas && $souAdmin): ?>
        <form method="post" action="acao.php" class="remover-admin">
            <?= campo_csrf() ?>
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button name="acao" value="remover" class="link-perigo"
                    onclick="return confirm('Remover esta publicação da comunidade? Ela será apagada.')">🛡 Remover (<?= $atual['papel'] === 'admin' ? 'administrador' : 'admin do site' ?>)</button>
        </form>
    <?php endif; ?>
</article>
<?php endforeach; ?>

<?php require __DIR__ . '/inc/rodape.php'; ?>
