<!--ClubSettings.php-->
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

    // 1. Fetch authentic identity data out of admins table
    $adminStmt = $conn->prepare("SELECT name FROM admins WHERE adminID = ?");
    $adminStmt->bind_param("s", $adminID);
    $adminStmt->execute();
    $adminData = $adminStmt->get_result()->fetch_assoc();
    $adminStmt->close();

    // 2. Fetch public presentation metrics from clubs table
    $clubStmt = $conn->prepare("SELECT clubName, clubEmail, description, profilePic FROM clubs WHERE adminID = ?");
    $clubStmt->bind_param("s", $adminID);
    $clubStmt->execute();
    $clubData = $clubStmt->get_result()->fetch_assoc();
    $clubStmt->close();

    $clubName    = $clubData['clubName'] ?? ($_SESSION['club_name'] ?? "My Club");
    $clubEmail   = $clubData['clubEmail'] ?? "club@inti.edu.my";
    $description = $clubData['description'] ?? "";
    $profilePic  = $clubData['profilePic'] ?? "";

    // 3. Process form updates securely
    if (isset($_POST['update_profile'])) {
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

        $checkStmt = $conn->prepare("SELECT clubName FROM clubs WHERE adminID = ?");
        $checkStmt->bind_param("s", $adminID);
        $checkStmt->execute();
        $hasRecord = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();

        if ($hasRecord) {
            $updateStmt = $conn->prepare("UPDATE clubs SET clubEmail = ?, description = ?, profilePic = ? WHERE adminID = ?");
            $updateStmt->bind_param("ssss", $clubEmail, $description, $profilePic, $adminID);
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
        } else {
            $message = "<div class='toast-notification error'>❌ Error processing profile adjustments.</div>";
        }
    }

    // 4. Collect active tracked events sorted into Upcoming and Passed
    $currentDate = date('Y-m-d');
    $eventStmt = $conn->prepare("SELECT eventID, eventTitle, eventDate, venue, eventTime FROM events WHERE adminID = ? ORDER BY eventDate ASC");
    $eventStmt->bind_param("s", $adminID);
    $eventStmt->execute();
    $eventsResult = $eventStmt->get_result();
    
    $upcomingEvents = [];
    $pastEvents = [];
    while($ev = $eventsResult->fetch_assoc()) {
        if ($ev['eventDate'] >= $currentDate) {
            $upcomingEvents[] = $ev;
        } else {
            $pastEvents[] = $ev;
        }
    }
    $eventStmt->close();

    // 5. Collect membership roster list
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
    <title>Club Profile - Setup Settings</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body class="premium-dashboard">

    <?php include 'AdminNavbar.php'; ?>

    <main class="profile-hero-container">
        <!-- Unified Profile Hero Header -->
        <div class="profile-brand-card">
            <div class="brand-identity-flex">
                <div class="avatar-uploader-wrapper">
                    <img src="<?php echo !empty($profilePic) ? htmlspecialchars($profilePic) : 'Image/default-club.png'; ?>" class="brand-avatar-img" alt="Club Logo">
                    <label class="avatar-edit-overlay">
                        📸
                        <input type="file" name="profilePic" form="mainProfileForm" accept="image/*" onchange="document.getElementById('mainProfileForm').submit();">
                    </label>
                </div>
                <div class="brand-meta-details">
                    <span class="badge-role">OFFICIAL CLUB</span>
                    <h2><?php echo htmlspecialchars($clubName); ?></h2>
                    <p class="meta-subline">📍 INTI International University • Admin Workspace</p>
                </div>
            </div>
            <div class="action-header-buttons">
                <button type="submit" name="update_profile" form="mainProfileForm" class="btn-modern-primary">Save Profile Changes</button>
            </div>
        </div>

        <?php echo $message; ?>

        <!-- Segmented Tab Navigation Bar -->
        <div class="tab-navigation-bar">
            <button class="tab-trigger active" onclick="switchActiveTab(event, 'overview-tab')">Overview</button>
            <button class="tab-trigger" onclick="switchActiveTab(event, 'events-tab')">Events <span class="tab-counter"><?php echo count($upcomingEvents); ?></span></button>
            <button class="tab-trigger" onclick="switchActiveTab(event, 'members-tab')">Members <span class="tab-counter"><?php echo $membersResult->num_rows; ?></span></button>
            <button class="tab-trigger" onclick="switchActiveTab(event, 'contacts-tab')">Contacts</button>
        </div>

        <!-- FIXED Form target correctly configured to loop inside ClubSettings.php -->
        <form action="ClubSettings.php" method="POST" id="mainProfileForm" enctype="multipart/form-data">
            
            <!-- OVERVIEW TAB -->
            <div id="overview-tab" class="tab-content-panel active">
                <div class="glass-editor-card">
                    <div class="card-title-header">
                        <h3>About Our Club</h3>
                        <p>Write a welcoming summary statement for prospective students looking to join.</p>
                    </div>
                    <div class="form-group-modern">
                        <textarea name="description" placeholder="Type club biography, values, mission, or practice weekly schedules..."><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- CONTACTS TAB -->
            <div id="contacts-tab" class="tab-content-panel">
                <div class="glass-editor-card">
                    <div class="card-title-header">
                        <h3>Public Contact Details</h3>
                        <p>These contact details will be featured publicly across student discovery listings.</p>
                    </div>
                    <div class="form-input-grid">
                        <div class="form-group-modern">
                            <label>Official Contact Email</label>
                            <input type="email" name="clubEmail" required value="<?php echo htmlspecialchars($clubEmail); ?>">
                        </div>
                        <div class="form-group-modern">
                            <label>Instagram Handle</label>
                            <input type="text" placeholder="@inti_badminton_club" value="@<?php echo strtolower(str_replace(' ', '', $clubName)); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="update_profile" value="1">
        </form>

        <!-- EVENTS TAB -->
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

        <!-- MEMBERS TAB -->
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

    <!-- Tab Transitions Controller -->
    <script>
        function switchActiveTab(event, tabId) {
            const panels = document.querySelectorAll('.tab-content-panel');
            panels.forEach(panel => panel.classList.remove('active'));

            const triggers = document.querySelectorAll('.tab-trigger');
            triggers.forEach(trigger => trigger.classList.remove('active'));

            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>