<?php
// Recuperar a conta pelo link enviado por e-mail: senha nova e, se quiser, telefone novo
// (o telefone novo é confirmado com código pelo WhatsApp, como no cadastro).
require_once __DIR__ . '/inc/funcoes.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$link  = buscar_link_email($token, 'recuperar');

$invalido = '';
if (!$link) {
    $invalido = 'Este link de recuperação é inválido. Confira se copiou o endereço inteiro do e-mail.';
} elseif ($link['situacao'] === 'usado') {
    $invalido = 'Este link já foi usado ou foi pedido um link mais novo. Cada link só pode ser usado uma vez.';
} elseif ($link['situacao'] === 'vencido') {
    $invalido = 'Este link venceu. O link de recuperação vale por ' . LINK_RECUPERAR_MIN . ' minutos.';
} elseif ($link['email_conta'] !== $link['email'] || !$link['email_confirmado_em']) {
    $invalido = 'Este link não vale mais, porque o e-mail da conta foi alterado.';
}

if ($invalido) {
    $titulo = 'Recuperar conta';
    require __DIR__ . '/inc/topo.php';
    ?>
    <section class="cartao formulario">
        <h1>Recuperar conta</h1>
        <div class="flash flash-erro"><?= e($invalido) ?></div>
        <a href="esqueci-senha.php" class="botao">Pedir um novo link</a>
        <p class="centro"><a href="login.php">Voltar para a entrada</a></p>
    </section>
    <?php
    require __DIR__ . '/inc/rodape.php';
    exit;
}

$usuarioId = (int) $link['usuario_id'];
$urlPagina = 'redefinir.php?token=' . $token;
// Troca de telefone esperando o código (guardada na sessão, ligada a este link)
$pend = $_SESSION['recuperar'][$link['id']] ?? null;
$erro = '';
$telefoneNovo = '';

