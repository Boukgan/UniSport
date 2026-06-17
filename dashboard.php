<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_non_admin();
$pageTitle='Facilities';
$extraCss=['dashboard.css'];

$q=trim($_GET['q']??'');
$status=$_GET['status']??'';
$sql='SELECT * FROM facilities WHERE 1=1';
$params=[]; $types='';
if ($q!==''){$sql.=' AND facility_name LIKE ?';$params[]='%'.$q.'%';$types.='s';}
$sql.=' ORDER BY facility_name';
$stmt=$conn->prepare($sql);
if ($params) $stmt->bind_param($types,...$params);
$stmt->execute();
$allFacs=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/**
 * Compute a real-time display status for a facility card.
 *
 * Rules (checked against TODAY then TOMORROW):
 *  - If facility maintenance_status = 'maintenance' AND every slot today
 *    is maintenance  → show 'maintenance' (card disabled)
 *  - If any slot today has an active reservation that fills it → contributes to full count
 *  - Available slots today < 3  → 'limited'
 *  - Available slots today = 0  → 'full'
 *  - Otherwise → use facility maintenance_status as-is ('available' / 'limited')
 */
function compute_card_status($conn, $fac) {
    $fid     = (int)$fac['facility_id'];
    $default = $fac['maintenance_status'];
    $today   = date('Y-m-d');

    // Get slot map for today
    $map = slot_status_map($conn, $fid, $today, $default, $fac['operating_hours']);

    if (empty($map)) return $default;

    $total       = count($map);
    $available   = count(array_filter($map, fn($s) => $s === 'available'));
    $maintenance = count(array_filter($map, fn($s) => $s === 'maintenance'));

    // All slots maintenance → facility is maintenance (disabled)
    if ($maintenance === $total) return 'maintenance';

    // No available slots at all (all full/maintenance/limited) → full
    if ($available === 0) return 'full';

    // Fewer than 3 available slots today → limited
    if ($available < 3) return 'limited';

    // Otherwise respect what admin set as default
    return in_array($default, ['available','limited']) ? $default : 'available';
}

// Build final list with computed statuses, then apply status filter
$facs = [];
foreach ($allFacs as $f) {
    $f['_card_status'] = compute_card_status($conn, $f);
    if ($status === '' || $f['_card_status'] === $status) {
        $facs[] = $f;
    }
}

require __DIR__.'/includes/header.php';
?>
<main class="page-wrap"><div class="container">
  <div class="page-header">
    <div><h1>Facilities</h1><p>Browse all sports facilities at UTeM.</p></div>
  </div>
  <form class="filter-bar" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search facility…">
    <select name="status">
      <option value="">All statuses</option>
      <option value="available"  <?= $status==='available'?'selected':'' ?>>Available</option>
      <option value="limited"    <?= $status==='limited'?'selected':'' ?>>Limited</option>
      <option value="full"       <?= $status==='full'?'selected':'' ?>>Full</option>
      <option value="maintenance"<?= $status==='maintenance'?'selected':'' ?>>Maintenance</option>
    </select>
    <button class="btn btn-primary btn-sm" type="submit">Apply</button>
    <a class="btn btn-outline btn-sm" href="<?= base_url('dashboard.php') ?>">Reset</a>
  </form>
  <?php if (!$facs): ?>
    <div class="empty"><div class="empty-icon">🔍</div>No facilities match your filters.</div>
  <?php else: ?>
    <div class="facilities-grid">
      <?php foreach ($facs as $f):
        $s        = $f['_card_status'];
        $disabled = in_array($s, ['full','maintenance']);
        $href     = $disabled ? '#' : base_url('booking.php?facility_id='.$f['facility_id']);
      ?>
        <a class="fac-card <?= $disabled?'disabled':'' ?>" href="<?= e($href) ?>">
          <div class="fac-img">
            <span class="fac-status <?= status_class($s) ?>"><?= e(ucfirst($s)) ?></span>
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
  <?php endif; ?>
</div></main>
<?php require __DIR__.'/includes/footer.php'; ?>
