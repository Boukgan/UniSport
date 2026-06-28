<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$uid = current_user_id();

// AJAX: mark a single notification read, or mark all read (used by the notification bell dropdown)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api'])) {
    csrf_verify();
    $api = $_POST['api'];
    if ($api === 'mark_read') {
        $nid = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?');
        $stmt->bind_param('ii', $nid, $uid);
        $stmt->execute();
    } elseif ($api === 'mark_all') {
        $stmt = $conn->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'unread' => unread_notification_count($conn, $uid)]);
    exit;
}

// Mark all as read when the full notifications page is opened
$stmt = $conn->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?');
$stmt->bind_param('i', $uid);
$stmt->execute();

// Handle single delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $nid = (int)$_POST['notification_id'];
    $stmt = $conn->prepare('DELETE FROM notifications WHERE notification_id=? AND user_id=?');
    $stmt->bind_param('ii', $nid, $uid);
    $stmt->execute();
    header('Location: ' . base_url('notifications.php'));
    exit;
}

// Fetch all notifications for this user
$stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC');
$stmt->bind_param('i', $uid);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'All Notifications';
require __DIR__ . '/includes/header.php';
?>
<main class="page-wrap"><div class="container">
  <div class="page-header">
    <div>
      <h1>All Notifications</h1>
      <p>Your full notification history.</p>
    </div>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="empty">
      <div class="empty-icon">🔔</div>
      You have no notifications yet.
    </div>
  <?php else: ?>
    <div class="data-table-wrap"><table class="data-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Message</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notifications as $n): ?>
          <tr>
            <td><strong><?= e($n['title']) ?></strong></td>
            <td><?= e($n['message']) ?></td>
            <td><?= e(date('d M Y · g:i A', strtotime($n['created_at']))) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Delete this notification?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="notification_id" value="<?= (int)$n['notification_id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div></main>
<?php require __DIR__ . '/includes/footer.php'; ?>