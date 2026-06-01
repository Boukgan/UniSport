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
    //ADD OR EDIT FACILITY
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
        $img = (string)($_POST['existing_image'] ?? '');

        //file upload
        if (!empty($_FILES['image']['name'])){
            $f = $_FILES['image'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) && $f['size'] <= 4*1024*1024) {
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = finfo_file($finfo, $f['tmp_name']);
                finfo_close($finfo);
                if (!in_array($detectedMime, $allowedMimes, true)) {
                    flash('error', 'Invalid image file. Please upload a real JPG, PNG, or GIF file.');
                    header =('Location: ' , base_url('admin/facility_management.php'));
                    exit;
                }
                $img = 'fac_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                move_uploaded_file($f['tmp_name'], __DIR__ . '/../uploads/facilities/' . $img);
            }
        }
        if ($action === 'add'){
            //the img may be empty string. Therefore, we store it as null in the database for new facilities with no image
            $imgVal = $imgh !== '' ? $img : null;
            $stmt = $conn->prepare('INSERT INTO facilities (facility_name, description, capacity, image, operating_hours, maintenance_status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssisss', $name, $desc, $cap, $imgVal, $hours, $stat);
            $stmt->execute();
            $newFid = $stmt->insert_id;
            $stmt->close();

            sync_availability_to_hours($conn, $newFid, $hours);
            notify_all($conn, 'New Facility Added', 'A new facility "'. $name .'" has been added.');
            flash('success', 'Facility added and time slots generated.');
        }else{
            // Fetch old hours to check changes
            $oldStmt = $conn->prepare('SELECT operating_hours FROM facilities WHERE facility_id = ?');
            $oldStmt->bind_param('i', $id);
            $oldStmt->execute();
            $oldRow = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();
            $oldHours = $oldRow['operating_hours'] ?? $hours;

            // Use seperate variable for each bind_param arg to avoid pass-by-reference error
            $p1 = $name; 
            $p2 = $desc;
            $p3 = $cap;
            $p4 = $img;
            $p5 = $hours;
            $p6 = $stat;
            $p7 = $id;
            $stmt = $conn->prepare('UPDATE facilities SET facility_name = ?, description = ?, capacity = ?, image = ?, operating_hours = ?, maintenance_status = ? WHERE facility_id = ?');
            $stmt->bind_param('ssisssi', $p1, $p2, $p3, $p4, $p5, $p6, $p7);
            $stmt->execute();
            $stmt->close();

            $applyToAll = !empty($_POST['apply_status_to_all']);
            if ($oldHours !== $hours) {
                sync_availability_to_hours($conn, $id, $hours, $oldHours);
                $hourMsg = 'Operating hours updated and time slots synchronized.';
            } else {
                $hourMsg = '';
            }

            if ($applyToAll){
                // Update all future availability rows to the new status
                //EXCEPT slots that havbe an active (Pending or Confirmed) reservation.
                
                $bookedSlots= $conn->prepare("SELECT DISTINCT booking_date, start_time FROM reservations WHERE facility_id = ? AND  booking_date >= CURDATE() AND reservation_status IN ('Pending', 'Confirmed')");
                $bookedSlots->bind_param('i', $id);
                $bookedSlots->execute();
                $booked = $bookedSlots->get_result()->fetch_all(MYSQLI_ASSOC);
                $bookedSlots->close();

                //Buildd exclusion lists as [(booking_date, start_time), ...]
                $exclude = [];
                foreach ($booked as $b) $exclude[] = $b['booking_date'] . '|' . $b['start_time'];

                //fetch all future availability rows for this facility
                $allSlots = $conn->prepare('SELECT availability_id, date, start_time FROM availability WHERE facility_id = ? AND date >= CURDATE()');
                $allSlots->bind_param('i', $id);
                $allSlots->execute();
                $rows = $allSlots->get_result()->fetch_all(MYSQLI_ASSOC);
                $allSlots->close();

                $updated= 0;
                foreach ($rows as $row){
                    $key = $row['date'] . '|' . $row['start_time'];
                    if (in_array($key, $exclude)) continue; //skip reserved slots
                    $upd = $conn->prepare('UPDATE availability SET status = ? WHERE availability_id = ?');
                    $upd->bind_param('si', $stat, $row['availability_id']);
                    $upd->execute();
                    $upd->close();
                    $updated++;
                }
                $applyMsg = 'Status"' . ucfirst($stat) . '" applied to '. $updated .' future slot(s).';
            }else{
                $applyMsg = '';
            }

            flash('success', 'Facility updated. ' . $hoursMsg . ' ' . $applyMsg);
            notify_all($conn, 'Facility Updated', 'The facility "'. $name .'" has been updated. ' );
        }
        header('Location: ' . base_url('admin/facility_management.php'));
        exit;
    }

    //DELETE FACILITY
}
