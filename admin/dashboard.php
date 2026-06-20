<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle='Admin Dashboard';
$extraCss=['admin.css'];

$total_fac = $conn->query('SELECT COUNT(*) c FROM facilities')->fetch_assoc()['c'];
$total_users = $conn->query("SELECT COUNT(*) c FROM users WHERE role <> 'admin'")->fetch_assoc()['c'];
$total_res = $conn->query('SELECT COUNT(*) c FROM reservations')->fetch_assoc()['c'];
$pending = $conn->query("SELECT COUNT(*) c FROM reservations WHERE reservation_status='Pending'")->fetch_assoc()['c'];
$recent = $conn->query("SELECT r.*, f.facility_name, u.full_name FROM reservations r JOIN facilities f ON r.facility_id=f.facility_id JOIN users u ON r.user_id=u.user_id ORDER BY r.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

require __DIR__.'/../includes/header.php';
?>
<main class="page-wrap"><div class="container">
  <div class="page-header"><div><h1>Admin Dashboard</h1><p>Operational overview of UniSport.</p></div></div>
  <?php require __DIR__.'/tabs.php'; ?>
  <div class="stats-row">
    <div class="stat-card"><div class="stat-icon">🏟️</div><div class="stat-label">Facilities</div><div class="stat-val"><?= (int)$total_fac ?></div></div>
    <div class="stat-card"><div class="stat-icon">👤</div><div class="stat-label">Users</div><div class="stat-val"><?= (int)$total_users ?></div></div>
    <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-label">Reservations</div><div class="stat-val"><?= (int)$total_res ?></div></div>
    <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-label">Pending</div><div class="stat-val"><?= (int)$pending ?></div></div>
  </div>
  <h2 class="section-title">Recent Reservations</h2><div class="section-line"></div>
  <?php if (!$recent): ?>
    <div class="empty">No reservations yet.</div>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>ID</th><th>User</th><th>Facility</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['reservation_id'] ?></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= e($r['facility_name']) ?></td>
            <td><?= e(fmt_date($r['booking_date'])) ?></td>
            <td><?= e(fmt_time($r['start_time'])) ?> – <?= e(fmt_time($r['end_time'])) ?></td>
            <td><span class="status-badge <?= status_class($r['reservation_status']) ?>"><?= e($r['reservation_status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div></main>
<?php require __DIR__.'/../includes/footer.php'; ?>
