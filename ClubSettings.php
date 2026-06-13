<?php
    session_start();
    require_once 'db_connect.php';

    // Security Authorization Check
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }

    $adminID = $_SESSION['admin_id'];
    $message = "";

    // 1. Fetch public presentation metrics from clubs table
    $clubStmt = $conn->prepare("SELECT clubName, clubEmail, description, profilePic FROM clubs WHERE adminID = ?");
    $clubStmt->bind_param("s", $adminID);
    $clubStmt->execute();
    $clubData = $clubStmt->get_result()->fetch_assoc();
    $clubStmt->close();

    // Dynamically loads the registered name from the database, or uses the session fallback if empty
    $clubName    = !empty($clubData['clubName']) ? $clubData['clubName'] : ($_SESSION['club_name'] ?? "My Club");
    $clubEmail   = !empty($clubData['clubEmail']) ? $clubData['clubEmail'] : "club@inti.edu.my";
    $description = !empty($clubData['description']) ? $clubData['description'] : "";
    $profilePic  = !empty($clubData['profilePic']) ? $clubData['profilePic'] : "";

    // 2. Process form updates securely
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clubName'])) {
        $clubName    = trim($_POST['clubName']);
        $clubEmail   = trim($_POST['clubEmail']);
        $description = trim($_POST['description']);

        if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profilePic']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $newName = "uploads/club_" . $adminID . "_" . time() . "." . $ext;
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                if (move_uploaded_file($_FILES['profilePic']['tmp_name'], $newName)) {
                    $profilePic = $newName;
                }
            }
        }

        // Check if row exists
        $checkStmt = $conn->prepare("SELECT clubID FROM clubs WHERE adminID = ?");
        $checkStmt->bind_param("s", $adminID);
        $checkStmt->execute();
        $hasRecord = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();

        if ($hasRecord) {
            $updateStmt = $conn->prepare("UPDATE clubs SET clubName = ?, clubEmail = ?, description = ?, profilePic = ? WHERE adminID = ?");
            $updateStmt->bind_param("sssss", $clubName, $clubEmail, $description, $profilePic, $adminID);
            $success = $updateStmt->execute();
            $updateStmt->close();
        } else {
            $insertStmt = $conn->prepare("INSERT INTO clubs (clubName, clubEmail, description, profilePic, adminID) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->bind_param("sssss", $clubName, $clubEmail, $description, $profilePic, $adminID);
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

    // 3. Collect active tracked events
    $currentDate = date('Y-m-d');
    $eventStmt = $conn->prepare("SELECT eventID, eventTitle, eventDate, venue, eventTime FROM events WHERE adminID = ? ORDER BY eventDate ASC");
    $eventStmt->bind_param("s", $adminID);
    $eventStmt->execute();
    $eventsResult = $eventStmt->get_result();
    
    $upcomingEvents = [];
    while($ev = $eventsResult->fetch_assoc()) {
        if ($ev['eventDate'] >= $currentDate) {
            $upcomingEvents[] = $ev;
        }
    }
    $eventStmt->close();

    // 4. Collect membership roster list
    $memberQuery = "SELECT DISTINCT s.studentID, s.name, s.email 
                    FROM students s 
                    JOIN registrations r ON s.studentID = r.studentID
                    JOIN events e ON r.eventID = e.eventID
                    WHERE e.adminID = ?";
    $memberStmt = $conn->prepare($memberQuery);
    $memberStmt->bind_param("s", $adminID);
    $memberStmt->execute();
    $membersResult = $memberStmt->get_result();
    $memberStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Profile - Settings</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body class="premium-dashboard">

    <?php include 'AdminNavbar.php'; ?>

    <main class="profile-hero-container">
        
        <form action="ClubSettings.php" method="POST" id="mainProfileForm" enctype="multipart/form-data">
            
            <div class="profile-brand-card">
                <div class="brand-identity-flex">
                    <div class="avatar-uploader-wrapper">
                        <img src="<?php echo !empty($profilePic) ? htmlspecialchars($profilePic) : 'Image/default-club.png'; ?>" class="brand-avatar-img" alt="Club Logo">
                        <label class="avatar-edit-overlay">
                            📸
                            <input type="file" name="profilePic" accept="image/*" onchange="document.getElementById('mainProfileForm').submit();">
                        </label>
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
                <button type="button" class="tab-trigger" onclick="switchActiveTab(event, 'events-tab')">Events <span class="tab-counter"><?php echo count($upcomingEvents); ?></span></button>
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
                            <label>Instagram Handle</label>
                            <input type="text" class="editable-field text-input disabled-field" placeholder="@inti_club" value="@<?php echo strtolower(str_replace(' ', '', $clubName)); ?>" readonly disabled>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div id="events-tab" class="tab-content-panel">
            <div class="section-flex-header">
                <h3>Ongoing & Scheduled Events</h3>
                <a href="CreateEvent.php" class="btn-modern-secondary">+ New Event</a>
            </div>
            
            <?php if (!empty($upcomingEvents)): ?>
                <div class="modern-events-list">
                    <?php foreach($upcomingEvents as $ev): ?>
                        <div class="event-strip-card">
                            <div class="date-badge-box">
                                <span class="day-num"><?php echo date('d', strtotime($ev['eventDate'])); ?></span>
                                <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                            </div>
                            <div class="strip-main-info">
                                <h4><?php echo htmlspecialchars($ev['eventTitle']); ?></h4>
                                <p class="strip-meta">⏰ <?php echo htmlspecialchars($ev['eventTime']); ?> • 📍 <?php echo htmlspecialchars($ev['venue']); ?></p>
                            </div>
                            <div class="strip-actions">
                                <a href="EditEvent.php?id=<?php echo $ev['eventID']; ?>" class="action-pill-btn">Edit</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-canvas">
                    <div class="empty-icon">📅</div>
                    <h4>No active ongoing events</h4>
                    <p>Schedule an interactive project or community workshop to gather members.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="members-tab" class="tab-content-panel">
            <div class="section-flex-header">
                <h3>Club Membership Roster</h3>
                <button class="btn-modern-secondary" onclick="window.print()">Export List</button>
            </div>

            <?php if ($membersResult->num_rows > 0): ?>
                <div class="table-card-container">
                    <table class="premium-modern-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Official Email Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($memb = $membersResult->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="id-tag"><?php echo htmlspecialchars($memb['studentID']); ?></span></td>
                                    <td class="user-cell-name"><strong><?php echo htmlspecialchars($memb['name']); ?></strong></td>
                                    <td><a href="mailto:<?php echo htmlspecialchars($memb['email']); ?>" class="email-link"><?php echo htmlspecialchars($memb['email']); ?></a></td>
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
        }

        function disableProfileEditingMode() {
            document.body.classList.remove('is-editing-mode');
            document.getElementById('clubNameInput').setAttribute('readonly', 'true');
            document.getElementById('descriptionInput').setAttribute('readonly', 'true');
            document.getElementById('clubEmailInput').setAttribute('readonly', 'true');
        }
    </script>
</body>
</html>