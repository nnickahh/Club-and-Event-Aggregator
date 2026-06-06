<?php
    // Start the session to check if they actually came from the registration page
    session_start();
    
    // Optional: If you want to make sure only people who just registered see this
    // You could set a session variable in CreateAdmin.php like $_SESSION['registered'] = true;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Pending</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body class="login-body">
    <div class="box">
        <div class="success-icon success-icon-warning">
            ⌛
        </div>
        
        <h3 class="text-center">Application Submitted!</h3>
        
        <p class="pending-text">
            Your application to become a Club Admin for 
            <strong>
                <?php 
                    // If you passed the club name in a session, you can show it here
                    echo isset($_SESSION['temp_club_name']) ? htmlspecialchars($_SESSION['temp_club_name']) : "your club"; 
                ?>
            </strong> 
            has been sent for review. <br><br>
            The Moderator will verify your details. You will be able to log in once your account has been <strong>Approved</strong>.
        </p>
        
        <br>
        <a href="AdminLogin.php" class="btn-outline btn-outline-block">Back to Login</a>
    </div>
</body>
</html>
