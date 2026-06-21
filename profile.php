<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
require_login();
$pageTitle='Profile';
$extraJs=['profile.js'];

$uid=current_user_id();
$stmt=$conn->prepare('SELECT * FROM users WHERE user_id=?');
$stmt->bind_param('i',$uid); $stmt->execute();
$u=$stmt->get_result()->fetch_assoc();

$err=''; $okmsg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $action=$_POST['action']??'';
    if ($action==='update') {
        $name  = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        // department auto-derived from matric, not user-editable
        $dept  = $u['matric_number'] ? (department_from_matric($u['matric_number']) ?: $u['department']) : $u['department'];
        $pic   = $u['profile_picture'];
        if (!empty($_FILES['profile_image']['name'])) {
            $f=$_FILES['profile_image'];
            if ($f['size']>2*1024*1024) $err='Image must be 2MB or less.';
            elseif (!in_array(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)),['jpg','jpeg','png','gif'])) $err='Image must be JPG/PNG/GIF.';
            else {
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = finfo_file($finfo, $f['tmp_name']);
                finfo_close($finfo);
                if (!in_array($detectedMime, $allowedMimes, true)) {
                    flash('error', 'Invalid image file. Please upload a real JPG, PNG, or GIF.');
                    header('Location: ' . base_url('profile.php'));
                    exit;
                }
                $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
                $pic='user_'.$uid.'_'.time().'.'.$ext;
                $uploadDir = __DIR__.'/uploads/profile/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                move_uploaded_file($f['tmp_name'], $uploadDir.$pic);
            }
        }
        if (!$err) {
            $stmt=$conn->prepare('UPDATE users SET full_name=?, phone=?, department=?, profile_picture=? WHERE user_id=?');
            $stmt->bind_param('ssssi',$name,$phone,$dept,$pic,$uid); $stmt->execute();
            $_SESSION['full_name']=$name; $_SESSION['profile_picture']=$pic;
            flash('success','Profile updated.'); header('Location: '.base_url('profile.php')); exit;
        }
    } elseif ($action==='remove_photo') {
        $old = $u['profile_picture'];
        if ($old) {
            $oldPath = __DIR__ . '/uploads/profile/' . $old;
            if (file_exists($oldPath)) unlink($oldPath);
        }
        $stmt = $conn->prepare('UPDATE users SET profile_picture=NULL WHERE user_id=?');
        $stmt->bind_param('i', $uid); $stmt->execute();
        $_SESSION['profile_picture'] = '';
        flash('success', 'Profile photo removed.');
        header('Location: ' . base_url('profile.php')); exit;
    } elseif ($action==='password') {
        $cur=$_POST['current_password']??''; $np=$_POST['new_password']??''; $cf=$_POST['confirm_password']??'';
        if (!password_verify($cur,$u['password']))        $err='Current password is incorrect.';
        elseif (strlen($np)<8 || strlen($np)>16)          $err='New password must be 8–16 characters.';
        elseif ($np!==$cf)                                $err='Passwords do not match.';
        else {
            $h=password_hash($np,PASSWORD_BCRYPT);
            $stmt=$conn->prepare('UPDATE users SET password=? WHERE user_id=?');
            $stmt->bind_param('si',$h,$uid); $stmt->execute();
            // Confirmation email + notification
            $body = email_template('Password Changed',
              '<p>Hi '.e($u['full_name']).',</p>
               <p>Your UniSport account password was changed on '.date('d M Y, g:i A').'.</p>
               <p>If this was not you, please contact the UTeM Sports Centre immediately.</p>');
            send_mail($u['email'], 'UniSport Password Changed', $body);
            notify($conn, $uid, 'Password updated', 'Your password was changed successfully.');
            flash('success','Password changed. Confirmation email sent.');
            header('Location: '.base_url('profile.php')); exit;
        }
    } elseif ($action==='dark_mode') {
        $val = !empty($_POST['dark_mode']) ? 1 : 0;
        $stmt = $conn->prepare('UPDATE users SET dark_mode=? WHERE user_id=?');
        $stmt->bind_param('ii', $val, $uid); $stmt->execute();
        $_SESSION['dark_mode'] = $val;
        header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
    }
    $stmt=$conn->prepare('SELECT * FROM users WHERE user_id=?');
    $stmt->bind_param('i',$uid); $stmt->execute();
    $u=$stmt->get_result()->fetch_assoc();
}
// keep department in sync with matric on view
if ($u['matric_number'] && ($auto = department_from_matric($u['matric_number'])) && $auto !== $u['department']) {
    $upd=$conn->prepare('UPDATE users SET department=? WHERE user_id=?');
    $upd->bind_param('si',$auto,$uid); $upd->execute();
    $u['department']=$auto;
}
require __DIR__.'/includes/header.php';
?>
<main class="page-wrap"><div class="container" style="max-width:820px">
  <div class="page-header"><div><h1>My Profile</h1><p>Manage your personal information.</p></div></div>
  <?php if($err): ?><div class="auth-err"><?= e($err) ?></div><?php endif; ?>

  <div class="form-card" style="margin-bottom:20px">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="update">
      <div style="display:flex;gap:24px;align-items:center;margin-bottom:18px">
        <img id="profilePreview" src="<?= e(profile_image_url($u['profile_picture'])) ?>" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:2px solid var(--border);background:var(--off)">
        <div>
          <label class="btn btn-outline btn-sm" for="profileImage">Change photo</label>
          <input id="profileImage" type="file" name="profile_image" accept="image/*" style="display:none">
          <?php if ($u['profile_picture']): ?>
          <form method="post" style="display:inline;margin-left:8px" onsubmit="return confirm('Remove profile photo?')">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="remove_photo">
            <button class="btn btn-danger btn-sm" type="submit">Remove photo</button>
          </form>
          <?php endif; ?>
          <div style="font-size:12px;color:var(--muted);margin-top:6px">JPG/PNG/GIF, max 2MB.</div>
        </div>
      </div>
      <div class="form-grid-2">
        <div class="form-row"><label>Full name</label><input type="text" name="full_name" value="<?= e($u['full_name']) ?>" required></div>
        <div class="form-row"><label>Phone</label><input type="text" name="phone" value="<?= e($u['phone']) ?>"></div>
        <div class="form-row"><label>Email (locked)</label><input type="email" value="<?= e($u['email']) ?>" disabled></div>
        <div class="form-row"><label>Matric (locked)</label><input type="text" value="<?= e($u['matric_number']??'—') ?>" disabled></div>
        <div class="form-row"><label>Department (auto from matric)</label><input type="text" value="<?= e($u['department']??'—') ?>" readonly></div>
        <div class="form-row"><label>Role</label><input type="text" value="<?= e(ucfirst($u['role'])) ?>" disabled></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button class="btn btn-primary">Save changes</button>
        <a class="btn btn-outline" href="<?= base_url('profile.php') ?>">Cancel</a>
      </div>
    </form>
  </div>

  <div class="form-card">
    <h2 class="section-title" style="font-size:18px">Change password</h2>
    <div class="section-line"></div>
    <p style="font-size:13px;color:var(--muted);margin-bottom:14px">A confirmation email will be sent to your UTeM inbox after a successful change.</p>
    <form id="passwordForm" method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="password">
      <div class="form-row"><label>Current password</label><input type="password" name="current_password" required></div>
      <div class="form-grid-2">
        <div class="form-row">
          <label>New password</label>
          <input type="password" name="new_password" id="pwInput" minlength="8" maxlength="16" required>
          <div class="pw-meter"><div class="pw-meter-bar" id="pwBar"></div></div>
        </div>
        <div class="form-row"><label>Confirm password</label><input type="password" name="confirm_password" minlength="8" maxlength="16" required></div>
      </div>
      <button class="btn btn-primary">Update password</button>
    </form>
  </div>
</div></main>
<?php require __DIR__.'/includes/footer.php'; ?>
