<nav class="navbar">
    <a href="AdminDashboard.php" class="logo">
        <img src="Image/inti-logo.png" alt="INTI Logo">
    </a>
    
    <div class="nav-links">
        <a href="AdminDashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'AdminDashboard.php') ? 'active' : ''; ?>">DASHBOARD</a>
        <a href="CreateEvent.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'CreateEvent.php') ? 'active' : ''; ?>">CREATE EVENT</a>
        <a href="ClubSettings.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'ClubSettings.php') ? 'active' : ''; ?>">MY CLUB</a>
        
        <div class="profile-dropdown">
            <span class="profile-name">
                👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
            </span>
            <div class="dropdown-content">
                <a href="AdminProfileSettings.php">Settings</a>
                <a href="Logout.php" class="logout-link">Log Out</a>
            </div>
        </div>
    </div>
</nav>