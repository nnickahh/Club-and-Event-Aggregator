<?php
    $notifCount = 0;
    $notifications = [];
    if (isset($_SESSION['admin_id'])) {
        require_once 'db_connect.php';
        $adminID = $_SESSION['admin_id'];
        $nStmt = $conn->prepare("SELECT id, message, eventID, clubID, created_at FROM notifications WHERE adminID = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
        $nStmt->bind_param("s", $adminID);
        $nStmt->execute();
        $nResult = $nStmt->get_result();
        $notifications = $nResult->fetch_all(MYSQLI_ASSOC);
        $notifCount = count($notifications);
        $nStmt->close();
    }
?>
<nav class="navbar">
    <a href="AdminDashboard.php" class="logo">
        <img src="Image/inti-logo.png" alt="INTI Logo">
    </a>
    
    <div class="nav-links">
        <a href="AdminDashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'AdminDashboard.php') ? 'active' : ''; ?>">HOME</a>
        <a href="CreateEvent.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'CreateEvent.php') ? 'active' : ''; ?>">CREATE EVENT</a>
        <a href="ClubSettings.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'ClubSettings.php') ? 'active' : ''; ?>">MY CLUB</a>
        
        <div class="profile-dropdown">
            <span class="profile-name">
                🔔<?php if ($notifCount > 0): ?><sup class="notif-sup"><?php echo $notifCount; ?></sup><?php endif; ?>
                👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
            </span>
            <div class="dropdown-content notif-dropdown">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $n): ?>
                        <a href="<?php
                            if (!empty($n['eventID'])) { echo 'EditEvent.php?id=' . (int)$n['eventID']; }
                            elseif (!empty($n['clubID'])) { echo 'ClubSettings.php'; }
                            else { echo 'ClubSettings.php'; }
                        ?>" class="notif-link">
                            <?php echo htmlspecialchars($n['message']); ?>
                            <br><small class="notif-time"><?php echo date('d M h:i A', strtotime($n['created_at'])); ?></small>
                        </a>
                    <?php endforeach; ?>
                    <a href="clear_notifications.php" class="clear-notif">Clear All</a>
                <?php else: ?>
                    <a class="notif-empty">No new notifications</a>
                <?php endif; ?>
                <hr class="notif-sep">
                <a href="AdminProfileSettings.php">Profile</a>
                <a href="Logout.php">Sign Out</a>
            </div>
        </div>
    </div>
</nav>