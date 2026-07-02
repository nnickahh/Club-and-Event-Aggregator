<?php
    // Throw exceptions on mysqli errors for clearer handling
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $servername = "127.0.0.1";
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
              security_question VARCHAR(255) DEFAULT NULL,
              security_answer VARCHAR(255) DEFAULT NULL,
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
              security_question VARCHAR(255) DEFAULT NULL,
              security_answer VARCHAR(255) DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $conn->query($create_sql);
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB moderator table setup error: ' . $e->getMessage());
    }

    // Add `security_question` and `security_answer` columns to `moderators` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM moderators LIKE 'security_question'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE moderators ADD COLUMN security_question VARCHAR(255) DEFAULT NULL AFTER password");
            $conn->query("ALTER TABLE moderators ADD COLUMN security_answer VARCHAR(255) DEFAULT NULL AFTER security_question");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB moderators security_question migration error: ' . $e->getMessage());
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

    // Add payment columns to `events` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'payment_methods'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN payment_methods VARCHAR(100) DEFAULT NULL AFTER status");
            $conn->query("ALTER TABLE events ADD COLUMN tng_phone VARCHAR(20) DEFAULT NULL AFTER payment_methods");
            $conn->query("ALTER TABLE events ADD COLUMN tng_qr VARCHAR(255) DEFAULT NULL AFTER tng_phone");
            $conn->query("ALTER TABLE events ADD COLUMN bank_details TEXT DEFAULT NULL AFTER tng_qr");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB payment columns migration error: ' . $e->getMessage());
    }

    // Add `fee` column to `events` table (for event fee)
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'fee'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN fee DECIMAL(10,2) DEFAULT 0.00 AFTER bank_details");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB fee column migration error: ' . $e->getMessage());
    }

    // Add `payment_status` column to `registrations` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM registrations LIKE 'payment_status'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE registrations ADD COLUMN payment_status ENUM('unpaid','paid') DEFAULT 'unpaid'");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB payment_status migration error: ' . $e->getMessage());
    }

    // Add `attendance_status` column to `registrations` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM registrations LIKE 'attendance_status'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE registrations ADD COLUMN attendance_status ENUM('absent','present') DEFAULT 'absent'");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB attendance_status migration error: ' . $e->getMessage());
    }

    // Add `payment_method` column to `registrations` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM registrations LIKE 'payment_method'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE registrations ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER payment_status");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB payment_method migration error: ' . $e->getMessage());
    }

    // Add `payment_receipt` column to `registrations` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM registrations LIKE 'payment_receipt'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE registrations ADD COLUMN payment_receipt VARCHAR(255) DEFAULT NULL AFTER payment_method");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB payment_receipt migration error: ' . $e->getMessage());
    }

    // Extend events.status ENUM to include 'ended' and 'cancelled'
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'status'");
        if ($check && $check->num_rows > 0) {
            $row = $check->fetch_assoc();
            if (strpos($row['Type'], 'cancelled') === false) {
                $conn->query("ALTER TABLE events MODIFY COLUMN status ENUM('pending','approved','declined','ended','cancelled') DEFAULT 'pending'");
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB status ENUM migration error: ' . $e->getMessage());
    }

    // Add `eventEndDate` column to `events` table (for multi-day events)
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'eventEndDate'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN eventEndDate DATE DEFAULT NULL AFTER eventDate");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB eventEndDate migration error: ' . $e->getMessage());
    }

    // Add recurring activity columns to `events` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'recurrence_group_id'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN recurrence_group_id VARCHAR(80) DEFAULT NULL AFTER eventEndDate");
        }
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'recurrence_type'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN recurrence_type VARCHAR(20) DEFAULT 'none' AFTER recurrence_group_id");
        }
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'recurrence_start_date'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN recurrence_start_date DATE DEFAULT NULL AFTER recurrence_type");
        }
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'recurrence_end_date'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN recurrence_end_date DATE DEFAULT NULL AFTER recurrence_start_date");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB recurring activity migration error: ' . $e->getMessage());
    }

    // Add `decline_reason` column to `events` table (for moderator decline feedback)
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'decline_reason'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE events ADD COLUMN decline_reason TEXT DEFAULT NULL AFTER description");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB decline_reason migration error: ' . $e->getMessage());
    }

    // Add `security_question` and `security_answer` columns to `admins` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM admins LIKE 'security_question'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE admins ADD COLUMN security_question VARCHAR(255) DEFAULT NULL AFTER password");
        }
        $check = $conn->query("SHOW COLUMNS FROM admins LIKE 'security_answer'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE admins ADD COLUMN security_answer VARCHAR(255) DEFAULT NULL AFTER security_question");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB admins security columns error: ' . $e->getMessage());
    }

    // Add `decline_reason` column to `admins` table (for declined club registration feedback)
    try {
        $check = $conn->query("SHOW COLUMNS FROM admins LIKE 'decline_reason'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE admins ADD COLUMN decline_reason TEXT DEFAULT NULL AFTER status");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB admin decline_reason migration error: ' . $e->getMessage());
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

    // Add `eventID` column to `notifications` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'eventID'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN eventID INT UNSIGNED DEFAULT NULL AFTER message");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB eventID migration error: ' . $e->getMessage());
    }

    // Add `clubID` column to `notifications` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'clubID'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN clubID INT UNSIGNED DEFAULT NULL AFTER eventID");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB clubID migration error: ' . $e->getMessage());
    }

    // Create `club_notify` table (student subscriptions to club notifications)
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS club_notify (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            studentID VARCHAR(20) NOT NULL,
            adminID VARCHAR(20) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_sub (studentID, adminID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (mysqli_sql_exception $e) {
        error_log('DB club_notify table creation error: ' . $e->getMessage());
    }

    // Create `student_notifications` table
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS student_notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            studentID VARCHAR(20) NOT NULL,
            message VARCHAR(500) NOT NULL,
            eventID INT UNSIGNED DEFAULT NULL,
            clubID INT UNSIGNED DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (mysqli_sql_exception $e) {
        error_log('DB student_notifications table creation error: ' . $e->getMessage());
    }

    // Track event reminder notifications so students do not receive duplicates
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS event_reminders_sent (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            studentID VARCHAR(20) NOT NULL,
            eventID INT UNSIGNED NOT NULL,
            reminder_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_event_reminder (studentID, eventID, reminder_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (mysqli_sql_exception $e) {
        error_log('DB event_reminders_sent table creation error: ' . $e->getMessage());
    }

    // Add `clubID` column to `student_notifications` table (migration for existing tables)
    try {
        $check = $conn->query("SHOW COLUMNS FROM student_notifications LIKE 'clubID'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE student_notifications ADD COLUMN clubID INT UNSIGNED DEFAULT NULL AFTER eventID");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB clubID migration for student_notifications error: ' . $e->getMessage());
    }

    // Create `waiting_list` table (event waitlist)
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS waiting_list (
            waitID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            studentID VARCHAR(50) NOT NULL,
            eventID INT NOT NULL,
            payment_method VARCHAR(50) DEFAULT NULL,
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_wait (studentID, eventID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (mysqli_sql_exception $e) {
        error_log('DB waiting_list table creation error: ' . $e->getMessage());
    }

    // Add payment tracking columns to `waiting_list` for paid waitlisted students
    try {
        $check = $conn->query("SHOW COLUMNS FROM waiting_list LIKE 'payment_status'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE waiting_list ADD COLUMN payment_status ENUM('unpaid','paid') DEFAULT 'unpaid' AFTER payment_method");
        }
        $check = $conn->query("SHOW COLUMNS FROM waiting_list LIKE 'payment_receipt'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE waiting_list ADD COLUMN payment_receipt VARCHAR(255) DEFAULT NULL AFTER payment_status");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB payment columns migration for waiting_list error: ' . $e->getMessage());
    }

    // Create `moderator_notifications` table
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS moderator_notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message VARCHAR(500) NOT NULL,
            eventID INT UNSIGNED DEFAULT NULL,
            clubID INT UNSIGNED DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (mysqli_sql_exception $e) {
        error_log('DB moderator_notifications table creation error: ' . $e->getMessage());
    }

    // Create moderator activity log for approvals, declines, edits, and deletions
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS moderator_activity_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            moderatorID VARCHAR(20) DEFAULT NULL,
            moderatorName VARCHAR(255) DEFAULT NULL,
            action_type VARCHAR(50) NOT NULL,
            target_type VARCHAR(30) NOT NULL,
            target_id VARCHAR(50) DEFAULT NULL,
            target_title VARCHAR(255) DEFAULT NULL,
            details TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (mysqli_sql_exception $e) {
        error_log('DB moderator_activity_log table creation error: ' . $e->getMessage());
    }

    // Create `announcements` table for admin broadcasts to student dashboard
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS announcements (
            announcementID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            adminID VARCHAR(20) DEFAULT NULL,
            moderatorID VARCHAR(20) DEFAULT NULL,
            created_by_role VARCHAR(20) DEFAULT 'admin',
            eventID INT UNSIGNED DEFAULT NULL,
            title VARCHAR(150) NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->query("ALTER TABLE announcements MODIFY COLUMN adminID VARCHAR(20) DEFAULT NULL");
        $check = $conn->query("SHOW COLUMNS FROM announcements LIKE 'moderatorID'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE announcements ADD COLUMN moderatorID VARCHAR(20) DEFAULT NULL AFTER adminID");
        }
        $check = $conn->query("SHOW COLUMNS FROM announcements LIKE 'created_by_role'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE announcements ADD COLUMN created_by_role VARCHAR(20) DEFAULT 'admin' AFTER moderatorID");
        }
        $check = $conn->query("SHOW COLUMNS FROM announcements LIKE 'eventID'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE announcements ADD COLUMN eventID INT UNSIGNED DEFAULT NULL AFTER created_by_role");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB announcements table creation error: ' . $e->getMessage());
    }

    // Add `security_question` and `security_answer` columns to `students` table
    try {
        $check = $conn->query("SHOW COLUMNS FROM students LIKE 'security_question'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE students ADD COLUMN security_question VARCHAR(255) DEFAULT NULL AFTER password");
        }
        $check = $conn->query("SHOW COLUMNS FROM students LIKE 'security_answer'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("ALTER TABLE students ADD COLUMN security_answer VARCHAR(255) DEFAULT NULL AFTER security_question");
        }
    } catch (mysqli_sql_exception $e) {
        error_log('DB students security columns error: ' . $e->getMessage());
    }

    // Create `event_feedback` table for student ratings and feedback
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS event_feedback (
            feedbackID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            eventID INT UNSIGNED NOT NULL,
            studentID VARCHAR(20) NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            `comment` TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_feedback (eventID, studentID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (mysqli_sql_exception $e) {
        error_log('DB event_feedback table creation error: ' . $e->getMessage());
    }

// ─── Helper functions ──────────────────────────────────────

function formatDateRange($eventDate, $eventEndDate = null) {
    $end = $eventEndDate ?? $eventDate;
    if ($end === $eventDate) {
        return date('d M Y', strtotime($eventDate));
    }
    $s = strtotime($eventDate);
    $e = strtotime($end);
    if (date('m Y', $s) === date('m Y', $e)) {
        return date('j', $s) . ' - ' . date('j M Y', $e);
    }
    return date('j M', $s) . ' - ' . date('j M Y', $e);
}

function getEventPeriod($eventDate, $eventEndDate, $currentDate, $eventTime = null, $eventEndTime = null) {
    $startDT = $eventDate . ' ' . ($eventTime ?? '00:00:00');
    $endDT   = ($eventEndDate ?? $eventDate) . ' ' . ($eventEndTime ?? '23:59:59');
    $now     = $currentDate . ' ' . date('H:i:s');
    if ($now >= $startDT && $now <= $endDT) return 'ongoing';
    if ($now < $startDT) return 'upcoming';
    return 'past';
}

function logModeratorActivity($conn, $moderatorID, $moderatorName, $action_type, $target_type, $target_id, $target_title, $details = null) {
    try {
        $stmt = $conn->prepare("INSERT INTO moderator_activity_log (moderatorID, moderatorName, action_type, target_type, target_id, target_title, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $target_id = $target_id !== null ? (string)$target_id : null;
        $stmt->bind_param("sssssss", $moderatorID, $moderatorName, $action_type, $target_type, $target_id, $target_title, $details);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('Moderator activity log error: ' . $e->getMessage());
    }
}
?>
