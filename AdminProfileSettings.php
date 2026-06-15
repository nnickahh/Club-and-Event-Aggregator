<?php
    session_start();
    require_once 'db_connect.php';

    // Security Check: Enforce strict admin access only
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }
    session_write_close();

    $admin_id = $_SESSION['admin_id'];

    // Query to pull data from your exact admins database columns
    $query = "SELECT name, clubEmail FROM admins WHERE adminID = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        die("SQL Error: " . $conn->error);
    }

    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $message = "";

    // Handle incoming password updates securely
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $message = "<p class='msg-error'>Passwords do not match.</p>";
        } else {
            // Hash the password safely using default system standards
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $update_query = "UPDATE admins SET password = ? WHERE adminID = ?";
            $update_stmt = $conn->prepare($update_query);

            if ($update_stmt === false) {
                die("SQL Error: " . $conn->error);
            }

            $update_stmt->bind_param("ss", $hashed_password, $admin_id);

            if ($update_stmt->execute()) {
                $message = "<p class='msg-success'>Password updated successfully!</p>";
            } else {
                $message = "<p class='msg-error'>Error updating password.</p>";
            }
            $update_stmt->close();
        }
    }
?>

<script>
    // Restored exact layout visibility controller functions
    function togglePassword(inputId, iconId) {
        const passwordInput = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        const openEyePath = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
        const closedEyePath = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.73 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2z"/>';

        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            icon.innerHTML = closedEyePath;
        } else {
            passwordInput.type = "password";
            icon.innerHTML = openEyePath;
        }
    }

    function checkPasswordMatch() {
        const password = document.getElementById("new_password").value;
        const confirmPassword = document.getElementById("confirm_password").value;
        const message = document.getElementById("password_match_message");

        if (confirmPassword === "") {
            message.innerHTML = "";
            return;
        }

        if (password === confirmPassword) {
            message.innerHTML = "Passwords match";
            message.style.color = "green";
        } else {
            message.innerHTML = "Passwords do not match";
            message.style.color = "red";
        }
    }
</script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>

    <?php include 'AdminNavbar.php'; ?>

    <div class="profile-container">
        <div class="profile-box">
            <h2>Admin Profile</h2>

            <?php echo $message; ?>

            <div class="form-group">
                <label>Admin ID Account :</label>
                <input type="text" value="<?php echo htmlspecialchars($admin_id); ?>" readonly>
            </div>

            <div class="form-group">
                <label>Full Registered Name :</label>
                <input type="text" value="<?php echo htmlspecialchars($user['name'] ?? 'System Administrator'); ?>" readonly>
            </div>

            <div class="form-group">
                <label>Official Login Email :</label>
                <input type="text" value="<?php echo htmlspecialchars($user['email'] ?? 'admin@inti.edu.my'); ?>" readonly>
            </div>

            <hr>

            <form action="AdminProfileSettings.php" method="POST">
                <h3>Update Security Password</h3>

                <div class="form-group">
                    <label>Enter New Password :</label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="new_password" required>
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
                    <div id="password_match_message" style="margin-top: 5px; font-weight: bold;"></div>
                </div>

                <button type="submit" class="btn-primary">Save New Password</button>
            </form>
        </div>
    </div>

</body>
</html>