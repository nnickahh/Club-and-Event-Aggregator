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
            <span class="profile-name">👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <div class="dropdown-content">
                <a href="ProfileSettings.php">Profile</a>
                <a href="Logout.php">Sign Out</a>
            </div>
        </div>
    </div>
</nav>
