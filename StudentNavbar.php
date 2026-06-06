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
            <a href="StudentDashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'StudentDashboard.php') ? 'active' : ''; ?>">Events</a>
            <a href="Clubs.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'Clubs.php') ? 'active' : ''; ?>">Clubs</a>
            <a href="Calendar.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'Calendar.php') ? 'active' : ''; ?>">Calendar</a>
            <a href="MyEvent.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'MyEvent.php') ? 'active' : ''; ?>">My Schedule</a>
        <?php elseif ($_SESSION['role'] === 'admin'): ?>
            <a href="AdminDashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'AdminDashboard.php') ? 'active' : ''; ?>">Home</a>
            <a href="CreateEvent.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'CreateEvent.php') ? 'active' : ''; ?>">New Event</a>
            <a href="ClubProfile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'ClubProfile.php') ? 'active' : ''; ?>">Club Profile</a>
        <?php endif; ?>

        <div class="profile-dropdown">
            <span class="profile-name">👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <div class="dropdown-content">
                <a href="ProfileSettings.php">Settings</a>
                <hr>
                <a href="Logout.php" class="logout-link">Log Out</a>
            </div>
        </div>
    </div>
</nav>