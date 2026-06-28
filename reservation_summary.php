<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$rid=(int)($_GET['id']??0);
$uid=current_user_id();
$stmt=$conn->prepare('SELECT r.*, f.facility_name, f.image, f.operating_hours FROM
reservations r JOIN facilities f ON r.facility_id=f.facility_id WHERE r.reservation_id=?
AND r.user_id=?');
$stmt->bind_param('ii',$rid,$uid); $stmt->execute();
$res=$stmt->get_result()->fetch_assoc();
if (!$res){ flash('error','Reservation not found.'); header('Location:
    '.base_url('my_bookings.php')); exit; }

$pageTitle='Reservation Summary';
require __DIR__.'/includes/header.php';
?>
<main class="page-wrap"><div class="container" style="max-width:720px">
<div class="page-header"><div><h1>Reservation Summary</h1><p>Reservation ID <strong>#<?=
(int)$res['reservation_id'] ?></strong></p></div></div>
<div class="form-card">
<div style="display:flex;gap:16px;align-items:center;margin-bottom:18px">
<div style="width:90px;height:90px;border-radius:10px;overflow:hidden;background:var(--off);flex-shrink:0">
<img src="<?= e(facility_image_url($res['image'])) ?>" alt=""
style="width:100%;height:100%;object-fit:cover">
</div>
<div>
<div style="font-family: 'Syne',sans-serif;font-size:20px;font-weight:800"><?=
e($res['facility_name']) ?></div>
<div style="font-size:13px;color:var(--muted)"><?= e($res['operating_hours']) ?>
</div>

<div style="margin-top:6px"><span class="status-badge <?=
status_class($res['reservation_status']) ?>"><?= e($res['reservation_status']) ?></span>
</div>
</div>
</div>
<table style="width:100%;font-size:14px">
<tr><td style="padding:6px 0;color:var(--muted);width:140px">Date</td><td><?=
e(fmt_date($res['booking_date'])) ?></td></tr>
<tr><td style="padding:6px 0;color:var(--muted)">Time</td><td><?=
e(fmt_time($res['start_time'])) ?> - <?= e(fmt_time($res['end_time'])) ?></td></tr>
<tr><td style="padding:6px 0;color:var(--muted)">Submitted</td><td><?=
e(fmt_date($res['created_at'])) ?> at <?= e(date('g:i A', strtotime($res['created_at']))) ?></td></tr>
</table>
<div style="display:flex;gap:10px;margin-top:18px">
<a class="btn btn-primary" href="<?= base_url('my_bookings.php') ?>">My
Reservations</a>
<a class="btn btn-outline" href="<?= base_url('dashboard.php') ?>">Back to facilities</a>
</div>
</div>
</div></main>
<?php require __DIR__.'/includes/footer.php'; ?>