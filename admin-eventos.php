<?php
// Eventos do agro (Home): cadastrar, editar e excluir. Só para o administrador do site.
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();
exigir_admin_do_site($usuario['id']);

$erros = [];
$vazio = ['id' => 0, 'titulo' => '', 'descricao' => '', 'local' => '', 'cidade' => '', 'uf' => '', 'data_inicio' => '', 'data_fim' => '', 'link' => ''];
$dados = $vazio;

/** Valor do banco para o campo datetime-local. */
function para_campo(?string $data): string
{
    return $data ? date('Y-m-d\TH:i', strtotime($data)) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'excluir') {
        $st = db()->prepare('DELETE FROM eventos_gerais WHERE id = ?');
        $st->execute([(int) ($_POST['id'] ?? 0)]);
        avisar($st->rowCount() ? 'Evento excluído.' : 'Evento não encontrado.', $st->rowCount() ? 'ok' : 'erro');
        header('Location: admin-eventos.php');
        exit;
    }

    if ($acao === 'salvar') {
        foreach (array_keys($vazio) as $campo) {
            $dados[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }
        $dados['id'] = (int) $dados['id'];
        $dados['uf'] = strtoupper($dados['uf']);

        $inicio = $dados['data_inicio'] !== '' ? strtotime($dados['data_inicio']) : false;
        $fim    = $dados['data_fim'] !== '' ? strtotime($dados['data_fim']) : null;

        if (mb_strlen($dados['titulo']) < 3 || mb_strlen($dados['titulo']) > 150) $erros[] = 'O título precisa ter entre 3 e 150 caracteres.';
        if ($dados['descricao'] === '')                                          $erros[] = 'Escreva a descrição.';
        if ($dados['local'] === '' || mb_strlen($dados['local']) > 150)          $erros[] = 'Informe o local (até 150 caracteres).';
        if ($dados['cidade'] === '' || mb_strlen($dados['cidade']) > 100)        $erros[] = 'Informe a cidade.';
        if (!isset(UFS[$dados['uf']]) || $dados['uf'] === 'BR')                  $erros[] = 'Escolha o estado.';
        if (!$inicio)                                                            $erros[] = 'Informe a data e hora de início.';
        if ($fim === false)                                                      $erros[] = 'Data de término inválida.';
        if ($inicio && $fim && $fim < $inicio)                                   $erros[] = 'O término não pode ser antes do início.';
        if ($dados['link'] !== '' && (!preg_match('#^https?://#i', $dados['link']) || !filter_var($dados['link'], FILTER_VALIDATE_URL) || strlen($dados['link']) > 500)) {
            $erros[] = 'O link precisa começar com http:// ou https://.';
        }

        if (!$erros) {
            $valores = [
                $dados['titulo'], $dados['descricao'], $dados['local'], $dados['cidade'], $dados['uf'],
                date('Y-m-d H:i:s', $inicio), $fim ? date('Y-m-d H:i:s', $fim) : null, $dados['link'] ?: null,
            ];
            if ($dados['id']) {
                $st = db()->prepare('UPDATE eventos_gerais SET titulo = ?, descricao = ?, local = ?, cidade = ?, uf = ?,
                                     data_inicio = ?, data_fim = ?, link = ? WHERE id = ?');
                $st->execute([...$valores, $dados['id']]);
                avisar('Evento atualizado.');
            } else {
                $st = db()->prepare('INSERT INTO eventos_gerais (titulo, descricao, local, cidade, uf, data_inicio, data_fim, link, criado_por)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $st->execute([...$valores, $usuario['id']]);
                avisar('Evento cadastrado.');
            }
            header('Location: admin-eventos.php');
            exit;
        }
    }
} elseif (isset($_GET['editar'])) {
    $st = db()->prepare('SELECT * FROM eventos_gerais WHERE id = ?');
    $st->execute([(int) $_GET['editar']]);
    if ($ev = $st->fetch()) {
        $dados = array_merge($vazio, array_intersect_key($ev, $vazio));
        $dados['data_inicio'] = para_campo($ev['data_inicio']);
        $dados['data_fim']    = para_campo($ev['data_fim']);
        $dados['link']        = (string) $ev['link'];
    }
}

// Futuros primeiro (do mais próximo ao mais distante), depois os que já passaram
$st = db()->prepare('SELECT * FROM eventos_gerais
                     ORDER BY COALESCE(data_fim, data_inicio) < ? , data_inicio');
$st->execute([date('Y-m-d 00:00:00')]);
$eventos = $st->fetchAll();
$hoje = date('Y-m-d 00:00:00');

$titulo = 'Eventos do agro (admin)';
require __DIR__ . '/inc/topo.php';
?>
<h1 class="titulo-pagina">🛠 Eventos do agro</h1>
<p class="sub">Estes eventos aparecem na Home de todos os usuários do estado escolhido.</p>

<section class="cartao formulario" id="formulario">
    <h2><?= $dados['id'] ? 'Editar evento' : 'Novo evento' ?></h2>

    <?php foreach ($erros as $erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= $dados['id'] ?>">
        <label>Título
            <input type="text" name="titulo" value="<?= e($dados['titulo']) ?>" required maxlength="150" placeholder="Ex.: Feira da Agricultura Familiar">
        </label>
        <label>Descrição
            <textarea name="descricao" rows="3" required><?= e($dados['descricao']) ?></textarea>
        </label>
        <label>Local
            <input type="text" name="local" value="<?= e($dados['local']) ?>" required maxlength="150" placeholder="Ex.: Parque de Exposições">
        </label>
        <div class="duas-colunas">
            <label>Cidade
                <input type="text" name="cidade" value="<?= e($dados['cidade']) ?>" required maxlength="100">
            </label>
            <label>Estado
                <select name="uf" required>
                    <option value="">Escolha…</option>
                    <?php foreach (UFS as $sigla => $nome): if ($sigla === 'BR') continue; ?>
                        <option value="<?= $sigla ?>" <?= $sigla === $dados['uf'] ? 'selected' : '' ?>><?= e($nome) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Início
            <input type="datetime-local" name="data_inicio" value="<?= e($dados['data_inicio']) ?>" required>
        </label>
        <label>Término (opcional)
            <input type="datetime-local" name="data_fim" value="<?= e($dados['data_fim']) ?>">
        </label>
        <label>Link para mais informações (opcional)
            <input type="url" name="link" value="<?= e($dados['link']) ?>" maxlength="500" placeholder="https://…">
        </label>
        <button type="submit" class="botao"><?= $dados['id'] ? 'Salvar alterações' : 'Cadastrar evento' ?></button>
    </form>
    <?php if ($dados['id']): ?><p class="centro"><a href="admin-eventos.php">Cancelar edição</a></p><?php endif; ?>
</section>

<h2 class="titulo-pagina">Eventos cadastrados (<?= count($eventos) ?>)</h2>
<?php if (!$eventos): ?><div class="vazio"><p>Nenhum evento cadastrado.</p></div><?php endif; ?>

<?php foreach ($eventos as $ev):
    $passou = ($ev['data_fim'] ?? $ev['data_inicio']) < $hoje;
?>
<article class="cartao evento-geral <?= $passou ? 'encerrada' : '' ?>">
    <div class="pub-cabeca">
        <span class="etiqueta"><?= e($ev['uf']) ?></span>
        <?php if ($passou): ?><span class="etiqueta cinza">Já passou</span><?php endif; ?>
        <time><?= e(data_br($ev['data_inicio'])) ?></time>
    </div>
    <h3><?= e($ev['titulo']) ?></h3>
    <p class="autor">📍 <?= e($ev['local']) ?> · <?= e($ev['cidade']) ?>/<?= e($ev['uf']) ?></p>
    <div class="pub-acoes">
        <a href="admin-eventos.php?editar=<?= $ev['id'] ?>#formulario" class="botao secundario">✏️ Editar</a>
        <form method="post">
            <?= campo_csrf() ?>
            <input type="hidden" name="id" value="<?= $ev['id'] ?>">
            <button name="acao" value="excluir" class="botao perigo"
                    onclick="return confirm(<?= e(json_encode('Excluir o evento "' . $ev['titulo'] . '"?')) ?>)">🗑 Excluir</button>
        </form>
    </div>
</article>
<?php endforeach; ?>
<?php require __DIR__ . '/inc/rodape.php'; ?>
