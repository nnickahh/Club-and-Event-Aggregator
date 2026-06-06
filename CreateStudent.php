<!--CreateStudentAccount.php-->
<!--Student create new account-->
<?php
    require_once 'db_connect.php';
    $message = "";

    if (isset($_POST["submit"])) {
        $fullname = trim($_POST['fullname']);
        $student_id = trim($_POST['student_id']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Backend Validation
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        $special   = preg_match('@[^\w]@', $password); 

        if(!$uppercase || !$lowercase || !$number || !$special || strlen($password) < 8) {
            $message = "<p class='msg-error-sm'>Password must meet complexity requirements.</p>";
        } 
        elseif ($password !== $confirm_password) {
            $message = "<p class='msg-error'>Passwords do not match!</p>";
        } 
        else {
            // Use PREPARED STATEMENT to check if Student ID or Email exists
            $stmt = $conn->prepare("SELECT studentID FROM students WHERE studentID = ? OR email = ?");
            $stmt->bind_param("ss", $student_id, $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $message = "<p class='msg-error'>Student ID or Email already registered!</p>";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Use PREPARED STATEMENT for Insertion (Matches Literature Review Sec 2.6)
                $insert_stmt = $conn->prepare("INSERT INTO students (name, studentID, email, password) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("ssss", $fullname, $student_id, $email, $hashed_password);

                if ($insert_stmt->execute()) {
                    header("Location: RegisterSuccess.php");
                    exit();
                } else {
                    $message = "<p class='msg-error'>Error: " . $conn->error . "</p>";
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Student Account</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
    
    <script>
        function checkPasswordMatch() {
            var password = document.getElementById("password").value;
            var confirmPassword = document.getElementById("confirm_password");

            if (password !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords do not match. Please try again.");
            } else {
                confirmPassword.setCustomValidity(""); 
            }
        }

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
    </script>
</head>
<body class="login-body">
    <div class="box">
        <h2>Create Student Account</h2>
        
        <?php echo $message; ?>

        <form action="CreateStudent.php" method="POST">
            <div class="form-group">
                <label>Full Name :</label>
                <input type="text" name="fullname" required value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Student ID :</label>
                <input type="text" name="student_id" required placeholder="e.g. P24012837"
                    pattern="[A-Za-z0-9]{1,20}"  
                    oninput="this.setCustomValidity('')" 
                    oninvalid="this.setCustomValidity('Please enter a valid Student ID (letters and numbers only, max 20 characters)')"
                    value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>INTI Email :</label>
                <input type="email" name="email" required placeholder="e.g. P24012837@gmail.com"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
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
                    <input type="password" name="confirm_password" id="confirm_password" required
                        oninput="checkPasswordMatch()" />
                    
                    <svg id="eye_icon_2" class="eye-icon" width="24" height="24" onclick="togglePassword('confirm_password', 'eye_icon_2')" viewBox="0 0 24 24">
                        <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>
                    </svg>
                </div>
            </div>
            
            <button type="submit" name="submit" class="btn-outline">Sign Up</button>
            
            <div class="links center-links">
                Already have an account? <a href="StudentLogin.php" class="link-primary"><u>Log In</u></a>
            </div>
        </form>
    </div>
</body>
</html>