<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in() && current_user_role()==='admin') {
    header('Location: '.base_url('admin/dashboard.php')); exit;
}
$pageTitle='Admin Login';
$extraCss=['login.css'];
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $rl_key = 'adminlogin_' . md5(strtolower(trim($_POST['email'] ?? '')));
    if (!rate_limit($rl_key, 5, 300)) {
        $error = 'Too many login attempts. Please wait 5 minutes and try again.';
    } else {
        [$ok,$msg] = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '', true);
        if ($ok) {
            flash('success',$msg);
            header('Location: '.base_url('admin/dashboard.php')); exit;
        }
        $error=$msg;
    }
}
require __DIR__.'/includes/header.php';
?>
<main class="auth-wrap">
  <div class="auth-card">
    <h1>Admin Portal</h1>
    <p class="lead">Restricted to UniSport administrators.</p>
    <?php if($error): ?><div class="auth-err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="form-row"><label>Admin email</label><input type="email" name="email" placeholder="admin@utem.edu.my" required></div>
      <div class="form-row"><label>Password</label><input type="password" name="password" required></div>
      <button class="btn btn-primary btn-block" type="submit">Sign in as Admin</button>
      <div style="margin-top:14px;text-align:center"><a class="auth-link" href="<?= base_url('login.php') ?>">← Back to user login</a></div>
    </form>
    </div>
</main>
<?php require __DIR__.'/includes/footer.php'; ?>