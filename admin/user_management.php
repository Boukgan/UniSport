<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle = 'User Management';
$extraCss  = ['admin.css'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'change_role') {
        $user_id  = (int)($_POST['user_id'] ?? 0);
        $new_role = $_POST['new_role'] ?? '';
        $allowed  = ['user', 'staff'];
        if ($user_id > 0 && in_array($new_role, $allowed, true)) {
            $stmt = $conn->prepare("UPDATE users SET role=? WHERE user_id=? AND role<>'admin'");
            $stmt->bind_param('si', $new_role, $user_id);
            $stmt->execute();
            $stmt->close();
            flash('success', 'Role updated.');
        } else {
            flash('error', 'Invalid role change request.');
        }
        header('Location: ' . base_url('admin/user_management.php'));
        exit;
    }
}

$users = $conn->query(
    'SELECT user_id, full_name, email, matric_number, department, role, created_at
     FROM users ORDER BY created_at DESC'
)->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/../includes/header.php';
?>
<main class="page-wrap"><div class="container">
  <div class="page-header"><div><h1>User Management</h1><p>View registered users and adjust their roles.</p></div></div>
  <?php require __DIR__ . '/_tabs.php'; ?>

  <?php if (!$users): ?>
    <div class="empty"><div class="empty-icon">👤</div>No registered users yet.</div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Matric</th>
          <th>Department</th>
          <th>Role</th>
          <th>Registered</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>#<?= (int)$u['user_id'] ?></td>
            <td><?= e($u['full_name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['matric_number'] ?? '—') ?></td>
            <td><?= e($u['department'] ?? '—') ?></td>
            <td><span class="status-badge"><?= e(ucfirst($u['role'])) ?></span></td>
            <td><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
            <td>
              <?php if ($u['role'] !== 'admin'): ?>
                <form method="post" style="display:inline-flex;gap:6px;align-items:center">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="change_role">
                  <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                  <select name="new_role">
                    <option value="user"  <?= $u['role']==='user'  ? 'selected' : '' ?>>user</option>
                    <option value="staff" <?= $u['role']==='staff' ? 'selected' : '' ?>>staff</option>
                  </select>
                  <button class="btn btn-primary btn-sm" type="submit">Save</button>
                </form>
              <?php else: ?>
                <small style="color:var(--muted)">Protected</small>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
