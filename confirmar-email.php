<?php
// Confirma o e-mail da conta pelo link enviado por e-mail (funciona com ou sem login)
require_once __DIR__ . '/inc/funcoes.php';

$token = (string) ($_GET['token'] ?? '');
$link  = buscar_link_email($token, 'confirmar');
$volta = usuario_logado() ? 'conta.php#email' : 'login.php';

/** Volta para Minha conta (ou para o login) com um aviso. */
function sair_com_aviso(string $url, string $msg, string $tipo): void
{
    avisar($msg, $tipo);
    header('Location: ' . $url);
    exit;
}

if (!$link) {
    sair_com_aviso($volta, 'Link de confirmação inválido. Confira se copiou o endereço inteiro do e-mail.', 'erro');
}
if ($link['situacao'] === 'usado') {
    // Pode ser o mesmo link aberto de novo depois de confirmar
    if ($link['email_conta'] === $link['email'] && $link['email_confirmado_em']) {
        sair_com_aviso($volta, 'Este e-mail já está confirmado.', 'ok');
    }
    sair_com_aviso($volta, 'Este link não vale mais (já foi usado ou foi pedido um novo). Peça outro em Minha conta.', 'erro');
}
if ($link['situacao'] === 'vencido') {
    sair_com_aviso($volta, 'Este link venceu. Peça um novo em Minha conta.', 'erro');
}

// Confirma: o e-mail do link passa a ser o e-mail da conta
$pdo = db();
$agora = date('Y-m-d H:i:s');
try {
    $pdo->beginTransaction();
    // Marca o link como usado (só uma vez, mesmo com dois cliques ao mesmo tempo)
    $st = $pdo->prepare('UPDATE links_email SET usado_em = ? WHERE id = ? AND usado_em IS NULL');
    $st->execute([$agora, $link['id']]);
    if (!$st->rowCount()) {
        $pdo->rollBack();
        sair_com_aviso($volta, 'Este link já foi usado.', 'erro');
    }
    $pdo->prepare('UPDATE usuarios SET email = ?, email_confirmado_em = ? WHERE id = ?')
        ->execute([$link['email'], $agora, $link['usuario_id']]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (($e->errorInfo[1] ?? 0) != 1062) throw $e;
    // Outra conta passou a usar este e-mail depois que o link foi enviado
    sair_com_aviso($volta, 'Este e-mail já está em uso em outra conta. Cadastre outro e-mail em Minha conta.', 'erro');
}

sair_com_aviso($volta, 'E-mail confirmado! Agora você pode usá-lo para recuperar sua conta.', 'ok');
