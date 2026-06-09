<?php
    session_start();

    // Database configuration connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "campus_system"; // Make sure this matches your database name exactly

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check database connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Ensure the student user is actually logged in
    // Note: Keep session checking key matching whatever you set during your StudentLogin.php script
    if (!isset($_SESSION['student_id'])) {
        header("Location: StudentLogin.php");
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $student_id = $_SESSION['student_id'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // 1. Validate that fields are not empty
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            echo "<script>alert('All fields are required.'); window.location.href='ProfileSettings.php';</script>";
            exit();
        }

        // 2. Validate that the two new passwords match
        if ($new_password !== $confirm_password) {
            echo "<script>alert('New passwords do not match.'); window.location.href='ProfileSettings.php';</script>";
            exit();
        }

        // 3. Fetch current password from database (Using your exact column name: studentID)
        $query = "SELECT password FROM students WHERE studentID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            
            // 4. Verify current password matches what is stored in the database
            if (password_verify($current_password, $row['password']) || $current_password === $row['password']) {
                
                // 5. Update the password row (Using your exact column name: studentID)
                // Encrypt the new password before saving it to the database
                $hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT);

                $update_query = "UPDATE students SET password = ? WHERE studentID = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ss", $hashed_new_password, $student_id);

                if ($update_stmt->execute()) {
                    // Redirect straight to your success view page
                    header("Location: UpdatePasswordSuccess.php");
                    exit();
                } else {
                    echo "<script>alert('Something went wrong. Please try again.'); window.location.href='ProfileSettings.php';</script>";
                    exit();
                }
            } else {
                echo "<script>alert('Incorrect current password.'); window.location.href='ProfileSettings.php';</script>";
                exit();
            }
        } else {
            echo "<script>alert('Something went wrong. Please try again.'); window.location.href='ProfileSettings.php';</script>";
            exit();
        }
    } else {
        header("Location: ProfileSettings.php");
        exit();
    }
?>