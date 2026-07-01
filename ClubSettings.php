<?php
    session_start();
    require_once 'db_connect.php';

    // Security Authorization Check
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }
    session_write_close();

    function formatClubPosition($role) {
        $role = preg_replace('/\s+/', ' ', str_replace('-', ' ', strtolower(trim($role ?? 'member'))));
        return ucwords($role ?: 'member');
    }

    $adminID = $_SESSION['admin_id'];
    $message = "";
    $activeSettingsTab = $_GET['tab'] ?? 'overview';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_member']) || isset($_POST['update_role']) || isset($_POST['remove_member']))) {
        $activeSettingsTab = 'members';
    }
    if (!in_array($activeSettingsTab, ['overview', 'members', 'contacts'], true)) {
        $activeSettingsTab = 'overview';
    }
    if (isset($_GET['member_added'])) {
        $message = "<div class='toast-notification success'>Member added successfully.</div>";
    }
    if (isset($_GET['role_updated'])) {
        $message = "<div class='toast-notification success'>Position updated successfully.</div>";
    }
    if (isset($_GET['member_removed'])) {
        $message = "<div class='toast-notification success'>Member removed successfully.</div>";
    }

    $adminStmt = $conn->prepare("SELECT clubName, clubEmail FROM admins WHERE adminID = ?");
    $adminStmt->bind_param("s", $adminID);
    $adminStmt->execute();
    $adminData = $adminStmt->get_result()->fetch_assoc();
    $adminStmt->close();
    $registeredClubName = $adminData['clubName'] ?? ($_SESSION['clubName'] ?? "My Club");
    $registeredClubEmail = $adminData['clubEmail'] ?? "club@inti.edu.my";

    // 1. Fetch public presentation metrics from clubs table
    $clubStmt = $conn->prepare("
        SELECT clubID, clubName, clubEmail, description, profilePic, socialMedia
        FROM clubs
        WHERE adminID = ? OR (adminID IS NULL AND LOWER(TRIM(clubName)) = LOWER(TRIM(?)))
        ORDER BY CASE WHEN adminID = ? THEN 0 ELSE 1 END, clubID DESC
        LIMIT 1
    ");
    $clubStmt->bind_param("sss", $adminID, $registeredClubName, $adminID);
    $clubStmt->execute();
    $clubData = $clubStmt->get_result()->fetch_assoc();
    $clubStmt->close();

    // Dynamically loads the registered name from the database, or uses the session fallback if empty
    $clubID      = !empty($clubData['clubID']) ? (int)$clubData['clubID'] : null;
    $clubName    = !empty($clubData['clubName']) ? $clubData['clubName'] : $registeredClubName;
    $clubEmail   = !empty($clubData['clubEmail']) ? $clubData['clubEmail'] : $registeredClubEmail;
    $description = !empty($clubData['description']) ? $clubData['description'] : "";
    $profilePic  = !empty($clubData['profilePic']) ? $clubData['profilePic'] : "";
    $socialMedia = !empty($clubData['socialMedia']) ? $clubData['socialMedia'] : "";

    // 2. Process form updates securely
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clubName'])) {
        $clubName    = trim($_POST['clubName']);
        $clubEmail   = trim($_POST['clubEmail']);
        $description = trim($_POST['description']);
        $socialMedia = trim($_POST['socialMedia'] ?? '');

        if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['profilePic']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $uploadDir = "uploads/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newName = $uploadDir . "club_" . $adminID . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES['profilePic']['tmp_name'], $newName)) {
                    // Delete old profile pic if not default
                    if (!empty($profilePic) && strpos($profilePic, 'default') === false && file_exists($profilePic)) {
                        @unlink($profilePic);
                    }
                    $profilePic = $newName;
                } else {
                    $message = "<div class='toast-notification error'>❌ Failed to move uploaded file. Check directory permissions.</div>";
                }
            } else {
                $message = "<div class='toast-notification error'>❌ Invalid file type. Allowed: " . implode(', ', $allowed) . "</div>";
            }
        } elseif (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] != 4) {
            $uploadErrors = [
                1 => 'File exceeds server upload_max_filesize',
                2 => 'File exceeds form MAX_FILE_SIZE',
                3 => 'File was only partially uploaded',
                6 => 'Missing temporary folder',
                7 => 'Failed to write file to disk',
                8 => 'File upload stopped by extension'
            ];
            $errMsg = $uploadErrors[$_FILES['profilePic']['error']] ?? 'Unknown error (' . $_FILES['profilePic']['error'] . ')';
            $message = "<div class='toast-notification error'>❌ Upload error: $errMsg</div>";
        }

        // Check if row exists
        $checkStmt = $conn->prepare("SELECT clubID FROM clubs WHERE adminID = ? OR (adminID IS NULL AND LOWER(TRIM(clubName)) = LOWER(TRIM(?)))");
        $checkStmt->bind_param("ss", $adminID, $registeredClubName);
        $checkStmt->execute();
        $hasRecord = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();

        if ($hasRecord) {
            $updateStmt = $conn->prepare("UPDATE clubs SET clubName = ?, clubEmail = ?, description = ?, profilePic = ?, socialMedia = ?, adminID = ? WHERE adminID = ? OR (adminID IS NULL AND LOWER(TRIM(clubName)) = LOWER(TRIM(?)))");
            $updateStmt->bind_param("ssssssss", $clubName, $clubEmail, $description, $profilePic, $socialMedia, $adminID, $adminID, $registeredClubName);
            $success = $updateStmt->execute();
            $updateStmt->close();
        } else {
            $insertStmt = $conn->prepare("INSERT INTO clubs (clubName, clubEmail, description, profilePic, socialMedia, adminID) VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("ssssss", $clubName, $clubEmail, $description, $profilePic, $socialMedia, $adminID);
            $success = $insertStmt->execute();
            $insertStmt->close();
        }

        if ($success) {
            $adminStmt = $conn->prepare("UPDATE admins SET clubName = ?, clubEmail = ? WHERE adminID = ?");
            $adminStmt->bind_param("sss", $clubName, $clubEmail, $adminID);
            $adminStmt->execute();
            $adminStmt->close();

            $eventClubStmt = $conn->prepare("UPDATE events SET clubName = ? WHERE adminID = ?");
            $eventClubStmt->bind_param("ss", $clubName, $adminID);
            $eventClubStmt->execute();
            $eventClubStmt->close();

            $message = "<div class='toast-notification success'>✨ Changes saved successfully!</div>";
            $_SESSION['clubName'] = $clubName;
        } else {
            $message = "<div class='toast-notification error'>❌ Error processing profile adjustments.</div>";
        }
    }

    // Handle manual member add
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
        $newMemberID = trim($_POST['member_student_id'] ?? '');
        $newMemberName = trim($_POST['member_name'] ?? '');
        $newMemberEmail = trim($_POST['member_email'] ?? '');
        $newMemberRole = trim($_POST['member_role'] ?? 'member');

        if ($newMemberRole === '') {
            $newMemberRole = 'member';
        }

        if ($newMemberID === '' || $newMemberName === '' || $newMemberEmail === '') {
            $message = "<div class='toast-notification error'>Please fill in Student ID, Full Name and Email Address.</div>";
        } elseif (!filter_var($newMemberEmail, FILTER_VALIDATE_EMAIL)) {
            $message = "<div class='toast-notification error'>Please enter a valid email address.</div>";
        } else {
            // Check if this student is already in this club
            $existsStmt = $conn->prepare("SELECT studentID FROM club_members WHERE studentID = ? AND adminID = ? LIMIT 1");
            $existsStmt->bind_param("ss", $newMemberID, $adminID);
            $existsStmt->execute();
            $alreadyMember = $existsStmt->get_result()->num_rows > 0;
            $existsStmt->close();

            if ($alreadyMember) {
                $message = "<div class='toast-notification error'>This student is already a club member.</div>";
            } else {
                // Check whether the student already exists in students table
                $studentStmt = $conn->prepare("SELECT studentID FROM students WHERE studentID = ? LIMIT 1");
                $studentStmt->bind_param("s", $newMemberID);
                $studentStmt->execute();
                $studentExists = $studentStmt->get_result()->num_rows > 0;
                $studentStmt->close();

                if (!$studentExists) {
                    // Add new student record first, then add them into club_members
                    $temporaryPassword = password_hash($newMemberID, PASSWORD_DEFAULT);
                    $insertStudent = $conn->prepare("INSERT INTO students (studentID, name, email, password) VALUES (?, ?, ?, ?)");
                    $insertStudent->bind_param("ssss", $newMemberID, $newMemberName, $newMemberEmail, $temporaryPassword);
                    $studentSaved = $insertStudent->execute();
                    $insertStudent->close();

                    if (!$studentSaved) {
                        $message = "<div class='toast-notification error'>Unable to create student record. Please check the student details.</div>";
                    }
                } else {
                    $updateStudent = $conn->prepare("UPDATE students SET name = ?, email = ? WHERE studentID = ?");
                    $updateStudent->bind_param("sss", $newMemberName, $newMemberEmail, $newMemberID);
                    $updateStudent->execute();
                    $updateStudent->close();
                }

                if ($message === '' || strpos($message, 'toast-notification success') !== false) {
                    $insertMember = $conn->prepare("INSERT INTO club_members (studentID, adminID, role) VALUES (?, ?, ?)");
                    $insertMember->bind_param("sss", $newMemberID, $adminID, $newMemberRole);
                    $memberSaved = $insertMember->execute();
                    $insertMember->close();

                    if ($memberSaved) {
                        $roleLabel = ucwords(strtolower(trim($newMemberRole)));
                        $notifMessage = "You have been added to {$clubName} as {$roleLabel}.";
                        if (!empty($clubID)) {
                            $notifStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, clubID) VALUES (?, ?, ?)");
                            $notifStmt->bind_param("ssi", $newMemberID, $notifMessage, $clubID);
                        } else {
                            $notifStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message) VALUES (?, ?)");
                            $notifStmt->bind_param("ss", $newMemberID, $notifMessage);
                        }
                        $notifStmt->execute();
                        $notifStmt->close();

                        header("Location: ClubSettings.php?tab=members&member_added=1");
                        exit();
                    } else {
                        $message = "<div class='toast-notification error'>Unable to add member to this club.</div>";
                    }
                }
            }
        }
    }

    // Handle role update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
        $targetStudent = $_POST['student_id'];
        $newRole = trim($_POST['role']);
        if (!empty($targetStudent) && !empty($newRole)) {
            $oldRole = '';
            $oldRoleStmt = $conn->prepare("SELECT role FROM club_members WHERE studentID = ? AND adminID = ? LIMIT 1");
            $oldRoleStmt->bind_param("ss", $targetStudent, $adminID);
            $oldRoleStmt->execute();
            $oldRoleRow = $oldRoleStmt->get_result()->fetch_assoc();
            $oldRoleStmt->close();
            if ($oldRoleRow) {
                $oldRole = strtolower(trim($oldRoleRow['role'] ?? ''));
            }

            $updateStmt = $conn->prepare("UPDATE club_members SET role = ? WHERE studentID = ? AND adminID = ?");
            $updateStmt->bind_param("sss", $newRole, $targetStudent, $adminID);
            $updateStmt->execute();
            $updateStmt->close();

            $normalizedOldRole = preg_replace('/\s+/', ' ', str_replace('-', ' ', $oldRole));
            $normalizedNewRole = preg_replace('/\s+/', ' ', str_replace('-', ' ', strtolower(trim($newRole))));
            $specialRoles = ['president', 'vice president', 'vice', 'secretary', 'vice secretary', 'treasurer', 'vice treasurer'];
            if (in_array($normalizedNewRole, $specialRoles, true) && $normalizedNewRole !== $normalizedOldRole) {
                $roleLabel = ucwords($normalizedNewRole);
                $notifMessage = "You have been assigned as {$roleLabel} for {$clubName}.";
                if (!empty($clubID)) {
                    $notifStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, clubID) VALUES (?, ?, ?)");
                    $notifStmt->bind_param("ssi", $targetStudent, $notifMessage, $clubID);
                } else {
                    $notifStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message) VALUES (?, ?)");
                    $notifStmt->bind_param("ss", $targetStudent, $notifMessage);
                }
                $notifStmt->execute();
                $notifStmt->close();
            }

            header("Location: ClubSettings.php?tab=members&role_updated=1");
            exit();
        }
    }

    // Handle member removal
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_member'])) {
        $targetStudents = [];
        if (isset($_POST['student_ids']) && is_array($_POST['student_ids'])) {
            $targetStudents = array_map('trim', $_POST['student_ids']);
        } elseif (!empty($_POST['student_id'])) {
            $targetStudents = [trim($_POST['student_id'])];
        }
        $targetStudents = array_values(array_unique(array_filter($targetStudents)));
        $removeReason = trim($_POST['remove_reason'] ?? '');

        if (empty($targetStudents) || $removeReason === '') {
            $message = "<div class='toast-notification error'>Please select at least one member and write a removal reason.</div>";
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM club_members WHERE studentID = ? AND adminID = ?");
            $removedStudents = [];
            foreach ($targetStudents as $targetStudent) {
                $deleteStmt->bind_param("ss", $targetStudent, $adminID);
                $deleteStmt->execute();
                if ($deleteStmt->affected_rows > 0) {
                    $removedStudents[] = $targetStudent;
                }
            }
            $deleteStmt->close();

            if (!empty($removedStudents)) {
                $notifMessage = "You have been removed from {$clubName}. Reason: {$removeReason}";
                foreach ($removedStudents as $removedStudent) {
                    if (!empty($clubID)) {
                        $notifStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, clubID) VALUES (?, ?, ?)");
                        $notifStmt->bind_param("ssi", $removedStudent, $notifMessage, $clubID);
                    } else {
                        $notifStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message) VALUES (?, ?)");
                        $notifStmt->bind_param("ss", $removedStudent, $notifMessage);
                    }
                    $notifStmt->execute();
                    $notifStmt->close();
                }

                header("Location: ClubSettings.php?tab=members&member_removed=1");
                exit();
            }

            $message = "<div class='toast-notification error'>Unable to remove this member.</div>";
        }
    }

    // 4. Collect membership roster list with positions
    $memberQuery = "SELECT cm.studentID, cm.role, cm.joined_at, s.name, s.email
                    FROM club_members cm
                    JOIN students s ON cm.studentID = s.studentID
                    WHERE cm.adminID = ?
                    ORDER BY
                        CASE LOWER(TRIM(cm.role))
                            WHEN 'president' THEN 1
                            WHEN 'vice president' THEN 2
                            WHEN 'vice' THEN 2
                            WHEN 'secretary' THEN 3
                            WHEN 'vice secretary' THEN 4
                            WHEN 'treasurer' THEN 5
                            WHEN 'vice treasurer' THEN 6
                            WHEN 'committee' THEN 7
                            WHEN 'member' THEN 99
                            ELSE 90
                        END ASC,
                        CASE WHEN LOWER(TRIM(cm.role)) = 'member' THEN cm.joined_at END ASC,
                        s.name ASC";
    $memberStmt = $conn->prepare($memberQuery);
    $memberStmt->bind_param("s", $adminID);
    $memberStmt->execute();
    $membersResult = $memberStmt->get_result();
    $memberStmt->close();

    $availableStudents = [];
    $availableStmt = $conn->prepare("
        SELECT studentID, name, email
        FROM students
        WHERE studentID NOT IN (
            SELECT studentID FROM club_members WHERE adminID = ?
        )
        ORDER BY name ASC
    ");
    $availableStmt->bind_param("s", $adminID);
    $availableStmt->execute();
    $availableStudents = $availableStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $availableStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Profile</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'AdminNavbar.php'; ?>

    <main class="profile-hero-container">
        
        <form action="ClubSettings.php" method="POST" id="mainProfileForm" enctype="multipart/form-data">
            
            <div class="profile-brand-card">
                <div class="brand-identity-flex">
                    <div class="avatar-uploader-wrapper">
                        <img src="<?php echo !empty($profilePic) ? htmlspecialchars($profilePic) . '?t=' . time() : 'Image/default-club.png'; ?>" class="brand-avatar-img cursor-pointer" id="clubProfilePreview" alt="Club Logo" onclick="handleAvatarClick(this.src)">
                        <label class="avatar-edit-overlay" for="profilePicInput">📸</label>
                        <input type="file" name="profilePic" id="profilePicInput" accept="image/*" class="hide-input" onchange="previewClubProfilePicture(this)">
                    </div>
                    <div class="brand-meta-details-wrapper">
                        <span class="badge-role">OFFICIAL CLUB</span>
                        
                        <div class="title-field-container">
                            <input type="text" name="clubName" id="clubNameInput" class="editable-field club-title-input" value="<?php echo htmlspecialchars($clubName); ?>" required readonly>
                        </div>
                        
                        <p class="meta-subline">📍 INTI International University • Admin Workspace</p>
                    </div>
                </div>
                
                <div class="action-header-buttons">
                    <button type="button" class="btn-modern-secondary hide-on-edit" onclick="enableProfileEditingMode()">✏️ Edit Profile</button>
                    <button type="button" class="btn-modern-cancel show-on-edit" onclick="disableProfileEditingMode()">Cancel</button>
                    <button type="submit" class="btn-modern-primary show-on-edit">💾 Save Changes</button>
                </div>
            </div>

            <?php echo $message; ?>

            <div class="tab-navigation-bar">
                <button type="button" class="tab-trigger <?php echo $activeSettingsTab === 'overview' ? 'active' : ''; ?>" onclick="switchActiveTab(event, 'overview-tab')">Overview</button>
                <button type="button" class="tab-trigger <?php echo $activeSettingsTab === 'members' ? 'active' : ''; ?>" onclick="switchActiveTab(event, 'members-tab')">Members <span class="tab-counter"><?php echo $membersResult->num_rows; ?></span></button>
                <button type="button" class="tab-trigger <?php echo $activeSettingsTab === 'contacts' ? 'active' : ''; ?>" onclick="switchActiveTab(event, 'contacts-tab')">Contacts</button>
            </div>

            <div id="overview-tab" class="tab-content-panel <?php echo $activeSettingsTab === 'overview' ? 'active' : ''; ?>">
                <div class="glass-editor-card">
                    <div class="card-title-header">
                        <h3>About Our Club</h3>
                        <p>Write a welcoming summary statement for prospective students looking to join.</p>
                    </div>
                    <div class="form-group-modern">
                        <textarea name="description" id="descriptionInput" class="editable-field" placeholder="Type club biography, values, mission, or practice weekly schedules..." readonly><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                </div>
            </div>

            <div id="contacts-tab" class="tab-content-panel <?php echo $activeSettingsTab === 'contacts' ? 'active' : ''; ?>">
                <div class="glass-editor-card">
                    <div class="card-title-header">
                        <h3>Public Contact Details</h3>
                        <p>These contact details will be featured publicly across student discovery listings.</p>
                    </div>
                    <div class="form-input-grid">
                        <div class="form-group-modern">
                            <label>Official Contact Email</label>
                            <input type="email" name="clubEmail" id="clubEmailInput" class="editable-field text-input" required value="<?php echo htmlspecialchars($clubEmail); ?>" readonly>
                        </div>
                        <div class="form-group-modern">
                            <label>Social Media & Other Contacts</label>
                            <textarea name="socialMedia" id="socialMediaInput" class="editable-field" placeholder="Instagram: @yourclub&#10;Facebook: /yourclub&#10;Website: https://..." readonly><?php echo htmlspecialchars($socialMedia ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div id="members-tab" class="tab-content-panel <?php echo $activeSettingsTab === 'members' ? 'active' : ''; ?>">
            <div class="section-flex-header">
                <h3>Club Members (<?php echo $membersResult->num_rows; ?>)</h3>
                <div class="member-header-actions">
                    <?php if ($membersResult->num_rows > 0): ?>
                        <button type="button" class="btn-modern-secondary" id="memberSelectModeBtn" onclick="toggleMemberSelectionMode()">Select</button>
                        <button type="button" class="btn-modern-secondary bulk-remove-btn hidden" id="bulkRemoveMemberBtn" onclick="openBulkRemoveMembers()">Remove Member</button>
                    <?php endif; ?>
                    <button type="button" class="btn-modern-secondary" onclick="openAddMemberModal()">+ Member</button>
                </div>
            </div>

            <?php if ($membersResult->num_rows > 0): ?>
                <div class="table-card-container">
                    <table class="premium-modern-table">
                        <thead>
                            <tr>
                                <th class="member-select-col hidden"><input type="checkbox" id="selectAllMembers" onchange="toggleAllMembers(this)"></th>
                                <th>No.</th>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Official Email Address</th>
                                <th>Position</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; while($memb = $membersResult->fetch_assoc()): ?>
                                <tr>
                                    <td class="member-select-col hidden">
                                        <input
                                            type="checkbox"
                                            class="member-select-checkbox"
                                            value="<?php echo htmlspecialchars($memb['studentID']); ?>"
                                            data-name="<?php echo htmlspecialchars($memb['name']); ?>"
                                            onchange="syncMemberSelectionState()"
                                        >
                                    </td>
                                    <td><?php echo $i++; ?></td>
                                    <td><span class="id-tag"><?php echo htmlspecialchars($memb['studentID']); ?></span></td>
                                    <td class="user-cell-name"><strong><?php echo htmlspecialchars($memb['name']); ?></strong></td>
                                    <td><a href="mailto:<?php echo htmlspecialchars($memb['email']); ?>" class="email-link"><?php echo htmlspecialchars($memb['email']); ?></a></td>
                                    <td>
                                        <?php $displayPosition = formatClubPosition($memb['role'] ?? 'member'); ?>
                                        <span class="badge-role-tag" style="background:<?php echo strtolower(trim($memb['role'] ?? 'member')) === 'member' ? '#f0fdf4' : '#eff6ff'; ?>;color:<?php echo strtolower(trim($memb['role'] ?? 'member')) === 'member' ? '#16a34a' : '#2563eb'; ?>;">
                                            <?php echo htmlspecialchars($displayPosition); ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <div class="member-action-row">
                                            <form method="POST" class="role-input-form" onsubmit="return confirm('Update role for <?php echo htmlspecialchars($memb['studentID']); ?>?');">
                                                <input type="hidden" name="update_role" value="1">
                                                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($memb['studentID']); ?>">
                                                <select name="role" class="role-select-input">
                                                    <?php
                                                        $roleOptions = ['member' => 'Member', 'president' => 'President', 'vice president' => 'Vice President', 'secretary' => 'Secretary', 'vice secretary' => 'Vice Secretary', 'treasurer' => 'Treasurer', 'vice treasurer' => 'Vice Treasurer', 'committee' => 'Committee'];
                                                        $currentRole = strtolower(trim($memb['role'] ?? 'member'));
                                                        foreach ($roleOptions as $roleValue => $roleLabel):
                                                    ?>
                                                        <option value="<?php echo htmlspecialchars($roleValue); ?>" <?php echo $currentRole === $roleValue ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($roleLabel); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit">Set</button>
                                            </form>
                                            <button
                                                type="button"
                                                class="remove-member-x"
                                                aria-label="Remove member"
                                                onclick="openRemoveMemberModal(<?php echo htmlspecialchars(json_encode($memb['studentID']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($memb['name']), ENT_QUOTES); ?>)"
                                            >x</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-canvas">
                    <div class="empty-icon">👥</div>
                    <h4>Your roster is currently empty</h4>
                    <p>Once students register for your published events, they will populate here automatically.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Avatar viewer modal -->
    <div id="avatarViewer" class="avatar-viewer-modal" onclick="closeAvatarViewer()">
        <span class="avatar-viewer-close">&times;</span>
        <img class="avatar-viewer-image" id="avatarViewerImage" alt="Enlarged club logo">
    </div>

    <div id="addMemberModal" class="modal-overlay" onclick="closeAddMemberModal(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button type="button" class="modal-close" onclick="closeAddMemberModal()">&times;</button>
            <h3>Add Club Member</h3>
            <p class="text-sm-muted mb-16">Fill in the student details and assign their club position.</p>
            <form method="POST">
                <input type="hidden" name="add_member" value="1">

                <label for="memberStudentIDInput" class="form-label-md">Student ID</label>
                <input type="text" name="member_student_id" id="memberStudentIDInput" class="form-input-lg" placeholder="e.g. P24012345" required>

                <label for="memberNameInput" class="form-label-md">Full Name</label>
                <input type="text" name="member_name" id="memberNameInput" class="form-input-lg" placeholder="Enter full name" required>

                <label for="memberEmailInput" class="form-label-md">Email Address</label>
                <input type="email" name="member_email" id="memberEmailInput" class="form-input-lg" placeholder="student@example.com" required>

                <label for="memberRoleInput" class="form-label-md">Position</label>
                <select name="member_role" id="memberRoleInput" class="form-select" required>
                    <option value="member">Member</option>
                    <option value="president">President</option>
                    <option value="vice president">Vice President</option>
                    <option value="secretary">Secretary</option>
                    <option value="vice secretary">Vice Secretary</option>
                    <option value="treasurer">Treasurer</option>
                    <option value="vice treasurer">Vice Treasurer</option>
                    <option value="committee">Committee</option>
                </select>

                <div class="modal-actions">
                    <button type="button" class="btn-mod-details" onclick="closeAddMemberModal()">Cancel</button>
                    <button type="submit" class="btn-modern-primary">Add Member</button>
                </div>
            </form>
        </div>
    </div>

    <div id="removeMemberModal" class="modal-overlay" onclick="closeRemoveMemberModal(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button type="button" class="modal-close" onclick="closeRemoveMemberModal()">&times;</button>
            <h3>Remove Club Member</h3>
            <p class="text-sm-muted mb-16">Write the reason for removing <strong id="removeMemberNameText">this member</strong>. Selected student(s) will be notified with this same reason.</p>
            <form method="POST">
                <input type="hidden" name="remove_member" value="1">
                <div id="removeMemberInputs"></div>

                <label for="removeMemberReason" class="form-label-md">Reason</label>
                <textarea name="remove_reason" id="removeMemberReason" class="form-textarea-lg" rows="4" required placeholder="e.g. No longer active, requested removal, no longer part of the club..."></textarea>

                <div class="modal-actions">
                    <button type="button" class="btn-mod-details" onclick="closeRemoveMemberModal()">Cancel</button>
                    <button type="submit" class="btn-danger">Confirm Remove</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchActiveTab(event, tabId) {
            const panels = document.querySelectorAll('.tab-content-panel');
            panels.forEach(panel => panel.classList.remove('active'));

            const triggers = document.querySelectorAll('.tab-trigger');
            triggers.forEach(trigger => trigger.classList.remove('active'));

            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        function enableProfileEditingMode() {
            document.body.classList.add('is-editing-mode');
            document.getElementById('clubNameInput').removeAttribute('readonly');
            document.getElementById('descriptionInput').removeAttribute('readonly');
            document.getElementById('clubEmailInput').removeAttribute('readonly');
            document.getElementById('socialMediaInput').removeAttribute('readonly');
        }

        function disableProfileEditingMode() {
            document.body.classList.remove('is-editing-mode');
            document.getElementById('clubNameInput').setAttribute('readonly', 'true');
            document.getElementById('descriptionInput').setAttribute('readonly', 'true');
            document.getElementById('clubEmailInput').setAttribute('readonly', 'true');
            document.getElementById('socialMediaInput').setAttribute('readonly', 'true');
        }

        function openAvatarViewer(src) {
            document.getElementById('avatarViewerImage').src = src;
            document.getElementById('avatarViewer').classList.add('active');
        }

        function handleAvatarClick(src) {
            if (document.body.classList.contains('is-editing-mode')) {
                document.getElementById('profilePicInput').click();
                return;
            }
            openAvatarViewer(src);
        }

        function previewClubProfilePicture(input) {
            const file = input.files && input.files[0] ? input.files[0] : null;

            if (!file) {
                return;
            }

            document.getElementById('clubProfilePreview').src = URL.createObjectURL(file);
        }

        function closeAvatarViewer() {
            document.getElementById('avatarViewer').classList.remove('active');
        }

        function openAddMemberModal() {
            document.getElementById('addMemberModal').classList.add('active');
        }

        function closeAddMemberModal(e) {
            if (!e || e.target === document.getElementById('addMemberModal')) {
                document.getElementById('addMemberModal').classList.remove('active');
            }
        }

        function setRemoveMemberInputs(studentIDs) {
            const container = document.getElementById('removeMemberInputs');
            container.innerHTML = '';
            studentIDs.forEach(studentID => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'student_ids[]';
                input.value = studentID;
                container.appendChild(input);
            });
        }

        function openRemoveMemberModal(studentID, memberName) {
            setRemoveMemberInputs([studentID]);
            document.getElementById('removeMemberNameText').textContent = memberName || 'this member';
            document.getElementById('removeMemberReason').value = '';
            document.getElementById('removeMemberModal').classList.add('active');
            document.getElementById('removeMemberReason').focus();
        }

        let memberSelectionMode = false;

        function getSelectedMemberCheckboxes() {
            return Array.from(document.querySelectorAll('.member-select-checkbox:checked'));
        }

        function setMemberSelectionMode(enabled) {
            memberSelectionMode = enabled;
            document.querySelectorAll('.member-select-col').forEach(cell => {
                cell.classList.toggle('hidden', !enabled);
            });

            const selectBtn = document.getElementById('memberSelectModeBtn');
            const bulkBtn = document.getElementById('bulkRemoveMemberBtn');
            const selectAll = document.getElementById('selectAllMembers');
            if (selectBtn) {
                selectBtn.textContent = enabled ? 'Cancel' : 'Select';
            }
            if (bulkBtn) {
                bulkBtn.classList.toggle('hidden', !enabled);
            }
            if (!enabled) {
                document.querySelectorAll('.member-select-checkbox').forEach(box => {
                    box.checked = false;
                });
                if (selectAll) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }
            }
        }

        function toggleMemberSelectionMode() {
            setMemberSelectionMode(!memberSelectionMode);
        }

        function openBulkRemoveMembers() {
            const selected = getSelectedMemberCheckboxes();
            if (selected.length === 0) {
                alert('Please select at least one member to remove.');
                return;
            }
            const studentIDs = selected.map(box => box.value);
            const names = selected.map(box => box.dataset.name || box.value);
            setRemoveMemberInputs(studentIDs);
            document.getElementById('removeMemberNameText').textContent = selected.length === 1 ? names[0] : selected.length + ' selected members';
            document.getElementById('removeMemberReason').value = '';
            document.getElementById('removeMemberModal').classList.add('active');
            document.getElementById('removeMemberReason').focus();
        }

        function toggleAllMembers(source) {
            document.querySelectorAll('.member-select-checkbox').forEach(box => {
                box.checked = source.checked;
            });
            syncMemberSelectionState();
        }

        function syncMemberSelectionState() {
            const boxes = Array.from(document.querySelectorAll('.member-select-checkbox'));
            const selectAll = document.getElementById('selectAllMembers');
            const bulkBtn = document.getElementById('bulkRemoveMemberBtn');
            if (!selectAll || boxes.length === 0) return;
            const checkedCount = boxes.filter(box => box.checked).length;
            selectAll.checked = checkedCount === boxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
            if (bulkBtn) {
                bulkBtn.classList.toggle('hidden', !memberSelectionMode);
            }
        }

        function closeRemoveMemberModal(e) {
            if (!e || e.target === document.getElementById('removeMemberModal')) {
                document.getElementById('removeMemberModal').classList.remove('active');
                document.getElementById('removeMemberInputs').innerHTML = '';
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAvatarViewer();
                closeAddMemberModal();
                closeRemoveMemberModal();
            }
        });
    </script>
</body>
</html>
