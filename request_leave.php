<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkEmployee();

$user_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF verification
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die("CSRF token validation failed.");
    }

    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];

    // Simple date validation
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days = $interval->days + 1;

    $today = new DateTime('today');

    $attachment_path = null;
    $upload_ok = true;

    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['attachment']['tmp_name'];
        $fileName = $_FILES['attachment']['name'];
        $fileSize = $_FILES['attachment']['size'];
        $fileType = $_FILES['attachment']['type'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array($fileExtension, $allowedfileExtensions)) {
            if ($fileSize < 2 * 1024 * 1024) { // 2MB limit
                $uploadFileDir = 'uploads/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;
                
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $attachment_path = $dest_path;
                } else {
                    $error = "There was an error moving the uploaded file.";
                    $upload_ok = false;
                }
            } else {
                $error = "File size must be less than 2MB.";
                $upload_ok = false;
            }
        } else {
            $error = "Upload failed. Allowed file types: " . implode(', ', $allowedfileExtensions);
            $upload_ok = false;
        }
    }

    if ($upload_ok) {
        if ($start > $end) {
            $error = "End date cannot be before start date.";
        } elseif ($start < $today) {
            $error = "Cannot request leave for a past date.";
        } else {
            // Check overlapping requests
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests 
                                   WHERE user_id = ? 
                                     AND status IN ('Pending', 'Approved') 
                                     AND (
                                         (start_date <= ? AND end_date >= ?) OR
                                         (start_date <= ? AND end_date >= ?) OR
                                         (start_date >= ? AND end_date <= ?)
                                     )");
            $stmt->execute([$user_id, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date]);
            $overlapCount = $stmt->fetchColumn();

            if ($overlapCount > 0) {
                $error = "You already have a pending or approved leave request that overlaps with these dates.";
            } else {
                // Check balance, factoring in existing pending requests of the same type
                $stmt = $pdo->prepare("SELECT * FROM leave_balances WHERE user_id = ? AND leave_type = ?");
                $stmt->execute([$user_id, $leave_type]);
                $balance = $stmt->fetch();

                if ($balance) {
                    // Calculate days already requested in other 'Pending' applications
                    $stmt = $pdo->prepare("SELECT start_date, end_date FROM leave_requests 
                                           WHERE user_id = ? AND leave_type = ? AND status = 'Pending'");
                    $stmt->execute([$user_id, $leave_type]);
                    $pendingRequests = $stmt->fetchAll();
                    
                    $pendingDays = 0;
                    foreach ($pendingRequests as $pr) {
                        $pStart = new DateTime($pr['start_date']);
                        $pEnd = new DateTime($pr['end_date']);
                        $pendingDays += $pStart->diff($pEnd)->days + 1;
                    }

                    $availableDays = $balance['total_allowed'] - $balance['days_used'] - $pendingDays;

                    if ($availableDays >= $days) {
                        $stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, attachment) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$user_id, $leave_type, $start_date, $end_date, $reason, $attachment_path]);
                        $message = "Leave request submitted successfully!";
                    } else {
                        $error = "Insufficient leave balance. You requested $days days, but only $availableDays remain (accounting for pending requests).";
                    }
                } else {
                    $error = "No leave balance record found for this leave type.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Leave - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-paper-plane"></i> Request Leave</h2>
            <div class="user-info">
                <span>Welcome back, <strong><?php echo e($_SESSION['name']); ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="card" style="max-width: 600px; margin: 0 auto;">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Leave Application</h3>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <p class="success"><?php echo e($message); ?></p>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <p class="error"><?php echo e($error); ?></p>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label>Leave Type</label>
                            <select name="leave_type" required>
                                <option value="Vacation">Vacation</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Unpaid">Unpaid</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" required>
                        </div>
                        <div class="form-group">
                            <label>Attachment (Optional, PDF/Images max 2MB)</label>
                            <input type="file" name="attachment" accept=".pdf,.png,.jpg,.jpeg">
                        </div>
                        <div class="form-group">
                            <label>Reason (Optional)</label>
                            <textarea name="reason" rows="3" placeholder="Please state the reason for your leave request..."></textarea>
                        </div>
                        <button type="submit" style="width: 100%;"><i class="fas fa-paper-plane"></i> Submit Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
