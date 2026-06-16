<?php
    session_start();
    require_once 'db_connect.php';

    // Security Authorization Check
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }
    session_write_close();

    $adminID = $_SESSION['admin_id'];
    $message = "";

    // 1. Fetch public presentation metrics from clubs table
    $clubStmt = $conn->prepare("SELECT clubName, clubEmail, description, profilePic, socialMedia FROM clubs WHERE adminID = ?");
    $clubStmt->bind_param("s", $adminID);
    $clubStmt->execute();
    $clubData = $clubStmt->get_result()->fetch_assoc();
    $clubStmt->close();

    // Dynamically loads the registered name from the database, or uses the session fallback if empty
    $clubName    = !empty($clubData['clubName']) ? $clubData['clubName'] : ($_SESSION['club_name'] ?? "My Club");
    $clubEmail   = !empty($clubData['clubEmail']) ? $clubData['clubEmail'] : "club@inti.edu.my";
    $description = !empty($clubData['description']) ? $clubData['description'] : "";
    $profilePic  = !empty($clubData['profilePic']) ? $clubData['profilePic'] : "";
    $socialMedia = !empty($clubData['socialMedia']) ? $clubData['socialMedia'] : "";

    // 2. Process form updates securely
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clubName'])) {
        $clubName    = trim($_POST['clubName']);
        $clubEmail   = trim($_POST['clubEmail']);
        $description = trim($_POST['description']);
        $socialMedia = trim($_POST['socialMedia'] ?? '');

        if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['profilePic']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $uploadDir = "uploads/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newName = $uploadDir . "club_" . $adminID . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES['profilePic']['tmp_name'], $newName)) {
                    // Delete old profile pic if not default
                    if (!empty($profilePic) && strpos($profilePic, 'default') === false && file_exists($profilePic)) {
                        @unlink($profilePic);
                    }
                    $profilePic = $newName;
                } else {
                    $message = "<div class='toast-notification error'>❌ Failed to move uploaded file. Check directory permissions.</div>";
                }
            } else {
                $message = "<div class='toast-notification error'>❌ Invalid file type. Allowed: " . implode(', ', $allowed) . "</div>";
            }
        } elseif (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] != 4) {
            $uploadErrors = [
                1 => 'File exceeds server upload_max_filesize',
                2 => 'File exceeds form MAX_FILE_SIZE',
                3 => 'File was only partially uploaded',
                6 => 'Missing temporary folder',
                7 => 'Failed to write file to disk',
                8 => 'File upload stopped by extension'
            ];
            $errMsg = $uploadErrors[$_FILES['profilePic']['error']] ?? 'Unknown error (' . $_FILES['profilePic']['error'] . ')';
            $message = "<div class='toast-notification error'>❌ Upload error: $errMsg</div>";
        }

        // Check if row exists
        $checkStmt = $conn->prepare("SELECT clubID FROM clubs WHERE adminID = ?");
        $checkStmt->bind_param("s", $adminID);
        $checkStmt->execute();
        $hasRecord = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();

        if ($hasRecord) {
            $updateStmt = $conn->prepare("UPDATE clubs SET clubName = ?, clubEmail = ?, description = ?, profilePic = ?, socialMedia = ? WHERE adminID = ?");
            $updateStmt->bind_param("ssssss", $clubName, $clubEmail, $description, $profilePic, $socialMedia, $adminID);
            $success = $updateStmt->execute();
            $updateStmt->close();
        } else {
            $insertStmt = $conn->prepare("INSERT INTO clubs (clubName, clubEmail, description, profilePic, socialMedia, adminID) VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("ssssss", $clubName, $clubEmail, $description, $profilePic, $socialMedia, $adminID);
            $success = $insertStmt->execute();
            $insertStmt->close();
        }

        if ($success) {
            $message = "<div class='toast-notification success'>✨ Changes saved successfully!</div>";
            $_SESSION['club_name'] = $clubName;
        } else {
            $message = "<div class='toast-notification error'>❌ Error processing profile adjustments.</div>";
        }
    }

    // Handle end event action
    if (isset($_POST['end_event'])) {
        $endEventID = (int)$_POST['end_event_id'];
        $upd = $conn->prepare("UPDATE events SET status = 'ended' WHERE eventID = ? AND adminID = ?");
        $upd->bind_param("is", $endEventID, $adminID);
        $upd->execute();
        $upd->close();
        header("Location: ClubSettings.php");
        exit();
    }

    // 3. Collect active tracked events
    $currentDate = date('Y-m-d');
    $eventStmt = $conn->prepare("SELECT eventID, eventTitle, eventDate, venue, eventTime, eventEndTime, status FROM events WHERE adminID = ? ORDER BY eventDate ASC");
    $eventStmt->bind_param("s", $adminID);
    $eventStmt->execute();
    $eventsResult = $eventStmt->get_result();
    
    $ongoingEvents = [];
    $upcomingEvents = [];
    $pendingEvents = [];
    $completedEvents = [];
    $cancelledEvents = [];
    while($ev = $eventsResult->fetch_assoc()) {
        if ($ev['status'] === 'pending') {
            $pendingEvents[] = $ev;
        } elseif ($ev['status'] === 'approved' && $ev['eventDate'] == $currentDate) {
            $ongoingEvents[] = $ev;
        } elseif ($ev['status'] === 'approved' && $ev['eventDate'] > $currentDate) {
            $upcomingEvents[] = $ev;
        } elseif (($ev['status'] === 'approved' && $ev['eventDate'] < $currentDate) || $ev['status'] === 'ended') {
            $completedEvents[] = $ev;
        } elseif ($ev['status'] === 'cancelled') {
            $cancelledEvents[] = $ev;
        }
    }
    $eventStmt->close();

    // 4. Collect membership roster list with roles
    $memberQuery = "SELECT cm.studentID, cm.role, s.name, s.email
                    FROM club_members cm
                    JOIN students s ON cm.studentID = s.studentID
                    WHERE cm.adminID = ?
                    ORDER BY cm.joined_at ASC";
    $memberStmt = $conn->prepare($memberQuery);
    $memberStmt->bind_param("s", $adminID);
    $memberStmt->execute();
    $membersResult = $memberStmt->get_result();
    $memberStmt->close();

    // Handle role update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
        $targetStudent = $_POST['student_id'];
        $newRole = trim($_POST['role']);
        if (!empty($targetStudent) && !empty($newRole)) {
            $updateStmt = $conn->prepare("UPDATE club_members SET role = ? WHERE studentID = ? AND adminID = ?");
            $updateStmt->bind_param("sss", $newRole, $targetStudent, $adminID);
            $updateStmt->execute();
            $updateStmt->close();
            echo "<div class='toast-notification success'>✅ Role updated successfully!</div>";
            // Refresh the roster
            $memberStmt = $conn->prepare($memberQuery);
            $memberStmt->bind_param("s", $adminID);
            $memberStmt->execute();
            $membersResult = $memberStmt->get_result();
            $memberStmt->close();
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Profile</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'AdminNavbar.php'; ?>

    <main class="profile-hero-container">
        
        <form action="ClubSettings.php" method="POST" id="mainProfileForm" enctype="multipart/form-data">
            
            <div class="profile-brand-card">
                <div class="brand-identity-flex">
                    <div class="avatar-uploader-wrapper">
                        <img src="<?php echo !empty($profilePic) ? htmlspecialchars($profilePic) . '?t=' . time() : 'Image/default-club.png'; ?>" class="brand-avatar-img" alt="Club Logo" onclick="openAvatarViewer(this.src)" style="cursor:pointer;">
                        <label class="avatar-edit-overlay" for="profilePicInput">📸</label>
                        <input type="file" name="profilePic" id="profilePicInput" accept="image/*" style="display:none;">
                    </div>
                    <div class="brand-meta-details-wrapper">
                        <span class="badge-role">OFFICIAL CLUB</span>
                        
                        <div class="title-field-container">
                            <input type="text" name="clubName" id="clubNameInput" class="editable-field club-title-input" value="<?php echo htmlspecialchars($clubName); ?>" required readonly>
                        </div>
                        
                        <p class="meta-subline">📍 INTI International University • Admin Workspace</p>
                    </div>
                </div>
                
                <div class="action-header-buttons">
                    <button type="button" class="btn-modern-secondary hide-on-edit" onclick="enableProfileEditingMode()">✏️ Edit Profile</button>
                    <button type="button" class="btn-modern-cancel show-on-edit" onclick="disableProfileEditingMode()">Cancel</button>
                    <button type="submit" class="btn-modern-primary show-on-edit">💾 Save Changes</button>
                </div>
            </div>

            <?php echo $message; ?>

            <div class="tab-navigation-bar">
                <button type="button" class="tab-trigger active" onclick="switchActiveTab(event, 'overview-tab')">Overview</button>
                <button type="button" class="tab-trigger" onclick="switchActiveTab(event, 'events-tab')">Events <span class="tab-counter"><?php echo count($ongoingEvents) + count($upcomingEvents) + count($pendingEvents) + count($completedEvents) + count($cancelledEvents); ?></span></button>
                <button type="button" class="tab-trigger" onclick="switchActiveTab(event, 'members-tab')">Members <span class="tab-counter"><?php echo $membersResult->num_rows; ?></span></button>
                <button type="button" class="tab-trigger" onclick="switchActiveTab(event, 'contacts-tab')">Contacts</button>
            </div>

            <div id="overview-tab" class="tab-content-panel active">
                <div class="glass-editor-card">
                    <div class="card-title-header">
                        <h3>About Our Club</h3>
                        <p>Write a welcoming summary statement for prospective students looking to join.</p>
                    </div>
                    <div class="form-group-modern">
                        <textarea name="description" id="descriptionInput" class="editable-field" placeholder="Type club biography, values, mission, or practice weekly schedules..." readonly><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                </div>
            </div>

            <div id="contacts-tab" class="tab-content-panel">
                <div class="glass-editor-card">
                    <div class="card-title-header">
                        <h3>Public Contact Details</h3>
                        <p>These contact details will be featured publicly across student discovery listings.</p>
                    </div>
                    <div class="form-input-grid">
                        <div class="form-group-modern">
                            <label>Official Contact Email</label>
                            <input type="email" name="clubEmail" id="clubEmailInput" class="editable-field text-input" required value="<?php echo htmlspecialchars($clubEmail); ?>" readonly>
                        </div>
                        <div class="form-group-modern">
                            <label>Social Media & Other Contacts</label>
                            <textarea name="socialMedia" id="socialMediaInput" class="editable-field" placeholder="Instagram: @yourclub&#10;Facebook: /yourclub&#10;Website: https://..." readonly><?php echo htmlspecialchars($socialMedia ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div id="events-tab" class="tab-content-panel">
            <div class="section-flex-header">
                <a href="CreateEvent.php" class="btn-modern-secondary">+ New Event</a>
            </div>

            <?php if (!empty($ongoingEvents)): ?>
                <div class="section-flex-header">
                    <h3>Ongoing Events</h3>
                </div>
                <div class="modern-events-list">
                    <?php foreach($ongoingEvents as $ev): ?>
                        <div class="event-strip-card">
                            <div class="date-badge-box">
                                <span class="day-num"><?php echo date('d', strtotime($ev['eventDate'])); ?></span>
                                <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                            </div>
                            <div class="strip-main-info">
                                <h4><?php echo htmlspecialchars($ev['eventTitle']); ?></h4>
                                <p class="strip-meta">⏰ <?php echo date('h:iA', strtotime($ev['eventTime'])); ?><?php if (!empty($ev['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($ev['eventEndTime'])); ?><?php endif; ?> • 📍 <?php echo htmlspecialchars($ev['venue']); ?></p>
                            </div>
                            <div class="strip-actions">
                                <a href="EditEvent.php?id=<?php echo $ev['eventID']; ?>" class="action-pill-btn">Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($upcomingEvents)): ?>
                <div class="section-flex-header" style="<?php echo !empty($ongoingEvents) ? 'margin-top:24px;' : ''; ?>">
                    <h3>Upcoming Events</h3>
                </div>
                <div class="modern-events-list">
                    <?php foreach($upcomingEvents as $ev): ?>
                        <div class="event-strip-card">
                            <div class="date-badge-box">
                                <span class="day-num"><?php echo date('d', strtotime($ev['eventDate'])); ?></span>
                                <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                            </div>
                            <div class="strip-main-info">
                                <h4><?php echo htmlspecialchars($ev['eventTitle']); ?></h4>
                                <p class="strip-meta">⏰ <?php echo date('h:iA', strtotime($ev['eventTime'])); ?><?php if (!empty($ev['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($ev['eventEndTime'])); ?><?php endif; ?> • 📍 <?php echo htmlspecialchars($ev['venue']); ?></p>
                            </div>
                            <div class="strip-actions">
                                <a href="EditEvent.php?id=<?php echo $ev['eventID']; ?>" class="action-pill-btn">Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($pendingEvents)): ?>
                <div class="section-flex-header" style="<?php echo !empty($ongoingEvents) || !empty($upcomingEvents) ? 'margin-top:24px;' : ''; ?>">
                    <h3>Pending Events</h3>
                </div>
                <div class="modern-events-list">
                    <?php foreach($pendingEvents as $ev): ?>
                        <div class="event-strip-card">
                            <div class="date-badge-box">
                                <span class="day-num"><?php echo date('d', strtotime($ev['eventDate'])); ?></span>
                                <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                            </div>
                            <div class="strip-main-info">
                                <h4><?php echo htmlspecialchars($ev['eventTitle']); ?></h4>
                                <p class="strip-meta">⏰ <?php echo date('h:iA', strtotime($ev['eventTime'])); ?><?php if (!empty($ev['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($ev['eventEndTime'])); ?><?php endif; ?> • 📍 <?php echo htmlspecialchars($ev['venue']); ?></p>
                            </div>
                            <div class="strip-actions">
                                <a href="EditEvent.php?id=<?php echo $ev['eventID']; ?>" class="action-pill-btn">Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($completedEvents)): ?>
                <div class="section-flex-header" style="<?php echo !empty($ongoingEvents) || !empty($upcomingEvents) || !empty($pendingEvents) ? 'margin-top:24px;' : ''; ?>">
                    <h3>Completed Events</h3>
                </div>
                <div class="modern-events-list">
                    <?php foreach($completedEvents as $ev): ?>
                        <div class="event-strip-card">
                            <div class="date-badge-box">
                                <span class="day-num"><?php echo date('d', strtotime($ev['eventDate'])); ?></span>
                                <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                            </div>
                            <div class="strip-main-info">
                                <h4><?php echo htmlspecialchars($ev['eventTitle']); ?></h4>
                                <p class="strip-meta">⏰ <?php echo date('h:iA', strtotime($ev['eventTime'])); ?><?php if (!empty($ev['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($ev['eventEndTime'])); ?><?php endif; ?> • 📍 <?php echo htmlspecialchars($ev['venue']); ?></p>
                            </div>
                            <div class="strip-actions">
                                <a href="EditEvent.php?id=<?php echo $ev['eventID']; ?>" class="action-pill-btn">Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($cancelledEvents)): ?>
                <div class="section-flex-header" style="margin-top:24px;">
                    <h3>Cancelled Events</h3>
                </div>
                <div class="modern-events-list">
                    <?php foreach($cancelledEvents as $ev): ?>
                        <div class="event-strip-card">
                            <div class="date-badge-box">
                                <span class="day-num"><?php echo date('d', strtotime($ev['eventDate'])); ?></span>
                                <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                            </div>
                            <div class="strip-main-info">
                                <h4><?php echo htmlspecialchars($ev['eventTitle']); ?></h4>
                                <p class="strip-meta">⏰ <?php echo date('h:iA', strtotime($ev['eventTime'])); ?><?php if (!empty($ev['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($ev['eventEndTime'])); ?><?php endif; ?> • 📍 <?php echo htmlspecialchars($ev['venue']); ?></p>
                            </div>
                            <div class="strip-actions">
                                <a href="EditEvent.php?id=<?php echo $ev['eventID']; ?>" class="action-pill-btn">Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($ongoingEvents) && empty($upcomingEvents) && empty($pendingEvents) && empty($completedEvents) && empty($cancelledEvents)): ?>
                <div class="empty-state-canvas">
                    <div class="empty-icon">📅</div>
                    <h4>No active ongoing events</h4>
                    <p>Schedule an interactive project or community workshop to gather members.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="members-tab" class="tab-content-panel">
            <div class="section-flex-header">
                <h3>Club Members (<?php echo $membersResult->num_rows; ?>)</h3>
            </div>

            <?php if ($membersResult->num_rows > 0): ?>
                <div class="table-card-container">
                    <table class="premium-modern-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Official Email Address</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; while($memb = $membersResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><span class="id-tag"><?php echo htmlspecialchars($memb['studentID']); ?></span></td>
                                    <td class="user-cell-name"><strong><?php echo htmlspecialchars($memb['name']); ?></strong></td>
                                    <td><a href="mailto:<?php echo htmlspecialchars($memb['email']); ?>" class="email-link"><?php echo htmlspecialchars($memb['email']); ?></a></td>
                                    <td>
                                        <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:<?php echo $memb['role'] === 'member' ? '#f0fdf4' : '#eff6ff'; ?>;color:<?php echo $memb['role'] === 'member' ? '#16a34a' : '#2563eb'; ?>;">
                                            <?php echo htmlspecialchars($memb['role']); ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <form method="POST" class="role-input-form" onsubmit="return confirm('Update role for <?php echo htmlspecialchars($memb['studentID']); ?>?');">
                                            <input type="hidden" name="update_role" value="1">
                                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($memb['studentID']); ?>">
                                            <input type="text" name="role" value="<?php echo htmlspecialchars($memb['role']); ?>" placeholder="Enter role">
                                            <button type="submit">Set</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-canvas">
                    <div class="empty-icon">👥</div>
                    <h4>Your roster is currently empty</h4>
                    <p>Once students register for your published events, they will populate here automatically.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Avatar viewer modal -->
    <div id="avatarViewer" class="avatar-viewer-modal" onclick="closeAvatarViewer()">
        <span class="avatar-viewer-close">&times;</span>
        <img class="avatar-viewer-image" id="avatarViewerImage" alt="Enlarged club logo">
    </div>

    <script>
        function switchActiveTab(event, tabId) {
            const panels = document.querySelectorAll('.tab-content-panel');
            panels.forEach(panel => panel.classList.remove('active'));

            const triggers = document.querySelectorAll('.tab-trigger');
            triggers.forEach(trigger => trigger.classList.remove('active'));

            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        function enableProfileEditingMode() {
            document.body.classList.add('is-editing-mode');
            document.getElementById('clubNameInput').removeAttribute('readonly');
            document.getElementById('descriptionInput').removeAttribute('readonly');
            document.getElementById('clubEmailInput').removeAttribute('readonly');
            document.getElementById('socialMediaInput').removeAttribute('readonly');
        }

        function disableProfileEditingMode() {
            document.body.classList.remove('is-editing-mode');
            document.getElementById('clubNameInput').setAttribute('readonly', 'true');
            document.getElementById('descriptionInput').setAttribute('readonly', 'true');
            document.getElementById('clubEmailInput').setAttribute('readonly', 'true');
            document.getElementById('socialMediaInput').setAttribute('readonly', 'true');
        }

        function openAvatarViewer(src) {
            document.getElementById('avatarViewerImage').src = src;
            document.getElementById('avatarViewer').classList.add('active');
        }

        function closeAvatarViewer() {
            document.getElementById('avatarViewer').classList.remove('active');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAvatarViewer();
        });
    </script>
</body>
</html>