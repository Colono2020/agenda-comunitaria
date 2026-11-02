<?php
// Tela de escolha de comunidade (primeira página depois de entrar no app)
require_once __DIR__ . '/inc/funcoes.php';
$usuario = exigir_login();

$atual       = comunidade_atual($usuario['id']);
$comunidades = minhas_comunidades($usuario['id']);

$titulo = 'Escolha uma comunidade';
require __DIR__ . '/inc/topo.php';
?>
<?php if ($comunidades): ?>
    <h1 class="titulo-pagina">Escolha uma comunidade</h1>

    <?php foreach ($comunidades as $c): ?>
    <article class="cartao comunidade-item">
        <div class="pub-cabeca">
            <?php if ($c['papel'] === 'admin'): ?><span class="etiqueta admin">Admin</span><?php endif; ?>
            <?php if ($atual && $c['id'] === $atual['id']): ?><span class="etiqueta">Aberta agora</span><?php endif; ?>
        </div>
        <h3><?= e($c['nome']) ?></h3>
        <?php if ($c['descricao']): ?><p class="sub"><?= e($c['descricao']) ?></p><?php endif; ?>
        <div class="pub-acoes">
            <a href="mural.php?comunidade=<?= $c['id'] ?>" class="botao">Abrir mural</a>
            <a href="comunidade.php?id=<?= $c['id'] ?>" class="botao secundario">Detalhes</a>
        </div>
    </article>
    <?php endforeach; ?>

    <p class="sub centro ou">ou</p>
<?php else: ?>
    <section class="cartao boas-vindas centro">
        <h1>Olá, <?= e(explode(' ', $usuario['nome'])[0]) ?>!</h1>
        <p class="sub">O mural só aparece dentro de uma comunidade.
            Crie a sua ou entre em uma com o código e a senha que você recebeu.</p>
    </section>
<?php endif; ?>

<div class="pilha-botoes escolha-grande">
    <a href="criar-comunidade.php" class="botao">＋ Criar comunidade</a>
    <a href="entrar.php" class="botao secundario">🔑 Entrar em uma comunidade</a>
</div>
<?php require __DIR__ . '/inc/rodape.php'; ?>
