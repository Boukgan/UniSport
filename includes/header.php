<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db_connection.php';
$current = basename($_SERVER['SCRIPT_NAME']);
$role    = current_user_role();
$unread  = 0; $recentNotifs = [];
if (is_logged_in()) {
    $unread = unread_notification_count($conn, current_user_id());
    $recentNotifs = recent_notifications($conn, current_user_id(), 8);
}
$darkPref = $_SESSION['dark_mode'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $darkPref ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' – UniSport' : 'UniSport – UTeM Facility Reservation' ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="stylesheet" href="<?= base_url('css/style.css') ?>">
<?php if (!empty($extraCss)) foreach ((array)$extraCss as $c): ?>
  <link rel="stylesheet" href="<?= base_url('css/' . $c) ?>">
<?php endforeach; ?>
<script>
(function(){
  try{
    var saved = localStorage.getItem('unisport-theme');
    if(saved){ document.documentElement.setAttribute('data-theme', saved); }
  }catch(e){}
})();
</script>
</head>
<body>
<nav data-base="<?= e(base_url()) ?>">
  <div class="nav-left">
    <a class="nav-logo" href="<?= base_url($role === 'admin' ? 'admin/dashboard.php' : 'index.php') ?>">Uni<span>Sport</span></a>
    <?php if ($role !== 'admin'): ?>
      <a class="nav-btn <?= $current === 'index.php' ? 'active' : '' ?>" href="<?= base_url('index.php') ?>">Home</a>
      <a class="nav-btn <?= $current === 'dashboard.php' ? 'active' : '' ?>" href="<?= base_url('dashboard.php') ?>">Facilities</a>
      <?php if (is_logged_in()): ?>
        <a class="nav-btn <?= $current === 'my_bookings.php' ? 'active' : '' ?>" href="<?= base_url('my_bookings.php') ?>">My Reservations</a>
      <?php endif; ?>
      <a class="nav-btn <?= $current === 'help_center.php' ? 'active' : '' ?>" href="<?= base_url('help_center.php') ?>">Help</a>
    <?php else: ?>
      <a class="nav-btn active" href="<?= base_url('admin/dashboard.php') ?>">Dashboard</a>
    <?php endif; ?>
  </div>
  <div class="nav-right">
    <button type="button" class="nav-icon-btn" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
      <span class="theme-icon-light">☀️</span>
      <span class="theme-icon-dark">🌙</span>
    </button>
    <?php if (is_logged_in()): ?>
      <div class="nav-notif" id="notifWrap">
        <button type="button" class="nav-icon-btn" id="notifBtn" aria-label="Notifications">
          🔔
          <span class="notif-badge" id="notifBadge" style="<?= $unread>0?'':'display:none' ?>"><?= (int)$unread ?></span>
        </button>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-head">
            <strong>Notifications</strong>
            <button type="button" class="notif-mark-all" id="notifMarkAll">Mark all read</button>
          </div>
          <div class="notif-list" id="notifList">
            <?php if (empty($recentNotifs)): ?>
              <div class="notif-empty">No notifications yet.</div>
            <?php else: foreach ($recentNotifs as $n): ?>
              <div class="notif-item <?= $n['is_read']?'read':'unread' ?>" data-id="<?= (int)$n['notification_id'] ?>">
                <div class="notif-title"><?= e($n['title']) ?></div>
                <div class="notif-msg"><?= e($n['message']) ?></div>
                <div class="notif-time"><?= e(date('d M Y · g:i A', strtotime($n['created_at']))) ?></div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
      <div class="nav-user" onclick="toggleUserMenu(event)">
        <div class="nav-avatar"><?= strtoupper(substr(current_user_name(),0,1)) ?></div>
        <span class="nav-name"><?= e(current_user_name()) ?></span>
        <div class="user-dropdown" id="userDropdown">
          <?php if ($role !== 'admin'): ?>
            <a class="dd-item" href="<?= base_url('profile.php') ?>"><span class="dd-icon">👤</span> Profile</a>
            <a class="dd-item" href="<?= base_url('my_bookings.php') ?>"><span class="dd-icon">📋</span> My Reservations</a>
            <a class="dd-item" href="<?= base_url('notifications.php') ?>"><span class="dd-icon">🔔</span> All Notifications</a>
          <?php else: ?>
            <a class="dd-item" href="<?= base_url('admin/dashboard.php') ?>"><span class="dd-icon">📊</span> Admin Dashboard</a>
          <?php endif; ?>
          <a class="dd-item danger" href="<?= base_url('logout.php') ?>"><span class="dd-icon">↩</span> Logout</a>
        </div>
      </div>
    <?php else: ?>
      <a class="nav-btn" href="<?= base_url('login.php') ?>">Login</a>
      <a class="nav-btn" href="<?= base_url('register.php') ?>">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<?php if ($msg = flash('success')): ?>
  <div class="toast toast-success"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
  <div class="toast toast-error"><?= e($msg) ?></div>
<?php endif; ?>
