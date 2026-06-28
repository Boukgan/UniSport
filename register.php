<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) { header('Location: '.base_url('index.php')); exit; }
$pageTitle='Create account';
$extraCss=['login.css'];
$extraJs=['validation.js'];
$err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $name   = trim($_POST['full_name'] ?? '');
    $matric = strtoupper(trim($_POST['matric_number'] ?? ''));
    $email  = strtolower(trim($_POST['email'] ?? ''));
    $phone  = trim($_POST['phone'] ?? '');
    $pw     = (string)($_POST['password'] ?? '');
    $cf     = (string)($_POST['confirm_password'] ?? '');

    $role = role_from_email($email);
    if ($name==='' || $email==='' || $pw==='') $err='Please fill in all required fields.';
    elseif ($role===null) $err='Email must be a UTeM address (@student.utem.edu.my or @utem.edu.my).';
    elseif ($role==='user' && $matric==='') $err='Students must provide a matric number.';
    elseif ($role==='user' && strtolower($matric).'@student.utem.edu.my' !== $email) $err='Matric number does not match UTeM email.';
    elseif (strlen($pw)<8 || strlen($pw)>16) $err='Password must be 8–16 characters.';
    elseif ($pw !== $cf) $err='Passwords do not match.';
    else {
        $check = $conn->prepare('SELECT 1 FROM users WHERE email=? OR (matric_number IS NOT NULL AND matric_number=?)');
        $check->bind_param('ss', $email, $matric);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) {
            $err='An account with that email or matric number already exists.';
        } else {
            $dept = department_from_matric($matric);
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $matricVal = $matric !== '' ? $matric : null;
            $ins = $conn->prepare('INSERT INTO users (full_name,matric_number,email,phone,department,password,role) VALUES (?,?,?,?,?,?,?)');
            $ins->bind_param('sssssss', $name, $matricVal, $email, $phone, $dept, $hash, $role);
            $ins->execute();
            $uid = $ins->insert_id;
            notify($conn, $uid, 'Welcome to UniSport', 'Your account has been created. Reserve a facility from the dashboard.');
            flash('success','Account created. Please sign in.');
            header('Location: '.base_url('login.php')); exit;
        }
    }
}
require __DIR__.'/includes/header.php';
?>
<main class="auth-wrap">
    <div class="auth-card" style="max-width:520px">
        <h1>Create account</h1>
        <p class="lead">Use your official UTeM email address to register.</p>
        <?php if($err): ?><div class="auth-err"><?= e($err) ?></div><?php endif; ?>
        <form id="registerForm" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-grid-2">
                <div class="form-row">
                    <label>Full name</label>
                    <input type="text" name="full_name" required value="<?= e($_POST['full_name']??'') ?>"></div>
                <div class="form-row">
                    <label>Matric number</label>
                    <input type="text" name="matric_number" id="matricInput" placeholder="e.g. B032410001" value="<?= e($_POST['matric_number']??'') ?>"></div>
                <div class="form-row">
                    <label>UTeM Email</label>
                    <input type="email" name="email" required placeholder="ali@student.utem.edu.my" value="<?= e($_POST['email']??'') ?>"></div>
                <div class="form-row">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= e($_POST['phone']??'') ?>"></div>
                <div class="form-row">
                    <label>Department (auto)</label>
                    <input type="text" id="deptPreview" disabled placeholder="Auto from matric">
                </div>
                <div class="form-row"></div>
                <div class="form-row">
                    <label>Password</label>
                    <input type="password" name="password" id="pwInput" minlength="8" maxlength="16" required>
                    <div class="pw-meter">
                    <div class="pw-meter-bar" id="pwBar"></div></div>
                    <small id="pwHint" style="font-size:11px;color:var(--muted)">8–16 characters.</small>
                </div>
                <div class="form-row">
                    <label>Confirm password</label>
                    <input type="password" name="confirm_password" minlength="8" maxlength="16" required>
                </div>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Create account</button>
            <div style="margin-top:14px;text-align:center">
                <a class="auth-link" href="<?= base_url('login.php') ?>">Already have an account? Sign in</a>
            </div>
        </form>
    </div>
</main>