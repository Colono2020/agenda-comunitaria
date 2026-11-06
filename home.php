<?php
// Home: notícias e eventos do agro do estado escolhido (sem publicações das comunidades)
require_once __DIR__ . '/inc/funcoes.php';
require_once __DIR__ . '/inc/noticias.php';
$usuario = exigir_login();

// Troca de estado da Home: vale só nesta sessão. Não muda a localização da conta
// (no próximo login a Home volta a abrir no estado da localização)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $uf = $_POST['uf'] ?? '';
    if (isset(UFS[$uf])) {
        $_SESSION['uf'] = $uf;
    }
    header('Location: home.php');
    exit;
}

$uf       = uf_atual($usuario);
$noticias = buscar_noticias($uf);

// Próximos eventos do agro (inclui os que estão acontecendo hoje)
$sql    = 'SELECT * FROM eventos_gerais WHERE COALESCE(data_fim, data_inicio) >= ?';
$params = [date('Y-m-d 00:00:00')];
if ($uf !== 'BR') {
    $sql     .= ' AND uf = ?';
    $params[] = $uf;
}
$st = db()->prepare($sql . ' ORDER BY data_inicio LIMIT 20');
$st->execute($params);
$eventos = $st->fetchAll();

/** "05/12/2026 às 08:00 até 07/12/2026" ou "20/11/2026, das 08:00 às 17:00". */
function periodo_evento(string $inicio, ?string $fim): string
{
    if (!$fim) return data_br($inicio);
    if (substr($inicio, 0, 10) === substr($fim, 0, 10)) {
        return date('d/m/Y', strtotime($inicio)) . ', das ' . date('H:i', strtotime($inicio)) . ' às ' . date('H:i', strtotime($fim));
    }
    return data_br($inicio) . ' até ' . data_br($fim, false);
}

$titulo = 'Home';
require __DIR__ . '/inc/topo.php';
?>
<form method="post" class="seletor-uf">
    <?= campo_csrf() ?>
    <label>📍 Estado
        <select name="uf" onchange="this.form.submit()">
            <?php foreach (UFS as $sigla => $nome): ?>
                <option value="<?= $sigla ?>" <?= $sigla === $uf ? 'selected' : '' ?>><?= e($nome) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <noscript><button type="submit" class="botao secundario">Trocar</button></noscript>
</form>

<section>
    <div class="secao-titulo">
        <h2>📅 Eventos do agro</h2>
        <?php if (admin_do_site($usuario['id'])): ?><a href="admin-eventos.php" class="link-secao">🛠 Gerenciar</a><?php endif; ?>
    </div>

    <?php if (!$eventos): ?>
        <div class="cartao vazio-pequeno">Nenhum evento cadastrado para <?= e(UFS[$uf]) ?> por enquanto.</div>
    <?php endif; ?>

    <?php foreach ($eventos as $ev):
        $agora = date('Y-m-d H:i:s');
        $acontecendo = $ev['data_inicio'] <= $agora && ($ev['data_fim'] ?? $ev['data_inicio']) >= $agora;
    ?>
    <article class="cartao evento-geral">
        <div class="pub-cabeca">
            <span class="etiqueta">📅 <?= e(periodo_evento($ev['data_inicio'], $ev['data_fim'])) ?></span>
            <?php if ($acontecendo): ?><span class="etiqueta admin">Acontecendo agora</span><?php endif; ?>
        </div>
        <h3><?= e($ev['titulo']) ?></h3>
        <p class="autor">📍 <?= e($ev['local']) ?> · <?= e($ev['cidade']) ?>/<?= e($ev['uf']) ?></p>
        <p class="descricao"><?= nl2br(e($ev['descricao'])) ?></p>
        <?php if ($ev['link']): ?>
            <a href="<?= e($ev['link']) ?>" target="_blank" rel="noopener noreferrer" class="botao secundario">Mais informações ↗</a>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
</section>

<section>
    <div class="secao-titulo">
        <h2>📰 Notícias do agro</h2>
        <span class="sub"><?= e(UFS[$uf]) ?></span>
    </div>

    <?php if (!$noticias): ?>
        <div class="flash flash-erro">Não foi possível carregar as notícias agora.</div>
    <?php else: ?>
        <?php if ($noticias['antigas']): ?>
            <div class="flash flash-aviso">Sem conexão com o Google Notícias. Mostrando as últimas notícias guardadas.</div>
        <?php endif; ?>
        <?php foreach ($noticias['itens'] as $n): ?>
        <article class="cartao noticia">
            <a href="<?= e($n['link']) ?>" target="_blank" rel="noopener noreferrer">
                <h3><?= e($n['titulo']) ?></h3>
            </a>
            <p class="autor">📰 <?= e($n['fonte'] ?: 'Google Notícias') ?><?= $n['data'] ? ' · ' . e(tempo_atras($n['data'])) : '' ?></p>
        </article>
        <?php endforeach; ?>
        <p class="sub centro dica">Notícias do Google Notícias. Abrem em uma nova aba.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
