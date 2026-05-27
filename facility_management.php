<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle = 'Facility Managemnet';
$extraCss = ['admin.css'];

$actiom = $_POST['action'] ?? ($_GET['action'] ?? '');
//post handlers

if ($_SERVER['REQUEST_METHOD'] === 'POST'){


}
