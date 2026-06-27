<?php
    $notifCount = 0;
    $notifications = [];
    if (isset($_SESSION['admin_id'])) {
        require_once 'db_connect.php';
        $adminID = $_SESSION['admin_id'];
        $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE adminID = ? AND is_read = 0");
        $countStmt->bind_param("s", $adminID);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $notifCount = $countResult ? (int)$countResult->fetch_assoc()['cnt'] : 0;
        $countStmt->close();

        $nStmt = $conn->prepare("SELECT id, message, eventID, clubID, is_read, created_at FROM notifications WHERE adminID = ? ORDER BY created_at DESC LIMIT 10");
        $nStmt->bind_param("s", $adminID);
        $nStmt->execute();
        $nResult = $nStmt->get_result();
        $notifications = $nResult->fetch_all(MYSQLI_ASSOC);
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
        <a href="AdminCalendar.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'AdminCalendar.php') ? 'active' : ''; ?>">CALENDAR</a>
        <a href="ClubSettings.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'ClubSettings.php') ? 'active' : ''; ?>">MY CLUB</a>
        
        <div class="profile-dropdown">
            <span class="profile-name">
                🔔<?php if ($notifCount > 0): ?><sup class="notif-sup"><?php echo $notifCount; ?></sup><?php endif; ?>
                👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
            </span>
            <div class="dropdown-content notif-dropdown">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $n): ?>
                        <?php
                            if (!empty($n['eventID'])) { $notifTarget = 'EditEvent.php?id=' . (int)$n['eventID']; }
                            elseif (!empty($n['clubID'])) { $notifTarget = 'ClubSettings.php'; }
                            else { $notifTarget = 'ClubSettings.php'; }
                            $notifHref = 'mark_notification_read.php?type=admin&id=' . (int)$n['id'] . '&redirect=' . urlencode($notifTarget);
                        ?>
                        <a href="<?php echo htmlspecialchars($notifHref); ?>" class="notif-link <?php echo empty($n['is_read']) ? 'notif-link-unread' : ''; ?>">
                            <?php if (empty($n['is_read'])): ?><span class="notif-dot notif-dot-red"></span><?php endif; ?>
                            <span class="notif-message-text"><?php echo htmlspecialchars($n['message']); ?></span>
                            <br><small class="notif-time"><?php echo date('d M h:i A', strtotime($n['created_at'])); ?></small>
                        </a>
                    <?php endforeach; ?>
                    <a href="clear_notifications.php" class="clear-notif" onclick="return confirm('Remove all notifications from the list?');">Clear All</a>
                <?php else: ?>
                    <a class="notif-empty">No notifications</a>
                <?php endif; ?>
                <hr class="notif-sep">
                <a href="AdminProfileSettings.php">Profile</a>
                <a href="Logout.php">Sign Out</a>
            </div>
        </div>
    </div>
</nav>
