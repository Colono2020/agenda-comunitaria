<?php
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();

$atual = exigir_comunidade($usuario['id']); // sem comunidade escolhida, vai para a tela de escolha
if ($atual['papel'] === 'site') {
    // O administrador do site vê e modera qualquer comunidade, mas só publica nas que participa
    avisar('Para publicar aqui, entre na comunidade como membro.', 'erro');
    header('Location: mural.php');
    exit;
}

$erros = [];
$dados = [
    'tipo'        => $_GET['tipo'] ?? 'aviso',
    'intencao'    => 'ofereco',
    'titulo'      => '',
    'descricao'   => '',
    'data_evento' => '',
];
if (!isset(TIPOS[$dados['tipo']])) $dados['tipo'] = 'aviso';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    // A comunidade vem do formulário (a que estava na tela) e é conferida no banco
    $comunidadeId = (int) ($_POST['comunidade_id'] ?? 0);
    if (!papel_na_comunidade($comunidadeId, $usuario['id'])) {
        http_response_code(403);
        exit('Você não participa dessa comunidade.');
    }
    // Se der erro no formulário, continua mostrando a mesma comunidade
    foreach (minhas_comunidades($usuario['id']) as $c) {
        if ($c['id'] === $comunidadeId) $atual = $c;
    }

    $dados['tipo']        = $_POST['tipo'] ?? '';
    $dados['intencao']    = $_POST['intencao'] ?? '';
    $dados['titulo']      = trim($_POST['titulo'] ?? '');
    $dados['descricao']   = trim($_POST['descricao'] ?? '');
    $dados['data_evento'] = trim($_POST['data_evento'] ?? '');

    if (!isset(TIPOS[$dados['tipo']]))   $erros[] = 'Escolha o tipo da publicação.';
    if (mb_strlen($dados['titulo']) < 3) $erros[] = 'Escreva um título.';
    if ($dados['descricao'] === '')      $erros[] = 'Escreva a descrição.';

    $intencao = null;
    if (in_array($dados['tipo'], TIPOS_COM_INTENCAO, true)) {
        $intencao = in_array($dados['intencao'], ['ofereco', 'preciso'], true) ? $dados['intencao'] : null;
        if (!$intencao) $erros[] = 'Informe se você oferece ou precisa.';
    }

    $dataEvento = null;
    if ($dados['data_evento'] !== '') {
        $ts = strtotime($dados['data_evento']);
        if ($ts === false) $erros[] = 'Data inválida.';
        else $dataEvento = date('Y-m-d H:i:s', $ts);
    } elseif ($dados['tipo'] === 'evento') {
        $erros[] = 'Informe a data e hora do evento.';
    }

    if (!$erros) {
        $st = db()->prepare(
            'INSERT INTO publicacoes (usuario_id, comunidade_id, tipo, intencao, titulo, descricao, data_evento)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$usuario['id'], $comunidadeId, $dados['tipo'], $intencao, $dados['titulo'], $dados['descricao'], $dataEvento]);
        avisar('Publicação feita! Agora a comunidade pode ver.');
        $_SESSION['comunidade_id'] = $comunidadeId;
        header('Location: mural.php');
        exit;
    }
}

$titulo = 'Nova publicação';
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao formulario">
    <h1>Nova publicação</h1>
    <p class="sub">Publicando em: <b>🏘️ <?= e($atual['nome']) ?></b></p>

    <?php foreach ($erros as $erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post" id="form-publicar">
        <?= campo_csrf() ?>
        <input type="hidden" name="comunidade_id" value="<?= $atual['id'] ?>">

        <span class="rotulo">O que você quer publicar?</span>
        <div class="escolha-tipo">
            <?php foreach (TIPOS as $chave => [$nome, $icone]): ?>
                <label class="opcao-tipo">
                    <input type="radio" name="tipo" value="<?= $chave ?>" <?= $dados['tipo'] === $chave ? 'checked' : '' ?>>
                    <span><b><?= $icone ?></b><?= $nome ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <div id="bloco-intencao">
            <span class="rotulo">Você…</span>
            <div class="escolha-intencao">
                <label><input type="radio" name="intencao" value="ofereco" <?= $dados['intencao'] !== 'preciso' ? 'checked' : '' ?>><span>Ofereço / Tenho</span></label>
                <label><input type="radio" name="intencao" value="preciso" <?= $dados['intencao'] === 'preciso' ? 'checked' : '' ?>><span>Preciso / Procuro</span></label>
            </div>
        </div>

        <label>Título
            <input type="text" name="titulo" value="<?= e($dados['titulo']) ?>" required maxlength="120" id="campo-titulo">
        </label>

        <label>Descrição
            <textarea name="descricao" rows="4" required id="campo-descricao"><?= e($dados['descricao']) ?></textarea>
        </label>

        <label><span id="rotulo-data">Data (opcional)</span>
            <input type="datetime-local" name="data_evento" value="<?= e($dados['data_evento']) ?>">
        </label>

        <button type="submit" class="botao">Publicar</button>
    </form>
</section>

<script>
// Mostra "Ofereço/Preciso" só nos tipos que fazem sentido e ajusta os exemplos
const comIntencao = <?= json_encode(TIPOS_COM_INTENCAO) ?>;
const exemplos = {
    aviso:      ['Ex.: Estrada da linha interditada', 'Ex.: A estrada está interditada perto da ponte até quinta.'],
    produto:    ['Ex.: 20 caixas de tomate', 'Ex.: Tenho 20 caixas de tomate disponíveis para venda ou troca.'],
    emprestimo: ['Ex.: Preciso de um pulverizador', 'Ex.: Preciso emprestado um pulverizador para sexta-feira.'],
    servico:    ['Ex.: Trator disponível para serviço', 'Ex.: Faço serviço de lavração e gradeação na região.'],
    evento:     ['Ex.: Reunião da associação', 'Ex.: Reunião da associação no salão da comunidade.']
};
function atualizar() {
    const tipo = document.querySelector('input[name=tipo]:checked').value;
    document.getElementById('bloco-intencao').style.display = comIntencao.includes(tipo) ? '' : 'none';
    document.getElementById('rotulo-data').textContent =
        tipo === 'evento' ? 'Data e hora do evento' :
        tipo === 'emprestimo' ? 'Para quando? (opcional)' : 'Data (opcional)';
    document.getElementById('campo-titulo').placeholder = exemplos[tipo][0];
    document.getElementById('campo-descricao').placeholder = exemplos[tipo][1];
}
document.querySelectorAll('input[name=tipo]').forEach(r => r.addEventListener('change', atualizar));
atualizar();
</script>
<?php require __DIR__ . '/inc/rodape.php'; ?>