/** Salva a senha (e o telefone novo), usa o link, encerra as outras sessões e entra na conta. */
function finalizar_recuperacao(array $link, string $senhaHash, ?string $telefone): string
{
    $pdo = db();
    $agora = date('Y-m-d H:i:s');
    try {
        $pdo->beginTransaction();
        // Usa o link (uma vez só, e só se ainda estiver no prazo)
        $st = $pdo->prepare('UPDATE links_email SET usado_em = ? WHERE id = ? AND usado_em IS NULL AND expira_em > ?');
        $st->execute([$agora, $link['id'], $agora]);
        if (!$st->rowCount()) {
            $pdo->rollBack();
            return 'Este link não vale mais (venceu ou já foi usado). Peça um novo.';
        }
        // Invalida qualquer outro link de recuperação da conta
        $pdo->prepare("UPDATE links_email SET usado_em = ? WHERE usuario_id = ? AND tipo = 'recuperar' AND usado_em IS NULL")
            ->execute([$agora, $link['usuario_id']]);
        // Senha nova (e telefone novo); sessao_versao + 1 encerra as sessões abertas em outros aparelhos
        if ($telefone !== null) {
            $pdo->prepare('UPDATE usuarios SET senha_hash = ?, telefone = ?, sessao_versao = sessao_versao + 1 WHERE id = ?')
                ->execute([$senhaHash, $telefone, $link['usuario_id']]);
        } else {
            $pdo->prepare('UPDATE usuarios SET senha_hash = ?, sessao_versao = sessao_versao + 1 WHERE id = ?')
                ->execute([$senhaHash, $link['usuario_id']]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (($e->errorInfo[1] ?? 0) != 1062) throw $e;
        return 'Este número já está em uso por outra conta.';
    }

    $st = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $st->execute([$link['usuario_id']]);
    $u = $st->fetch();
    unset($_SESSION['recuperar'], $_SESSION['codigo_teste']);
    iniciar_sessao($u);
    avisar('Sua conta foi atualizada.');
    header('Location: home.php');
    exit;
}

/** Gera e envia um código novo para o telefone da troca pendente. */
function enviar_codigo_recuperacao(array &$pend): bool
{
    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pend['codigo_hash'] = password_hash($codigo, PASSWORD_DEFAULT);
    $pend['tentativas']  = 0;
    $pend['expira']      = time() + CODIGO_VALIDADE_MIN * 60;
    $pend['enviado']     = time();
    if (enviar_codigo_whatsapp($pend['telefone'], $codigo)) return true;
    $pend['enviado'] = 0; // não enviou: libera o reenvio na hora
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $acao = $_POST['acao'] ?? 'salvar';

    if ($acao === 'salvar') {
        $senha        = $_POST['senha'] ?? '';
        $senha2       = $_POST['senha2'] ?? '';
        $telefoneNovo = limpar_telefone($_POST['telefone'] ?? '');

        if (strlen($senha) < 6) {
            $erro = 'A senha precisa ter pelo menos 6 caracteres.';
        } elseif ($senha !== $senha2) {
            $erro = 'As senhas não conferem.';
        } elseif ($telefoneNovo === '' || $telefoneNovo === $link['telefone']) {
            // Sem troca de telefone: salva só a senha
            $erro = finalizar_recuperacao($link, password_hash($senha, PASSWORD_DEFAULT), null);
        } elseif (!in_array(strlen($telefoneNovo), [10, 11], true)) {
            $erro = 'Informe o telefone novo com DDD (ex.: 49 99999-0000).';
        } else {
            $st = db()->prepare('SELECT 1 FROM usuarios WHERE telefone = ? AND id <> ?');
            $st->execute([$telefoneNovo, $usuarioId]);
            if ($st->fetchColumn()) {
                $erro = 'Este número já está em uso por outra conta.';
            } else {
                // Guarda a senha e o telefone e manda o código para o número novo
                $pend = ['senha_hash' => password_hash($senha, PASSWORD_DEFAULT), 'telefone' => $telefoneNovo];
                if (enviar_codigo_recuperacao($pend)) {
                    avisar('Enviamos um código pelo WhatsApp para o número novo.');
                } else {
                    avisar('Não foi possível enviar o código pelo WhatsApp. Toque em "Reenviar código".', 'erro');
                }
                $_SESSION['recuperar'] = [$link['id'] => $pend];
                header('Location: ' . $urlPagina);
                exit;
            }
        }
    } elseif ($acao === 'cancelar') {
        unset($_SESSION['recuperar']);
        header('Location: ' . $urlPagina);
        exit;
    } elseif ($pend && $acao === 'reenviar') {
        $espera = max(0, $pend['enviado'] + CODIGO_ESPERA_SEG - time());
        if ($espera > 0) {
            $erro = 'Aguarde ' . $espera . ' segundos para pedir um novo código.';
        } else {
            $ok = enviar_codigo_recuperacao($pend);
            $_SESSION['recuperar'][$link['id']] = $pend;
            if ($ok) {
                avisar('Enviamos um novo código.');
                header('Location: ' . $urlPagina);
                exit;
            }
            $erro = 'Não foi possível enviar o código pelo WhatsApp. Tente novamente.';
        }
    } elseif ($pend && $acao === 'codigo') {
        $codigo = preg_replace('/\D/', '', $_POST['codigo'] ?? '');
        if ($pend['expira'] < time()) {
            $erro = 'Este código expirou. Toque em "Reenviar código" para receber outro.';
        } elseif ($pend['tentativas'] >= CODIGO_MAX_ERROS) {
            $erro = 'Você errou o código ' . CODIGO_MAX_ERROS . ' vezes. Toque em "Reenviar código" para receber outro.';
        } else {
            $pend['tentativas']++;
            $_SESSION['recuperar'][$link['id']] = $pend;
            if (password_verify($codigo, $pend['codigo_hash'])) {
                // Confere de novo se o número continua livre (o UPDATE também confere)
                $erro = finalizar_recuperacao($link, $pend['senha_hash'], $pend['telefone']);
                if ($erro) unset($_SESSION['recuperar']);
                $pend = $_SESSION['recuperar'][$link['id']] ?? null;
            } else {
                $restam = CODIGO_MAX_ERROS - $pend['tentativas'];
                $erro = $restam > 0
                    ? 'Código incorreto. Você ainda tem ' . $restam . ($restam == 1 ? ' tentativa.' : ' tentativas.')
                    : 'Código incorreto. Você errou ' . CODIGO_MAX_ERROS . ' vezes: toque em "Reenviar código" para receber outro.';
            }
        }
    }
}

$espera      = $pend ? max(0, $pend['enviado'] + CODIGO_ESPERA_SEG - time()) : 0;
$codigoTeste = $pend && WHATSAPP_MODO !== 'api' ? ($_SESSION['codigo_teste'][$pend['telefone']] ?? '') : '';

$titulo = 'Recuperar conta';
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao formulario">
    <h1>Recuperar conta</h1>
    <p class="sub">Olá, <b><?= e($link['nome']) ?></b>!</p>

    <?php if ($erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endif; ?>

<?php if ($pend): ?>
    <p>Enviamos um código de 6 números para o WhatsApp do número novo
        <b><?= e(formatar_telefone($pend['telefone'])) ?></b>. Ele vale por <?= CODIGO_VALIDADE_MIN ?> minutos.</p>

    <?php if ($codigoTeste): ?>
        <div class="flash flash-aviso">Modo teste: seu código é <?= e($codigoTeste) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="acao" value="codigo">
        <label>Código
            <input type="text" name="codigo" class="campo-codigo" required inputmode="numeric" pattern="[0-9]{6}"
                   maxlength="6" autocomplete="one-time-code" placeholder="000000" autofocus>
        </label>
        <button type="submit" class="botao">Confirmar e salvar</button>
    </form>

    <form method="post" class="form-reenviar">
        <?= campo_csrf() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="acao" value="reenviar">
        <button type="submit" class="botao secundario" id="reenviar" data-espera="<?= $espera ?>" <?= $espera ? 'disabled' : '' ?>>
            Reenviar código
        </button>
    </form>

    <form method="post" class="centro form-linha">
        <?= campo_csrf() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="acao" value="cancelar">
        <button type="submit" class="link-botao">Número errado? Voltar e corrigir</button>
    </form>
    <script>
    // Contagem regressiva do botão "Reenviar código"
    (function () {
        var botao = document.getElementById('reenviar');
        var seg = parseInt(botao.dataset.espera, 10) || 0;
        function tique() {
            if (seg <= 0) { botao.disabled = false; botao.textContent = 'Reenviar código'; return; }
            botao.textContent = 'Reenviar código (' + seg + 's)';
            seg--;
            setTimeout(tique, 1000);
        }
        tique();
    })();
    </script>
<?php else: ?>
    <form method="post">
        <?= campo_csrf() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="acao" value="salvar">
        <label>Senha nova
            <input type="password" name="senha" required minlength="6" autocomplete="new-password">
        </label>
        <label>Repita a senha nova
            <input type="password" name="senha2" required minlength="6" autocomplete="new-password">
        </label>

        <span class="rotulo">Mudou de número? (opcional)</span>
        <p class="sub dica-conta">Número atual da conta: <b><?= e(formatar_telefone($link['telefone'])) ?></b>.
            Se você trocou de número, digite o novo abaixo. Vamos mandar um código pelo WhatsApp para confirmar.</p>
        <label>Telefone novo (com DDD)
            <input type="tel" name="telefone" value="<?= e($telefoneNovo) ?>" inputmode="numeric" placeholder="Deixe em branco para manter o atual" autocomplete="tel">
        </label>
        <button type="submit" class="botao">Salvar</button>
    </form>
<?php endif; ?>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
