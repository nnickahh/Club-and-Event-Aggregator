<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }

    $currentPage = 'profile';
    $moderatorID = $_SESSION['moderator_id'] ?? null;
    $message = '';
    $msgType = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $message = 'All password fields are required.';
            $msgType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match.';
            $msgType = 'error';
        } elseif (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}$/', $newPassword)) {
            $message = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
            $msgType = 'error';
        } else {
            $passStmt = $conn->prepare("SELECT password FROM moderators WHERE moderatorID = ?");
            $passStmt->bind_param("s", $moderatorID);
            $passStmt->execute();
            $passRow = $passStmt->get_result()->fetch_assoc();
            $passStmt->close();

            if ($passRow && (password_verify($currentPassword, $passRow['password']) || $currentPassword === $passRow['password'])) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE moderators SET password = ? WHERE moderatorID = ?");
                $updateStmt->bind_param("ss", $hashedPassword, $moderatorID);
                $updateStmt->execute();
                $updateStmt->close();
                $message = 'Password updated successfully.';
                $msgType = 'success';
            } else {
                $message = 'Incorrect current password.';
                $msgType = 'error';
            }
        }
    }

    $stmt = $conn->prepare("SELECT moderatorID, name, email, status, created_at FROM moderators WHERE moderatorID = ?");
    $stmt->bind_param("s", $moderatorID);
    $stmt->execute();
    $moderator = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$moderator) {
        die("Moderator profile not found.");
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Profile</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>
    <?php include 'ModeratorNavBar.php'; ?>

    <main class="container">
        <h2 class="page-title-spaced">Moderator Profile</h2>

        <?php if ($message): ?>
            <div class="msg-banner <?php echo $msgType === 'success' ? 'feedback-success-banner' : 'feedback-error-banner'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="settings-card">
            <section class="settings-section">
                <h4>Personal Details</h4>
                <div class="dashed-line"></div>
                <div class="detail-row">
                    <strong>Moderator ID:</strong>
                    <span><?php echo htmlspecialchars($moderator['moderatorID']); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Full Name:</strong>
                    <span><?php echo htmlspecialchars($moderator['name']); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars($moderator['email']); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Status:</strong>
                    <span><?php echo htmlspecialchars(ucfirst($moderator['status'])); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Joined:</strong>
                    <span><?php echo date('d M Y', strtotime($moderator['created_at'])); ?></span>
                </div>
            </section>

            <form method="POST">
                <section class="settings-section settings-section-spaced">
                    <h4>Security</h4>
                    <div class="dashed-line"></div>
                    <p class="section-heading">Change Password:</p>
                    <p class="password-hint-settings">* Must be at least 8 chars with uppercase, lowercase, numbers & symbols.</p>

                    <div class="form-group">
                        <label>Current Password:</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password:</label>
                        <input type="password" name="new_password" required pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password:</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </section>

                <div class="settings-footer">
                    <button type="submit" name="update_password" class="save-btn">Save</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
