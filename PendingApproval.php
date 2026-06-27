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
    <div class="box pending-approval-box">
        <svg class="pending-hourglass" viewBox="0 0 120 150" aria-hidden="true">
            <g>
                <animateTransform attributeName="transform" type="rotate" dur="4s" repeatCount="indefinite"
                    values="0 60 75;0 60 75;180 60 75;180 60 75;360 60 75"
                    keyTimes="0;0.68;0.78;0.92;1"/>

                <rect x="24" y="8" width="72" height="10" rx="5" fill="#7c2d12"/>
                <rect x="24" y="132" width="72" height="10" rx="5" fill="#7c2d12"/>
                <path d="M34 22H86C86 54 70 66 60 75C70 84 86 96 86 128H34C34 96 50 84 60 75C50 66 34 54 34 22Z"
                    fill="#dbeafe" fill-opacity="0.42" stroke="#8b5e34" stroke-width="5" stroke-linejoin="round"/>

                <path d="M43 30H77C76 48 68 59 60 66C52 59 44 48 43 30Z" fill="#f59e0b">
                    <animate attributeName="d" dur="4s" repeatCount="indefinite"
                        values="M43 30H77C76 48 68 59 60 66C52 59 44 48 43 30Z;M43 30H77C76 48 68 59 60 66C52 59 44 48 43 30Z;M57 61H63C62 64 61 65 60 66C59 65 58 64 57 61Z;M57 61H63C62 64 61 65 60 66C59 65 58 64 57 61Z;M43 30H77C76 48 68 59 60 66C52 59 44 48 43 30Z"
                        keyTimes="0;0.08;0.66;0.92;1"/>
                </path>

                <path d="M60 82C67 89 76 101 78 121H42C44 101 53 89 60 82Z" fill="#f59e0b">
                    <animate attributeName="d" dur="4s" repeatCount="indefinite"
                        values="M60 116C62 118 64 120 65 121H55C56 120 58 118 60 116Z;M60 116C62 118 64 120 65 121H55C56 120 58 118 60 116Z;M60 82C67 89 76 101 78 121H42C44 101 53 89 60 82Z;M60 82C67 89 76 101 78 121H42C44 101 53 89 60 82Z;M60 116C62 118 64 120 65 121H55C56 120 58 118 60 116Z"
                        keyTimes="0;0.08;0.66;0.92;1"/>
                </path>

                <line x1="60" y1="68" x2="60" y2="102" stroke="#f59e0b" stroke-width="4" stroke-linecap="round">
                    <animate attributeName="opacity" dur="4s" repeatCount="indefinite"
                        values="0;1;1;0;0" keyTimes="0;0.12;0.64;0.72;1"/>
                </line>

                <path d="M38 22V128M82 22V128" fill="none" stroke="#7c2d12" stroke-width="5" stroke-linecap="round"/>
            </g>
        </svg>
        
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
