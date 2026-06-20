<?php

require_once __DIR__ . '/db_connection.php';

function notify($conn, $user_id, $title, $message = null) {
    if ($message === null) { $message = $title; $title = 'Notification'; }
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
    $stmt->bind_param('iss', $user_id, $title, $message);
    $stmt->execute();
    $stmt->close();
}

function notify_admins($conn, $title, $message) {
    $res = $conn->query("SELECT user_id FROM users WHERE role='admin'");
    while ($r = $res->fetch_assoc()) notify($conn, (int)$r['user_id'], $title, $message);
}

function notify_all($conn, $title, $message) {
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, title, message)
         SELECT user_id, ?, ? FROM users WHERE role IN ('user','staff')"
    );
    $stmt->bind_param('ss', $title, $message);
    $stmt->execute();
    $stmt->close();
}

function unread_notification_count($conn, $user_id) {
    $stmt = $conn->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0');
    $stmt->bind_param('i', $user_id); $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

function recent_notifications($conn, $user_id, $limit = 10) {
    $stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ?');
    $stmt->bind_param('ii', $user_id, $limit); $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function department_from_matric($matric) {
    $m = strtoupper(trim((string)$matric));
    if (strlen($m) < 3) return null;
    $code = substr($m, 1, 2);
    $map = [
        '01'=>'FKE','02'=>'FKEKK','03'=>'FTMK','04'=>'FKM','05'=>'FKP',
        '06'=>'FPTT','07'=>'FTK','08'=>'FTKEE','09'=>'FTKMP'
    ];
    return $map[$code] ?? null;
}
