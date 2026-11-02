</main>
<?php if ($eu = usuario_logado()):
    $minhasPub     = $pagina === 'mural.php' && !empty($_GET['minhas']);
    $naComunidade  = ($pagina === 'mural.php' && !$minhasPub)
                  || in_array($pagina, ['comunidades.php', 'comunidade.php', 'criar-comunidade.php', 'entrar.php', 'publicar.php'], true);
    $naAreaEu      = $minhasPub || in_array($pagina, ['conta.php', 'excluir-conta.php', 'admin-eventos.php', 'admin-comunidades.php', 'admin-usuarios.php'], true);
?>
<nav class="menu-baixo">
    <a href="home.php" class="<?= $pagina === 'home.php' ? 'ativo' : '' ?>">
        <span>🏠</span>Home
    </a>
    <a href="comunidades.php" class="<?= $naComunidade ? 'ativo' : '' ?>">
        <span>👥</span>Comunidade
    </a>
    <details class="menu-eu">
        <summary class="<?= $naAreaEu ? 'ativo' : '' ?>"><span>👤</span>Eu</summary>
        <div class="menu-eu-lista">
            <a href="mural.php?minhas=1">📝 Minhas publicações</a>
            <a href="comunidades.php">🏘️ Minhas comunidades</a>
            <a href="conta.php">⚙️ Minha conta</a>
            <?php if (admin_do_site($eu['id'])): ?>
                <span class="menu-eu-secao">🛠 Administração</span>
                <a href="admin-eventos.php" class="menu-eu-admin">📅 Eventos do agro</a>
                <a href="admin-comunidades.php" class="menu-eu-admin">👥 Comunidades</a>
                <a href="admin-usuarios.php" class="menu-eu-admin">👤 Usuários</a>
            <?php endif; ?>
        </div>
    </details>
</nav>
<script>
// Fecha o menu "Eu" ao tocar fora dele
document.addEventListener('click', function (ev) {
    var menu = document.querySelector('.menu-eu');
    if (menu && menu.open && !menu.contains(ev.target)) menu.open = false;
});
</script>
<?php endif; ?>
</body>
</html>
