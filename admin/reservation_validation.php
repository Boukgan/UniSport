<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle='Reservation Validation';
$extraCss=['admin.css'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $rid=(int)$_POST['reservation_id'];
    $act=$_POST['action']??'';
    $newStatus = $act==='approve' ? 'Confirmed' : ($act==='reject' ? 'Cancelled' : null);
    if ($newStatus) {
        // conflict check on approve
        if ($act==='approve') {
            $stmt_r = $conn->prepare('SELECT * FROM reservations WHERE reservation_id=?');
            $stmt_r->bind_param('i', $rid);
            $stmt_r->execute();
            $r = $stmt_r->get_result()->fetch_assoc();
            $stmt_r->close();
            $c=$conn->prepare("SELECT 1 FROM reservations WHERE facility_id=? AND booking_date=? AND start_time=? AND reservation_status='Confirmed' AND reservation_id<>?");
            $c->bind_param('issi',$r['facility_id'],$r['booking_date'],$r['start_time'],$rid); $c->execute();
            if ($c->get_result()->fetch_assoc()) { flash('error','Conflict: that slot is already confirmed.'); header('Location: '.base_url('admin/reservation_validation.php')); exit; }
        }
        $stmt=$conn->prepare('UPDATE reservations SET reservation_status=? WHERE reservation_id=?');
        $stmt->bind_param('si',$newStatus,$rid); $stmt->execute();
        $stmt_r2 = $conn->prepare(
            'SELECT r.user_id, f.facility_name
             FROM reservations r
             JOIN facilities f ON r.facility_id=f.facility_id
             WHERE r.reservation_id=?'
        );
        $stmt_r2->bind_param('i', $rid);
        $stmt_r2->execute();
        $r = $stmt_r2->get_result()->fetch_assoc();
        $stmt_r2->close();
        $title = $newStatus==='Confirmed' ? 'Reservation approved' : 'Reservation cancelled';
        notify($conn,$r['user_id'],$title,'Your reservation #'.$rid.' for '.$r['facility_name'].' has been '.strtolower($newStatus).'.');
        flash('success','Reservation '.$newStatus.'.');
    }
    header('Location: '.base_url('admin/reservation_validation.php')); exit;
}

$pending=$conn->query("SELECT r.*, f.facility_name, u.full_name, u.email FROM reservations r JOIN facilities f ON r.facility_id=f.facility_id JOIN users u ON r.user_id=u.user_id WHERE r.reservation_status='Pending' ORDER BY r.booking_date,r.start_time")->fetch_all(MYSQLI_ASSOC);

require __DIR__.'/../includes/header.php';
?>
<main class="page-wrap"><div class="container">
  <div class="page-header"><div><h1>Reservation Validation</h1><p>Approve or reject pending reservations.</p></div></div>
  <?php require __DIR__.'/tabs.php'; ?>
  <?php if (!$pending): ?>
    <div class="empty"><div class="empty-icon">✅</div>No pending reservations.</div>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>ID</th><th>User</th><th>Facility</th><th>Date</th><th>Time</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($pending as $r): ?>
          <tr>
            <td>#<?= (int)$r['reservation_id'] ?></td>
            <td><?= e($r['full_name']) ?><br><small style="color:var(--muted)"><?= e($r['email']) ?></small></td>
            <td><?= e($r['facility_name']) ?></td>
            <td><?= e(fmt_date($r['booking_date'])) ?></td>
            <td><?= e(fmt_time($r['start_time'])) ?> – <?= e(fmt_time($r['end_time'])) ?></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="reservation_id" value="<?= (int)$r['reservation_id'] ?>">
                <button class="btn btn-success btn-sm" name="action" value="approve" onclick="return confirm('Approve?')">Approve</button>
                <button class="btn btn-danger btn-sm" name="action" value="reject" onclick="return confirm('Reject?')">Reject</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div></main>
<?php require __DIR__.'/../includes/footer.php'; ?>
