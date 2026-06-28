<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_non_admin();

$uid=current_user_id();

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cancel') {
    $rid=(int)$_POST['reservation_id'];
    // can only cancel if not expired
    $stmt=$conn->prepare("UPDATE reservations SET reservation_status='Cancelled' WHERE 
    reservation_id=? AND user_id=? AND reservation_status IN ('Pending','Confirmed') AND
    booking_date >= CURDATE()");
    $stmt->bind_param('ii',$rid,$uid); $stmt->execute();
    if ($stmt->affected_rows>0) { notify($conn,$uid,'Reservation #'.$rid.' cancelled.');
    flash('success','Reservation cancelled.'); }
    else flash('error','This reservation can no longer be cancelled.');
    header('Location: '.base_url('my_bookings.php')); exit;
}

$upcoming=$conn->query("SELECT r.*, f.facility_name FROM reservations r JOIN facilities f
ON r.facility_id=f.facility_id WHERE r.user_id=$uid AND r.booking_date >= CURDATE() AND
r.reservation_status IN ('Pending', 'Confirmed') ORDER BY r.booking_date,r.start_time")->
fetch_all(MYSQLI_ASSOC);
$past=$conn->query("SELECT r.*, f.facility_name FROM reservations r JOIN facilities f ON
r.facility_id=f.facility_id WHERE r.user_id=$uid AND (r.booking_date < CURDATE() OR 
r.reservation_status IN ('Cancelled', 'Completed')) ORDER BY r.booking_date DESC LIMIT
50")->fetch_all(MYSQLI_ASSOC);

$pageTitle='My Reservations';
require __DIR__.'/includes/header.php';

function render_row($r){
    $today=date('Y-m-d');
    $can_cancel = in_array($r['reservation_status'],['Pending', 'Confirmed']) &&
    $r['booking_date']>=$today;
    echo '<tr>';
    echo '<td>#'.$r['reservation_id'].'</td>';
    echo '<td>'.e($r['facility_name']).'</td>';
    echo '<td>'.e(fmt_date($r['booking_date'])).'</td>';
    echo '<td>'.e(fmt_time($r['start_time'])).' – '.e(fmt_time($r['end_time'])).'</td>';
    echo '<td>'.e(fmt_date($r['created_at'])).'<br><small style="color:var(--muted)">'.e(date('g:i A', strtotime($r['created_at']))).'</small></td>';
    echo '<td><span class="status-badge
    '.status_class($r['reservation_status']).'">'.e($r['reservation_status']).'</span></td>';
    echo '<td><a class="btn btn-outline btn-sm" href="'.base_url('reservation_summary.php?id='.$r['reservation_id']).'"
    >View</a> ';
    if($can_cancel) {
        echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Cancel this reservation?\\n\\nNote: You can only cancel before the scheduled session date. This action cannot be undone.\')">';
    echo '<input type="hidden" name="action" value="cancel"><input type="hidden"
    name="reservation_id" value="'.$r['reservation_id'].'">';
    echo '<button class="btn btn-danger btn-sm">Cancel</button></form>';
    }
    echo '</td></tr>';
}
?>
<main class="page-wrap"><div class="container">
    <div class="page-header"><div><h1>My Reservations</h1><p>View, track and cancel your
        facility reservations.</p></div></div>

        <!-- Cancellation success modal -->
        <?php $__flashMsg = flash('success'); if ($__flashMsg && str_contains(strtolower($__flashMsg), 'cancel')): ?>
        <div id="cancelModal" style="position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:9999">
          <div style="background:var(--surface);border-radius:14px;padding:36px 32px;max-width:380px;width:90%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.18)">
            <div style="font-size:48px;margin-bottom:12px">✅</div>
            <h2 style="margin:0 0 8px">Reservation Cancelled</h2>
            <p style="color:var(--muted);font-size:14px;margin-bottom:24px"><?= e($__flashMsg) ?></p>
            <button onclick="document.getElementById('cancelModal').remove()" class="btn btn-primary" style="width:100%">OK</button>
          </div>
        </div>
        <?php elseif ($__flashMsg): ?>
        <script>/* non-cancel flash: <?= addslashes($__flashMsg) ?> */</script>
        <?php endif; ?>

        <h2 class="section-title">Upcoming</h2><div class="section-line"></div>
        <?php if (!$upcoming): ?>
            <div class="empty"><div class="empty-icon">📅</div>No upcoming reservations.<br><a class="auth-link" href="<?= base_url('dashboard.php') ?>">Reserve a facility →</a></div>
            <?php else: ?>
                <table class="data-table" style="margin-bottom:32px">
                    <thead><tr><th>ID</th><th>Facility</th><th>Date</th><th>Time</th><th>Submitted</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($upcoming as $r) render_row($r); ?>
                    </tbody>
                </table>
        <?php endif; ?>

        <h2 class="section-title">Past & Cancelled</h2><div class="section-line"></div>
        <?php if (!$past): ?>
            <div class="empty">No past reservations yet.</div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Facility</th><th>Date</th><th>Time</th><th>Submitted</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($past as $r) render_row($r); ?>
                    </tbody>
            </table>
                    <?php endif; ?>
            </div></main>
<?php require __DIR__.'/includes/footer.php'; ?>