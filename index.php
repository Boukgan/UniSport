<?
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user_role() === 'admin') {
    header('Location: ' . base_url('admin/dashboard.php'));
    exit;
}

