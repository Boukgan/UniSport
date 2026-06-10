<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user_role() === 'admin') {
    header('Location: ' . base_url('admin/dashboard.php'));
    exit;
}

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';

$facilities = $conn->query('SELECT * FROM facilities ORDER BY facility_id ASC LIMIT 14')->fetch_all(MYSQLI_ASSOC);
?>
<div class="page-wrap">
  <section class="hero">
    <div class="hero-inner">
      <div class="slider-wrap">
        <button class="slider-arrow prev" type="button">‹</button>
        <div class="slider-track" id="homeSlider">
          <?php foreach (array_slice($facilities,0,8) as $f): ?>
            <div class="slider-card">
              <img src="<?= e(facility_image_url($f['image'])) ?>" alt="<?= e($f['facility_name']) ?>">
              <div class="cap"><?= e($f['facility_name']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="slider-arrow next" type="button">›</button>
      </div>
      <div class="find-section">
        <div class="find-title">UTeM Sports Centre</div>
        <h1 class="find-big">Reserve your <span>facility</span> in seconds</h1>
        <form class="search-bar" action="<?= base_url('dashboard.php') ?>" method="get">
          <input type="search" name="q" placeholder="Search facility, e.g. Badminton…">
          <button class="search-btn" type="submit">Search</button>
        </form>
      </div>
    </div>
  </section>

  <section class="home-content">
    <h2 class="section-title">What Facilities are Available at UTeM</h2>
    <div class="section-line"></div>
    <div class="facilities-grid">
      <?php foreach ($facilities as $f):
        $status = $f['maintenance_status'];
        $disabled = in_array($status, ['full','maintenance']);
        $href = $disabled ? '#' : base_url('booking.php?facility_id=' . $f['facility_id']);
      ?>
        <a class="fac-card <?= $disabled?'disabled':'' ?>" href="<?= e($href) ?>">
          <div class="fac-img">
            <span class="fac-status <?= status_class($status) ?>"><?= e(ucfirst($status)) ?></span>
            <img src="<?= e(facility_image_url($f['image'])) ?>" alt="<?= e($f['facility_name']) ?>">
          </div>
          <div class="fac-info">
            <div class="fac-name"><?= e($f['facility_name']) ?></div>
            <div class="fac-loc">Capacity: <?= (int)$f['capacity'] ?></div>
            <div class="fac-meta"><span><?= e($f['operating_hours']) ?></span></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <h2 class="section-title">About UniSport</h2>
    <div class="section-line"></div>
    <div class="about-section">
      <div>
        <p>UniSport is a convenient online platform designed for UTeM students to easily reserve sports facilities and venues on campus. Browse availability, pick a date and a two-hour slot, and submit your reservation — admin will confirm it shortly after.</p>
      </div>
      <div class="about-img"><img src="<?= base_url('assets/images/placeholder.svg') ?>" alt="About"></div>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
