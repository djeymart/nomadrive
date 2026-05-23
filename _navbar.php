<?php
$_nd_page  = $nd_page ?? '';
$_nd_super = !empty($_SESSION['nd_settings_unlocked']);
?>
<style>
.nd-nav { background: #1e293b; border-bottom: 1px solid #334155; padding: 0 24px; display: flex; align-items: center; height: 52px; font-family: system-ui, -apple-system, sans-serif; position: relative; z-index: 100; }
.nd-nav-brand { font-size: 14px; font-weight: 800; color: #fff; letter-spacing: 1px; text-decoration: none; padding-right: 24px; border-right: 1px solid #334155; margin-right: 8px; flex-shrink: 0; }
.nd-nav-links { display: flex; align-items: center; flex: 1; }
.nd-nav-link { display: flex; align-items: center; height: 52px; padding: 0 14px; font-size: 13px; font-weight: 500; color: #94a3b8; text-decoration: none; border-bottom: 2px solid transparent; white-space: nowrap; box-sizing: border-box; }
.nd-nav-link:hover { color: #e2e8f0; border-bottom-color: #475569; }
.nd-nav-link.nd-active { color: #fff; border-bottom-color: #6366f1; }
.nd-nav-right { display: flex; align-items: center; gap: 12px; margin-left: auto; }
.nd-super-badge { font-size: 10px; font-weight: 700; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 8px; flex-shrink: 0; }
.nd-nav-logout { background: none; border: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 500; color: #64748b; padding: 6px 12px; border-radius: 6px; line-height: 1; }
.nd-nav-logout:hover { background: #334155; color: #e2e8f0; }
</style>
<nav class="nd-nav">
  <a class="nd-nav-brand" href="dashboard.php">NOMADRIVE</a>
  <div class="nd-nav-links">
    <a href="dashboard.php" class="nd-nav-link <?= $_nd_page === 'dashboard' ? 'nd-active' : '' ?>">Dashboard</a>
    <a href="manage.php"    class="nd-nav-link <?= $_nd_page === 'manage'    ? 'nd-active' : '' ?>">Gestion</a>
    <a href="settings.php"  class="nd-nav-link <?= $_nd_page === 'settings'  ? 'nd-active' : '' ?>">Paramètres</a>
  </div>
  <div class="nd-nav-right">
    <?php if ($_nd_super): ?>
      <span class="nd-super-badge">MADI ADMIN</span>
    <?php endif; ?>
    <form method="POST" action="dashboard.php" style="margin:0">
      <input type="hidden" name="action" value="logout">
      <button type="submit" class="nd-nav-logout">Déconnexion</button>
    </form>
  </div>
</nav>
