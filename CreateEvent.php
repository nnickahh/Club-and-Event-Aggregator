<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    session_start();
    require_once 'db_connect.php';

    // Security Check: Redirect to login if user is not authorized as an admin
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }
    session_write_close();

    $adminID = $_SESSION['admin_id'];
    $message = "";

    // Handle Form Processing when user clicks submit
    if (isset($_POST["submit"])) {
        $eventTitle  = trim($_POST['eventTitle']);
        $eventDate   = $_POST['eventDate'];
        $eventTime   = trim($_POST['eventTime']);
        $eventEndTime = !empty(trim($_POST['eventEndTime'])) ? trim($_POST['eventEndTime']) : null;
        $venue       = trim($_POST['venue']);
        $capacity    = intval($_POST['capacity']);
        $description = trim($_POST['description']);
        $eventImage  = null;

        // Handle image upload
        if (isset($_FILES['eventImage']) && $_FILES['eventImage']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/events/';
            $fileName = time() . '_' . basename($_FILES['eventImage']['name']);
            $targetPath = $uploadDir . $fileName;
            $imageFileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($imageFileType, $allowedTypes)) {
                $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>Only JPG, JPEG, PNG, GIF & WEBP files are allowed.</p>";
            } elseif ($_FILES['eventImage']['size'] > 5 * 1024 * 1024) {
                $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>File size must be less than 5MB.</p>";
            } elseif (move_uploaded_file($_FILES['eventImage']['tmp_name'], $targetPath)) {
                $eventImage = $targetPath;
            } else {
                $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>Failed to upload image.</p>";
            }
        }

        if (empty($message)) {
            $stmt = $conn->prepare("INSERT INTO events (adminID, eventTitle, eventDate, eventTime, eventEndTime, venue, capacity, description, eventImage, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

            if ($stmt) {
                $stmt->bind_param("ssssssiss", $adminID, $eventTitle, $eventDate, $eventTime, $eventEndTime, $venue, $capacity, $description, $eventImage);

                if ($stmt->execute()) {
                    header("Location: AdminDashboard.php");
                    exit();
                } else {
                    $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>Error creating event. Please try again.</p>";
                }
                $stmt->close();
            } else {
                $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>Database preparation failed.</p>";
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Event</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'AdminNavbar.php'; ?>

    <div class="container">
        <div class="profile-box">
            <h2>Create New Event</h2>

            <?php echo $message; ?>

            <form action="CreateEvent.php" method="POST" enctype="multipart/form-data">

                <div class="form-group">
                    <label>Event Title :</label>
                    <input type="text" name="eventTitle" placeholder="e.g., Annual General Meeting" required>
                </div>

                <div class="form-group">
                    <label>Event Date :</label>
                    <input type="date" name="eventDate" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Event Time Schedule :</label>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <input type="time" name="eventTime" required style="flex:1;">
                        <span style="font-weight:600;color:var(--ink-3,#888);">—</span>
                        <input type="time" name="eventEndTime" style="flex:1;">
                    </div>
                </div>

                <div class="form-group">
                    <label>Venue Location :</label>
                    <input type="text" name="venue" placeholder="e.g., Sports Hall Block B" required>
                </div>

                <div class="form-group">
                    <label>Max Capacity Limit :</label>
                    <input type="number" name="capacity" min="1" placeholder="e.g., 50" required>
                </div>

                <div class="form-group">
                    <label>Event Description / Remarks :</label>
                    <textarea name="description" placeholder="Provide event context guidelines or register rules..." required style="width: 100%; min-height: 100px; padding: 10px; border: 1px solid var(--border, rgba(0,0,0,0.15)); border-radius: 6px; box-sizing: border-box; font-family: inherit; resize: vertical;"></textarea>
                </div>

                <div class="form-group">
                    <label>Event Poster (optional) :</label>
                    <input type="file" name="eventImage" accept="image/jpeg,image/png,image/gif,image/webp" style="width: 100%; padding: 8px 0;">
                </div>

                <button type="submit" name="submit" class="btn-primary" style="margin-top: 15px; width: 100%;">Publish Event</button>

                <div style="text-align: center; margin-top: 20px; font-size: 14px; color: var(--ink-3, #888);">
                    Changed your mind? <a href="AdminDashboard.php" style="color: var(--red, #ED1C24); text-decoration: none; font-weight: 600;">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
