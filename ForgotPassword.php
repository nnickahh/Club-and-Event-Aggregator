<?php
    session_start();
    require_once 'db_connect.php';

    $message = '';
    $msgType = '';
    $showResetForm = false;
    $resetEmail = '';

    $questions = [
        "What was the name of your first school?",
        "What is your pet's name?",
        "What city were you born in?",
        "What is your mother's maiden name?",
        "What was your first car?"
    ];

    // Step 2: update password
    if (isset($_POST['update'])) {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $email = $_SESSION['reset_email'] ?? '';
        $role = $_SESSION['reset_role'] ?? '';

        $uppercase = preg_match('@[A-Z]@', $newPassword);
        $lowercase = preg_match('@[a-z]@', $newPassword);
        $number    = preg_match('@[0-9]@', $newPassword);
        $special   = preg_match('@[^\w]@', $newPassword);

        if (!$email) {
            $message = 'Session expired. Please start over.';
            $msgType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $msgType = 'error';
            $showResetForm = true;
        } elseif (!$uppercase || !$lowercase || !$number || !$special || strlen($newPassword) < 8) {
            $message = 'Password must be 8+ chars with uppercase, lowercase, number, and symbol.';
            $msgType = 'error';
            $showResetForm = true;
        } else {
            $hashedPw = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($role === 'admin') {
                $upd = $conn->prepare("UPDATE admins SET password = ? WHERE clubEmail = ?");
            } else {
                $upd = $conn->prepare("UPDATE students SET password = ? WHERE email = ?");
            }
            $upd->bind_param("ss", $hashedPw, $email);
            $upd->execute();
            $upd->close();

            unset($_SESSION['reset_email'], $_SESSION['reset_role']);
            header("Location: ResetPasswordSuccess.php");
            exit();
        }
    }

    // Step 1: verify email + security question + answer in one go
    if (isset($_POST['verify'])) {
        $answer = trim($_POST['security_answer'] ?? '');
        $question = trim($_POST['security_question'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($email && $question && $answer) {
            // Check students table first
            $stmt = $conn->prepare("SELECT security_question, security_answer FROM students WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && $question === $row['security_question'] && password_verify($answer, $row['security_answer'])) {
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_role'] = 'student';
                $showResetForm = true;
            } else {
                // Check admins table
                $stmt2 = $conn->prepare("SELECT security_question, security_answer FROM admins WHERE clubEmail = ?");
                $stmt2->bind_param("s", $email);
                $stmt2->execute();
                $row2 = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();

                if ($row2 && $question === $row2['security_question'] && password_verify($answer, $row2['security_answer'])) {
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_role'] = 'admin';
                    $showResetForm = true;
                } else {
                    $message = 'Incorrect email, question, or answer. Please try again.';
                    $msgType = 'error';
                }
            }
        } else {
            $message = 'Please enter your email, select your security question, and provide the answer.';
            $msgType = 'error';
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body class="login-body">
    <div class="box">
        <?php if ($showResetForm): ?>
            <h2>Reset Password</h2>
            <p class="password-hint">* Must be 8+ chars with uppercase, lowercase, numbers & symbols.</p>

            <?php if ($message): ?>
                <div class="msg-banner" style="background:<?php echo $msgType === 'success' ? 'var(--green-bg)' : 'var(--red-light)'; ?>;color:<?php echo $msgType === 'success' ? 'var(--green)' : 'var(--red)'; ?>;border:1px solid <?php echo $msgType === 'success' ? 'rgba(45,125,70,0.2)' : 'rgba(237,28,36,0.2)'; ?>;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>New Password :</label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="new_password" required
                            pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                            oninput="this.setCustomValidity(''); checkPasswordMatch();"
                            oninvalid="this.setCustomValidity('Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special symbol.')">
                        <svg id="eye_icon_1" class="eye-icon" width="24" height="24" onclick="togglePassword('new_password', 'eye_icon_1')" viewBox="0 0 24 24">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm New Password :</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" required oninput="checkPasswordMatch()">
                        <svg id="eye_icon_2" class="eye-icon" width="24" height="24" onclick="togglePassword('confirm_password', 'eye_icon_2')" viewBox="0 0 24 24">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                    </div>
                </div>
                <button type="submit" name="update" class="btn-outline">Update Password</button>
            </form>

            <a href="ForgotPassword.php" class="cancel-btn">Start Over</a>

        <?php else: ?>
            <h2>Reset Password</h2>

            <?php if ($message): ?>
                <div class="msg-banner" style="background:<?php echo $msgType === 'success' ? 'var(--green-bg)' : 'var(--red-light)'; ?>;color:<?php echo $msgType === 'success' ? 'var(--green)' : 'var(--red)'; ?>;border:1px solid <?php echo $msgType === 'success' ? 'rgba(45,125,70,0.2)' : 'rgba(237,28,36,0.2)'; ?>;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email :</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Security Question :</label>
                    <select name="security_question" required>
                        <option value="" disabled selected>Select your security question</option>
                        <?php foreach ($questions as $q): ?>
                            <option value="<?php echo htmlspecialchars($q); ?>"><?php echo htmlspecialchars($q); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Your Answer :</label>
                    <input type="text" name="security_answer" required placeholder="Enter your answer">
                </div>
                <button type="submit" name="verify" class="btn-outline">Verify & Reset</button>
            </form>
        <?php endif; ?>

        <div class="links center-links">
            <a href="StudentLogin.php" class="link-primary"><u>Student Login</u></a>
            &nbsp;|&nbsp;
            <a href="AdminLogin.php" class="link-primary"><u>Admin Login</u></a>
        </div>
    </div>

    <script>
        function checkPasswordMatch() {
            var password = document.getElementById("new_password").value;
            var confirmPassword = document.getElementById("confirm_password");
            if (password !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords do not match.");
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
</body>
</html>