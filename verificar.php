<?php
require_once __DIR__ . '/inc/funcoes.php';

if (usuario_logado()) {
    header('Location: home.php');
    exit;
}

// Telefone do cadastro pendente (guardado pelo cadastro.php)
$telefone = $_SESSION['verificar_telefone'] ?? '';
$v = $telefone !== '' ? buscar_verificacao($telefone) : null;
if (!$v) {
    unset($_SESSION['verificar_telefone']);
    header('Location: cadastro.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    if (($_POST['acao'] ?? '') === 'reenviar') {
        $espera = espera_reenvio($v);
        if ($espera > 0) {
            $erro = 'Aguarde ' . $espera . ' segundos para pedir um novo código.';
        } elseif (enviar_novo_codigo($telefone)) {
            avisar('Enviamos um novo código.');
            header('Location: verificar.php');
            exit;
        } else {
            $erro = 'Não foi possível enviar o código pelo WhatsApp. Tente novamente.';
        }
    } else {
        $codigo = preg_replace('/\D/', '', $_POST['codigo'] ?? '');

        if (!$v['expira_em'] || strtotime($v['expira_em']) < time()) {
            $erro = 'Este código expirou. Toque em "Reenviar código" para receber outro.';
        } else {
            // Conta a tentativa antes de conferir, para não passar do limite com envios simultâneos
            $st = db()->prepare('UPDATE verificacoes SET tentativas = tentativas + 1 WHERE id = ? AND tentativas < ?');
            $st->execute([$v['id'], CODIGO_MAX_ERROS]);

            if ($st->rowCount() === 0) {
                $erro = 'Você errou o código ' . CODIGO_MAX_ERROS . ' vezes. Toque em "Reenviar código" para receber outro.';
            } elseif (!password_verify($codigo, $v['codigo_hash'])) {
                $restam = CODIGO_MAX_ERROS - $v['tentativas'] - 1;
                $erro = $restam > 0
                    ? 'Código incorreto. Você ainda tem ' . $restam . ($restam == 1 ? ' tentativa.' : ' tentativas.')
                    : 'Código incorreto. Você errou ' . CODIGO_MAX_ERROS . ' vezes: toque em "Reenviar código" para receber outro.';
            } else {
                // Código certo: cria a conta e apaga o cadastro pendente
                $pdo = db();
                try {
                    $pdo->beginTransaction();
                    $st = $pdo->prepare('INSERT INTO usuarios (nome, telefone, localidade, senha_hash) VALUES (?, ?, ?, ?)');
                    $st->execute([$v['nome'], $telefone, $v['localidade'], $v['senha_hash']]);
                    $id = (int) $pdo->lastInsertId();
                    $pdo->prepare('DELETE FROM verificacoes WHERE id = ?')->execute([$v['id']]);
                    $pdo->commit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if (($e->errorInfo[1] ?? 0) != 1062) throw $e;
                    // Telefone já cadastrado (ex.: conta criada em outra aba)
                    $pdo->prepare('DELETE FROM verificacoes WHERE id = ?')->execute([$v['id']]);
                    unset($_SESSION['verificar_telefone'], $_SESSION['codigo_teste']);
                    avisar('Já existe uma conta com este telefone. Faça login.', 'erro');
                    header('Location: login.php');
                    exit;
                }

                unset($_SESSION['verificar_telefone'], $_SESSION['codigo_teste']);
                session_regenerate_id(true);
                $_SESSION['usuario'] = [
                    'id'         => $id,
                    'nome'       => $v['nome'],
                    'telefone'   => $telefone,
                    'localidade' => $v['localidade'],
                ];
                avisar('Bem-vindo(a), ' . $v['nome'] . '! Sua conta foi criada.');
                unset($_SESSION['comunidade_id'], $_SESSION['uf']); // comunidade e estado vêm de novo a cada login
                ir_para_volta(); // vai para o convite, se veio de um; senão, para a Home
            }
        }
    }

    $v = buscar_verificacao($telefone);
}

$espera      = espera_reenvio($v);
$codigoTeste = WHATSAPP_MODO !== 'api' ? ($_SESSION['codigo_teste'][$telefone] ?? '') : '';

$titulo = 'Confirmar telefone';
require __DIR__ . '/inc/topo.php';
?>
<section class="cartao formulario">
    <h1>Confirmar telefone</h1>
    <p class="sub">Enviamos um código de 6 números para o WhatsApp <b><?= e(formatar_telefone($telefone)) ?></b>.
        Ele vale por <?= CODIGO_VALIDADE_MIN ?> minutos.</p>

    <?php if ($codigoTeste): ?>
        <div class="flash flash-aviso">Modo teste: seu código é <?= e($codigoTeste) ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="flash flash-erro"><?= e($erro) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= campo_csrf() ?>
        <label>Código
            <input type="text" name="codigo" class="campo-codigo" required inputmode="numeric" pattern="[0-9]{6}"
                   maxlength="6" autocomplete="one-time-code" placeholder="000000" autofocus>
        </label>
        <button type="submit" class="botao">Confirmar e entrar</button>
    </form>

    <form method="post" class="form-reenviar">
        <?= campo_csrf() ?>
        <input type="hidden" name="acao" value="reenviar">
        <button type="submit" class="botao secundario" id="reenviar" data-espera="<?= $espera ?>" <?= $espera ? 'disabled' : '' ?>>
            Reenviar código
        </button>
    </form>

    <p class="centro">Telefone errado? <a href="cadastro.php">Corrigir dados</a></p>
</section>
<script>
// Contagem regressiva do botão "Reenviar código"
(function () {
    var botao = document.getElementById('reenviar');
    var seg = parseInt(botao.dataset.espera, 10) || 0;
    function tique() {
        if (seg <= 0) {
            botao.disabled = false;
            botao.textContent = 'Reenviar código';
            return;
        }
        botao.textContent = 'Reenviar código (' + seg + 's)';
        seg--;
        setTimeout(tique, 1000);
    }
    tique();
})();
</script>
<?php require __DIR__ . '/inc/rodape.php'; ?>
