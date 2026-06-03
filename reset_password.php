<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
$pageTitle='Reset password';
$extraCss=['login.css'];
$extraJs=['validation.js'];
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$err=''; $msg='';
$valid = false; $uid = null; $tid = null;

if ($token !== '') {
    $stmt = $conn->prepare('SELECT id, user_id, expires_at, used_at FROM password_reset_tokens WHERE token=?');
    $stmt->bind_param('s', $token); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row)                                  $err='Invalid reset token.';
    elseif ($row['used_at'] !== null)           $err='This reset link has already been used.';
    elseif (strtotime($row['expires_at']) < time()) $err='This reset link has expired. Please request a new one.';
    else { $valid = true; $uid = (int)$row['user_id']; $tid = (int)$row['id']; }
} else {
    $err='Missing token.';
}

if ($valid && $_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $np = (string)($_POST['new_password'] ?? '');
    $cf = (string)($_POST['confirm_password'] ?? '');
    if (strlen($np) < 8 || strlen($np) > 16) $err='Password must be 8–16 characters.';
    elseif ($np !== $cf)                     $err='Passwords do not match.';
    else {
        $hash = password_hash($np, PASSWORD_BCRYPT);
        $u = $conn->prepare('UPDATE users SET password=? WHERE user_id=?');
        $u->bind_param('si', $hash, $uid); $u->execute();
        $m = $conn->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE id=?');
        $m->bind_param('i', $tid); $m->execute();

        // Send confirmation email
        $uinfo = $conn->prepare('SELECT full_name, email FROM users WHERE user_id=?');
        $uinfo->bind_param('i', $uid);
        $uinfo->execute();
        $urow = $uinfo->get_result()->fetch_assoc();
        $uinfo->close();

        $confirmBody = email_template('Password Changed Successfully',
            '<p style="font-size:15px;color:#222;margin:0 0 12px">Hi ' . e($urow['full_name']) . ',</p>

             <p style="font-size:14px;color:#444;line-height:1.55;margin:0 0 18px">
               Your UniSport account password was successfully reset on
               ' . date('d M Y') . ' at ' . date('g:i A') . '.
             </p>

             <p style="font-size:14px;color:#444;line-height:1.55;margin:0 0 18px">
               You can now
               <a href="' . e(app_base_url()) . '/login.php" style="color:#003b8e;font-weight:600">sign in</a>
               with your new password.
             </p>

             <p style="font-size:12px;color:#6b7fa3;line-height:1.6;margin:0 0 0">
               🔒 If you did not make this change, contact UTeM Sports Centre
               immediately at
               <a href="mailto:unisportsupport@gmail.com" style="color:#003b8e">unisportsupport@gmail.com</a>.
             </p>'
        );
        send_mail($urow['email'], 'UniSport – Your Password Has Been Reset', $confirmBody);

        notify($conn, $uid, 'Password reset', 'Your password was reset successfully.');
        flash('success','Password reset. Please sign in with your new password.');
        header('Location: '.base_url('login.php')); exit;
    }
}
require __DIR__.'/includes/header.php';
?>
<main class="auth-wrap">
  <div class="auth-card">
    <h1>Reset password</h1>
    <p class="lead">Choose a new password (8–16 characters).</p>
    <?php if($err): ?><div class="auth-err"><?= e($err) ?></div><?php endif; ?>
    <?php if($valid): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="form-row">
        <label>New password</label>
        <input type="password" name="new_password" id="pwInput" minlength="8" maxlength="16" required>
        <div class="pw-meter"><div class="pw-meter-bar" id="pwBar"></div></div>
      </div>
      <div class="form-row"><label>Confirm password</label><input type="password" name="confirm_password" minlength="8" maxlength="16" required></div>
      <button class="btn btn-primary btn-block">Update password</button>
    </form>
    <?php endif; ?>
    <div style="margin-top:14px;text-align:center"><a class="auth-link" href="<?= base_url('login.php') ?>">← Back to login</a></div>
  </div>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>