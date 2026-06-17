<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/notifications.php';

function role_from_email($email) {
    $email = strtolower (trim($email));
    if ($email === 'admin@utem.edu.my') return 'admin';
    if (preg_match('/@student\.utem\.edu\.my$/', $email)) return 'user';
    if (preg_match('/@utem\.edu\.my$/', $email)) return 'staff';
    return null;
}

/** 
 * login using matric number or utem email.
 * if both is filled, the matric number must match with the email.
*/
function attempt_login($identifier, $password, $admin_only = false) {
    global $conn;
    $id = strtolower(trim($identifier));
    if ($id === '') return [false, 'Please enter your matric number pr UTeM email.'];

    $is_email = strpos($id, '@') !== false;
    if ($is_email) {
        $role = role_from_email($id);
        if ($role === null) return [false, 'Only official UTeM email accounts are allowed.'];
        $stmt = $conn->prepare('SELECT user_id, full_name, email, matric_number, password, role, profile_picture FROM users WHERE email=? LIMIT 1');
        $stmt->bind_param('s', $id);
    }else {
        $stmt = $conn->prepare('SELECT user_id, full_name, email, matric_number, password, role, profile_picture FROM users WHERE LOWER(matric_number)=? LIMIT 1');
        $stmt->bind_param('s', $id);
    }
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc(); 
    $stmt->close();
    if (!$res) return [false, 'Account not found.'];

    // matric and email check, must match
    if (!empty($res['matric_number'])) {
        $expected = strtolower($res['matric_number']) . '@student.utem.edu.my';
        if (strtolower($res['email']) !== $expected && $res['role'] === 'user') {
            return [false, 'Matric number and email do not match.'];
        }
    }

    if (!password_verify($password, $res['password'])) return [false, 'Incorrect password.'];

    if($admin_only && $res['role'] !== 'admin') {
        return [false, 'This portal is for administrators only.'];
    }
    if(!$admin_only && $res['role'] === 'admin') {
        return [false, 'Please use the Admin Login page.'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$res['user_id'];
    $_SESSION['full_name'] = $res['full_name'];
    $_SESSION['role'] = $res['role'];
    $_SESSION['profile_picture'] = $res['profile_picture']; 
    $_SESSION['email'] = $res['email'];
    $_SESSION['dark_mode'] = $res['dark_mode'];
    return [true, 'Welcome, ' . $res['full_name'] . '!'];

}

function logout_user() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'],
            $p['secure'], $p['httponly']);
    }
    session_destroy();
}