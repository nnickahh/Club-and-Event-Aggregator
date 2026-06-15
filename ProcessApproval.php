<?php
    session_start();
    require_once 'db_connect.php';

    // Security: Ensure only the logged-in Moderator can execute this script
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }
    session_write_close();

    $statusUpdated = false;
    $displayAction = ""; 
    $errorMessage = "";

    // Process the form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['adminID']) && isset($_POST['action'])) {
        $adminID = $_POST['adminID'];
        $action = $_POST['action'];

        // Map the button action to the exact database status value
        if ($action === 'approve') {
            $newStatus = 'active';
            $displayAction = "Approved";
        } elseif ($action === 'decline') {
            $newStatus = 'declined';
            $displayAction = "Declined";
        } else {
            header("Location: ModeratorDashboard.php");
            exit();
        }

        // Update the club creator status in the 'admins' table
        $query = "UPDATE admins SET status = ? WHERE adminID = ?";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            // FIXED: Changed "si" to "ss" because your adminID column holds strings like 'A240129'
            $stmt->bind_param("ss", $newStatus, $adminID);
            if ($stmt->execute()) {
                $statusUpdated = true;
            } else {
                $errorMessage = "Database update failed. Please try again.";
            }
            $stmt->close();
        } else {
            $errorMessage = "Database preparation error.";
        }
    } else {
        header("Location: ModeratorDashboard.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Processed</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body class="login-body">

    <div class="box">
        
        <?php if ($statusUpdated): ?>
            <div class="success-icon" style="<?php echo ($action === 'decline') ? 'color: var(--red);' : ''; ?>">
                <?php echo ($action === 'approve') ? '✓' : '✕'; ?>
            </div>
            
            <h2>Request <?php echo $displayAction; ?>!</h2>
            
            <div class="center-links">
                <p>
                    The club registration request has been successfully <br>
                    <strong><?php echo strtolower($displayAction); ?></strong> in the system database.
                </p>
            </div>
            
            <a href="ModeratorDashboard.php" class="btn-primary">Back to Dashboard</a>

        <?php else: ?>
            <div class="success-icon" style="color: var(--red);">✕</div>
            
            <h2>Processing Error</h2>

            <div class="center-links">
                <p>
                    <strong>Error:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                </p>
            </div>
            
            <a href="ModeratorDashboard.php" class="btn-primary">Back to Dashboard</a>
        <?php endif; ?>

    </div>

</body>
</html>