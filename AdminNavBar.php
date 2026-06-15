<?php
    $notifCount = 0;
    $notifications = [];
    if (isset($_SESSION['admin_id'])) {
        require_once 'db_connect.php';
        $adminID = $_SESSION['admin_id'];
        $nStmt = $conn->prepare("SELECT id, message, created_at FROM notifications WHERE adminID = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
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
                🔔<?php if ($notifCount > 0): ?><sup style="color:var(--red);font-weight:700;"><?php echo $notifCount; ?></sup><?php endif; ?>
                👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
            </span>
            <div class="dropdown-content notif-dropdown">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $n): ?>
                        <a href="ClubSettings.php" style="font-size:12px;white-space:normal;line-height:1.4;padding:10px 14px;border-bottom:1px solid #f1f5f9;">
                            <?php echo htmlspecialchars($n['message']); ?>
                            <br><small style="color:#94a3b8;"><?php echo date('d M h:i A', strtotime($n['created_at'])); ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a style="font-size:12px;color:#94a3b8;cursor:default;padding:14px;">No new notifications</a>
                <?php endif; ?>
                <hr style="border:0;border-top:1px solid var(--border,#e2e8f0);margin:4px 0;">
                <a href="AdminProfileSettings.php">Profile</a>
                <a href="Logout.php">Sign Out</a>
            </div>
        </div>
    </div>
</nav>