<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['moderator_id']) || ($_SESSION['role'] ?? '') !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }

    $message = "";
    $currentPage = 'create-moderator';

    if (isset($_POST['submit'])) {
        $name     = trim($_POST['name']);
        $email    = trim($_POST['email']);
        $password = $_POST['password'];

        if ($name === '' || $email === '' || $password === '') {
            $message = "<p class='msg-error'>All fields are required.</p>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<p class='msg-error'>Please enter a valid email address.</p>";
        } elseif (strlen($password) < 6) {
            $message = "<p class='msg-error'>Password must be at least 6 characters.</p>";
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
                $stmt = $conn->prepare("INSERT INTO moderators (name, email, password, status) VALUES (?, ?, ?, 'active')");
                $stmt->bind_param("sss", $name, $email, $hashedPassword);

                if ($stmt->execute()) {
                    $message = "<p class='msg-success'>Moderator account created successfully.</p>";
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
</head>
<body>
    <?php include 'ModeratorNavBar.php'; ?>

    <main class="container">
        <div class="profile-box" style="max-width:500px;">
            <h2>Create Moderator</h2>
            <p class="text-sm-muted" style="text-align:center;margin-bottom:20px;">Create a new moderator account with full access.</p>

            <?php echo $message; ?>

            <form action="CreateModerator.php" method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. Jane Moderator" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="moderator@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" required placeholder="At least 6 characters" minlength="6">
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-primary" style="width:100%;">Create Moderator</button>
            </form>
        </div>
    </main>
</body>
</html>
