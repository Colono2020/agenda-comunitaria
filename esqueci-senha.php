<?php
// Esqueci minha senha: envia um link de recuperação para o e-mail confirmado da conta.
// A resposta é sempre a mesma, exista ou não a conta, para ninguém descobrir quais e-mails estão cadastrados.
require_once __DIR__ . '/inc/funcoes.php';

const MSG_ENVIADO = 'Se este e-mail estiver cadastrado, enviamos um link para recuperar sua conta. ' . DICA_SPAM;

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $email   = trim($_POST['email'] ?? '');
    $destino = normalizar_email($email);

    if ($destino === '') {
        $erro = 'Informe um e-mail válido (ex.: maria@gmail.com).';
    } elseif (!email_configurado()) {
        // Envio não configurado: mostra o erro para qualquer e-mail (não revela se a conta existe)
        $erro = ERRO_EMAIL;
    } else {
        // Só e-mail confirmado recupera a conta
        $st = db()->prepare('SELECT id, nome FROM usuarios WHERE email = ? AND email_confirmado_em IS NOT NULL');
        $st->execute([$destino]);
        $conta = $st->fetch();

        // No máximo 3 links por e-mail por hora; acima disso a mensagem é a mesma, mas nada é enviado
        if ($conta && links_na_ultima_hora($destino, 'recuperar') < LINKS_POR_HORA) {
            $token  = criar_link_email((int) $conta['id'], 'recuperar', $destino, LINK_RECUPERAR_MIN * 60);
            $enviou = enviar_email(
                $destino, $conta['nome'],
                'Recuperar sua conta',
                'Recuperar sua conta',
                'Olá, ' . $conta['nome'] . '! Recebemos um pedido para recuperar sua conta da Agenda Comunitária. '
                    . 'Toque no botão abaixo para criar uma senha nova (e, se precisar, trocar o número de telefone). '
                    . 'O link vale por ' . LINK_RECUPERAR_MIN . ' minutos e só pode ser usado uma vez.',
                'Recuperar minha conta',
                url_do_site('redefinir.php?token=' . $token)
            );
            if (!$enviou) $erro = ERRO_EMAIL;
        }

        if (!$erro) {
            avisar(MSG_ENVIADO);
            header('Location: esqueci-senha.php');
            exit;
        }
    }
}

$titulo = 'Esqueci minha senha';
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao formulario">
    <h1>Esqueci minha senha</h1>
    <p class="sub">Digite o e-mail cadastrado na sua conta. Vamos enviar um link para você criar uma senha nova
        e, se tiver trocado de número, atualizar o telefone.</p>

    <?php if ($erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <label>E-mail
            <input type="email" name="email" value="<?= e($email) ?>" required maxlength="190"
                   placeholder="maria@gmail.com" autocomplete="email" inputmode="email" autofocus>
        </label>
        <button type="submit" class="botao">Enviar link</button>
    </form>
    <p class="centro"><a href="login.php">Voltar para a entrada</a></p>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
