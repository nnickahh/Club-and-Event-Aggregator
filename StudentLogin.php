<!--StudentLogin.php-->
<!--Student login into system-->
<?php
    // Turn on error reporting for debugging
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Start the session
    session_start();
    
    // Connect to the database
    require_once 'db_connect.php';
    $message = "";

    // Check if the user clicked the Login button
    if (isset($_POST["submit"])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // FIXED: Change 'users' to 'students' and match your new column names
        $stmt = $conn->prepare("SELECT studentID, name, password FROM students WHERE email = ?");
        
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                // Verify the entered password against the hashed password (Bcrypt)
                if (password_verify($password, $row['password'])) {

                    // Login Success! 
                    // Note: Students don't need 'status' check usually, but Admins do.
                    $_SESSION['student_id'] = $row['studentID'];
                    $_SESSION['full_name'] = $row['name'];
                    $_SESSION['role'] = 'student';
                    session_regenerate_id(true);
                    session_write_close();
                    
                    // Redirect them to the student dashboard
                    header("Location: StudentDashboard.php");
                    exit();
                    
                } else {
                    $message = "<p class='msg-error'>Incorrect password!</p>";
                }
            } else {
                $message = "<p class='msg-error'>Account not found. Please check your email or Sign Up.</p>";
            }
            $stmt->close();
        } else {
            $message = "<p class='msg-error'>Database query error.</p>";
        }
    }
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Login</title>
    <link rel="stylesheet" type="text/css" href="Style.css">

    <script>
        function togglePassword() {
            var input = document.getElementById("password");
            var icon = document.getElementById("eye_icon");
            
            var eyeOpen = "M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z";
            var eyeSlash = "M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z";

            if (input.type === "password") {
                input.type = "text"; 
                icon.innerHTML = '<path d="' + eyeOpen + '"/>'; 
            } else {
                input.type = "password"; 
                icon.innerHTML = '<path d="' + eyeSlash + '"/>'; 
            }
        }
    </script>
</head>
<body class="login-body">
    <div class="box">
        <h2 class="h2">Log In</h2>
        <div class="tabs">
            <a href="StudentLogin.php" class="tab-btn active">Student</a>
            <a href="AdminLogin.php" class="tab-btn">Admin</a>
        </div>
        
        <?php echo $message; ?>

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
            <div class="msg-banner" style="background:var(--green-bg);color:var(--green);border:1px solid rgba(45,125,70,0.2);padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;">
                Your account has been deleted successfully.
            </div>
        <?php endif; ?>

        <form action="StudentLogin.php" method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" required>
                    <svg id="eye_icon" class="eye-icon" width="24" height="24" onclick="togglePassword()" viewBox="0 0 24 24">
                        <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>
                    </svg>
                </div>
            </div>
            
            <!--  NEW LINE 97 (Triggers your PHP credential validation) -->
            <button type="submit" name="submit" class="btn-primary">Log In</button>            
            
            <div class="links">
                <a href="ForgotPassword.php">Forgot Password?</a>
            </div>
            <div class="links center-links">
                Don't have an account? <a href="CreateStudent.php" class="link-primary">Sign Up</a>
            </div>
        </form>
    </div>
</body>
</html>