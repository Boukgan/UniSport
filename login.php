<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    header('Location: ' . base_url(current_user_role()==='admin'?'admin/dashboard.php':'index.php'));
    exit;
}
$pageTitle = 'Login';
$extraCss = ['login.css'];
$extraJs = ['validation.js'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $rl_key = 'login_' . md5(strtolower(trim($_POST['identifier'] ?? '')));
    if (!rate_limit($rl_key, 5, 300)) {
        $error = 'Too many login attempts. Please wait 5 minutes and try again.';
    } else {
        [$ok, $msg] = attempt_login($_POST['identifier'] ?? '', $_POST['password'] ?? '', false);
        if ($ok) {
            flash('success', $msg);
            header('Location: ' . base_url('index.php'));
            exit;
        }
        $error = $msg;
    }
}
require __DIR__ . '/includes/header.php';
?>
<main class="auth-wrap">
    <div class="auth-card">
        <h1> Welcome back</h1>
        <p class="lead">Sign in with your matric number or UTeM email.</p>
        <?php if($error): ?><div class="auth-err"><?= e($error) ?></div><?php endif; ?>
        <form id="loginForm" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-row">
                <label>Matric number or UTeM email</label>
                <input type="text" name="identifier" placeholder="B032410001 or name@student.utem.edu.my" required>
            </div>
            <div class="form-row">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Login</button>
            <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                <a class="auth-link" href="<?= base_url('forgot_password.php') ?>">Forgot password?</a>
                <a class="auth-link" href="<?= base_url('register.php') ?>">Create account</a>
            </div>
            <div style="margin-top:18px;text-align:center;border-top:1px solid var(--border);padding-top:14px">
                <a class="btn btn-outline btn-block" href="<?= base_url('admin_login.php') ?>">Admin Login</a>
            </div>
        </form>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>