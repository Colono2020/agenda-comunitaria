<?php
// Minha conta: dados da conta, troca do nome, do e-mail e da localização (o telefone é o login e não muda aqui)
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();

$erro      = '';
$erroEmail = '';
$errosLocal = [];
$nome      = $usuario['nome'];
$novoEmail = '';
$local     = ['uf' => $usuario['uf'], 'cidade' => $usuario['cidade'], 'bairro' => $usuario['bairro'], 'referencia' => $usuario['referencia']];
if (!uf_valida($local['uf']) && uf_valida(uf_pelo_ddd($usuario['telefone']))) $local['uf'] = uf_pelo_ddd($usuario['telefone']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? 'nome';

    if ($acao === 'nome') {
        $nome = trim($_POST['nome'] ?? '');
        if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
            $erro = 'O nome precisa ter entre 3 e 100 caracteres.';
        } else {
            db()->prepare('UPDATE usuarios SET nome = ? WHERE id = ?')->execute([$nome, $usuario['id']]);
            $_SESSION['usuario']['nome'] = $nome; // o "Olá, ..." e o resto do app mudam na hora
            avisar('Nome atualizado!');
            header('Location: conta.php');
            exit;
        }
    } elseif ($acao === 'local') {
        [$local, $errosLocal] = ler_localizacao($_POST, true);
        if (!$errosLocal) {
            db()->prepare('UPDATE usuarios SET uf = ?, cidade = ?, bairro = ?, referencia = ? WHERE id = ?')
                ->execute([$local['uf'], $local['cidade'], $local['bairro'], $local['referencia'], $usuario['id']]);
            // A sessão muda na hora (mural, membros, Home); a Home passa a abrir no estado novo
            foreach ($local as $campo => $valor) $_SESSION['usuario'][$campo] = $valor;
            unset($_SESSION['uf']);
            avisar('Localização atualizada!');
            header('Location: conta.php#local');
            exit;
        }
    } elseif ($acao === 'email' || $acao === 'reenviar') {
        if ($acao === 'email') {
            $novoEmail = trim($_POST['email'] ?? '');
            $destino   = normalizar_email($novoEmail);
            if ($destino === '') {
                $erroEmail = 'Informe um e-mail válido (ex.: maria@gmail.com).';
            } elseif ($destino === $usuario['email'] && $usuario['email_ok']) {
                $erroEmail = 'Este já é o seu e-mail confirmado.';
            }
        } else {
            // Reenviar: para o e-mail que espera confirmação (troca pendente ou o e-mail ainda não confirmado)
            $pendente = confirmacao_pendente($usuario['id']);
            $destino  = $pendente['email'] ?? (!$usuario['email_ok'] ? (string) $usuario['email'] : '');
            if ($destino === '') $erroEmail = 'Não há e-mail esperando confirmação.';
        }

        if (!$erroEmail && email_em_uso($destino, $usuario['id'])) {
            $erroEmail = 'Este e-mail já está em uso em outra conta.';
        } elseif (!$erroEmail && links_na_ultima_hora($destino, 'confirmar') >= LINKS_POR_HORA) {
            $erroEmail = 'Já enviamos vários e-mails para este endereço. Espere 1 hora para pedir de novo.';
        } elseif (!$erroEmail && !email_configurado()) {
            $erroEmail = ERRO_EMAIL;
        }

        if (!$erroEmail) {
            if (!$usuario['email_ok'] && $destino !== $usuario['email']) {
                // Sem e-mail confirmado: o novo e-mail já fica na conta (ainda não confirmado).
                // Com e-mail confirmado: o antigo continua valendo até o novo ser confirmado.
                try {
                    db()->prepare('UPDATE usuarios SET email = ?, email_confirmado_em = NULL WHERE id = ?')
                        ->execute([$destino, $usuario['id']]);
                } catch (PDOException $e) {
                    if (($e->errorInfo[1] ?? 0) != 1062) throw $e;
                    $erroEmail = 'Este e-mail já está em uso em outra conta.';
                }
            }
            if (!$erroEmail) {
                if (enviar_confirmacao_email($usuario['id'], $usuario['nome'], $destino)) {
                    avisar('Enviamos um link para ' . $destino . '. Abra o e-mail e toque em "Confirmar meu e-mail". ' . DICA_SPAM);
                } else {
                    avisar(ERRO_EMAIL, 'erro');
                }
                header('Location: conta.php#email');
                exit;
            }
        }
    }
    $usuario = exigir_login(); // e-mail em dia, caso algo tenha mudado
}

