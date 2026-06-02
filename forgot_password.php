<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
$pageTitle='Forgot password';
$extraCss=['login.css'];
$msg=''; $err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $rl_key = 'forgot_' . md5($email);
    $generic = 'If this email is registered with UniSport, a reset link has been sent to your inbox. The link expires in 15 minutes.';

    if (!rate_limit($rl_key, 3, 600)) {
        $msg = $generic;
    } else {
        if (role_from_email($email) === null) {
            $err = 'Only official UTeM email accounts are allowed.';
        } else {
            $stmt = $conn->prepare('SELECT user_id, full_name FROM users WHERE email=?');
            $stmt->bind_param('s', $email); $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) {
                // Do not reveal whether email exists
                $msg = $generic;
            } else {
                // Invalidate any previously issued, still-active tokens for this user
                $expire_old = $conn->prepare(
                    'UPDATE password_reset_tokens
                     SET used_at = NOW()
                     WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()'
                );
                $expire_old->bind_param('i', $row['user_id']);
                $expire_old->execute();
                $expire_old->close();

                $token = bin2hex(random_bytes(32));
                $exp   = date('Y-m-d H:i:s', time() + 15*60);
                $ins   = $conn->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?,?,?)');
                $ins->bind_param('iss', $row['user_id'], $token, $exp);
                $ins->execute();

                $link = app_base_url() . '/reset_password.php?token=' . $token;
                $body = email_template('Password Reset Request',
                    '<p style="font-size:15px;color:#222;margin:0 0 12px">Hi ' . e($row['full_name']) . ',</p>

                     <p style="font-size:14px;color:#444;line-height:1.55;margin:0 0 18px">
                       We received a request to reset the password for your UniSport
                       account (' . e($email) . ').
                     </p>

                     <p style="font-size:14px;color:#444;margin:0 0 18px">
                       Click the button below to choose a new password:
                     </p>

                     <p style="margin:0 0 24px">
                       <a href="' . e($link) . '"
                          style="display:inline-block;background:#003b8e;color:#fff;
                                 padding:12px 28px;border-radius:8px;text-decoration:none;
                                 font-weight:600;font-size:14px">
                         Reset my password
                       </a>
                     </p>

                     <p style="font-size:12px;color:#6b7fa3;line-height:1.5;margin:0 0 18px">
                       Or copy and paste this link into your browser:<br>
                       <span style="word-break:break-all">' . e($link) . '</span>
                     </p>

                     <p style="font-size:12px;color:#6b7fa3;line-height:1.6;margin:0 0 20px">
                       ⏱ This link expires in 15 minutes.<br>
                       🔒 If you did not request a password reset, you can safely
                       ignore this email. Your password will not change.
                     </p>

                     <hr style="border:0;border-top:1px solid #e2e8f0;margin:20px 0">
                     <p style="font-size:11px;color:#94a3b8;margin:0;line-height:1.5">
                       Sent by UniSport · UTeM Sports Centre<br>
                       <a href="mailto:unisportsupport@gmail.com" style="color:#003b8e">unisportsupport@gmail.com</a>
                     </p>'
                );
                [$mailOk, $mailErr] = send_mail($email, 'UniSport – Password Reset Request', $body);
                if ($mailOk) {
                    $msg = $generic;
                } else {
                    $err = 'Mail error (see mail_config.php): ' . $mailErr;
                }
            }
        }
    }
}
require __DIR__.'/includes/header.php';
?>
<main class="auth-wrap">
  <div class="auth-card">
    <h1>Forgot password</h1>
    <p class="lead">Enter your UTeM email to receive a reset link.</p>

    <?php if($err): ?>
      <div class="auth-err"><?= e($err) ?></div>
    <?php endif; ?>

    <?php if($msg): ?>
      <!-- Success state: hide form, show success card -->
      <div class="auth-ok" style="display:flex;align-items:flex-start;gap:12px;padding:16px 18px;border-radius:10px;margin-bottom:18px">
        <span style="font-size:22px;line-height:1">✅</span>
        <div>
          <div style="font-weight:700;font-size:14px;margin-bottom:4px">Reset link sent!</div>
          <div style="font-size:13px;line-height:1.55"><?= e($msg) ?></div>
          <div style="font-size:12px;margin-top:8px;color:#166534">
            📧 Check your <strong>Inbox</strong> and <strong>Junk/Spam</strong> folder.
          </div>
        </div>
      </div>
      <div style="text-align:center;margin-top:4px">
        <a class="auth-link" href="<?= base_url('login.php') ?>">← Back to login</a>
      </div>

    <?php else: ?>
      <!-- Normal form state -->
      <form method="post" id="forgotForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-row">
          <label>UTeM Email</label>
          <input type="email" name="email" id="emailInput" required
                 placeholder="e.g. d032410091@student.utem.edu.my"
                 value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <button class="btn btn-primary btn-block" id="submitBtn" type="submit">
          Send reset link
        </button>
        <div style="margin-top:14px;text-align:center">
          <a class="auth-link" href="<?= base_url('login.php') ?>">← Back to login</a>
        </div>
      </form>

      <script>
        document.getElementById('forgotForm').addEventListener('submit', function() {
          var btn = document.getElementById('submitBtn');
          btn.disabled = true;
          btn.textContent = 'Sending…';
          btn.style.opacity = '0.75';
        });
      </script>
    <?php endif; ?>

  </div>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>