<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }

    $adminID = $_SESSION['admin_id'];
    $eventID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // Handle AJAX payment toggle (before rendering)
    if (isset($_POST['ajax_toggle_payment']) && $eventID) {
        $sid = $_POST['student_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? '';
        if ($sid && in_array($newStatus, ['unpaid','paid'])) {
            $upd = $conn->prepare("UPDATE registrations SET payment_status = ? WHERE eventID = ? AND studentID = ?");
            $upd->bind_param("sis", $newStatus, $eventID, $sid);
            $upd->execute();
            $upd->close();
        }
        exit();
    }

    // Handle AJAX attendance toggle
    if (isset($_POST['ajax_toggle_attendance']) && $eventID) {
        $sid = $_POST['student_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? '';
        if ($sid && in_array($newStatus, ['absent','present'])) {
            $upd = $conn->prepare("UPDATE registrations SET attendance_status = ? WHERE eventID = ? AND studentID = ?");
            $upd->bind_param("sis", $newStatus, $eventID, $sid);
            $upd->execute();
            $upd->close();
        }
        exit();
    }

    // Handle add participant
    if (isset($_POST['add_participant']) && $eventID) {
        $newSid = trim($_POST['new_student_id'] ?? '');
        $newName = trim($_POST['new_name'] ?? '');
        $newEmail = trim($_POST['new_email'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? '';
        $paymentStatus = $_POST['payment_status'] ?? 'unpaid';
        if ($newSid && $newName && $newEmail) {
            // Check student exists
            $check = $conn->prepare("SELECT studentID FROM students WHERE studentID = ?");
            $check->bind_param("s", $newSid);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();
            if ($exists) {
                // Update name/email in case they changed
                $upd = $conn->prepare("UPDATE students SET name = ?, email = ? WHERE studentID = ?");
                $upd->bind_param("sss", $newName, $newEmail, $newSid);
                $upd->execute();
                $upd->close();
            } else {
                // Insert new student with placeholder password (studentID as default)
                $defaultPass = password_hash($newSid, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO students (studentID, name, email, password) VALUES (?, ?, ?, ?)");
                $ins->bind_param("ssss", $newSid, $newName, $newEmail, $defaultPass);
                $ins->execute();
                $ins->close();
            }
            // Check not already registered
            $dup = $conn->prepare("SELECT * FROM registrations WHERE eventID = ? AND studentID = ?");
            $dup->bind_param("is", $eventID, $newSid);
            $dup->execute();
            $already = $dup->get_result()->num_rows > 0;
            $dup->close();
            if (!$already) {
                $ins = $conn->prepare("INSERT INTO registrations (studentID, eventID, payment_method, payment_status) VALUES (?, ?, ?, ?)");
                $ins->bind_param("siss", $newSid, $eventID, $paymentMethod, $paymentStatus);
                $ins->execute();
                $ins->close();
            }
        }
        header("Location: EditEvent.php?id=" . $eventID);
        exit();
    }

    // Handle cancel event
    if (isset($_POST['cancel_event'])) {
        // Notify registered students
        $regStmt = $conn->prepare("SELECT studentID FROM registrations WHERE eventID = ?");
        $regStmt->bind_param("i", $eventID);
        $regStmt->execute();
        $regStudents = $regStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $regStmt->close();

        $eStmt = $conn->prepare("SELECT eventTitle, adminID FROM events WHERE eventID = ?");
        $eStmt->bind_param("i", $eventID);
        $eStmt->execute();
        $eRow = $eStmt->get_result()->fetch_assoc();
        $eStmt->close();

        if ($eRow) {
            $clubStmt = $conn->prepare("SELECT a.clubName, c.clubID FROM admins a LEFT JOIN clubs c ON a.adminID = c.adminID WHERE a.adminID = ?");
            $clubStmt->bind_param("s", $eRow['adminID']);
            $clubStmt->execute();
            $clubRow = $clubStmt->get_result()->fetch_assoc();
            $clubName = $clubRow ? $clubRow['clubName'] : 'Your club';
            $clubID = $clubRow ? (int)($clubRow['clubID'] ?? 0) : 0;
            $clubStmt->close();

            // Notify registered students
            $regMsg = $eRow['eventTitle'] . ' has been cancelled. If you have made any payment, please contact the club for a refund.';
            $nStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID, clubID) VALUES (?, ?, ?, ?)");
            foreach ($regStudents as $s) {
                $nStmt->bind_param("ssii", $s['studentID'], $regMsg, $eventID, $clubID);
                $nStmt->execute();
            }

            // Notify club_notify subscribers who are not already registered
            $subStmt = $conn->prepare("SELECT studentID FROM club_notify WHERE adminID = ? AND studentID NOT IN (SELECT studentID FROM registrations WHERE eventID = ?)");
            $subStmt->bind_param("si", $eRow['adminID'], $eventID);
            $subStmt->execute();
            $subStudents = $subStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $subStmt->close();

            $subMsg = $eRow['eventTitle'] . ' by ' . $clubName . ' has been cancelled.';
            foreach ($subStudents as $s) {
                $nStmt->bind_param("ssii", $s['studentID'], $subMsg, $eventID, $clubID);
                $nStmt->execute();
            }
            $nStmt->close();

            // Notify admin
            $adminMsg = $eRow['eventTitle'] . ' has been cancelled.';
            $aStmt = $conn->prepare("INSERT INTO notifications (adminID, message, eventID) VALUES (?, ?, ?)");
            $aStmt->bind_param("ssi", $adminID, $adminMsg, $eventID);
            $aStmt->execute();
            $aStmt->close();
        }

        $upd = $conn->prepare("UPDATE events SET status = 'cancelled' WHERE eventID = ? AND adminID = ?");
        $upd->bind_param("ii", $eventID, $adminID);
        $upd->execute();
        $upd->close();

        $_SESSION['flash_message'] = 'Event has been cancelled.';
        header("Location: AdminDashboard.php");
        exit();
    }

    if (!$eventID) {
        header("Location: AdminDashboard.php");
        exit();
    }

    // Fetch event
    $stmt = $conn->prepare("SELECT * FROM events WHERE eventID = ? AND adminID = ?");
    $stmt->bind_param("is", $eventID, $adminID);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        die("Event not found.");
    }

    $period = getEventPeriod($event['eventDate'], $event['eventEndDate'] ?? null, date('Y-m-d'));
    $isOngoing = $period === 'ongoing' && $event['status'] === 'approved';
    $isUpcoming = $period === 'upcoming' && $event['status'] === 'approved';
    $isPending = $event['status'] === 'pending';

    // Handle end event
    if (isset($_POST['end_event'])) {
        $upd = $conn->prepare("UPDATE events SET status = 'ended' WHERE eventID = ? AND adminID = ?");
        $upd->bind_param("ii", $eventID, $adminID);
        $upd->execute();
        $upd->close();
        header("Location: AdminDashboard.php");
        exit();
    }

    // Handle edit event form submission
    if (isset($_POST['save_event']) && $isPending) {
        $newTitle = trim($_POST['eventTitle'] ?? '');
        $newDate = $_POST['eventDate'] ?? '';
        $newEndDate = !empty(trim($_POST['eventEndDate'] ?? '')) ? trim($_POST['eventEndDate']) : null;
        $newTime = $_POST['eventTime'] ?? '';
        $newEndTime = trim($_POST['eventEndTime'] ?? '') ?: null;
        $newVenue = trim($_POST['venue'] ?? '');
        $newCapacity = intval($_POST['capacity'] ?? 0);
        $newDesc = trim($_POST['description'] ?? '');
        $newFee = !empty(trim($_POST['fee'] ?? '')) ? floatval($_POST['fee']) : 0.00;

        if ($newTitle && $newDate && $newTime && $newVenue && $newCapacity) {
            $upd = $conn->prepare("UPDATE events SET eventTitle=?, eventDate=?, eventEndDate=?, eventTime=?, eventEndTime=?, venue=?, capacity=?, description=?, fee=? WHERE eventID=? AND adminID=?");
            $upd->bind_param("ssssssisdsi", $newTitle, $newDate, $newEndDate, $newTime, $newEndTime, $newVenue, $newCapacity, $newDesc, $newFee, $eventID, $adminID);
            $upd->execute();
            $upd->close();
            // Refresh event data
            $event['eventTitle'] = $newTitle;
            $event['eventDate'] = $newDate;
            $event['eventEndDate'] = $newEndDate;
            $event['eventTime'] = $newTime;
            $event['eventEndTime'] = $newEndTime;
            $event['venue'] = $newVenue;
            $event['capacity'] = $newCapacity;
            $event['description'] = $newDesc;
            $event['fee'] = $newFee;
            $fee = $newFee;
        }
    }

    // Fetch participants with payment & attendance status
    $partStmt = $conn->prepare("SELECT r.studentID, r.payment_status, r.attendance_status, r.payment_method, s.name, s.email FROM registrations r JOIN students s ON r.studentID = s.studentID WHERE r.eventID = ? ORDER BY s.name ASC");
    $partStmt->bind_param("i", $eventID);
    $partStmt->execute();
    $participants = $partStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $partStmt->close();

    $totalRegistered = count($participants);
    $totalPaid = count(array_filter($participants, fn($p) => $p['payment_status'] === 'paid'));
    $totalPresent = count(array_filter($participants, fn($p) => $p['attendance_status'] === 'present'));
    $fee = floatval($event['fee'] ?? 0);
    $totalCollected = $totalPaid * $fee;
    $potentialTotal = $event['capacity'] * $fee;
    $capacity = intval($event['capacity']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event - <?php echo htmlspecialchars($event['eventTitle']); ?></title>
    <link rel="stylesheet" href="Style.css">
    <style>
        .edit-layout { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        .edit-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:24px; }
        .edit-card h3 { margin:0 0 16px; font-size:16px; display:flex; align-items:center; justify-content:space-between; }
        .stat-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .stat-box { background:#f8f9fa; border-radius:8px; padding:14px; text-align:center; }
        .stat-box .num { font-size:22px; font-weight:700; color:var(--ink); }
        .stat-box .lbl { font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; }
        .part-table { width:100%; border-collapse:collapse; font-size:13px; }
        .part-table th { text-align:left; padding:10px 12px; background:#f8f9fa; border-bottom:2px solid var(--border); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--ink-3); white-space:nowrap; }
        .part-table td { padding:10px 12px; border-bottom:1px solid var(--border); white-space:nowrap; }
        .part-table tr:hover td { background:#fafafa; }
        .toggle-btn { display:inline-flex; width:28px; height:28px; border-radius:50%; align-items:center; justify-content:center; cursor:pointer; font-size:16px; font-weight:700; transition:background 0.15s; border:none; }
        .toggle-btn.off { background:#fef2f2; color:#dc2626; }
        .toggle-btn.on { background:#dcfce7; color:#16a34a; }
        .toggle-btn:hover { opacity:0.8; }
        .search-part { width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:var(--radius-md); font-size:13px; box-sizing:border-box; margin-bottom:16px; }
        .meta-line { font-size:13px; color:var(--ink-2); margin:4px 0; }
        .meta-line strong { color:var(--ink); }
        .full-width { grid-column:1/-1; }
        .back-link { display:inline-flex; align-items:center; gap:4px; font-size:13px; color:var(--red); text-decoration:none; font-weight:600; margin-bottom:16px; }
        .add-btn { display:inline-flex; width:32px; height:32px; border-radius:50%; align-items:center; justify-content:center; background:var(--red); color:#fff; font-size:20px; font-weight:700; cursor:pointer; border:none; transition:transform 0.15s; }
        .add-btn:hover { transform:scale(1.1); }
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.4); display:none; align-items:center; justify-content:center; z-index:1000; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#fff; border-radius:12px; padding:28px 32px; max-width:400px; width:90%; }
        .modal-box h3 { margin:0 0 16px; font-size:16px; }
        .modal-box input { width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:var(--radius-md); font-size:14px; box-sizing:border-box; margin-bottom:12px; }
        .modal-actions { display:flex; gap:10px; justify-content:flex-end; }
    </style>
</head>
<body>
    <?php include 'AdminNavbar.php'; ?>
    <main class="container">
        <a href="AdminDashboard.php" class="back-link">&larr; Back to Dashboard</a>
        <div class="flex-space-between">
            <h2 class="clubs-title"><?php echo htmlspecialchars($event['eventTitle']); ?></h2>
            <?php if ($isPending): ?>
            <div class="flex-row-nowrap">
                <button onclick="toggleEdit()" id="editBtn" class="action-pill-btn">Edit</button>
                <a href="DeleteEvent.php?id=<?php echo $eventID; ?>" class="btn-red-inline" onclick="return confirm('Delete this event?')">Delete</a>
            </div>
            <?php elseif ($isOngoing || $isUpcoming): ?>
            <div class="flex-row-nowrap">
                <?php if ($isOngoing): ?>
                <form method="POST" onsubmit="return confirm('End this event and move it to completed?');">
                    <button type="submit" name="end_event" class="btn-outline-sm">End Event</button>
                </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Cancel this event? Registered students and subscribers will be notified.');">
                    <button type="submit" name="cancel_event" class="btn-outline-danger">Cancel Event</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <div class="edit-layout">
            <!-- Event Info -->
            <div class="edit-card">
                <h3>Event Details</h3>
                <div id="eventView">
                    <p class="meta-line"><strong>Date:</strong> <?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?></p>
                    <p class="meta-line"><strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?></p>
                    <p class="meta-line"><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
                    <p class="meta-line"><strong>Fee:</strong> <?php echo $fee > 0 ? 'RM' . number_format($fee, 2) : 'Free'; ?></p>
                    <p class="meta-line"><strong>Capacity:</strong> <?php echo (int)$event['capacity']; ?></p>
                    <p class="meta-line"><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    <?php if ($fee > 0 && !empty($event['payment_methods'])): ?>
                        <p class="meta-line"><strong>Payment:</strong>
                            <?php
                            $methodLabels = ['cash'=>'Cash', 'tng'=>'TNG', 'bank_in'=>'Bank In'];
                            $ms = explode(',', $event['payment_methods']);
                            echo implode(', ', array_map(fn($m) => $methodLabels[trim($m)] ?? trim($m), $ms));
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php if ($isPending): ?>
                <div id="eventEdit" style="display:none;">
                    <form method="POST" id="editForm">
                        <div class="mb-10">
                            <label class="form-label-sm">Event Title</label>
                            <input type="text" name="eventTitle" value="<?php echo htmlspecialchars($event['eventTitle']); ?>" required class="form-input">
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Date</label>
                            <input type="date" name="eventDate" value="<?php echo $event['eventDate']; ?>" required class="form-input">
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">End Date</label>
                            <input type="date" name="eventEndDate" value="<?php echo $event['eventEndDate'] ?? ''; ?>" class="form-input">
                            <small class="text-xs-muted-alt">Leave blank for single-day event.</small>
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Time</label>
                            <div class="flex-row">
                                <input type="time" name="eventTime" value="<?php echo $event['eventTime']; ?>" required class="form-input-flex">
                                <span>—</span>
                                <input type="time" name="eventEndTime" value="<?php echo $event['eventEndTime'] ?? ''; ?>" class="form-input-flex">
                            </div>
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Venue</label>
                            <input type="text" name="venue" value="<?php echo htmlspecialchars($event['venue']); ?>" required class="form-input">
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Capacity</label>
                            <input type="number" name="capacity" min="1" value="<?php echo (int)$event['capacity']; ?>" required class="form-input">
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Fee (0 = free)</label>
                            <input type="number" name="fee" min="0" step="0.01" value="<?php echo number_format($fee, 2); ?>" class="form-input">
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Description</label>
                            <textarea name="description" required class="form-textarea"><?php echo htmlspecialchars($event['description']); ?></textarea>
                        </div>
                        <div class="flex-end">
                            <button type="button" onclick="toggleEdit()" class="btn-secondary">Cancel</button>
                            <button type="submit" name="save_event" class="btn-primary-sm">Save Changes</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="edit-card">
                <h3>Registration &amp; Payment Stats</h3>
                <div class="stat-grid">
                    <div class="stat-box">
                        <div class="num"><?php echo $totalRegistered; ?> / <?php echo $capacity; ?></div>
                        <div class="lbl">Registered</div>
                    </div>
                    <?php if ($isOngoing): ?>
                    <div class="stat-box">
                        <div class="num"><?php echo $totalPresent; ?> / <?php echo $totalRegistered; ?></div>
                        <div class="lbl">Present</div>
                    </div>
                    <?php endif; ?>
                    <div class="stat-box">
                        <div class="num"><?php echo $fee > 0 ? 'RM' . number_format($totalCollected, 2) . ' / RM' . number_format($potentialTotal, 2) : '—'; ?></div>
                        <div class="lbl">Fees Collected</div>
                    </div>
                    <div class="stat-box">
                        <div class="num"><?php echo $fee > 0 ? $totalPaid . ' / ' . $totalRegistered : '—'; ?></div>
                        <div class="lbl">Payment Done</div>
                    </div>
                </div>
            </div>

            <!-- Participant List -->
            <div class="edit-card full-width">
                <h3>
                    <span>Participant List (<?php echo $totalRegistered; ?>)</span>
                    <?php if ($isOngoing): ?>
                        <button class="add-btn" onclick="document.getElementById('addModal').classList.add('open')" title="Add participant">+</button>
                    <?php endif; ?>
                </h3>
                <input type="text" id="partSearch" class="search-part" placeholder="Search by student ID or name...">
                <div class="table-responsive">
                    <table class="part-table" id="partTable">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Payment Method</th>
                                <th>Payment Status</th>
                                <?php if ($isOngoing): ?><th>Attendance</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($participants as $p): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($p['studentID']); ?></td>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo htmlspecialchars($p['email']); ?></td>
                                <td><?php
                                    if ($fee == 0) {
                                        echo '—';
                                    } else {
                                        $pm = $p['payment_method'] ?? '';
                                        $methodLabels = ['cash'=>'Cash', 'tng'=>'TNG', 'bank_in'=>'Bank In'];
                                        echo $pm ? ($methodLabels[$pm] ?? $pm) : '<span style="color:var(--ink-3)">—</span>';
                                    }
                                ?></td>
                                <td><?php if ($fee == 0): ?>—<?php else: ?>
                                    <button class="toggle-btn <?php echo $p['payment_status'] === 'paid' ? 'on' : 'off'; ?>"
                                            data-sid="<?php echo htmlspecialchars($p['studentID']); ?>"
                                            data-eid="<?php echo $eventID; ?>"
                                            data-type="payment"
                                            onclick="toggleField(this)">
                                        <?php echo $p['payment_status'] === 'paid' ? '✓' : '✗'; ?>
                                    </button>
                                <?php endif; ?></td>
                                <?php if ($isOngoing): ?>
                                <td>
                                    <button class="toggle-btn <?php echo $p['attendance_status'] === 'present' ? 'on' : 'off'; ?>"
                                            data-sid="<?php echo htmlspecialchars($p['studentID']); ?>"
                                            data-eid="<?php echo $eventID; ?>"
                                            data-type="attendance"
                                            onclick="toggleField(this)">
                                        <?php echo $p['attendance_status'] === 'present' ? '✓' : '✗'; ?>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($participants)): ?>
                    <p class="text-center text-xs-muted" style="padding:24px 0;">No participants yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add Participant Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <h3>Add Participant</h3>
            <form method="POST">
                <input type="text" name="new_student_id" placeholder="Student ID" required>
                <input type="text" name="new_name" placeholder="Full Name" required>
                <input type="email" name="new_email" placeholder="Email" required>
                <?php if ($fee > 0 && !empty($event['payment_methods'])): ?>
                <select name="payment_method" class="form-select">
                    <option value="">Payment method (optional)</option>
                    <?php
                    $methodLabels = ['cash'=>'Cash', 'tng'=>'TNG (Touch \'n Go)', 'bank_in'=>'Bank In'];
                    foreach (explode(',', $event['payment_methods']) as $m):
                        $m = trim($m);
                    ?>
                    <option value="<?php echo htmlspecialchars($m); ?>"><?php echo $methodLabels[$m] ?? $m; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <?php if ($fee > 0): ?>
                <select name="payment_status" class="form-select">
                    <option value="unpaid">Payment: Unpaid</option>
                    <option value="paid">Payment: Paid</option>
                </select>
                <?php endif; ?>
                <div class="modal-actions">
                    <button type="button" class="btn-sm btn-sm-outline btn-pad-sm" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                    <button type="submit" name="add_participant" class="btn-primary-sm btn-pad-sm">Add</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function toggleEdit() {
        const view = document.getElementById('eventView');
        const edit = document.getElementById('eventEdit');
        const btn = document.getElementById('editBtn');
        if (!view || !edit) return;
        const isEditing = edit.style.display !== 'none';
        view.style.display = isEditing ? '' : 'none';
        edit.style.display = isEditing ? 'none' : '';
        btn.textContent = isEditing ? 'Edit' : 'Cancel';
    }

    document.getElementById('partSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#partTable tbody tr').forEach(row => {
            const text = row.cells[1].textContent.toLowerCase() + ' ' + row.cells[2].textContent.toLowerCase();
            row.style.display = !q || text.includes(q) ? '' : 'none';
        });
    });

    function toggleField(btn) {
        const sid = btn.dataset.sid;
        const eid = btn.dataset.eid;
        const type = btn.dataset.type;
        const current = btn.classList.contains('on') ? 
            (type === 'payment' ? 'paid' : 'present') : 
            (type === 'payment' ? 'unpaid' : 'absent');
        const newStatus = type === 'payment' ?
            (current === 'paid' ? 'unpaid' : 'paid') :
            (current === 'present' ? 'absent' : 'present');
        const ajaxField = type === 'payment' ? 'ajax_toggle_payment' : 'ajax_toggle_attendance';

        fetch('EditEvent.php?id=' + eid, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: ajaxField + '=1&student_id=' + encodeURIComponent(sid) + '&new_status=' + newStatus
        }).then(() => {
            btn.className = 'toggle-btn ' + (newStatus === 'paid' || newStatus === 'present' ? 'on' : 'off');
            btn.textContent = (newStatus === 'paid' || newStatus === 'present') ? '✓' : '✗';
        });
    }

    // Close modal on overlay click
    document.getElementById('addModal').addEventListener('click', function (e) {
        if (e.target === this) this.classList.remove('open');
    });
    </script>
</body>
</html>
