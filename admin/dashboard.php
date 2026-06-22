<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle = 'Admin Dashboard';
$extraCss  = ['admin.css'];

// ── Stat cards ──────────────────────────────────────────────────────────────
$total_fac   = $conn->query('SELECT COUNT(*) c FROM facilities')->fetch_assoc()['c'];
$total_users = $conn->query("SELECT COUNT(*) c FROM users WHERE role <> 'admin'")->fetch_assoc()['c'];
$total_res   = $conn->query('SELECT COUNT(*) c FROM reservations')->fetch_assoc()['c'];
$pending     = $conn->query("SELECT COUNT(*) c FROM reservations WHERE reservation_status='Pending'")->fetch_assoc()['c'];
$recent      = $conn->query("SELECT r.*, f.facility_name, u.full_name FROM reservations r
    JOIN facilities f ON r.facility_id=f.facility_id
    JOIN users u ON r.user_id=u.user_id
    ORDER BY r.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// ── Graph 1: Monthly Reservation Trend (last 6 months) ──────────────────────────
// Meaningful: shows which months had high/low demand so admin can plan maintenance
$monthlyRaw = $conn->query("
    SELECT DATE_FORMAT(booking_date,'%b %Y') AS month,
           DATE_FORMAT(booking_date,'%Y-%m') AS ym,
           COUNT(*) AS total
    FROM reservations
    WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym, month
    ORDER BY ym ASC
")->fetch_all(MYSQLI_ASSOC);
$monthLabels = json_encode(array_column($monthlyRaw, 'month'));
$monthData   = json_encode(array_column($monthlyRaw, 'total'));

// ── Graph 2: Top 5 Most Reserved  Facilities ───────────────────────────────────
// Meaningful: reveals which facilities are in highest demand (confirmed+completed only)
$topFacRaw = $conn->query("
    SELECT f.facility_name, COUNT(*) AS bookings
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.facility_id
    WHERE r.reservation_status IN ('Confirmed','Completed')
    GROUP BY r.facility_id
    ORDER BY bookings DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
$topFacLabels = json_encode(array_column($topFacRaw, 'facility_name'));
$topFacData   = json_encode(array_column($topFacRaw, 'bookings'));

// ── Graph 3: Reservations by Status breakdown ────────────────────────────────
$statusRaw = $conn->query("
    SELECT reservation_status AS status, COUNT(*) AS total
    FROM reservations
    GROUP BY reservation_status
")->fetch_all(MYSQLI_ASSOC);
$statusLabels = json_encode(array_column($statusRaw, 'status'));
$statusData   = json_encode(array_column($statusRaw, 'total'));

// ── Graph 4: Peak Reservation Hours ──────────────────────────────────────────────
// Meaningful: shows which time slots are most popular so admin can manage slot availability
$peakRaw = $conn->query("
    SELECT HOUR(start_time) AS hr, COUNT(*) AS total
    FROM reservations
    WHERE reservation_status IN ('Confirmed','Completed','Pending')
    GROUP BY hr
    ORDER BY hr ASC
")->fetch_all(MYSQLI_ASSOC);
// Build full 8am–10pm range with 0 defaults
$peakMap = [];
foreach ($peakRaw as $p) $peakMap[(int)$p['hr']] = (int)$p['total'];
$peakHours = $peakVals = [];
for ($h = 8; $h <= 22; $h++) {
    $label = date('g A', mktime($h, 0, 0));
    $peakHours[] = $label;
    $peakVals[]  = $peakMap[$h] ?? 0;
}
$peakHoursJson = json_encode($peakHours);
$peakValsJson  = json_encode($peakVals);

// ── Graph 5: Facility Utilisation Rate (%) ───────────────────────────────────
// Meaningful: compares confirmed bookings per facility as % of total confirmed bookings
$utilRaw = $conn->query("
    SELECT f.facility_name,
           COUNT(r.reservation_id) AS bookings
    FROM facilities f
    LEFT JOIN reservations r
        ON r.facility_id = f.facility_id
        AND r.reservation_status IN ('Confirmed','Completed')
    GROUP BY f.facility_id
    ORDER BY bookings DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);
$utilTotal  = max(1, array_sum(array_column($utilRaw, 'bookings')));
$utilLabels = json_encode(array_column($utilRaw, 'facility_name'));
$utilRates  = json_encode(array_map(fn($r) => round($r['bookings'] / $utilTotal * 100, 1), $utilRaw));

require __DIR__ . '/../includes/header.php';
?>
<main class="page-wrap"><div class="container">
  <div class="page-header">
    <div><h1>Admin Dashboard</h1><p>Operational overview of UniSport.</p></div>
    <div><span class="btn btn-primary">Welcome, <?= e(current_user_name()) ?>!</span></div>
  </div>
  <?php require __DIR__ . '/tabs.php'; ?>

  <!-- Stat Cards -->
  <div class="stats-row">
    <div class="stat-card"><div class="stat-icon">🏟️</div><div class="stat-label">Facilities</div><div class="stat-val"><?= (int)$total_fac ?></div></div>
    <div class="stat-card"><div class="stat-icon">👤</div><div class="stat-label">Users</div><div class="stat-val"><?= (int)$total_users ?></div></div>
    <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-label">Reservations</div><div class="stat-val"><?= (int)$total_res ?></div></div>
    <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-label">Pending</div><div class="stat-val"><?= (int)$pending ?></div></div>
  </div>

  <!-- Charts Row 1 -->
  <div class="chart-grid" style="margin-top:28px">

    <!-- Graph 1: Monthly Reservation Trend -->
    <div class="chart-card">
      <div class="chart-card-title">Monthly Reservation Trend</div>
      <div class="chart-card-sub">Total reservations per month over the last 6 months</div>
      <div class="chart-wrap">
        <?php if (empty($monthlyRaw)): ?>
          <div class="chart-empty">No data yet.</div>
        <?php else: ?>
          <canvas id="monthlyChart"></canvas>
        <?php endif; ?>
      </div>
    </div>

    <!-- Graph 2: Top 5 Most Reserved Facilities -->
    <div class="chart-card">
      <div class="chart-card-title">Top 5 Most Reserved Facilities</div>
      <div class="chart-card-sub">Based on confirmed &amp; completed reservations</div>
      <div class="chart-wrap">
        <?php if (empty($topFacRaw)): ?>
          <div class="chart-empty">No confirmed bookings yet.</div>
        <?php else: ?>
          <canvas id="topFacChart"></canvas>
        <?php endif; ?>
      </div>
    </div>

    <!-- Graph 3: Reservations by Status -->
    <div class="chart-card chart-card--narrow">
      <div class="chart-card-title">Reservations by Status</div>
      <div class="chart-card-sub">All-time breakdown</div>
      <div class="chart-wrap" style="max-height:220px">
        <?php if (empty($statusRaw)): ?>
          <div class="chart-empty">No reservations yet.</div>
        <?php else: ?>
          <canvas id="statusChart"></canvas>
        <?php endif; ?>
      </div>
      <div class="chart-legend" id="statusLegend"></div>
    </div>

    <!-- Graph 4: Peak Reservation Hours -->
    <div class="chart-card">
      <div class="chart-card-title">Peak Reservation Hours</div>
      <div class="chart-card-sub">Which time slots are most in-demand (confirmed &amp; pending)</div>
      <div class="chart-wrap">
        <canvas id="peakChart"></canvas>
      </div>
    </div>

  </div>

  <!-- Graph 5: Facility Utilisation Rate -->
  <div class="chart-card" style="margin-top:20px">
    <div class="chart-card-title">Facility Utilisation Rate</div>
    <div class="chart-card-sub">Share of confirmed &amp; completed reservations per facility — reveals which venues are underused</div>
    <div class="chart-wrap" style="max-height:260px">
      <?php if (empty($utilRaw)): ?>
        <div class="chart-empty">No data yet.</div>
      <?php else: ?>
        <canvas id="utilChart"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Reservations -->
  <h2 class="section-title" style="margin-top:36px">Recent Reservations</h2>
  <div class="section-line"></div>
  <?php if (!$recent): ?>
    <div class="empty">No reservations yet.</div>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>ID</th><th>User</th><th>Facility</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['reservation_id'] ?></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= e($r['facility_name']) ?></td>
            <td><?= e(fmt_date($r['booking_date'])) ?></td>
            <td><?= e(fmt_time($r['start_time'])) ?> – <?= e(fmt_time($r['end_time'])) ?></td>
            <td><span class="status-badge <?= status_class($r['reservation_status']) ?>"><?= e($r['reservation_status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div></main>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const COLORS = ['#2563eb','#16a34a','#dc2626','#d97706','#7c3aed','#0891b2','#be185d','#15803d'];

// Graph 1 – Monthly Reservation Trend
<?php if (!empty($monthlyRaw)): ?>
new Chart(document.getElementById('monthlyChart'), {
  type: 'line',
  data: {
    labels: <?= $monthLabels ?>,
    datasets: [{
      label: 'Reservations',
      data: <?= $monthData ?>,
      borderColor: '#2563eb',
      backgroundColor: 'rgba(37,99,235,0.12)',
      tension: 0.4, fill: true, pointRadius: 5, pointBackgroundColor: '#2563eb'
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
<?php endif; ?>

// Graph 2 – Top Facilities (horizontal bar)
<?php if (!empty($topFacRaw)): ?>
new Chart(document.getElementById('topFacChart'), {
  type: 'bar',
  data: {
    labels: <?= $topFacLabels ?>,
    datasets: [{
      label: 'Bookings',
      data: <?= $topFacData ?>,
      backgroundColor: COLORS,
      borderRadius: 6
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});
<?php endif; ?>

// Graph 3 – Status Doughnut
<?php if (!empty($statusRaw)): ?>
(function(){
  const labels = <?= $statusLabels ?>;
  const data   = <?= $statusData ?>;
  const colors = ['#d97706','#16a34a','#dc2626','#2563eb','#7c3aed'];
  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2 }] },
    options: { responsive: true, cutout: '62%', plugins: { legend: { display: false } } }
  });
  // Custom legend
  const leg = document.getElementById('statusLegend');
  labels.forEach((l,i) => {
    leg.innerHTML += `<span class="legend-dot" style="background:${colors[i]}"></span>${l} (${data[i]}) &nbsp;`;
  });
})();
<?php endif; ?>

// Graph 4 – Peak Reservation Hours
new Chart(document.getElementById('peakChart'), {
  type: 'bar',
  data: {
    labels: <?= $peakHoursJson ?>,
    datasets: [{
      label: 'Reservations',
      data: <?= $peakValsJson ?>,
      backgroundColor: 'rgba(37,99,235,0.75)',
      borderRadius: 5
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});

// Graph 5 – Utilisation Rate (horizontal bar %)
<?php if (!empty($utilRaw)): ?>
new Chart(document.getElementById('utilChart'), {
  type: 'bar',
  data: {
    labels: <?= $utilLabels ?>,
    datasets: [{
      label: 'Utilisation %',
      data: <?= $utilRates ?>,
      backgroundColor: COLORS,
      borderRadius: 6
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
  }
});
<?php endif; ?>
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
