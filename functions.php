<?php
require_once __DIR__ . '/notifications.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function status_class($status) {
    $s = strtolower($status);
    return [
        'available' => 'status-available',
        'limited' => 'status-limited',
        'full' => 'status-unavailable',
        'maintenance' => 'status-maintenance',
        'pending' => 'badge-pending',
        'confirmed' => 'badge-confirm',
        'cancelled' => 'badge-cancel',
        'completed' => 'badge-completed'
    ][$s] ?? 'status-available';
}

function fmt_time($t) { return date('g:i A', strtotime($t)); }
function fmt_date($d) { return date('d M Y', strtotime($d)); }

function facility_image_url($filename) {
    if (!$filename) return base_url('assets/images/placeholder.svg');
    $local = __DIR__ . '/../uploads/facilities/' . $filename;
    if (file_exists($local)) return base_url('uploads/facilities/' . $filename);
    $bundled = __DIR__ . '/../assets/images/' . $filename;
    if (file_exists($bundled)) return base_url('assets/images/' . $filename);
    return base_url('assets/images/placeholder.svg');
}
    function profile_image_url($filename){
    if (!$filename) return base_url('assets/images/avatar.svg');
    $local = __DIR__ . '/../uploads/profile/' . $filename;
    if (file_exists($local)) return base_url('uploads/profile/' . $filename);
    return base_url('assets/images/avatar.svg');
}

/**
 * Parse "8:00AM - 11:00PM" style strings into [startHour, endHour] (24h ints).
 * Falls back to 8..23 (8AM-11PM).
 */
function parse_operating_hours($str) {
    $start = 8; $end = 23;
    if (preg_match('/(\d{1,2})(?::\d{2})?\s*([AP]M)\s*-\s*(\d{1,2})(?::\d{2})?\s*([AP]M)/i', (string)$str, $m)) {
        $start = (int)$m[1] % 12 + (strtoupper($m[2]) === 'PM' ?12:0);
        $end = (int)$m[3] % 12 + (strtoupper($m[4]) === 'PM' ?12:0);
    }
    if ($start < 0) $start = 0;
    if ($end > 24) $end = 24;
    if ($end <= $start) $end = $start + 1;
    return [$start, $end];
}

/**
 * Generate 1-hour time slots between operating hours (default 8AM-11PM).
 * Returns array of [start_time, end_time, label].
 */
function generate_time_slots($operating_hours = null) {
    [$start, $end] = parse_operating_hours($operating_hours);
    $slots = [];
    for ($h = $start; $h < $end; $h++) {
        $s = sprintf('%02d:00:00', $h);
        $e = sprintf('%02d:00:00', $h + 1);
        $label = date('gA', strtotime($s)) . ' - ' . date('gA', strtotime($e));
        $slots[] = [$s, $e, $label];
    }
    return $slots;
}

/**
 *  Map of [start_time => status] combining facility default, per-slot admin
 *  overrides (availability table), and existing Pending/Confirmed reservations.
 * 
 *  Priority (highest wins):
 *  1. Existing active reservation → 'full' (cannot be overridden by admin)
 *  2. Admin override in availability table (e.g. 'maintenance', 'limited')
 *  3. Facility-level maintenance_status seed (applies only where no per-slot
 *  override exists - e.g. 'maintenance' facility seeds all new slots as
 *  maintenance, but admin can open individual ones via availability rows)
 */
function slot_status_map($conn, $facility_id, $date, $facility_default_status,
$operating_hours = null) {
    // Seed every slot with facility-level default
    $map = [];
    $default = in_array($facility_default_status,
['available', 'limited', 'full', 'maintenance'])
        ? $facility_default_status : 'available';
    foreach (generate_time_slots($operating_hours) as [$s, $e, $label]) {
        $map[$s] = $default;
    }

    // Mark past time slots as 'full' when date is today
    if ($date === date('Y-m-d')) {
        $current_hour = (int)date('H');
        foreach ($map as $start_time => $status) {
            if ((int)substr($start_time, 0, 2) <= $current_hour) {
                $map[$start_time] = 'full';
            }
        }
    }

    // Apply per-slot admin overrides from availability table (always wins over seed)
    $stmt = $conn->prepare('SELECT start_time, status FROM  availability WHERE facility_id=?
    AND date=?');
    $stmt->bind_param('is', $facility_id, $date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (isset($map[$row['start_time']])) {  //only slots within operating hours
            $map[$row['start_time']] = $row['status']; 
            }
    }
$stmt->close();

// Active reservations mark their slots as 'full' regardless of everything else
$stmt = $conn->prepare(
    "SELECT start_time, end_time FROM reservations
    WHERE facility_id=? AND booking_date=?
    AND reservation_status IN ('Pending', 'Confirmed')"
);
$stmt->bind_param('is', $facility_id, $date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $h = (int)substr($row['start_time'], 0, 2);
    $eh = (int)substr($row['end_time'], 0, 2);
    for ($x = $h; $x < $eh; $x++) {
        $key = sprintf('%02d:00:00', $x);
        if (isset($map[$key]) && $map[$key] !== 'maintenance') { //don't override if already full
            $map[$key] = 'full';
        }
    }

}
$stmt->close();
return $map;
}
