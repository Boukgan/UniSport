<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle = 'Facility Management';
$extraCss  = ['admin.css'];

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

/* ---------------------------------------------------------------
   POST handlers
--------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    /* ---------- ADD / EDIT facility ---------- */
    if ($action === 'add' || $action === 'edit') {
        $id    = (int)($_POST['facility_id'] ?? 0);
        $name  = trim($_POST['facility_name'] ?? '');
        $desc  = trim($_POST['description']   ?? '');
        $cap   = (int)($_POST['capacity']     ?? 0);
        $hours = trim($_POST['operating_hours'] ?? '8:00AM - 11:00PM');
        $stat  = $_POST['maintenance_status'] ?? 'available';
        if (!in_array($stat, ['available','limited','full','maintenance'], true)) $stat = 'available';

        // FIX: keep existing image as string or empty string — never null
        // mysqli bind_param cannot pass null by reference for 's' type
        $img = (string)($_POST['existing_image'] ?? '');

        // File upload
        if (!empty($_FILES['image']['name'])) {
            $f   = $_FILES['image'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif']) && $f['size'] <= 4 * 1024 * 1024) {
                $allowedMimes = ['image/jpeg','image/png','image/gif'];
                $finfo        = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = finfo_file($finfo, $f['tmp_name']);
                finfo_close($finfo);
                if (!in_array($detectedMime, $allowedMimes, true)) {
                    flash('error', 'Invalid image file. Please upload a real JPG, PNG, or GIF.');
                    header('Location: ' . base_url('admin/facility_management.php'));
                    exit;
                }
                $img = 'fac_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $uploadDir = __DIR__ . '/../uploads/facilities/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                move_uploaded_file($f['tmp_name'], $uploadDir . $img);
            }
        }

        if ($action === 'add') {
            // img may be empty string — store as NULL in DB for new facilities with no image
            $imgVal = $img !== '' ? $img : null;
            $stmt   = $conn->prepare(
                'INSERT INTO facilities (facility_name,description,capacity,image,operating_hours,maintenance_status)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmt->bind_param('ssisss', $name, $desc, $cap, $imgVal, $hours, $stat);
            $stmt->execute();
            $newFid = $stmt->insert_id;
            $stmt->close();

            sync_availability_to_hours($conn, $newFid, $hours);
            notify_all($conn, 'New facility added', 'A new facility "' . $name . '" has been added.');
            flash('success', 'Facility added and time slots generated.');
        } else {
            // Fetch old hours to detect change
            $oldStmt = $conn->prepare('SELECT operating_hours FROM facilities WHERE facility_id=?');
            $oldStmt->bind_param('i', $id);
            $oldStmt->execute();
            $oldRow   = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();
            $oldHours = $oldRow['operating_hours'] ?? $hours;

            // FIX: use separate variable for each bind_param arg to avoid pass-by-reference error
            $p1 = $name; $p2 = $desc; $p3 = $cap; $p4 = $img; $p5 = $hours; $p6 = $stat; $p7 = $id;
            $stmt = $conn->prepare(
                'UPDATE facilities
                 SET facility_name=?,description=?,capacity=?,image=?,operating_hours=?,maintenance_status=?
                 WHERE facility_id=?'
            );
            $stmt->bind_param('ssisssi', $p1, $p2, $p3, $p4, $p5, $p6, $p7);
            $stmt->execute();
            $stmt->close();

            $applyToAll = !empty($_POST['apply_status_to_all']);

            if ($oldHours !== $hours) {
                sync_availability_to_hours($conn, $id, $hours, $oldHours);
                $hoursMsg = ' Time slots adjusted to new operating hours.';
            } else {
                $hoursMsg = '';
            }

            if ($applyToAll) {
                // Update all future availability rows to the new status,
                // EXCEPT slots that have an active (Pending/Confirmed) reservation.
                $bookedSlots = $conn->prepare(
                    "SELECT DISTINCT booking_date, start_time FROM reservations
                     WHERE facility_id=? AND booking_date >= CURDATE()
                     AND reservation_status IN ('Pending','Confirmed')"
                );
                $bookedSlots->bind_param('i', $id);
                $bookedSlots->execute();
                $booked = $bookedSlots->get_result()->fetch_all(MYSQLI_ASSOC);
                $bookedSlots->close();

                // Build exclusion list as [(date, start_time), ...]
                $exclude = [];
                foreach ($booked as $b) $exclude[] = $b['booking_date'] . '|' . $b['start_time'];

                // Fetch all future availability rows for this facility
                $allSlots = $conn->prepare(
                    'SELECT availability_id, date, start_time FROM availability
                     WHERE facility_id=? AND date >= CURDATE()'
                );
                $allSlots->bind_param('i', $id);
                $allSlots->execute();
                $rows = $allSlots->get_result()->fetch_all(MYSQLI_ASSOC);
                $allSlots->close();

                $updated = 0;
                foreach ($rows as $row) {
                    $key = $row['date'] . '|' . $row['start_time'];
                    if (in_array($key, $exclude)) continue; // skip reserved slots
                    $upd = $conn->prepare('UPDATE availability SET status=? WHERE availability_id=?');
                    $upd->bind_param('si', $stat, $row['availability_id']);
                    $upd->execute();
                    $upd->close();
                    $updated++;
                }
                $applyMsg = ' Status "' . ucfirst($stat) . '" applied to ' . $updated . ' future slot(s).';
            } else {
                $applyMsg = '';
            }

            flash('success', 'Facility updated.' . $hoursMsg . $applyMsg);
            notify_all($conn, 'Facility updated', 'Facility "' . $name . '" has been updated.');
        }
        header('Location: ' . base_url('admin/facility_management.php'));
        exit;
    }

    /* ---------- DELETE facility ---------- */
    if ($action === 'delete') {
        $id  = (int)$_POST['facility_id'];
        $del = $conn->prepare('DELETE FROM facilities WHERE facility_id=?');
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
        flash('success', 'Facility deleted.');
        header('Location: ' . base_url('admin/facility_management.php'));
        exit;
    }

    /* ---------- SAVE individual slot statuses for a date ---------- */
    if ($action === 'slot_status') {
        $fid  = (int)$_POST['facility_id'];
        $date = trim($_POST['date'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            flash('error', 'Invalid date selected.');
            header('Location: ' . base_url('admin/facility_management.php?action=slots&facility_id=' . $fid));
            exit;
        }

        $facStmt = $conn->prepare('SELECT operating_hours FROM facilities WHERE facility_id=?');
        $facStmt->bind_param('i', $fid);
        $facStmt->execute();
        $facRow  = $facStmt->get_result()->fetch_assoc();
        $facStmt->close();
        $opHours = $facRow['operating_hours'] ?? null;

        $validStatuses = ['available','limited','full','maintenance'];
        foreach (generate_time_slots($opHours) as [$s, $e, $label]) {
            $st = $_POST['slot'][$s] ?? 'available';
            if (!in_array($st, $validStatuses, true)) $st = 'available';
            // Use explicit variables to avoid bind_param reference error
            $p1 = $fid; $p2 = $date; $p3 = $s; $p4 = $e; $p5 = $st;
            $stmt = $conn->prepare(
                'INSERT INTO availability (facility_id, date, start_time, end_time, status)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE status=VALUES(status), end_time=VALUES(end_time)'
            );
            $stmt->bind_param('issss', $p1, $p2, $p3, $p4, $p5);
            $stmt->execute();
            $stmt->close();
        }
        flash('success', 'Slot statuses saved for ' . date('d M Y', strtotime($date)) . '.');
        header('Location: ' . base_url('admin/facility_management.php?action=slots&facility_id=' . $fid . '&date=' . $date));
        exit;
    }
}

/* ---------------------------------------------------------------
   sync_availability_to_hours — called on add and on hours change
--------------------------------------------------------------- */
function sync_availability_to_hours($conn, $facility_id, $newHours, $oldHours = null) {
    [$newStart, $newEnd] = parse_operating_hours($newHours);

    $datesStmt = $conn->prepare(
        'SELECT DISTINCT date FROM availability
         WHERE facility_id=? AND date >= CURDATE() ORDER BY date'
    );
    $datesStmt->bind_param('i', $facility_id);
    $datesStmt->execute();
    $existingDates = $datesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $datesStmt->close();
    $datesToSync = array_column($existingDates, 'date');

    $today = new DateTime();
    for ($i = 0; $i <= 60; $i++) {
        $d = (clone $today)->modify("+$i day")->format('Y-m-d');
        if (!in_array($d, $datesToSync, true)) $datesToSync[] = $d;
    }

    foreach ($datesToSync as $date) {
        if ($oldHours !== null) {
            [$oldStart, $oldEnd] = parse_operating_hours($oldHours);
            for ($h = $oldStart; $h < $oldEnd; $h++) {
                if ($h < $newStart || $h >= $newEnd) {
                    $s   = sprintf('%02d:00:00', $h);
                    $fid = $facility_id;
                    $del = $conn->prepare('DELETE FROM availability WHERE facility_id=? AND date=? AND start_time=?');
                    $del->bind_param('iss', $fid, $date, $s);
                    $del->execute(); $del->close();
                }
            }
        }
        for ($h = $newStart; $h < $newEnd; $h++) {
            $s   = sprintf('%02d:00:00', $h);
            $e   = sprintf('%02d:00:00', $h + 1);
            $fid = $facility_id;
            $ins = $conn->prepare(
                "INSERT IGNORE INTO availability (facility_id, date, start_time, end_time, status)
                 VALUES (?,?,?,?,'available')"
            );
            $ins->bind_param('isss', $fid, $date, $s, $e);
            $ins->execute(); $ins->close();
        }
    }
}

/* ---------------------------------------------------------------
   GET: determine current view mode
   Modes:
     ''      → show Add form + facility table
     'edit'  → show Edit form + facility table
     'slots' → show Slot editor only (no add/edit form)
--------------------------------------------------------------- */
$edit = null;
if ($action === 'edit' && !empty($_GET['facility_id'])) {
    $editFid = (int)$_GET['facility_id'];
    $stmt = $conn->prepare('SELECT * FROM facilities WHERE facility_id=?');
    $stmt->bind_param('i', $editFid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$facs = $conn->query('SELECT * FROM facilities ORDER BY facility_name')->fetch_all(MYSQLI_ASSOC);

// Slot editor data
$slotFid = null; $slotDate = date('Y-m-d'); $slotMap = null; $slotFac = null;
if ($action === 'slots' && !empty($_GET['facility_id'])) {
    $slotFid  = (int)$_GET['facility_id'];
    $slotDate = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $slotDate) || !strtotime($slotDate)) {
        $slotDate = date('Y-m-d');
    }
    $stmt = $conn->prepare('SELECT * FROM facilities WHERE facility_id=?');
    $stmt->bind_param('i', $slotFid);
    $stmt->execute();
    $slotFac = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($slotFac) {
        $slotMap = slot_status_map(
            $conn, $slotFid, $slotDate,
            $slotFac['maintenance_status'],
            $slotFac['operating_hours']
        );
    }
}

$flashSuccess = flash('success');
$flashError   = flash('error');

require __DIR__ . '/../includes/header.php';
?>
<main class="page-wrap"><div class="container">
  <div class="page-header">
    <div><h1>Facility Management</h1><p>Add, edit and manage facilities and individual time slots.</p></div>
  </div>
  <?php require __DIR__ . '/tabs.php'; ?>

  <?php if ($flashSuccess): ?>
    <div class="auth-ok" style="margin-bottom:16px"><?= e($flashSuccess) ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="auth-err" style="margin-bottom:16px"><?= e($flashError) ?></div>
  <?php endif; ?>

<?php if ($action === 'slots' && $slotFac): ?>
  <!-- ===================== SLOT EDITOR VIEW ===================== -->
  <div class="form-card" id="slotEditorCard">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:4px">
      <div>
        <h2 class="section-title" style="font-size:18px;margin-bottom:2px">
          Slot Manager — <?= e($slotFac['facility_name']) ?>
        </h2>
        <p style="font-size:13px;color:var(--muted);margin:0">
          Pick a date, then set each time slot's status individually.
          Changes here override the facility default for that slot and date only.
        </p>
      </div>
      <a class="btn btn-outline btn-sm" href="<?= base_url('admin/facility_management.php') ?>">← Back to facilities</a>
    </div>
    <div class="section-line" style="margin-bottom:18px"></div>

    <!-- Date picker — required before editing slots -->
    <form method="get" class="fmgmt-date-row">
      <input type="hidden" name="action"      value="slots">
      <input type="hidden" name="facility_id" value="<?= (int)$slotFid ?>">
      <label style="font-size:13px;font-weight:600;white-space:nowrap">📅 Select Date <span style="color:var(--red)">*</span></label>
      <input type="date" name="date" value="<?= e($slotDate) ?>" min="<?= date('Y-m-d') ?>" required>
      <button class="btn btn-primary btn-sm">Load Slots</button>
      <span style="font-size:12px;color:var(--muted)">Choose a date to view and edit its time slots.</span>
    </form>

    <?php if ($slotMap !== null): ?>
      <!-- Legend -->
      <div class="fmgmt-slot-legend">
        <div class="fmgmt-legend-item fmgmt-legend-available">✅ Available</div>
        <div class="fmgmt-legend-item fmgmt-legend-limited">⚡ Limited</div>
        <div class="fmgmt-legend-item fmgmt-legend-full">🔴 Full / Booked</div>
        <div class="fmgmt-legend-item fmgmt-legend-maintenance">🔧 Maintenance</div>
      </div>

      <div class="fmgmt-date-heading">
        Editing slots for: <strong><?= date('l, d M Y', strtotime($slotDate)) ?></strong>
      </div>

      <!-- Slot grid form -->
      <form method="post" id="slotStatusForm">
        <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action"      value="slot_status">
        <input type="hidden" name="facility_id" value="<?= (int)$slotFid ?>">
        <input type="hidden" name="date"        value="<?= e($slotDate) ?>">

        <div class="fmgmt-slot-grid">
          <?php foreach (generate_time_slots($slotFac['operating_hours']) as [$s, $e_time, $label]):
            $cur      = $slotMap[$s] ?? 'available';
            $isBooked = ($cur === 'full');
          ?>
          <div class="fmgmt-slot-tile fmgmt-tile-<?= $cur ?>">
            <div class="fmgmt-tile-time"><?= e($label) ?></div>
            <div class="fmgmt-tile-status"><?= ucfirst($cur) ?><?= $isBooked ? ' 🔒' : '' ?></div>
            <input type="hidden" name="slot[<?= e($s) ?>]" value="<?= e($cur) ?>" class="slot-val-input">
            <div class="fmgmt-tile-picker">
              <?php foreach (['available','limited','full','maintenance'] as $opt): ?>
                <button type="button"
                        class="fmgmt-pick-btn fmgmt-pick-<?= $opt ?> <?= $cur === $opt ? 'active' : '' ?>"
                        data-value="<?= $opt ?>"
                        <?= ($isBooked && $opt !== 'full') ? 'disabled title="Active reservation — cannot change"' : '' ?>>
                  <?= ucfirst($opt) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="fmgmt-slot-actions">
          <div class="fmgmt-bulk-row">
            <span style="font-size:13px;font-weight:600;color:var(--navy)">Set all to:</span>
            <?php foreach (['available','limited','maintenance'] as $opt): ?>
              <button type="button" class="btn btn-outline btn-sm fmgmt-bulk-btn" data-bulk="<?= $opt ?>">
                All <?= ucfirst($opt) ?>
              </button>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary">💾 Save Slot Statuses</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

<?php else: ?>
  <!-- ===================== ADD / EDIT FORM ===================== -->
  <div class="form-card" style="margin-bottom:24px">
    <h2 class="section-title" style="font-size:18px"><?= $edit ? 'Edit Facility' : 'Add Facility' ?></h2>
    <div class="section-line"></div>

    <?php if ($edit): ?>
      <div class="fmgmt-hours-note">
        <span>⏱</span>
        <span>Changing <strong>Operating Hours</strong> will automatically add or remove time slots for all future dates. Existing reservations within the new range are preserved.</span>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token"    value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action"        value="<?= $edit ? 'edit' : 'add' ?>">
      <?php if ($edit): ?>
        <input type="hidden" name="facility_id"    value="<?= (int)$edit['facility_id'] ?>">
        <input type="hidden" name="existing_image" value="<?= e($edit['image'] ?? '') ?>">
      <?php endif; ?>

      <div class="form-grid-2">
        <div class="form-row">
          <label>Facility Name</label>
          <input type="text" name="facility_name" required value="<?= e($edit['facility_name'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label>Capacity</label>
          <input type="number" name="capacity" min="0" value="<?= (int)($edit['capacity'] ?? 0) ?>">
        </div>
        <div class="form-row">
          <label>Operating Hours <span style="font-weight:400;font-size:11px;color:var(--muted)">(e.g. 8:00AM - 11:00PM)</span></label>
          <input type="text" name="operating_hours"
                 value="<?= e($edit['operating_hours'] ?? '8:00AM - 11:00PM') ?>"
                 placeholder="8:00AM - 11:00PM"
                 id="opHoursInput">
          <div id="hoursPreview" class="fmgmt-hours-preview"></div>
        </div>
        <div class="form-row">
          <label>Facility Default Status
            <span style="font-weight:400;font-size:11px;color:var(--muted);display:block;margin-top:2px">
              Seeds all new slots. Individual slots overridden via Manage Slots.
            </span>
          </label>
          <select name="maintenance_status" id="statusSelect">
            <?php foreach (['available','limited','full','maintenance'] as $opt): ?>
              <option value="<?= $opt ?>" <?= ($edit['maintenance_status'] ?? 'available') === $opt ? 'selected' : '' ?>>
                <?= ucfirst($opt) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($edit): ?>
          <label class="fmgmt-apply-label" style="margin-top:10px;display:flex;align-items:flex-start;gap:8px;cursor:pointer">
            <input type="checkbox" name="apply_status_to_all" value="1" style="margin-top:2px;flex-shrink:0">
            <span style="font-size:13px;line-height:1.5">
              <strong>Apply this status to all future time slots</strong><br>
              <span style="color:var(--muted);font-weight:400">
                Updates every slot from today onward to the selected status.<br>
                Slots with active reservations (Pending/Confirmed) will not be changed.
              </span>
            </span>
          </label>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <label>Description</label>
        <textarea name="description"><?= e($edit['description'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <label>Image <span style="font-weight:400;font-size:11px;color:var(--muted)">(JPG/PNG/GIF, max 4MB)</span></label>
        <input type="file" name="image" accept="image/*">
        <?php if (!empty($edit['image'])): ?>
          <div style="font-size:12px;color:var(--muted);margin-top:4px">Current: <?= e($edit['image']) ?></div>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:10px;margin-top:6px">
        <button class="btn btn-primary"><?= $edit ? 'Update Facility' : 'Add Facility' ?></button>
        <?php if ($edit): ?>
          <a class="btn btn-outline" href="<?= base_url('admin/facility_management.php') ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ===================== FACILITIES TABLE ===================== -->
  <h2 class="section-title">All Facilities</h2>
  <div class="section-line"></div>
  <table class="data-table" style="margin-bottom:32px">
    <thead>
      <tr>
        <th>Name</th><th>Capacity</th><th>Operating Hours</th><th>Default Status</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($facs as $f): ?>
      <tr>
        <td><?= e($f['facility_name']) ?></td>
        <td><?= (int)$f['capacity'] ?></td>
        <td style="font-size:13px"><?= e($f['operating_hours']) ?></td>
        <td><span class="status-badge <?= status_class($f['maintenance_status']) ?>"><?= e(ucfirst($f['maintenance_status'])) ?></span></td>
        <td>
          <a class="btn btn-outline btn-sm" href="?action=edit&facility_id=<?= (int)$f['facility_id'] ?>">Edit</a>
          <a class="btn btn-primary btn-sm"  href="?action=slots&facility_id=<?= (int)$f['facility_id'] ?>">Manage Slots</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this facility and all its data?')">
            <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action"      value="delete">
            <input type="hidden" name="facility_id" value="<?= (int)$f['facility_id'] ?>">
            <button class="btn btn-danger btn-sm">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

</div></main>

<script>
(function () {
  /* ---- Operating hours live preview ---- */
  const hoursInput = document.getElementById('opHoursInput');
  const preview    = document.getElementById('hoursPreview');
  function updatePreview() {
    if (!hoursInput || !preview) return;
    const val = hoursInput.value.trim();
    const m = val.match(/(\d{1,2})(?::\d{2})?\s*([AP]M)\s*-\s*(\d{1,2})(?::\d{2})?\s*([AP]M)/i);
    if (!m) { preview.textContent = ''; return; }
    const toH = (n, ampm) => parseInt(n) % 12 + (ampm.toUpperCase() === 'PM' ? 12 : 0);
    const s = toH(m[1], m[2]);
    const e = Math.min(toH(m[3], m[4]), 23);
    if (e <= s) { preview.textContent = 'End must be after start.'; preview.style.color = 'var(--red)'; return; }
    const slots = [];
    for (let h = s; h < e; h++) {
      const fmt = x => { const a = x >= 12 ? 'PM' : 'AM'; return (x % 12 || 12) + a; };
      slots.push(fmt(h) + '–' + fmt(h + 1));
    }
    preview.style.color = 'var(--muted)';
    preview.textContent = slots.length + ' slot' + (slots.length !== 1 ? 's' : '') + ': ' + slots.join(', ');
  }
  if (hoursInput) { hoursInput.addEventListener('input', updatePreview); updatePreview(); }

  /* ---- Individual slot picker ---- */
  document.querySelectorAll('.fmgmt-slot-tile').forEach(tile => {
    const hidden = tile.querySelector('.slot-val-input');
    const label  = tile.querySelector('.fmgmt-tile-status');
    tile.querySelectorAll('.fmgmt-pick-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        if (this.disabled) return;
        const val = this.dataset.value;
        hidden.value = val;
        ['available','limited','full','maintenance'].forEach(c => tile.classList.remove('fmgmt-tile-' + c));
        tile.classList.add('fmgmt-tile-' + val);
        label.textContent = val.charAt(0).toUpperCase() + val.slice(1);
        tile.querySelectorAll('.fmgmt-pick-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
      });
    });
  });

  /* ---- Bulk set ---- */
  document.querySelectorAll('.fmgmt-bulk-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const bulk = this.dataset.bulk;
      document.querySelectorAll('.fmgmt-slot-tile').forEach(tile => {
        const hidden = tile.querySelector('.slot-val-input');
        const label  = tile.querySelector('.fmgmt-tile-status');
        const target = tile.querySelector('.fmgmt-pick-btn[data-value="' + bulk + '"]');
        if (target && target.disabled) return;
        hidden.value = bulk;
        ['available','limited','full','maintenance'].forEach(c => tile.classList.remove('fmgmt-tile-' + c));
        tile.classList.add('fmgmt-tile-' + bulk);
        label.textContent = bulk.charAt(0).toUpperCase() + bulk.slice(1);
        tile.querySelectorAll('.fmgmt-pick-btn').forEach(b => b.classList.toggle('active', b.dataset.value === bulk));
      });
    });
  });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
