<?php
    session_start();
    // Clear and destroy the session for security so they must re-authenticate
    session_unset();
    session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Updated</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body class="login-body">
    <div class="box box-center">
        
        <div class="success-icon success-icon-green-lg">✓</div>
        
        <h3 class="success-title-lg">Password Updated!</h3>
        
        <p class="success-text-muted">
            Your password has been successfully updated! <br>
            Please log in again with your new credentials.
        </p>
        
        <a href="StudentLogin.php" class="btn-primary btn-primary-link-center">Back to Login</a>
    </div>
</body>
</html>