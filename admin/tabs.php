<?php
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<div class="admin-tabs">
  <a class="admin-tab <?= $current==='dashboard.php'?'active':'' ?>" href="<?= base_url('admin/dashboard.php') ?>">Overview</a>
  <a class="admin-tab <?= $current==='facility_management.php'?'active':'' ?>" href="<?= base_url('admin/facility_management.php') ?>">Facility Management</a>
  <a class="admin-tab <?= $current==='reservation_validation.php'?'active':'' ?>" href="<?= base_url('admin/reservation_validation.php') ?>">Validation</a>
  <a class="admin-tab <?= $current==='reservation_monitoring.php'?'active':'' ?>" href="<?= base_url('admin/reservation_monitoring.php') ?>">Monitoring</a>
  <a class="admin-tab <?= $current==='user_management.php'?'active':'' ?>" href="<?= base_url('admin/user_management.php') ?>">Users</a>
</div>
