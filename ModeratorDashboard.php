<?php
    session_start();
    require_once 'db_connect.php';

    // 1. Security: Ensure only the System Host/Moderator can access
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorDashboard.php");
        exit();
    }

    // 2. Fetch all clubs that are currently 'pending'
    $query = "SELECT * FROM admins WHERE status = 'pending' ORDER BY created_at ASC";
    $result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moderator Dashboard</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>
    <nav class="navbar">
        <a href="ModeratorDashboard.php" class="logo">
            <img src="Image/inti-logo.png" alt="INTI Logo">
        </a>
        <div class="nav-links">
            <a href="ModeratorDashboard.php" class="active">Pending Approvals</a>
            <a href="Logout.php" class="logout-link">Log Out</a>
        </div>
    </nav>

    <main class="container">
        <h2>Club Registration Approvals</h2>
        <p style="text-align: center; color: #666;">Review and approve club registration requests below.</p>

        <section class="event-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <article class="event-card">
                        <h3><?php echo htmlspecialchars($row['clubName']); ?></h3>
                        <div class="event-meta">
                            <strong>Admin:</strong> <?php echo htmlspecialchars($row['name']); ?><br>
                            <strong>Email:</strong> <?php echo htmlspecialchars($row['clubEmail']); ?>
                        </div>
                        
                        <div class="action-buttons" style="display: flex; gap: 10px;">
                            <form action="ProcessApproval.php" method="POST" style="flex: 1;">
                                <input type="hidden" name="adminID" value="<?php echo $row['adminID']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-primary" style="background-color: #4CAF50; border: none;">Approve</button>
                            </form>
                            
                            <form action="ProcessApproval.php" method="POST" style="flex: 1;">
                                <input type="hidden" name="adminID" value="<?php echo $row['adminID']; ?>">
                                <input type="hidden" name="action" value="decline">
                                <button type="submit" class="btn-outline" style="color: #ED1C24; border-color: #ED1C24; width: 100%;">Decline</button>
                            </form>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Pending Approvals</p>
                    <p class="empty-subtext">All club registration requests have been processed.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>