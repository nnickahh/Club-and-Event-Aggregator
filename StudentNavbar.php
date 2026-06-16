<?php
    $notifCount = 0;
    $notifications = [];
    if (isset($_SESSION['student_id'])) {
        require_once 'db_connect.php';
        $studentID = $_SESSION['student_id'];
        $nStmt = $conn->prepare("SELECT id, message, eventID, created_at FROM student_notifications WHERE studentID = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
        $nStmt->bind_param("s", $studentID);
        $nStmt->execute();
        $nResult = $nStmt->get_result();
        $notifications = $nResult->fetch_all(MYSQLI_ASSOC);
        $notifCount = count($notifications);
        $nStmt->close();
    }
?>
<nav class="navbar">
    <a href="
        <?php 
            if ($_SESSION['role'] === 'student') echo 'StudentDashboard.php';
            elseif ($_SESSION['role'] === 'admin') echo 'AdminDashboard.php';
            else echo 'ModeratorDashboard.php';
        ?>" class="logo">
        <img src="Image/inti-logo.png" alt="INTI Logo">
    </a>
    
    <div class="nav-links">
        <?php if ($_SESSION['role'] === 'student'): ?>
            <a href="StudentDashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'StudentDashboard.php') ? 'active' : ''; ?>">HOME</a>
            <a href="StudentEvents.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'StudentEvents.php') ? 'active' : ''; ?>">EVENTS</a>
            <a href="Clubs.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'Clubs.php') ? 'active' : ''; ?>">CLUBS</a>
            <a href="Calendar.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'Calendar.php') ? 'active' : ''; ?>">CALENDAR</a>
            <a href="MyEvent.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'MyEvent.php') ? 'active' : ''; ?>">MY ACTIVITIES</a>
        <?php elseif ($_SESSION['role'] === 'admin'): ?>
            <a href="AdminDashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'AdminDashboard.php') ? 'active' : ''; ?>">Home</a>
            <a href="CreateEvent.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'CreateEvent.php') ? 'active' : ''; ?>">New Event</a>
            <a href="ClubProfile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'ClubProfile.php') ? 'active' : ''; ?>">Club Profile</a>
        <?php endif; ?>

        <div class="profile-dropdown">
            <span class="profile-name">
                🔔<?php if ($notifCount > 0): ?><sup style="color:var(--red);font-weight:700;"><?php echo $notifCount; ?></sup><?php endif; ?>
                👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <div class="dropdown-content notif-dropdown">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $n): ?>
                        <a href="<?php echo !empty($n['eventID']) ? 'DetailedEvent.php?id=' . (int)$n['eventID'] : 'Clubs.php'; ?>" style="font-size:12px;white-space:normal;line-height:1.4;padding:10px 14px;border-bottom:1px solid #f1f5f9;text-decoration:none;display:block;">
                            <?php echo htmlspecialchars($n['message']); ?>
                            <br><small style="color:#94a3b8;"><?php echo date('d M h:i A', strtotime($n['created_at'])); ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a style="font-size:12px;color:#94a3b8;cursor:default;padding:14px;">No new notifications</a>
                <?php endif; ?>
                <hr style="border:0;border-top:1px solid var(--border,#e2e8f0);margin:4px 0;">
                <a href="ProfileSettings.php">Profile</a>
                <a href="Logout.php">Sign Out</a>
            </div>
        </div>
    </div>
</nav>
