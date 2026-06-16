<?php
$flash = getFlash();
?>
<header class="admin-topbar">
    <div class="topbar-left">
        <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
        <span class="page-title"><?= $pageTitle ?? 'Admin Panel' ?></span>
    </div>
    <div class="topbar-right">
        <span class="admin-name">👑 <?= htmlspecialchars($_SESSION['user']['name']) ?></span>
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</header>
<?php if ($flash): ?>
<div class="flash-msg flash-<?= $flash['type'] ?>">
    <?= htmlspecialchars($flash['message']) ?>
    <button onclick="this.parentElement.remove()">✕</button>
</div>
<?php endif; ?>
<script>
function toggleSidebar() {
    document.querySelector('.admin-sidebar').classList.toggle('open');
}
</script>
