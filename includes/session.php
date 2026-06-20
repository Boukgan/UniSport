<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}
function current_user_role() {
    return $_SESSION['role'] ?? null;
}
function current_user_name() {
    return $_SESSION['full_name'] ?? 'Guest';
}
function current_user_picture() {
    return $_SESSION['profile_picture'] ?? '';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
}
function require_admin() {
    require_login();
    if (current_user_role() !== 'admin') {
        header('Location: ' . base_url('dashboard.php'));
        exit;
    }
}
function require_non_admin() {
    require_login();
    if (current_user_role() === 'admin') {
        header('Location: ' . base_url('admin/dashboard.php'));
        exit;
    }
}

function base_url($path = '') {
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (substr($script, -6) === '/admin') {
        $script = substr($script, 0, -6);
    }
    $base = rtrim($script, '/');
    return $base . '/' . ltrim($path, '/');
}

function flash($key, $message = null) {
    if ($message === null) {
        $m = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $m;
    }
    $_SESSION['_flash'][$key] = $message;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF validation failed. Please go back and try again.');
    }
}

function rate_limit($key, $maxAttempts = 5, $windowSeconds = 300) {
    $now = time();
    $attempts = $_SESSION['_rl'][$key] ?? [];
    $attempts = array_filter($attempts, fn($t) => $t > $now - $windowSeconds);
    if (count($attempts) >= $maxAttempts) {
        return false;
    }
    $attempts[] = $now;
    $_SESSION['rl'][$key] = array_values($attempts);
    return true;
}