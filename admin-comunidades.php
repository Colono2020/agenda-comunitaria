<?php
// Administração: todas as comunidades do site. Só para o administrador do site.
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();
exigir_admin_do_site($usuario['id']);

$busca = trim($_GET['q'] ?? '');

$sql = "SELECT c.id, c.nome, c.criado_em, cr.nome AS criador,
               (SELECT u.nome FROM membros m JOIN usuarios u ON u.id = m.usuario_id
                 WHERE m.comunidade_id = c.id AND m.papel = 'admin' LIMIT 1) AS admin_nome,
               (SELECT COUNT(*) FROM membros m WHERE m.comunidade_id = c.id) AS qtd_membros,
               (SELECT COUNT(*) FROM publicacoes p WHERE p.comunidade_id = c.id) AS qtd_publicacoes,
               (SELECT 1 FROM membros m WHERE m.comunidade_id = c.id AND m.usuario_id = ?) AS sou_membro
        FROM comunidades c
        LEFT JOIN usuarios cr ON cr.id = c.criador_id";
$params = [$usuario['id']];
if ($busca !== '') {
    $sql     .= ' WHERE c.nome LIKE ?';
    $params[] = '%' . $busca . '%';
}
$st = db()->prepare($sql . ' ORDER BY c.nome');
$st->execute($params);
$comunidades = $st->fetchAll();

$titulo = 'Comunidades (admin)';
require __DIR__ . '/inc/topo.php';
?>
<h1 class="titulo-pagina">🛠 Comunidades</h1>
<p class="sub">Todas as comunidades do site. Você pode abrir o mural e gerenciar qualquer uma, sem senha e sem virar membro.</p>

<form class="busca" method="get">
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar comunidade pelo nome…">
    <button type="submit" aria-label="Buscar">🔍</button>
</form>

<p class="sub"><?= count($comunidades) ?> <?= count($comunidades) == 1 ? 'comunidade' : 'comunidades' ?><?= $busca !== '' ? ' encontrada(s)' : '' ?></p>

<?php if (!$comunidades): ?><div class="vazio"><p>Nenhuma comunidade encontrada.</p></div><?php endif; ?>

<?php foreach ($comunidades as $c): ?>
<article class="cartao comunidade-item">
    <div class="pub-cabeca">
        <span class="etiqueta">👥 <?= (int) $c['qtd_membros'] ?> <?= $c['qtd_membros'] == 1 ? 'membro' : 'membros' ?></span>
        <span class="etiqueta">📝 <?= (int) $c['qtd_publicacoes'] ?> <?= $c['qtd_publicacoes'] == 1 ? 'publicação' : 'publicações' ?></span>
        <?php if ($c['sou_membro']): ?><span class="etiqueta admin">Você participa</span><?php endif; ?>
    </div>
    <h3><?= e($c['nome']) ?></h3>
    <p class="autor">
        Criada por <?= $c['criador'] !== null ? e($c['criador']) : '<i>conta excluída</i>' ?> em <?= e(data_br($c['criado_em'], false)) ?><br>
        Admin: <?= $c['admin_nome'] !== null ? e($c['admin_nome']) : '<i>nenhum</i>' ?>
    </p>
    <div class="pub-acoes">
        <a href="mural.php?comunidade=<?= $c['id'] ?>" class="botao">Ver mural</a>
        <a href="comunidade.php?id=<?= $c['id'] ?>" class="botao secundario">Gerenciar</a>
    </div>
</article>
<?php endforeach; ?>
<?php require __DIR__ . '/inc/rodape.php'; ?>
