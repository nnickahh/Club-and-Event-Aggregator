<!--CreateAdminAccount.php-->
<!--Admin request for new account-->
<?php
    // Turn on error reporting for debugging
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Start the session
    session_start();
    
    // Connect to the database
    require_once 'db_connect.php';
    
    $message = "";

    // Check if the form was submitted
    if (isset($_POST["submit"])) {
        $fullname = trim($_POST["fullname"]);
        $admin_id = trim($_POST["staff_id"]); 
        $club_name = trim($_POST["club_name"]);
        $club_email = trim($_POST["club_email"]);
        $password = $_POST["password"];
        $confirm_password = $_POST["confirm_password"];

        // 1. Password Validation (Matches Literature Review Sec 2.6)
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        $special   = preg_match('@[^\w]@', $password); 

        if (!ctype_alnum($admin_id)) {
            $message = "<p class='msg-error'>Staff ID cannot contain spaces or special characters (e.g. @, #, $).</p>";
        }
        elseif(!$uppercase || !$lowercase || !$number || !$special || strlen($password) < 8) {
            $message = "<p class='msg-error'>Password must be 8+ chars with uppercase, lowercase, number, and symbol.</p>";
        } 
        elseif ($password !== $confirm_password) {
            $message = "<p class='msg-error'>Passwords do not match!</p>";
        }
        else {
            // 2. Check if AdminID or Club Email exists (Prepared Statement)
            $check_stmt = $conn->prepare("SELECT adminID FROM admins WHERE adminID = ? OR clubEmail = ?");
            $check_stmt->bind_param("ss", $admin_id, $club_email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $message = "<p class='msg-error'>Admin ID or Club Email already exists!</p>";
            } else {
                // 3. Hash the password using Bcrypt
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // 4. Insert into the 'admins' table
                // Note: status is passed as a variable to match the 6 's' types in bind_param
                $status = 'pending';
                $insert_stmt = $conn->prepare("INSERT INTO admins (name, adminID, clubName, clubEmail, password, status) VALUES (?, ?, ?, ?, ?, ?)");
                
                if ($insert_stmt) {
                    $insert_stmt->bind_param("ssssss", $fullname, $admin_id, $club_name, $club_email, $hashed_password, $status);
                    
                    if ($insert_stmt->execute()) {
                        // Store club name in session to show on the success page
                        $_SESSION['temp_club_name'] = $club_name;
                        header("Location: PendingApproval.php");
                        exit();
                    } else {
                        $message = "<p class='msg-error'>Registration failed: " . $conn->error . "</p>";
                    }
                    $insert_stmt->close();
                } else {
                    $message = "<p class='msg-error'>Database Error: " . $conn->error . "</p>";
                }
            }
            $check_stmt->close();
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Apply for Club Admin</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
    
    <script>
        // Toggle password visibility
        function togglePassword(inputId, iconId) {
            var input = document.getElementById(inputId);
            var icon = document.getElementById(iconId);
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

        // Real-time password match validation
        function checkPasswordMatch() {
            var password = document.getElementById("password").value;
            var confirmPassword = document.getElementById("confirm_password");

            if (password !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords do not match.");
            } else {
                confirmPassword.setCustomValidity(""); 
            }
        }
    </script>
</head>
<body class="login-body">
    <div class="box">
        <h2>Apply for Club Admin</h2>
        
        <?php echo $message; ?>

        <form action="CreateAdmin.php" method="POST">
            <div class="form-group">
                <label>Full Name :</label>
                <input type="text" name="fullname" required value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
            </div>
          
            <div class="form-group">
                <label>Staff ID / Student ID :</label>
                <input type="text" name="staff_id" pattern="[a-zA-Z0-9]+" title="Only letters and numbers are allowed. No special characters or spaces." required 
                    value="<?php echo isset($_POST['staff_id']) ? htmlspecialchars($_POST['staff_id']) : ''; ?>" />
            </div>
            
            <div class="form-group">
                <label>Club Name :</label>
                <input type="text" name="club_name" placeholder="e.g. INTI Badminton Club" required value="<?php echo isset($_POST['club_name']) ? htmlspecialchars($_POST['club_name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Official Club Email :</label>
                <input type="email" name="club_email" placeholder="e.g. club@inti.edu.my" required value="<?php echo isset($_POST['club_email']) ? htmlspecialchars($_POST['club_email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Password :</label>
                <p class="password-hint-inline">* Must be at least 8 chars with uppercase, lowercase, numbers & symbols.</p>
                
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" required
                        pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"  
                        oninput="this.setCustomValidity(''); checkPasswordMatch();" 
                        oninvalid="this.setCustomValidity('Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special symbol.')" /> 
                    
                    <svg id="eye_icon_1" class="eye-icon" width="24" height="24" onclick="togglePassword('password', 'eye_icon_1')" viewBox="0 0 24 24">
                        <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>
                    </svg>
                </div>
            </div>
            
            <div class="form-group">
                <label>Confirm Password :</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" required oninput="checkPasswordMatch()" />
                    <svg id="eye_icon_2" class="eye-icon" width="24" height="24" onclick="togglePassword('confirm_password', 'eye_icon_2')" viewBox="0 0 24 24">
                        <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>
                    </svg>
                </div>
            </div>
            
            <button type="submit" name="submit" class="btn-outline">Submit Application</button>
            
            <div class="links center-links">
                Already have an account? <a href="AdminLogin.php" class="link-primary"><u>Log In</u></a>
            </div>
        </form>
    </div>
</body>
</html>
