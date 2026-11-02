<?php
// Entrar em uma comunidade: pelo link de convite (?c=CODIGO) ou digitando o código
require_once __DIR__ . '/inc/funcoes.php';

$usuario  = usuario_logado();
$peloLink = isset($_GET['c']);
$codigo   = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['c'] ?? $_POST['codigo'] ?? ''));
$erro     = '';

/** Comunidade pelo código (ou null). */
function comunidade_pelo_codigo(string $codigo): ?array
{
    if ($codigo === '') return null;
    $st = db()->prepare('SELECT * FROM comunidades WHERE codigo = ?');
    $st->execute([$codigo]);
    return $st->fetch() ?: null;
}

$comunidade = $peloLink ? comunidade_pelo_codigo($codigo) : null;

// Sem login: guarda o convite e pede para entrar ou criar conta primeiro
if (!$usuario) {
    if (!$peloLink) {
        header('Location: login.php');
        exit;
    }
    $_SESSION['voltar_para'] = 'entrar.php?c=' . $codigo;
    $titulo = 'Convite';
    require __DIR__ . '/inc/topo.php';
    ?>
    <section class="cartao formulario centro">
        <?php if ($comunidade): ?>
            <p class="sub">Você foi convidado(a) para a comunidade</p>
            <h1><?= e($comunidade['nome']) ?></h1>
            <p class="sub">Para participar, primeiro entre na sua conta ou crie uma.</p>
        <?php else: ?>
            <h1>Convite inválido</h1>
            <div class="flash flash-erro">Este link de convite não vale mais. Peça um novo link ao administrador.</div>
        <?php endif; ?>
        <div class="pilha-botoes">
            <a href="login.php" class="botao">Entrar</a>
            <a href="cadastro.php" class="botao secundario">Criar conta</a>
        </div>
    </section>
    <?php
    require __DIR__ . '/inc/rodape.php';
    exit;
}

// Já é membro: só troca para essa comunidade
if ($comunidade && papel_na_comunidade($comunidade['id'], $usuario['id'])) {
    $_SESSION['comunidade_id'] = (int) $comunidade['id'];
    avisar('Você já participa da comunidade ' . $comunidade['nome'] . '.');
    header('Location: mural.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    $espera = acesso_bloqueado($usuario['id']);
    if ($espera) {
        $erro = 'Muitas tentativas erradas. Tente de novo em ' . $espera . ($espera == 1 ? ' minuto.' : ' minutos.');
    } else {
        $alvo = comunidade_pelo_codigo($codigo);

        if ($alvo && papel_na_comunidade($alvo['id'], $usuario['id'])) {
            $_SESSION['comunidade_id'] = (int) $alvo['id'];
            avisar('Você já participa da comunidade ' . $alvo['nome'] . '.');
            header('Location: mural.php');
            exit;
        }

        if ($alvo && password_verify($_POST['senha'] ?? '', $alvo['senha_hash'])) {
            db()->prepare("INSERT IGNORE INTO membros (comunidade_id, usuario_id, papel) VALUES (?, ?, 'membro')")
                ->execute([$alvo['id'], $usuario['id']]);
            limpar_erros_de_acesso($usuario['id']);
            $_SESSION['comunidade_id'] = (int) $alvo['id'];
            avisar('Bem-vindo(a) à comunidade ' . $alvo['nome'] . '!');
            header('Location: mural.php');
            exit;
        }

        // Código ou senha errados contam igual, para ninguém ficar adivinhando códigos
        registrar_erro_de_acesso($usuario['id']);
        $restam = ACESSO_MAX_ERROS - erros_de_acesso($usuario['id']);
        $erro = ($peloLink ? 'Senha incorreta.' : 'Código ou senha incorretos.')
            . ($restam > 0
                ? ' Você ainda tem ' . $restam . ($restam == 1 ? ' tentativa.' : ' tentativas.')
                : ' Muitas tentativas erradas: espere ' . ACESSO_JANELA_MIN . ' minutos para tentar de novo.');
    }
}

$titulo = 'Entrar em uma comunidade';
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao formulario">
    <?php if ($peloLink && !$comunidade): ?>
        <h1>Convite inválido</h1>
        <div class="flash flash-erro">Este link de convite não vale mais. Peça um novo link ao administrador.</div>
        <p class="centro"><a href="entrar.php">Entrar com código</a></p>
    <?php else: ?>
        <?php if ($comunidade): ?>
            <p class="sub">Você foi convidado(a) para a comunidade</p>
            <h1><?= e($comunidade['nome']) ?></h1>
            <?php if ($comunidade['descricao']): ?><p><?= e($comunidade['descricao']) ?></p><?php endif; ?>
        <?php else: ?>
            <h1>Entrar com código</h1>
            <p class="sub">Peça o código e a senha ao administrador da comunidade.</p>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="flash flash-erro"><?= e($erro) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= campo_csrf() ?>
            <?php if (!$peloLink): ?>
                <label>Código da comunidade
                    <input type="text" name="codigo" value="<?= e($codigo) ?>" required maxlength="8"
                           class="campo-codigo-comunidade" placeholder="Ex.: K7QX4MPA" autocapitalize="characters" autocomplete="off">
                </label>
            <?php endif; ?>
            <label>Senha da comunidade
                <input type="password" name="senha" required autocomplete="off" <?= $peloLink ? 'autofocus' : '' ?>>
            </label>
            <button type="submit" class="botao">Entrar na comunidade</button>
        </form>
        <p class="centro"><a href="comunidades.php">Cancelar</a></p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/inc/rodape.php'; ?>
