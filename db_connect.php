<?php
    // Throw exceptions on mysqli errors for clearer handling
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $servername = "localhost";
    $username = "root"; // Default XAMPP username
    $password = "";     // Default XAMPP password is blank
    $dbname = "campus_system";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Database Connection failed: " . $conn->connect_error);
    }

    // Use UTF-8
    $conn->set_charset('utf8mb4');

    // Ensure the `clubs` table exists. If not, create a minimal schema.
    try {
        $check = $conn->query("SHOW TABLES LIKE 'clubs'");
        if (!$check || $check->num_rows === 0) {
            $create_sql = "CREATE TABLE IF NOT EXISTS clubs (
              clubID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              clubName VARCHAR(255) NOT NULL,
              description TEXT,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($create_sql);
        }
    } catch (mysqli_sql_exception $e) {
        // Log and continue; other pages can handle absence of table more gracefully
        error_log('DB setup/check error: ' . $e->getMessage());
    }

    // Ensure the `admins` table exists. If not, create it.
    try {
        $check = $conn->query("SHOW TABLES LIKE 'admins'");
        if (!$check || $check->num_rows === 0) {
            $create_sql = "CREATE TABLE IF NOT EXISTS admins (
              adminID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(255) NOT NULL,
              clubName VARCHAR(255) NOT NULL,
              clubEmail VARCHAR(255) UNIQUE,
              password VARCHAR(255) NOT NULL,
              status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($create_sql);
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB admin table setup error: ' . $e->getMessage());
    }

    // Ensure the `moderators` table exists. If not, create it.
    try {
        $check = $conn->query("SHOW TABLES LIKE 'moderators'");
        if (!$check || $check->num_rows === 0) {
            $create_sql = "CREATE TABLE IF NOT EXISTS moderators (
              moderatorID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(255) NOT NULL,
              email VARCHAR(255) UNIQUE NOT NULL,
              password VARCHAR(255) NOT NULL,
              status ENUM('active', 'inactive') DEFAULT 'active',
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($create_sql);
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB moderator table setup error: ' . $e->getMessage());
    }

    // Add `status` column to `events` table if it doesn't exist (for moderator approval workflow)
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'status'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN status ENUM('pending','approved','declined') DEFAULT 'pending' AFTER description");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB events status migration error: ' . $e->getMessage());
    }

    // Add `eventImage` column to `events` table if it doesn't exist (for event picture upload)
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'eventImage'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN eventImage VARCHAR(255) DEFAULT NULL AFTER description");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB eventImage migration error: ' . $e->getMessage());
    }

    // Add `eventEndTime` column to `events` table (for event end time)
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'eventEndTime'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN eventEndTime TIME DEFAULT NULL AFTER eventTime");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB eventEndTime migration error: ' . $e->getMessage());
    }

    // Add `socialMedia` column to `clubs` table if it doesn't exist (for club social links)
    try {
        $check = $conn->query("SHOW COLUMNS FROM clubs LIKE 'socialMedia'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE clubs ADD COLUMN socialMedia TEXT DEFAULT NULL AFTER description");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB socialMedia migration error: ' . $e->getMessage());
    }

    // Ensure the `club_members` table exists
    try {
        $check = $conn->query("SHOW TABLES LIKE 'club_members'");
        if (!$check || $check->num_rows === 0) {
            $create_sql = "CREATE TABLE IF NOT EXISTS club_members (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              studentID VARCHAR(20) NOT NULL,
              adminID VARCHAR(20) NOT NULL,
              role VARCHAR(50) DEFAULT 'member',
              joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY unique_member (studentID, adminID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($create_sql);
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB club_members table creation error: ' . $e->getMessage());
    }

    // Ensure the `notifications` table exists
    try {
        $check = $conn->query("SHOW TABLES LIKE 'notifications'");
        if (!$check || $check->num_rows === 0) {
            $create_sql = "CREATE TABLE IF NOT EXISTS notifications (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              adminID VARCHAR(20) NOT NULL,
              message VARCHAR(500) NOT NULL,
              is_read TINYINT(1) DEFAULT 0,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($create_sql);
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB notifications table creation error: ' . $e->getMessage());
    }
?>