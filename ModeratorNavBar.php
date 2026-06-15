<nav class="navbar">
    <a href="ModeratorDashboard.php" class="logo">
        <img src="Image/inti-logo.png" alt="INTI Logo">
    </a>
    <div class="nav-links">
        <a href="ModeratorDashboard.php" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">Home</a>
        <a href="ModeratorEvents.php" class="<?php echo $currentPage === 'events' ? 'active' : ''; ?>">
            Events
            <?php if (isset($pendingEvents) && $pendingEvents > 0): ?>
                <span class="nav-badge"><?php echo $pendingEvents; ?></span>
            <?php endif; ?>
        </a>
        <a href="ModeratorClubs.php" class="<?php echo $currentPage === 'clubs' ? 'active' : ''; ?>">
            Clubs
            <?php if (isset($pendingClubs) && $pendingClubs > 0): ?>
                <span class="nav-badge"><?php echo $pendingClubs; ?></span>
            <?php endif; ?>
        </a>
        <div class="profile-dropdown">
            <span class="profile-name">👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Moderator'); ?></span>
            <div class="dropdown-content">
                <a href="ProfileSettings.php">Profile</a>
                <a href="LogOut.php">Sign Out</a>
            </div>
        </div>
    </div>
</nav>
