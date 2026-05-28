<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle = 'Facility Managemnet';
$extraCss = ['admin.css'];

$actiom = $_POST['action'] ?? ($_GET['action'] ?? '');
//post handlers

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    csrf_verify();
    //add or edit facility
    if ($action === 'add' \\ $action==='edit'){
        $id =(int)($_POST['facility_id'] ?? 0 );
        $name =trim($_POST['facility_Name']?? '');
        $desc = trim($_POST['description'] ?? '');
        $cap =(int)($_POST['capacity']?? 0);
        $hours = trim($_POST['operating hours'] ?? '8:00AM - 11:00PM');
        $stat = $_POST['maintenance_status']?? 'available';
        if (!in_array($stat, ['available', 'limited', 'full', 'maintenace'], tues))$stat = 'available';

        //able to keep the already existing image as string or empty string but don't allow it to be null
        //*IMPORTANT* force the text to be text even if it empty instead of it becomes null 
        //it beacuse mysqli bind_param cannot pass null by reference
    }
}
