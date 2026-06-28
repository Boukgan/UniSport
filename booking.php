<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_non_admin();
require_login();

$fid=(int)($_GET['facility_id']??0);
if (!$fid){ header('Location: ' . base_url('dashboard.php')); exit; }
$stmt=$conn->prepare('SELECT * FROM facilities WHERE facility_id=?');
$stmt->bind_param('i',$fid); $stmt->execute();
$fac=$stmt->get_result()->fetch_assoc();
if (!$fac){ header('Location: ' . base_url('dashboard.php')); exit; }

// AJAX slot fetch
if (isset($_GET['fetch_slots'])) { 
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    header('Content-Type: application/json');
    $date=$_GET['date']??date('Y-m-d');
    $map=slot_status_map($conn,$fid,$date,$fac['maintenance_status'],
$fac['operating_hours']);
    $out=[];
    foreach (generate_time_slots($fac['operating_hours']) as [$s,$e,$label]) {
        $out[]=['start'=>$s,'end'=>$e,'label'=>$label,'status'=>$map[$s]??'available'];
    }
    echo json_encode(['slots'=>$out]); exit;
}

// Handle reservation submit
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $date  = $_POST['booking_date'] ?? '';
    $start = $_POST['start_time']   ?? '';
    $end   = $_POST['end_time']     ?? '';
    $uid   = current_user_id();

    $sh = (int)substr($start,0,2); $eh = (int)substr($end,0,2);
    $hours = $eh - $sh;

    // Daily reservation limit check
    $limit_stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM reservations
         WHERE user_id=? AND booking_date=?
         AND reservation_status IN ('Pending','Confirmed')"
    );
    $limit_stmt->bind_param('is', $uid, $date);
    $limit_stmt->execute();
    $daily_count = (int)$limit_stmt->get_result()->fetch_assoc()['cnt'];
    $limit_stmt->close();

    if ($daily_count >= 2) {
        flash('error', 'You have reached the maximum of 2 reservations per day for this date.');
    } elseif ($date < date('Y-m-d')) {
        flash('error','please select today or a future date. ');
    } elseif ($date === date('Y-m-d') && $start <= date('H:i:s')) {
        flash('error','That time slot has already passed. Please select a future time. ');
    } elseif ($hours < 1) {
        flash('error','Minimum reservation duration is 1 hour. ');
    } elseif ($hours > 3) {
        flash('error','Maximum reservation duration is 3 hours. ');
    } else {
        $conn->begin_transaction();
        try {
            $lock = $conn->prepare(
                "SELECT reservation_id FROM reservations
                WHERE facility_id=? AND booking_date=?
                AND start_time < ? AND end_time > ?
                AND reservation_status IN ('Pending','Confirmed')
                FOR UPDATE"
            );
            $lock->bind_param('isss', $fid, $date, $end, $start);
            $lock->execute();
            $conflict = $lock->get_result()->fetch_assoc();
            $lock->close();

            if ($conflict) {
                $conn->rollback();
                flash('error', 'This slot was just taken by another user. Please pick a different time. ');
                header('Location: ' . base_url('booking.php?facility_id=' . $fid));
                exit;
            }

            $map = slot_status_map($conn, $fid, $date, $fac['maintenance_status'], $fac['operating_hours']);
            $clash = false;
            for ($h = $sh; $h < $eh; $h++) {
                $k = sprintf('%02d:00:00', $h);
                if (!isset($map[$k]) || in_array($map[$k], ['full', 'maintenance'], true))
{                 
                   $clash = true;
                   break;
                }

            }

            if ($clash) {
                $conn->rollback();
                flash('error', 'One of the selected slots is no longer available.');
                header('Location: ' . base_url('booking.php?facility_id=' . $fid));
                exit;
            }

            $ins = $conn->prepare(
                'INSERT INTO reservations
                (user_id, facility_id, booking_date, start_time, end_time, reservation_status)
                VALUES (?, ?, ?, ?, ?, "Pending")'
            );
            $ins->bind_param('iisss', $uid, $fid, $date, $start, $end);
            $ins->execute();
            $rid = $ins->insert_id;

            notify($conn, $uid, 'Reservation submitted',
            'Your reservation #' . $rid . ' for ' . $fac['facility_name'] . ' is awaiting approval.');
            notify_admins($conn, 'New reservation', 
            'Reservation #' . $rid . ' for ' . $fac['facility_name'] . ' on ' . $date . ' needs approval.');
            
            $conn->commit();
            flash('success', 'Reservation submitted!');
            header('Location: ' . base_url('reservation_summary.php?id=' . $rid));
            exit;
            
        } catch (Throwable $e) {
            $conn->rollback();
            flash('error', 'A system error occurred. Please try again. ');
            header('Location: ' . base_url('booking.php?facility_id=' . $fid));
            exit;

        }
        }
        }

        $pageTitle='Reserve - '.$fac['facility_name'];
        $extraJs=['booking.js'];
        require __DIR__.'/includes/header.php';
        ?>
        <main class="page-wrap"><div class="container">
            <div class="page-header">
                
                <div>
                    <a class="auth-link" href="<?= base_url('dashboard.php') ?>">← Back to facilities</a>
                    <h1 style="margin-top:6px"><?= e($fac['facility_name']) ?></h1>
</div>
</div>

<div class="detail-cols">
    <div>
        <div class="detail-img">
            <img src="<?= e(facility_image_url($fac['image'])) ?>" alt="">
        </div>

<div class="feature-pills">
    <div class="pill">👥 Capacity: <?= (int)$fac['capacity'] ?></div>
    <div class="pill">🕒 <?= e($fac['operating_hours']) ?></div>

    <div class="pill">
    <span class="status-badge <?= status_class($fac['maintenance_status']) ?>">
        <?= e(ucfirst($fac['maintenance_status'])) ?>
    </span>
</div>

</div>
<div class="desc-block">
    <h3>About this facility</h3>
    <p><?= nl2br(e($fac['description'])) ?></p>
</div>
</div>

<aside>
    <div class="booking-card">
        <div class="booking-card-title">Pick a date</div>
        <div class="cal-container" id="calendar" data-facility-id="<?=
        (int)$fac['facility_id'] ?>"></div>
        <div id="slotsBox"><p style="color:var(--muted);font-size:13px">Select a date to see time slots.</p></div>
        <div id="slotInfo" style="font-size:12px;color:var(--muted);margin-top:8px">Click 1 to 3 consecutive slots, then confirm.</div>
        <form id="reserveForm" method="post" style="margin-top:12px;display:none">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="booking_date">
            <input type="hidden" name="start_time">
            <input type="hidden" name="end_time">
            <button type="submit" class="btn btn-primary btn-block">Confirm
                reservation</button>
</form>
<input type="hidden" id="selectedDate" value="">
</div>
</aside>
</div>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>