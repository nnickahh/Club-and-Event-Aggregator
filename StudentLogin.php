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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
<body class="student-login-body">
    <main class="student-login-shell">
        <section class="student-login-visual" aria-label="INTI campus">
            <img src="Image/inti-campus.jpg" alt="INTI International College Penang campus">
            <div class="student-login-visual-copy">
                <span>INTI Campus Event System</span>
                <h1>Student Access</h1>
                <p>Register for campus events, manage activities, and keep track of club updates in one place.</p>
            </div>
        </section>

        <section class="student-login-panel">
            <div class="student-login-header">
                <span class="login-kicker">Welcome Back</span>
                <h2>Student Login</h2>
                <p>Sign in with your student account to continue.</p>
            </div>

            <div class="tabs student-login-tabs">
                <a href="StudentLogin.php" class="tab-btn active">Student</a>
                <a href="AdminLogin.php" class="tab-btn">Admin</a>
            </div>

            <?php echo $message; ?>

            <form action="StudentLogin.php" method="POST" class="student-login-form">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="student@email.com">

                    <label>Password</label>
                    <div class="password-wrapper student-password-wrapper">
                        <input type="password" name="password" id="password" required placeholder="Enter your password">
                        <svg id="eye_icon" class="eye-icon" width="24" height="24" onclick="togglePassword()" viewBox="0 0 24 24">
                            <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>
                        </svg>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-primary student-login-submit">Log In</button>

                <div class="student-login-links">
                    <a href="ForgotPassword.php">Forgot Password?</a>
                    <span>New student? <a href="CreateStudent.php">Create account</a></span>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
