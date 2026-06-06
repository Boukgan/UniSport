<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle='Reservation Monitoring';
$extraCss=['admin.css','dashboard.css'];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cancel') {
    $rid=(int)$_POST['reservation_id'];
    $stmt=$conn->prepare("UPDATE reservations SET reservation_status='Cancelled' WHERE reservation_id=?");
    $stmt->bind_param('i',$rid); $stmt->execute();
    $r=$conn->query("SELECT user_id FROM reservations WHERE reservation_id=$rid")->fetch_assoc();
    if ($r) notify($conn,$r['user_id'],'Reservation #'.$rid.' cancelled by admin.');
    flash('success','Reservation cancelled.');
    header('Location: '.base_url('admin/reservation_monitoring.php')); exit;
}

$q=trim($_GET['q']??''); $st=$_GET['status']??'';
$sql="SELECT r.*, f.facility_name, u.full_name, u.email FROM reservations r JOIN facilities f ON r.facility_id=f.facility_id JOIN users u ON r.user_id=u.user_id WHERE 1=1";
$params=[]; $types='';
if ($q!==''){$sql.=' AND (u.full_name LIKE ? OR u.email LIKE ? OR f.facility_name LIKE ?)';$like='%'.$q.'%';$params=[$like,$like,$like];$types='sss';}
if (in_array($st,['Pending','Confirmed','Cancelled','Completed'])){$sql.=' AND r.reservation_status=?';$params[]=$st;$types.='s';}
$sql.=' ORDER BY r.booking_date DESC, r.start_time DESC';
$stmt=$conn->prepare($sql);
if ($params) $stmt->bind_param($types,...$params);
$stmt->execute();
$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require __DIR__.'/../includes/header.php';
?>
<main class="page-wrap"><div class="container">
  <div class="page-header"><div><h1>Reservation Monitoring</h1><p>Search, filter and cancel reservations.</p></div></div>
  <?php require __DIR__.'/_tabs.php'; ?>
  <form class="filter-bar" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search user, email or facility…">
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach (['Pending','Confirmed','Cancelled','Completed'] as $s): ?>
        <option value="<?= $s ?>" <?= $st===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm">Apply</button>
    <a class="btn btn-outline btn-sm" href="<?= base_url('admin/reservation_monitoring.php') ?>">Reset</a>
  </form>
  <?php if (!$rows): ?>
    <div class="empty">No reservations found.</div>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>ID</th><th>User</th><th>Facility</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['reservation_id'] ?></td>
            <td><?= e($r['full_name']) ?><br><small style="color:var(--muted)"><?= e($r['email']) ?></small></td>
            <td><?= e($r['facility_name']) ?></td>
            <td><?= e(fmt_date($r['booking_date'])) ?></td>
            <td><?= e(fmt_time($r['start_time'])) ?> – <?= e(fmt_time($r['end_time'])) ?></td>
            <td><span class="status-badge <?= status_class($r['reservation_status']) ?>"><?= e($r['reservation_status']) ?></span></td>
            <td>
              <?php if (in_array($r['reservation_status'],['Pending','Confirmed'])): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Cancel this reservation?')">
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="reservation_id" value="<?= (int)$r['reservation_id'] ?>">
                  <button class="btn btn-danger btn-sm">Cancel</button>
                </form>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div></main>
<?php require __DIR__.'/../includes/footer.php'; ?>
