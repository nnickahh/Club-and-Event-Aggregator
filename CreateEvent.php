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
    $clubName = $_SESSION['clubName'] ?? '';
    $message = "";
    $showSuccessPopup = false;
    $createdEventCount = 0;

    function prepareUploadDirectory($relativeDir) {
        $relativeDir = trim($relativeDir, '/');
        $absoluteDir = __DIR__ . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true)) {
            return false;
        }

        return is_writable($absoluteDir) ? $absoluteDir . '/' : false;
    }

    function buildRecurringDates($startDate, $endDate, $recurrenceType, $weeklyDays, $monthlyDay) {
        $dates = [];
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);

        if ($recurrenceType === 'weekly') {
            $selectedDays = array_map('intval', $weeklyDays);
            for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
                if (in_array((int)$cursor->format('N'), $selectedDays, true)) {
                    $dates[] = $cursor->format('Y-m-d');
                }
            }
        } elseif ($recurrenceType === 'monthly') {
            $day = (int)$monthlyDay;
            $cursor = $start->modify('first day of this month');
            $endMonth = $end->modify('first day of this month');

            while ($cursor <= $endMonth) {
                $year = (int)$cursor->format('Y');
                $month = (int)$cursor->format('m');
                if (checkdate($month, $day, $year)) {
                    $candidate = $cursor->setDate($year, $month, $day);
                    if ($candidate >= $start && $candidate <= $end) {
                        $dates[] = $candidate->format('Y-m-d');
                    }
                }
                $cursor = $cursor->modify('+1 month');
            }
        }

        return array_values(array_unique($dates));
    }

    // Handle Form Processing when user clicks submit
    if (isset($_POST["submit"])) {
        $eventTitle  = trim($_POST['eventTitle']);
        $eventDate   = $_POST['eventDate'];
        $eventEndDate = !empty(trim($_POST['eventEndDate'])) ? trim($_POST['eventEndDate']) : null;
        $eventTime   = trim($_POST['eventTime']);
        $eventEndTime = !empty(trim($_POST['eventEndTime'])) ? trim($_POST['eventEndTime']) : null;
        $venue       = trim($_POST['venue']);
        $capacity    = intval($_POST['capacity']);
        $description = trim($_POST['description']);
        $fee        = !empty(trim($_POST['fee'])) ? floatval($_POST['fee']) : 0.00;
        $eventImage  = null;
        $recurrenceType = $_POST['recurrenceType'] ?? 'none';
        $weeklyDays = $_POST['weeklyDays'] ?? [];
        $monthlyDay = $_POST['monthlyDay'] ?? '';
        $eventDatesToCreate = [$eventDate];
        $recurrenceGroupID = null;
        $recurrenceStartDate = null;
        $recurrenceEndDate = null;

        if (!in_array($recurrenceType, ['none', 'weekly', 'monthly'], true)) {
            $recurrenceType = 'none';
        }

        if ($recurrenceType !== 'none' && $eventEndDate === null) {
            $message = "<p class='msg-error'>End date is required for recurring activities.</p>";
        }

        if (empty($message) && $eventEndDate !== null && $eventEndDate < $eventDate) {
            $message = "<p class='msg-error'>End date is before the event date. Please choose a correct date.</p>";
        }

        $isSingleDayEvent = $recurrenceType !== 'none' || $eventEndDate === null || $eventEndDate === $eventDate;
        if (empty($message) && $isSingleDayEvent && $eventEndTime !== null && $eventEndTime <= $eventTime) {
            $message = "<p class='msg-error'>End time is before the event time. Please choose a correct time.</p>";
        }

        if (empty($message) && $recurrenceType === 'weekly') {
            $weeklyDays = array_values(array_filter(array_map('intval', $weeklyDays), function ($day) {
                return $day >= 1 && $day <= 7;
            }));
            if (empty($weeklyDays)) {
                $message = "<p class='msg-error'>Please choose at least one weekday for the weekly activity.</p>";
            } else {
                $eventDatesToCreate = buildRecurringDates($eventDate, $eventEndDate, 'weekly', $weeklyDays, null);
            }
        }

        if (empty($message) && $recurrenceType === 'monthly') {
            $monthlyDay = (int)$monthlyDay;
            if ($monthlyDay < 1 || $monthlyDay > 31) {
                $message = "<p class='msg-error'>Please choose a valid day of the month.</p>";
            } else {
                $eventDatesToCreate = buildRecurringDates($eventDate, $eventEndDate, 'monthly', [], $monthlyDay);
            }
        }

        if (empty($message) && $recurrenceType !== 'none' && empty($eventDatesToCreate)) {
            $message = "<p class='msg-error'>No activity dates match your recurring schedule. Please check the date range.</p>";
        }

        if (empty($message) && count($eventDatesToCreate) > 120) {
            $message = "<p class='msg-error'>This recurring schedule creates too many events. Please choose a shorter date range.</p>";
        }

        if (empty($message) && $recurrenceType !== 'none') {
            $recurrenceGroupID = 'rec_' . $adminID . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
            $recurrenceStartDate = $eventDate;
            $recurrenceEndDate = $eventEndDate;
        }

        // Handle image upload
        if (empty($message) && isset($_FILES['eventImage']) && $_FILES['eventImage']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . basename($_FILES['eventImage']['name']);
            $uploadDir = prepareUploadDirectory('uploads/events');
            $relativePath = 'uploads/events/' . $fileName;
            $targetPath = $uploadDir ? $uploadDir . $fileName : '';
            $imageFileType = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($imageFileType, $allowedTypes)) {
                $message = "<p class='msg-error'>Only JPG, JPEG, PNG, GIF & WEBP files are allowed.</p>";
            } elseif ($_FILES['eventImage']['size'] > 5 * 1024 * 1024) {
                $message = "<p class='msg-error'>File size must be less than 5MB.</p>";
            } elseif (!$uploadDir) {
                $message = "<p class='msg-error'>Upload folder is not writable. Please check the uploads/events folder permission.</p>";
            } elseif (move_uploaded_file($_FILES['eventImage']['tmp_name'], $targetPath)) {
                $eventImage = $relativePath;
            } else {
                $message = "<p class='msg-error'>Failed to upload image.</p>";
            }
        }

        // Payment fields
        $payment_methods = !empty($_POST['payment_methods']) ? implode(',', $_POST['payment_methods']) : null;
        $tng_phone = null;
        $tng_qr = null;
        $bank_details = null;

        if (empty($message) && $payment_methods && strpos($payment_methods, 'tng') !== false) {
            $tng_phone = !empty(trim($_POST['tng_phone'])) ? trim($_POST['tng_phone']) : null;
            if ($tng_phone === null) {
                $message = "<p class='msg-error'>TNG phone number is required.</p>";
            } elseif (!ctype_digit($tng_phone)) {
                $message = "<p class='msg-error'>Only numbers allowed.</p>";
            }
            if (isset($_FILES['tng_qr']) && $_FILES['tng_qr']['error'] === UPLOAD_ERR_OK) {
                $qrName = time() . '_qr_' . basename($_FILES['tng_qr']['name']);
                $qrDir = prepareUploadDirectory('uploads/payments');
                $relativeQrPath = 'uploads/payments/' . $qrName;
                $qrPath = $qrDir ? $qrDir . $qrName : '';
                $qrType = strtolower(pathinfo($relativeQrPath, PATHINFO_EXTENSION));
                if (!in_array($qrType, ['jpg','jpeg','png','gif','webp'])) {
                    $message = "<p class='msg-error'>Only JPG, JPEG, PNG, GIF & WEBP QR files are allowed.</p>";
                } elseif (!$qrDir) {
                    $message = "<p class='msg-error'>Upload folder is not writable. Please check the uploads/payments folder permission.</p>";
                } elseif (move_uploaded_file($_FILES['tng_qr']['tmp_name'], $qrPath)) {
                    $tng_qr = $relativeQrPath;
                } else {
                    $message = "<p class='msg-error'>Failed to upload Touch 'n Go QR image.</p>";
                }
            }
        }

        if (empty($message) && $payment_methods && strpos($payment_methods, 'bank_in') !== false) {
            $bankData = [];
            if (empty(trim($_POST['bank_name']))) {
                $message = "<p class='msg-error'>Bank name is required.</p>";
            } else {
                $bankData['bank_name'] = trim($_POST['bank_name']);
            }
            if (empty($message) && empty(trim($_POST['bank_account']))) {
                $message = "<p class='msg-error'>Bank account number is required.</p>";
            } elseif (empty($message)) {
                $bankAccount = trim($_POST['bank_account']);
                if (!ctype_digit($bankAccount)) {
                    $message = "<p class='msg-error'>Only numbers allowed.</p>";
                } else {
                    $bankData['bank_account'] = $bankAccount;
                }
            }
            if (empty($message) && empty(trim($_POST['bank_holder']))) {
                $message = "<p class='msg-error'>Account holder name is required.</p>";
            } elseif (empty($message)) {
                $bankData['bank_holder'] = trim($_POST['bank_holder']);
            }
            if (!empty($bankData)) $bank_details = json_encode($bankData);
        }

        if (empty($message) && empty($clubName)) {
            $clubStmt = $conn->prepare("SELECT clubName FROM admins WHERE adminID = ?");
            $clubStmt->bind_param("s", $adminID);
            $clubStmt->execute();
            $clubResult = $clubStmt->get_result();
            if ($clubRow = $clubResult->fetch_assoc()) {
                $clubName = $clubRow['clubName'];
            }
            $clubStmt->close();
        }

        if (empty($message)) {
            $stmt = $conn->prepare("INSERT INTO events (adminID, clubName, eventTitle, eventDate, eventEndDate, recurrence_group_id, recurrence_type, recurrence_start_date, recurrence_end_date, eventTime, eventEndTime, venue, capacity, description, eventImage, status, payment_methods, tng_phone, tng_qr, bank_details, fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)");

            if ($stmt) {
                $createdEventIds = [];
                foreach ($eventDatesToCreate as $scheduledDate) {
                    $scheduledEndDate = $recurrenceType === 'none' ? $eventEndDate : null;
                    $stmt->bind_param("ssssssssssssissssssd", $adminID, $clubName, $eventTitle, $scheduledDate, $scheduledEndDate, $recurrenceGroupID, $recurrenceType, $recurrenceStartDate, $recurrenceEndDate, $eventTime, $eventEndTime, $venue, $capacity, $description, $eventImage, $payment_methods, $tng_phone, $tng_qr, $bank_details, $fee);
                    if ($stmt->execute()) {
                        $createdEventIds[] = $stmt->insert_id;
                    }
                }

                if (!empty($createdEventIds)) {
                    $createdEventCount = count($createdEventIds);
                    $newEventId = $createdEventIds[0];
                    $modMsg = $createdEventCount > 1
                        ? "New recurring event submitted for review: " . $eventTitle . " (" . $createdEventCount . " sessions)"
                        : "New event submitted for review: " . $eventTitle;
                    $modStmt = $conn->prepare("INSERT INTO moderator_notifications (message, eventID) VALUES (?, ?)");
                    $modStmt->bind_param("si", $modMsg, $newEventId);
                    $modStmt->execute();
                    $modStmt->close();
                    $showSuccessPopup = true;
                } else {
                    $message = "<p class='msg-error'>Error creating event. Please try again.</p>";
                }
                $stmt->close();
            } else {
                $message = "<p class='msg-error'>Database preparation failed.</p>";
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
                    <label id="eventDateLabel">Event Date :</label>
                    <input type="date" name="eventDate" id="eventDateInput" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label id="eventEndDateLabel">End Date :</label>
                    <input type="date" name="eventEndDate" id="eventEndDateInput" min="<?php echo date('Y-m-d'); ?>" placeholder="Leave blank if the event is only one day.">
                </div>

                <div class="form-group recurring-form-group">
                    <label>Activity Schedule :</label>
                    <div class="recurring-panel">
                        <div class="schedule-type-row">
                            <label class="schedule-option">
                                <input type="radio" name="recurrenceType" value="none" checked>
                                <span class="schedule-icon">1</span>
                                <span class="schedule-copy">
                                    <strong>One-time</strong>
                                    <small>Single event only</small>
                                </span>
                            </label>
                            <label class="schedule-option">
                                <input type="radio" name="recurrenceType" value="weekly">
                                <span class="schedule-icon">W</span>
                                <span class="schedule-copy">
                                    <strong>Weekly</strong>
                                    <small>Repeat by weekday</small>
                                </span>
                            </label>
                            <label class="schedule-option">
                                <input type="radio" name="recurrenceType" value="monthly">
                                <span class="schedule-icon">M</span>
                                <span class="schedule-copy">
                                    <strong>Monthly</strong>
                                    <small>Repeat by date</small>
                                </span>
                            </label>
                        </div>

                        <p class="recurring-hint" id="recurringHint">Create one event using the selected event date.</p>

                        <div id="weeklyOptions" class="recurring-options">
                            <span class="recurring-option-title">Repeat every week on</span>
                            <div class="weekday-grid">
                                <label><input type="checkbox" name="weeklyDays[]" value="1"><span>Mon</span></label>
                                <label><input type="checkbox" name="weeklyDays[]" value="2"><span>Tue</span></label>
                                <label><input type="checkbox" name="weeklyDays[]" value="3"><span>Wed</span></label>
                                <label><input type="checkbox" name="weeklyDays[]" value="4"><span>Thu</span></label>
                                <label><input type="checkbox" name="weeklyDays[]" value="5"><span>Fri</span></label>
                                <label><input type="checkbox" name="weeklyDays[]" value="6"><span>Sat</span></label>
                                <label><input type="checkbox" name="weeklyDays[]" value="7"><span>Sun</span></label>
                            </div>
                        </div>

                        <div id="monthlyOptions" class="recurring-options">
                            <label class="recurring-option-title" for="monthlyDayInput">Repeat every month on day</label>
                            <select name="monthlyDay" id="monthlyDayInput" class="form-input recurring-select">
                                <option value="">Select day</option>
                                <?php for ($day = 1; $day <= 31; $day++): ?>
                                    <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Event Time Schedule :</label>
                    <div class="flex-time-row">
                        <input type="time" name="eventTime" required class="form-input-flex">
                        <span class="flex-time-sep">—</span>
                        <input type="time" name="eventEndTime" class="form-input-flex">
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
                    <textarea name="description" placeholder="Provide event context guidelines or register rules..." required class="form-textarea-lg"></textarea>
                </div>

                <div class="form-group">
                    <label>Event Fee (optional) :</label>
                    <input type="number" name="fee" min="0" step="0.01" placeholder="0.00 (free)" class="form-input-lg">
                </div>

                <div class="form-group">
                    <label>Event Poster (optional) :</label>
                    <input type="file" name="eventImage" accept="image/jpeg,image/png,image/gif,image/webp" class="form-file-input">
                </div>

                <div class="form-group">
                    <label>Payment Methods (optional) :</label>
                    <div class="flex-payment-checkboxes">
                        <label>
                            <input type="checkbox" name="payment_methods[]" value="cash" onchange="togglePaymentFields()" class="form-checkbox"> Cash
                        </label>
                        <label>
                            <input type="checkbox" name="payment_methods[]" value="tng" onchange="togglePaymentFields()" class="form-checkbox"> TNG (Touch 'n Go)
                        </label>
                        <label>
                            <input type="checkbox" name="payment_methods[]" value="bank_in" onchange="togglePaymentFields()" class="form-checkbox"> Bank In
                        </label>
                    </div>

                    <div id="tngFields" class="payment-section">
                        <p class="payment-section-title">TNG Payment Details</p>
                        <div style="margin-bottom:10px;">
                            <label class="form-label-sm">Phone Number</label>
                            <input type="text" name="tng_phone" placeholder="e.g., 0123456789" inputmode="numeric" pattern="[0-9]*" oninvalid="this.setCustomValidity(this.value ? 'Only numbers allowed' : 'Please fill out this field')" oninput="this.setCustomValidity(''); this.value=this.value.replace(/[^0-9]/g,'')" class="form-input">
                        </div>
                        <div>
                            <label class="form-label-sm">QR Code Image (optional)</label>
                            <input type="file" name="tng_qr" accept="image/*" class="form-file-input-sm">
                        </div>
                    </div>

                    <div id="bankFields" class="payment-section">
                        <p class="payment-section-title">Bank In Details</p>
                        <div style="margin-bottom:10px;">
                            <label class="form-label-sm">Bank Name</label>
                            <input type="text" name="bank_name" placeholder="e.g., CIMB Bank" oninvalid="this.setCustomValidity('Please fill out this field')" oninput="this.setCustomValidity('')" class="form-input">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label class="form-label-sm">Account Number</label>
                            <input type="text" name="bank_account" placeholder="e.g., 1234567890" inputmode="numeric" pattern="[0-9]*" oninvalid="this.setCustomValidity(this.value ? 'Only numbers allowed' : 'Please fill out this field')" oninput="this.setCustomValidity(''); this.value=this.value.replace(/[^0-9]/g,'')" class="form-input">
                        </div>
                        <div>
                            <label class="form-label-sm">Account Holder Name</label>
                            <input type="text" name="bank_holder" placeholder="e.g., John Doe" oninvalid="this.setCustomValidity('Please fill out this field')" oninput="this.setCustomValidity('')" class="form-input">
                        </div>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-primary mt-16 w-100">Submit for Approval</button>

                <div class="text-center text-sm-muted" style="margin-top:20px;">
                    Changed your mind? <a href="AdminDashboard.php" class="text-muted-link">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($showSuccessPopup): ?>
    <div class="logout-modal-overlay">
        <div class="logout-modal-box modal-content-center">
            <div class="modal-icon-success">✓</div>
            <h3 class="modal-title">Event Submitted!</h3>
            <p class="modal-text">
                <?php if ($createdEventCount > 1): ?>
                    <?php echo $createdEventCount; ?> activity sessions have been submitted for review.<br>
                <?php else: ?>
                    Your event has been submitted for review.<br>
                <?php endif; ?>
                A moderator will review it before it becomes visible to students.
            </p>
            <button onclick="window.location.href='AdminDashboard.php'" class="btn-primary modal-btn">OK</button>
        </div>
    </div>
    <?php else: ?>
    <script>
    const eventDateInput = document.getElementById('eventDateInput');
    const eventEndDateInput = document.getElementById('eventEndDateInput');
    const eventDateLabel = document.getElementById('eventDateLabel');
    const eventEndDateLabel = document.getElementById('eventEndDateLabel');
    const eventTimeInput = document.querySelector('input[name="eventTime"]');
    const eventEndTimeInput = document.querySelector('input[name="eventEndTime"]');
    const recurrenceInputs = document.querySelectorAll('input[name="recurrenceType"]');
    const weeklyOptions = document.getElementById('weeklyOptions');
    const monthlyOptions = document.getElementById('monthlyOptions');
    const monthlyDayInput = document.getElementById('monthlyDayInput');
    const weeklyDayInputs = document.querySelectorAll('input[name="weeklyDays[]"]');
    const recurringHint = document.getElementById('recurringHint');

    function getRecurrenceType() {
        const selected = document.querySelector('input[name="recurrenceType"]:checked');
        return selected ? selected.value : 'none';
    }

    function validateEventDateRange() {
        if (eventDateInput && eventEndDateInput && eventEndDateInput.value && eventDateInput.value && eventEndDateInput.value < eventDateInput.value) {
            eventEndDateInput.setCustomValidity('End date is before the event date. Please choose a correct date.');
        } else if (eventEndDateInput && getRecurrenceType() !== 'none' && !eventEndDateInput.value) {
            eventEndDateInput.setCustomValidity('End date is required for recurring activities.');
        } else if (eventEndDateInput) {
            eventEndDateInput.setCustomValidity('');
        }
    }

    function validateEventTimeRange() {
        if (!eventTimeInput || !eventEndTimeInput || !eventDateInput || !eventEndDateInput) return;
        const singleDay = getRecurrenceType() !== 'none' || !eventEndDateInput.value || eventEndDateInput.value === eventDateInput.value;
        if (singleDay && eventEndTimeInput.value && eventTimeInput.value && eventEndTimeInput.value <= eventTimeInput.value) {
            eventEndTimeInput.setCustomValidity('End time is before the event time. Please choose a correct time.');
        } else {
            eventEndTimeInput.setCustomValidity('');
        }
    }

    function validateRecurringOptions() {
        const type = getRecurrenceType();
        const firstWeeklyInput = weeklyDayInputs[0];

        weeklyDayInputs.forEach(input => input.setCustomValidity(''));
        if (monthlyDayInput) monthlyDayInput.setCustomValidity('');

        if (type === 'weekly') {
            const hasSelectedDay = Array.from(weeklyDayInputs).some(input => input.checked);
            if (!hasSelectedDay && firstWeeklyInput) {
                firstWeeklyInput.setCustomValidity('Please choose at least one weekday for the weekly activity.');
            }
        }

        if (type === 'monthly' && monthlyDayInput && !monthlyDayInput.value) {
            monthlyDayInput.setCustomValidity('Please choose a valid day of the month.');
        }
    }

    function toggleRecurringFields() {
        const type = getRecurrenceType();
        if (weeklyOptions) weeklyOptions.style.display = type === 'weekly' ? 'block' : 'none';
        if (monthlyOptions) monthlyOptions.style.display = type === 'monthly' ? 'block' : 'none';
        if (eventEndDateInput) eventEndDateInput.required = type !== 'none';

        if (type === 'weekly') {
            if (eventDateLabel) eventDateLabel.textContent = 'Start Date :';
            if (eventEndDateLabel) eventEndDateLabel.textContent = 'End Date :';
            if (recurringHint) recurringHint.textContent = 'Creates one pending event for each selected weekday inside the date range.';
        } else if (type === 'monthly') {
            if (eventDateLabel) eventDateLabel.textContent = 'Start Date :';
            if (eventEndDateLabel) eventEndDateLabel.textContent = 'End Date :';
            if (recurringHint) recurringHint.textContent = 'Creates one pending event every month on the selected day inside the date range.';
        } else {
            if (eventDateLabel) eventDateLabel.textContent = 'Event Date :';
            if (eventEndDateLabel) eventEndDateLabel.textContent = 'End Date :';
            if (recurringHint) recurringHint.textContent = 'Create one event using the selected event date.';
        }

        validateEventDateRange();
        validateEventTimeRange();
        validateRecurringOptions();
    }

    if (eventDateInput && eventEndDateInput) {
        eventDateInput.addEventListener('change', validateEventDateRange);
        eventEndDateInput.addEventListener('change', validateEventDateRange);
        eventDateInput.addEventListener('change', validateEventTimeRange);
        eventEndDateInput.addEventListener('change', validateEventTimeRange);
    }
    if (eventTimeInput && eventEndTimeInput) {
        eventTimeInput.addEventListener('change', validateEventTimeRange);
        eventEndTimeInput.addEventListener('change', validateEventTimeRange);
    }
    recurrenceInputs.forEach(input => input.addEventListener('change', toggleRecurringFields));
    weeklyDayInputs.forEach(input => input.addEventListener('change', validateRecurringOptions));
    if (monthlyDayInput) monthlyDayInput.addEventListener('change', validateRecurringOptions);
    toggleRecurringFields();

    function togglePaymentFields() {
        const tng = document.querySelector('input[name="payment_methods[]"][value="tng"]');
        const bank = document.querySelector('input[name="payment_methods[]"][value="bank_in"]');
        const tng_phone = document.querySelector('input[name="tng_phone"]');
        const bankName = document.querySelector('input[name="bank_name"]');
        const bankAccount = document.querySelector('input[name="bank_account"]');
        const bankHolder = document.querySelector('input[name="bank_holder"]');
        document.getElementById('tngFields').style.display = tng && tng.checked ? 'block' : 'none';
        document.getElementById('bankFields').style.display = bank && bank.checked ? 'block' : 'none';
        if (tng_phone) {
            tng_phone.required = !!(tng && tng.checked);
            if (!tng_phone.required) tng_phone.setCustomValidity('');
        }
        if (bankName) {
            bankName.required = !!(bank && bank.checked);
            if (!bankName.required) bankName.setCustomValidity('');
        }
        if (bankAccount) {
            bankAccount.required = !!(bank && bank.checked);
            if (!bankAccount.required) bankAccount.setCustomValidity('');
        }
        if (bankHolder) {
            bankHolder.required = !!(bank && bank.checked);
            if (!bankHolder.required) bankHolder.setCustomValidity('');
        }
    }
    togglePaymentFields();
    </script>
    <?php endif; ?>

</body>
</html>
