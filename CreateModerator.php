<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    session_start();
    require_once 'db_connect.php';

    $message = "";

    $questions = [
        "What was the name of your first school?",
        "What is your pet's name?",
        "What city were you born in?",
        "What is your mother's maiden name?",
        "What was your first car?"
    ];

    if (isset($_POST['submit'])) {
        $name     = trim($_POST['name']);
        $email    = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'] ?? '';
        $security_question = trim($_POST['security_question'] ?? '');
        $security_answer = trim($_POST['security_answer'] ?? '');

        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        $special   = preg_match('@[^\w]@', $password);

        if ($name === '' || $email === '' || $password === '' || $confirm_password === '') {
            $message = "<p class='msg-error'>All fields are required.</p>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<p class='msg-error'>Please enter a valid email address.</p>";
        } elseif (!$uppercase || !$lowercase || !$number || !$special || strlen($password) < 8) {
            $message = "<p class='msg-error'>Password must be 8+ chars with uppercase, lowercase, number, and symbol.</p>";
        } elseif ($password !== $confirm_password) {
            $message = "<p class='msg-error'>Passwords do not match.</p>";
        } elseif (!$security_question || !$security_answer) {
            $message = "<p class='msg-error'>Please select a security question and provide an answer.</p>";
        } else {
            $checkStmt = $conn->prepare("SELECT moderatorID FROM moderators WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkStmt->close();

            if ($checkResult->num_rows > 0) {
                $message = "<p class='msg-error'>A moderator with this email already exists.</p>";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $hashedAnswer = password_hash($security_answer, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO moderators (name, email, password, security_question, security_answer, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->bind_param("sssss", $name, $email, $hashedPassword, $security_question, $hashedAnswer);

                if ($stmt->execute()) {
                    $message = "<p class='msg-success'>Moderator account created successfully. You can now log in.</p>";
                } else {
                    $message = "<p class='msg-error'>Failed to create moderator account.</p>";
                }
                $stmt->close();
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Moderator</title>
    <link rel="stylesheet" type="text/css" href="Style.css">

    <script>
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
        <h2>Create Moderator</h2>

        <?php echo $message; ?>

        <form action="CreateModerator.php" method="POST">
            <div class="form-group">
                <label>Full Name :</label>
                <input type="text" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Email Address :</label>
                <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Password :</label>
                <p class="password-hint-inline">* Must be 8+ chars with uppercase, lowercase, numbers & symbols.</p>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" required
                        pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                        oninput="this.setCustomValidity(''); checkPasswordMatch();"
                        oninvalid="this.setCustomValidity('Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special symbol.')">
                    <svg id="eye_icon_1" class="eye-icon" width="24" height="24" onclick="togglePassword('password', 'eye_icon_1')" viewBox="0 0 24 24">
                        <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>
                    </svg>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password :</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" required oninput="checkPasswordMatch()">
                    <svg id="eye_icon_2" class="eye-icon" width="24" height="24" onclick="togglePassword('confirm_password', 'eye_icon_2')" viewBox="0 0 24 24">
                        <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>
                    </svg>
                </div>
            </div>

            <div class="form-group">
                <label>Security Question :</label>
                <select name="security_question" required>
                    <option value="" disabled selected>Select your security question</option>
                    <?php foreach ($questions as $q): ?>
                        <option value="<?php echo htmlspecialchars($q); ?>" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === $q) ? 'selected' : ''; ?>><?php echo htmlspecialchars($q); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Your Answer :</label>
                <input type="text" name="security_answer" required placeholder="Your answer"
                    value="<?php echo isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : ''; ?>">
            </div>

            <button type="submit" name="submit" class="btn-outline">Create Moderator</button>

            <div class="links center-links">
                Already have an account? <a href="ModeratorLogin.php" class="link-primary"><u>Log In</u></a>
            </div>
        </form>
    </div>
</body>
</html>