$pendente  = confirmacao_pendente($usuario['id']);
$esperando = $pendente && ($pendente['email'] !== $usuario['email'] || !$usuario['email_ok']);
$semLink   = $usuario['email'] !== null && !$usuario['email_ok'] && !$pendente; // sem link válido (venceu ou não foi enviado)

$titulo = 'Minha conta';
require __DIR__ . '/inc/topo.php';
?>
<h1 class="titulo-pagina">Minha conta</h1>

<section class="cartao formulario">
    <?php if ($erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <input type="hidden" name="acao" value="nome">
        <label>Nome
            <input type="text" name="nome" value="<?= e($nome) ?>" required minlength="3" maxlength="100" autocomplete="name">
        </label>
        <button type="submit" class="botao">Salvar nome</button>
    </form>
</section>

<section class="cartao formulario" id="email">
    <h2>📧 E-mail</h2>
    <?php if ($erroEmail): ?>
        <div class="flash flash-erro"><?= e($erroEmail) ?></div>
    <?php endif; ?>

    <?php if ($usuario['email'] === null): ?>
        <p class="sub dica-conta">Você ainda não cadastrou um e-mail. Com ele confirmado, você consegue recuperar
            sua conta se esquecer a senha ou trocar de número.</p>
    <?php else: ?>
        <p class="email-atual"><b><?= e($usuario['email']) ?></b>
            <?php if ($usuario['email_ok']): ?>
                <span class="etiqueta admin">✅ Confirmado</span>
            <?php else: ?>
                <span class="etiqueta cinza">⏳ Não confirmado</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($esperando || $semLink): ?>
        <div class="flash flash-aviso">
            <?php if ($esperando): ?>
                Enviamos um link para <b><?= e($pendente['email']) ?></b>. Abra o e-mail e toque em
                "Confirmar meu e-mail". <?= e(DICA_SPAM) ?>
                <?php if ($usuario['email_ok']): ?><br>Até confirmar, continua valendo o e-mail <?= e($usuario['email']) ?>.<?php endif; ?>
            <?php else: ?>
                Seu e-mail ainda não foi confirmado. Toque no botão abaixo para receber o link de confirmação.
            <?php endif; ?>
        </div>
        <form method="post" class="form-linha">
            <?= campo_csrf() ?>
            <input type="hidden" name="acao" value="reenviar">
            <button type="submit" class="botao secundario">✉️ Reenviar link de confirmação</button>
        </form>
    <?php endif; ?>

    <form method="post" class="form-email">
        <?= campo_csrf() ?>
        <input type="hidden" name="acao" value="email">
        <label><?= $usuario['email'] === null ? 'Seu e-mail' : 'Trocar o e-mail' ?>
            <input type="email" name="email" value="<?= e($novoEmail) ?>" required maxlength="190"
                   placeholder="maria@gmail.com" autocomplete="email" inputmode="email">
        </label>
        <button type="submit" class="botao"><?= $usuario['email'] === null ? 'Cadastrar e-mail' : 'Trocar e-mail' ?></button>
    </form>
</section>

<section class="cartao formulario" id="local">
    <h2>📍 Minha localização</h2>
    <p class="local-atual"><b><?= e(local_formatado($usuario)) ?></b>
        <?php if ($usuario['referencia'] !== ''): ?><br><span class="sub">Referência: <?= e($usuario['referencia']) ?></span><?php endif; ?></p>
    <p class="sub dica-conta">Os membros das suas comunidades veem o bairro, a cidade e o estado nas suas publicações.
        O ponto de referência aparece só aqui.</p>

    <?php foreach ($errosLocal as $erroLocal): ?>
        <div class="flash flash-erro"><?= e($erroLocal) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <input type="hidden" name="acao" value="local">
        <?= campos_localizacao($local, true) ?>
        <button type="submit" class="botao">Salvar localização</button>
    </form>
</section>

<section class="cartao conta">
    <p>📞 <?= e(formatar_telefone($usuario['telefone'])) ?></p>
    <p class="sub dica-conta">O telefone é usado para entrar no app e foi confirmado pelo WhatsApp, por isso não pode ser alterado aqui.
        Se você trocou de número, use "Esqueci minha senha" na tela de entrada (é preciso ter o e-mail confirmado).</p>
</section>

<section class="cartao conta">
    <a href="sair.php" class="botao secundario">Sair do app</a>
    <a href="excluir-conta.php" class="botao perigo">🗑 Excluir minha conta</a>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
