<?php
// Ações sobre publicações: encerrar, reabrir ou excluir (dono) e remover (admin da comunidade ou do site)
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mural.php');
    exit;
}
validar_csrf();

$id   = (int) ($_POST['id'] ?? 0);
$acao = $_POST['acao'] ?? '';

// O dono só mexe nas próprias publicações e só se ainda for membro da comunidade delas
$doDono = 'id = ? AND usuario_id = ? AND comunidade_id IN (SELECT comunidade_id FROM membros WHERE usuario_id = ?)';
$params = [$id, $usuario['id'], $usuario['id']];

switch ($acao) {
    case 'encerrar':
        $st = db()->prepare("UPDATE publicacoes SET ativo = 0 WHERE $doDono");
        $st->execute($params);
        avisar($st->rowCount() ? 'Publicação marcada como encerrada.' : 'Publicação não encontrada.', $st->rowCount() ? 'ok' : 'erro');
        break;
    case 'reabrir':
        $st = db()->prepare("UPDATE publicacoes SET ativo = 1 WHERE $doDono");
        $st->execute($params);
        avisar($st->rowCount() ? 'Publicação reaberta.' : 'Publicação não encontrada.', $st->rowCount() ? 'ok' : 'erro');
        break;
    case 'excluir':
        $st = db()->prepare("DELETE FROM publicacoes WHERE $doDono");
        $st->execute($params);
        avisar($st->rowCount() ? 'Publicação excluída.' : 'Publicação não encontrada.', $st->rowCount() ? 'ok' : 'erro');
        break;
    case 'remover':
        // Administrador do site remove qualquer uma; admin da comunidade, só as da comunidade dele
        if (admin_do_site($usuario['id'])) {
            $st = db()->prepare('DELETE FROM publicacoes WHERE id = ?');
            $st->execute([$id]);
        } else {
            $st = db()->prepare("DELETE p FROM publicacoes p
                                 JOIN membros m ON m.comunidade_id = p.comunidade_id AND m.usuario_id = ? AND m.papel = 'admin'
                                 WHERE p.id = ?");
            $st->execute([$usuario['id'], $id]);
        }
        avisar($st->rowCount() ? 'Publicação removida da comunidade.' : 'Você não pode remover esta publicação.', $st->rowCount() ? 'ok' : 'erro');
        header('Location: mural.php');
        exit;
}

header('Location: mural.php?minhas=1');
exit;
