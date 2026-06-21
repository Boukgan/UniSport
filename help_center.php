<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle='Help Center';
require __DIR__.'/includes/header.php';
$facilities = $conn->query('SELECT facility_name FROM facilities ORDER BY facility_name')->fetch_all(MYSQLI_ASSOC);
?>
<main class="page-wrap"><div class="container" style="max-width:820px">
  <div class="page-header"><div><h1>UniSport Help Center</h1><p>How to use the Reservation System</p></div></div>
  <div class="help-cards">
    <div class="help-card" onclick="openHelp('h1')"><div class="help-card-icon">📝</div><div class="help-card-title">How to Make a Reservation</div></div>
    <div class="help-card" onclick="openHelp('h2')"><div class="help-card-icon">📜</div><div class="help-card-title">Booking Rules</div></div>
    <div class="help-card" onclick="openHelp('h3')"><div class="help-card-icon">❌</div><div class="help-card-title">How to Cancel a Booking</div></div>
    <div class="help-card" onclick="openHelp('h4')"><div class="help-card-icon">🏟️</div><div class="help-card-title">Common Facilities</div></div>
    <div class="help-card" onclick="openHelp('h5')"><div class="help-card-icon">✉️</div><div class="help-card-title">Need Further Help?</div></div>
  </div>

  <div class="help-detail" id="h1"><h2>How to Make a Reservation</h2><ul>
    <li>Select a facility from the Facilities page.</li>
    <li>Choose your preferred date on the calendar.</li>
    <li>Select an available 2-hour time slot.</li>
    <li>Confirm your reservation.</li>
    <li>Wait for admin confirmation.</li>
  </ul></div>
  <div class="help-detail" id="h2"><h2>Booking Rules</h2><ul>
    <li>Reservations are only for official UTeM users.</li>
    <li>Maximum 2 hours per reservation.</li>
    <li>Cannot reserve unavailable facilities.</li>
    <li>Cannot reserve past dates.</li>
    <li>Reservations require admin confirmation.</li>
  </ul></div>
  <div class="help-detail" id="h3"><h2>How to Cancel a Booking</h2><ul>
    <li>Go to <strong>My Reservations</strong>.</li>
    <li>Select the reservation you wish to cancel.</li>
    <li>Click <strong>Cancel Reservation</strong>.</li>
  </ul></div>
  <div class="help-detail" id="h4"><h2>Common Facilities Available</h2><ul>
    <?php foreach ($facilities as $f): ?><li><?= e($f['facility_name']) ?></li><?php endforeach; ?>
  </ul></div>
  <div class="help-detail" id="h5"><h2>Need Further Help?</h2>
    <p>Email: <a class="auth-link" href="mailto:unisportsupport@gmail.com">unisportsupport@gmail.com</a></p>
  </div>
</div></main>s
<script>
function openHelp(id){
  document.querySelectorAll('.help-detail').forEach(d=>d.classList.remove('open'));
  document.getElementById(id)?.classList.add('open');
  document.getElementById(id)?.scrollIntoView({behavior:'smooth',block:'start'});
}
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
