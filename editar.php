<?php
// Editar uma publicação: só o autor, e só até 24 horas depois de publicar.
// Muda título, descrição, data e "Ofereço / Preciso". O tipo e a comunidade não mudam.
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();

$id     = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$voltar = ($_POST['voltar'] ?? $_GET['voltar'] ?? '') === 'minhas' ? 'minhas' : '';
$urlVolta = $voltar === 'minhas' ? 'mural.php?minhas=1' : 'mural.php';

/** Volta para o mural (ou "Minhas publicações") com um aviso. */
function voltar_com_aviso(string $url, string $msg, string $tipo = 'erro'): void
{
    avisar($msg, $tipo);
    header('Location: ' . $url);
    exit;
}

$pub = publicacao_do_autor($id, $usuario['id']);
if (!$pub) {
    // Não existe, é de outra pessoa ou o autor saiu da comunidade
    voltar_com_aviso($urlVolta, 'Você só pode editar as suas próprias publicações.');
}
if ($pub['seg_editar'] <= 0) {
    voltar_com_aviso($urlVolta, 'O prazo de 24 horas para editar esta publicação terminou.');
}

$comIntencao = in_array($pub['tipo'], TIPOS_COM_INTENCAO, true);
$erros = [];
$dados = [
    'intencao'    => $pub['intencao'] ?: 'ofereco',
    'titulo'      => $pub['titulo'],
    'descricao'   => $pub['descricao'],
    'data_evento' => $pub['data_evento'] ? date('Y-m-d\TH:i', strtotime($pub['data_evento'])) : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $dados['intencao']    = $_POST['intencao'] ?? '';
    $dados['titulo']      = trim($_POST['titulo'] ?? '');
    $dados['descricao']   = trim($_POST['descricao'] ?? '');
    $dados['data_evento'] = trim($_POST['data_evento'] ?? '');

    if (mb_strlen($dados['titulo']) < 3)   $erros[] = 'Escreva um título.';
    if (mb_strlen($dados['titulo']) > 120) $erros[] = 'O título pode ter no máximo 120 letras.';
    if ($dados['descricao'] === '')        $erros[] = 'Escreva a descrição.';

    $intencao = null;
    if ($comIntencao) {
        $intencao = in_array($dados['intencao'], ['ofereco', 'preciso'], true) ? $dados['intencao'] : null;
        if (!$intencao) $erros[] = 'Informe se você oferece ou precisa.';
    }

    $dataEvento = null;
    if ($dados['data_evento'] !== '') {
        $ts = strtotime($dados['data_evento']);
        if ($ts === false) $erros[] = 'Data inválida.';
        else $dataEvento = date('Y-m-d H:i:s', $ts);
    } elseif ($pub['tipo'] === 'evento') {
        $erros[] = 'Informe a data e hora do evento.';
    }

    if (!$erros) {
        $mudou = $dados['titulo'] !== $pub['titulo']
            || $dados['descricao'] !== $pub['descricao']
            || $intencao !== $pub['intencao']
            || $dataEvento !== $pub['data_evento'];

        if (!$mudou) {
            voltar_com_aviso($urlVolta . '#pub-' . $pub['id'], 'Nenhuma alteração foi feita.', 'ok');
        }

        // O prazo e o autor são conferidos de novo no próprio UPDATE
        // (a página pode ter ficado aberta e o prazo terminado antes de salvar)
        $st = db()->prepare(
            'UPDATE publicacoes p
             SET p.titulo = ?, p.descricao = ?, p.intencao = ?, p.data_evento = ?, p.editado_em = ?
             WHERE p.id = ? AND p.usuario_id = ? AND ' . SQL_SEG_EDITAR . ' > 0
               AND p.comunidade_id IN (SELECT comunidade_id FROM membros WHERE usuario_id = ?)'
        );
        $st->execute([
            $dados['titulo'], $dados['descricao'], $intencao, $dataEvento, date('Y-m-d H:i:s'),
            $pub['id'], $usuario['id'], $usuario['id'],
        ]);
        if (!$st->rowCount()) {
            voltar_com_aviso($urlVolta, 'O prazo de 24 horas para editar esta publicação terminou.');
        }
        voltar_com_aviso($urlVolta . '#pub-' . $pub['id'], 'Publicação alterada!', 'ok');
    }
}

[$nomeTipo, $icone] = TIPOS[$pub['tipo']];
$rotuloData = $pub['tipo'] === 'evento' ? 'Data e hora do evento'
    : ($pub['tipo'] === 'emprestimo' ? 'Para quando? (opcional)' : 'Data (opcional)');

$titulo = 'Editar publicação';
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao formulario">
    <h1>Editar publicação</h1>
    <p class="sub">Em: <b>🏘️ <?= e($pub['comunidade_nome']) ?></b> · ⏳ <?= e(texto_prazo_edicao($pub['seg_editar'])) ?></p>

    <?php foreach ($erros as $erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" action="editar.php" id="form-publicar">
        <?= campo_csrf() ?>
        <input type="hidden" name="id" value="<?= $pub['id'] ?>">
        <?php if ($voltar): ?><input type="hidden" name="voltar" value="<?= e($voltar) ?>"><?php endif; ?>

        <span class="rotulo">Tipo da publicação (não pode ser trocado)</span>
        <div class="escolha-tipo">
            <label class="opcao-tipo">
                <input type="radio" checked disabled>
                <span><b><?= $icone ?></b><?= $nomeTipo ?></span>
            </label>
        </div>

        <?php if ($comIntencao): ?>
        <div id="bloco-intencao">
            <span class="rotulo">Você…</span>
            <div class="escolha-intencao">
                <label><input type="radio" name="intencao" value="ofereco" <?= $dados['intencao'] !== 'preciso' ? 'checked' : '' ?>><span>Ofereço / Tenho</span></label>
                <label><input type="radio" name="intencao" value="preciso" <?= $dados['intencao'] === 'preciso' ? 'checked' : '' ?>><span>Preciso / Procuro</span></label>
            </div>
        </div>
        <?php endif; ?>

        <label>Título
            <input type="text" name="titulo" value="<?= e($dados['titulo']) ?>" required maxlength="120" id="campo-titulo">
        </label>

        <label>Descrição
            <textarea name="descricao" rows="4" required id="campo-descricao"><?= e($dados['descricao']) ?></textarea>
        </label>

        <label><span id="rotulo-data"><?= e($rotuloData) ?></span>
            <input type="datetime-local" name="data_evento" value="<?= e($dados['data_evento']) ?>" <?= $pub['tipo'] === 'evento' ? 'required' : '' ?>>
        </label>

        <button type="submit" class="botao">💾 Salvar alterações</button>
    </form>
    <p class="centro"><a href="<?= e($urlVolta . '#pub-' . $pub['id']) ?>">Cancelar edição</a></p>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
