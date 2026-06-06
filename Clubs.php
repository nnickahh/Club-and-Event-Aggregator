<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    session_start();
    require_once 'db_connect.php';

    // Security Check: Redirect to login if not a student (Matches your exact layout rules)
    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }

    // Fetch registered clubs from your database table
    // (Assuming your table is named 'clubs' and has 'clubName' and 'description' columns)
    try {
        $query = "SELECT * FROM clubs ORDER BY clubName ASC";
        $result = $conn->query($query);
    } catch (mysqli_sql_exception $e) {
        // Log the error for debugging and avoid fatal crash in production
        error_log('Clubs.php DB query error: ' . $e->getMessage());
        $result = false;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Campus Clubs</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>
    <?php include 'StudentNavbar.php'; ?>

    <main class="container">
        <h2>Explore Campus Clubs</h2>
        
        <input type="text" class="search-bar" placeholder="Search clubs by name or category...">

        <section class="event-grid">
            <?php
                // Check if there are any clubs in your table
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
            ?>
            <article class="event-card">
                <h3><?php echo htmlspecialchars($row['clubName']); ?></h3>
                <div class="dashed-line"></div>
                <div class="event-meta club-meta">
                    <p class="club-desc">
                        <?php echo htmlspecialchars($row['description'] ?? 'No description available.'); ?>
                    </p>
                </div>
                <a href="DetailedClub.php?id=<?php echo $row['clubID']; ?>" class="btn-primary btn-primary-block">View Club Info</a>
            </article>
            <?php
                    }
                } else {
                    // Show this if the 'clubs' table is currently empty 
                    // This matches your exact empty box class setup
                    echo "<div class='event-empty-box'>
                        <p>No Clubs Registered Yet</p>
                        <p class='empty-subtext'>There are currently no active clubs listed on our portal. Check back later!</p>
                    </div>";
                }
            ?>
        </section>
    </main>
</body>
</html>