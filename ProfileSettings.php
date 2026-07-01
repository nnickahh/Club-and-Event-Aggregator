<?php
    session_start();
    require_once 'db_connect.php';

    // Security Check
    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }

    $student_id = $_SESSION['student_id'];
    session_write_close();

    $query = "SELECT name, email FROM students WHERE studentID = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        die("SQL Error: " . $conn->error);
    }

    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
?>

<script>
    function togglePassword(inputId, iconId) {
        const passwordInput = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        // SVG Path for "Open Eye" (Visible)
        const openEyePath = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
        
        // SVG Path for "Slashed Eye" (Hidden) - This matches your current code
        const slashedEyePath = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>';

        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            icon.innerHTML = openEyePath;
            icon.classList.add("visible");
        } else {
            passwordInput.type = "password";
            icon.innerHTML = slashedEyePath;
            icon.classList.remove("visible");
        }
    }

    function checkPasswordMatch() {
        const password = document.getElementById("new_password");
        const confirm = document.getElementById("confirm_password");
        if (confirm.value === "") {
            confirm.setCustomValidity("");
            return;
        }
        if (password.value !== confirm.value) {
            confirm.setCustomValidity("Passwords do not match");
        } else {
            confirm.setCustomValidity("");
        }
    }
</script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Profile</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>
    <?php include 'StudentNavbar.php'; ?>
    
    <main class="container">
        <h2 class="page-title-spaced">Account Profile</h2>
        
        <div class="settings-card">
            
            <section class="settings-section">
                <h4>Personal Details</h4>
                <div class="dashed-line"></div>
                <div class="detail-row">
                    <strong>Full Name:</strong> <span><?php echo htmlspecialchars($user['name']); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Student ID:</strong> <span><?php echo htmlspecialchars($student_id); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Email:</strong> <span><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
            </section>

            <form action="UpdatePassword.php" method="POST">
                <section class="settings-section settings-section-spaced">
                    <h4>Security</h4>
                    <div class="dashed-line"></div>
                    <p class="section-heading">Change Password:</p>
                    <p class="password-hint-settings">* Must be at least 8 chars with uppercase, lowercase, numbers & symbols.</p>
                    
                    <div class="form-group">
                        <label>Current Password:</label>
                        <div class="password-wrapper">
                            <input type="password" name="current_password" id="current_pass" required>
                            <svg id="eye_icon_0" class="eye-icon" width="24" height="24" onclick="togglePassword('current_pass', 'eye_icon_0')" viewBox="0 0 24 24">
                                <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>New Password :</label>
                        <div class="password-wrapper">
                            <input type="password" name="new_password" id="new_password" required
                                pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"  
                                oninput="this.setCustomValidity(''); checkPasswordMatch();" 
                                oninvalid="this.setCustomValidity('Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special symbol.')">
                            
                            <svg id="eye_icon_1" class="eye-icon" width="24" height="24" onclick="togglePassword('new_password', 'eye_icon_1')" viewBox="0 0 24 24">
                                <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password :</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" required
                                oninput="checkPasswordMatch()">
                            
                            <svg id="eye_icon_2" class="eye-icon" width="24" height="24" onclick="togglePassword('confirm_password', 'eye_icon_2')" viewBox="0 0 24 24">
                                <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>
                            </svg>
                        </div>
                    </div>
                </section>

                <div class="settings-footer">
                    <button type="submit" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>