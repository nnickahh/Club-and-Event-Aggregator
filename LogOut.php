<?php
    session_start();

    // Check if the confirmation form was submitted
    $isLoggedOut = false;
    $lastRole = $_SESSION['role'] ?? 'student';
    $loginPage = 'StudentLogin.php';
    if ($lastRole === 'moderator') {
        $loginPage = 'ModeratorLogin.php';
    } elseif ($lastRole === 'admin') {
        $loginPage = 'AdminLogin.php';
    }

    if (isset($_POST['confirm_logout'])) {
        session_unset();
        session_destroy();
        $isLoggedOut = true;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logged Out</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body class="login-body">

    <?php if (!$isLoggedOut): ?>
        <div class="logout-modal-overlay" id="confirmModal">
            <div class="logout-modal-box">
                <h3>Logging Out?</h3>
                <p>Are you sure you want to log out of the system?</p>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="window.history.back();">Cancel</button>
                    
                    <form action="LogOut.php" method="POST">
                        <input type="hidden" name="confirm_logout" value="1">
                        <button type="submit" class="btn-confirm-logout">Yes, Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="box">
            <div class="success-icon">✓</div>
            <h2>Logout Successful!</h2>
            <div class="center-links">
                <p class="logout-success-text">
                    You have successfully logged out of your session. <br>
                    Thank you for using the campus portal!
                </p>
            </div>
            <a href="<?php echo $loginPage; ?>" class="btn-primary">Back to Login</a>
        </div>
    <?php endif; ?>

</body>
</html>
