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
?>