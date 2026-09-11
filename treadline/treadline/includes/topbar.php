<?php
$__u = current_user();
$__unread = (int) db()->query("SELECT COUNT(*) c FROM alerts WHERE is_read=0")->fetch()['c'];
?>
<header class="tl-topbar">
  <button class="tl-menu-toggle" onclick="document.getElementById('tlSidebar').classList.toggle('open')">&#9776;</button>
  <form class="tl-search" action="search.php" method="get">
    <span class="ic">&#128269;</span>
    <input type="text" name="q" placeholder="Search registration, serial, branding code..." value="<?= e($_GET['q'] ?? '') ?>">
  </form>
  <div class="spacer"></div>
  <button class="tl-icon-btn" title="Scan Tyre" onclick="location.href='scan.php'">&#128247;</button>
  <button class="tl-icon-btn" title="Notifications" onclick="location.href='alerts.php'">
    &#128276;
    <?php if ($__unread > 0): ?><span class="dot"><?= $__unread ?></span><?php endif; ?>
  </button>
  <button class="btn btn-accent" onclick="location.href='quick_add.php'">+ Quick Add</button>
  <div class="tl-profile">
    <div class="tl-avatar"><?= e(strtoupper(substr($__u['full_name'] ?? 'U',0,1))) ?></div>
    <div>
      <div class="name"><?= e($__u['full_name'] ?? '') ?></div>
      <div class="role"><?= e($__u['role_name'] ?? '') ?><?= !empty($__u['branch_name']) ? ' · '.e($__u['branch_name']) : '' ?></div>
    </div>
    <a href="logout.php" class="btn btn-outline btn-sm" style="margin-left:8px;">Logout</a>
  </div>
</header>
