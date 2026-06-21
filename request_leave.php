<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkEmployee();

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch leave balances for the side panel
$bal_stmt = $pdo->prepare("SELECT leave_type, total_allowed, days_used FROM leave_balances WHERE user_id = ?");
$bal_stmt->execute([$user_id]);
$user_balances = $bal_stmt->fetchAll();

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
        // Fetch user gender
        $stmt = $pdo->prepare("SELECT gender FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_gender = $stmt->fetchColumn();

        $auto_reject = false;
        $auto_reject_comment = '';
        if ($leave_type === 'Maternity Leave' && $user_gender === 'Male') {
            $auto_reject = true;
            $auto_reject_comment = 'System Auto-Rejection: Gender restriction.';
        } elseif ($leave_type === 'Paternity Leave' && $user_gender === 'Female') {
            $auto_reject = true;
            $auto_reject_comment = 'System Auto-Rejection: Gender restriction.';
        }

        if ($auto_reject) {
            $stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status, admin_comment, attachment) VALUES (?, ?, ?, ?, ?, 'Rejected', ?, ?)");
            $stmt->execute([$user_id, $leave_type, $start_date, $end_date, $reason, $auto_reject_comment, $attachment_path]);
            $message = "Leave request submitted successfully!";
        } elseif ($start > $end) {
            $error = "End date cannot be before start date.";
        } elseif ($start < $today) {
            $error = "Cannot request leave for a past date.";
        } elseif ($leave_type === 'Vacation Leave' && $start < new DateTime('today + 5 days')) {
            $error = "Vacation Leave must be requested at least 5 days in advance.";
        } elseif ($leave_type === 'Sick Leave' && $days >= 3 && !$attachment_path) {
            $error = "A medical certificate attachment is required for Sick Leave of 3 or more days.";
        } else {
            // Check overlapping requests
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests 
                                   WHERE user_id = ? 
                                     AND status IN ('Pending', 'Pending Manager Approval', 'Pending Admin Approval', 'Approved') 
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
                    // Calculate days already requested in other pending applications
                    $stmt = $pdo->prepare("SELECT start_date, end_date FROM leave_requests 
                                           WHERE user_id = ? AND leave_type = ? AND status IN ('Pending', 'Pending Manager Approval', 'Pending Admin Approval')");
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
                        // Check if department has a manager
                        $mgr_stmt = $pdo->prepare("
                            SELECT d.manager_id 
                            FROM users u
                            JOIN departments d ON u.department_id = d.id
                            WHERE u.id = ?
                        ");
                        $mgr_stmt->execute([$user_id]);
                        $manager_id = $mgr_stmt->fetchColumn();

                        $initial_status = $manager_id ? 'Pending Manager Approval' : 'Pending Admin Approval';

                        $stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status, attachment) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$user_id, $leave_type, $start_date, $end_date, $reason, $initial_status, $attachment_path]);
                        $message = "Leave request submitted successfully!";
                        
                        // Refresh balances side-panel
                        $bal_stmt->execute([$user_id]);
                        $user_balances = $bal_stmt->fetchAll();
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
    <style>
        .request-split-layout {
            display: grid;
            grid-template-columns: 1fr 1.6fr;
            gap: 30px;
            align-items: start;
        }
        
        .balance-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .balance-item-row:last-child {
            border-bottom: none;
        }
        .balance-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .balance-nums {
            font-size: 0.85rem;
            font-weight: 700;
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 6px;
            color: var(--primary);
        }

        .helper-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(99, 102, 241, 0.06);
            border: 1px solid rgba(99, 102, 241, 0.15);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: var(--primary-hover);
        }

        .file-upload-wrapper {
            position: relative;
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background: #f8fafc;
            transition: var(--transition);
            cursor: pointer;
        }
        .file-upload-wrapper:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.02);
        }
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .file-upload-wrapper i {
            font-size: 1.8rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .file-upload-text {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Success Modal */
        .success-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(6px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .success-modal-overlay.show { display: flex; }
        .success-modal {
            background: #fff;
            border-radius: 20px;
            padding: 40px 36px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.18);
            animation: modalPop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalPop {
            from { transform: scale(0.75); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        .success-modal .check-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 0 0 12px rgba(16,185,129,0.12);
        }
        .success-modal .check-circle i { font-size: 2rem; color: #fff; }
        .success-modal h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: 6px; }
        .success-modal p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 18px; }
        .success-modal .detail-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 16px;
            margin: 18px 0;
            text-align: left;
            font-size: 0.85rem;
        }
        .success-modal .detail-box div { display: flex; justify-content: space-between; padding: 3px 0; }
        .success-modal .detail-box div span:first-child { color: var(--text-muted); }
        .modal-actions { display: flex; gap: 10px; margin-top: 22px; }
        .modal-actions a, .modal-actions button {
            flex: 1; padding: 11px; border-radius: 10px; font-size: 0.9rem;
            font-weight: 600; cursor: pointer; text-decoration: none; text-align: center;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-dashboard { background: var(--primary); color: #fff; border: none; }
        .btn-another { background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border) !important; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-paper-plane"></i> Apply for Leave</h2>
            <div class="user-info">
                <span>Welcome, <strong><?php echo e($_SESSION['name']); ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="request-split-layout">
                
                <!-- Left column: Live Balances -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-wallet"></i> My Leave Balances</h3>
                    </div>
                    <div class="card-body" style="padding: 20px 24px;">
                        <?php foreach ($user_balances as $b): ?>
                            <div class="balance-item-row">
                                <span class="balance-name"><?php echo e($b['leave_type']); ?></span>
                                <span class="balance-nums">
                                    <?php echo (float)($b['total_allowed'] - $b['days_used']); ?> / <?php echo (float)$b['total_allowed']; ?> remaining
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right column: Application Form -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-edit"></i> Submit Application Form</h3>
                    </div>
                    <div class="card-body" style="padding: 30px;">
                        <?php if (isset($error)): ?>
                            <p class="error"><i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?></p>
                        <?php endif; ?>

                        <!-- Dynamic notice banner based on chosen type -->
                        <div id="dynamic-rule-alert" class="helper-alert" style="display:none;">
                            <i class="fas fa-info-circle" style="margin-top:2px;"></i>
                            <span id="dynamic-rule-text"></span>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            
                            <div class="form-group">
                                <label>Leave Category <span style="color:var(--danger)">*</span></label>
                                <select name="leave_type" id="leave_type" onchange="checkLeaveRules(this.value)" required>
                                    <option value="Vacation Leave">Vacation Leave</option>
                                    <option value="Sick Leave">Sick Leave</option>
                                    <option value="Emergency Leave">Emergency Leave</option>
                                    <option value="Maternity Leave">Maternity Leave</option>
                                    <option value="Paternity Leave">Paternity Leave</option>
                                    <option value="Bereavement Leave">Bereavement Leave</option>
                                    <option value="Study Leave">Study Leave</option>
                                    <option value="Compensatory Leave">Compensatory Leave</option>
                                    <option value="Unpaid Leave">Unpaid Leave</option>
                                    <option value="Special Leave">Special Leave</option>
                                </select>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                                <div class="form-group">
                                    <label>Start Date <span style="color:var(--danger)">*</span></label>
                                    <input type="date" name="start_date" id="start_date" onchange="calculateRequestedDays()" required>
                                </div>
                                <div class="form-group">
                                    <label>End Date <span style="color:var(--danger)">*</span></label>
                                    <input type="date" name="end_date" id="end_date" onchange="calculateRequestedDays()" required>
                                </div>
                            </div>

                            <!-- Dynamic Calculated Days summary block -->
                            <div id="calculated-days-row" class="helper-alert" style="display:none; background:rgba(16, 185, 129, 0.05); border-color:rgba(16,185,129,0.15); color:#059669;">
                                <i class="fas fa-calculator" style="margin-top:2px;"></i>
                                <span>Total days calculated: <strong id="calc-days-val">0</strong> day(s)</span>
                            </div>

                            <div class="form-group">
                                <label>Supporting Document <span style="font-size:0.8rem; color:var(--text-muted); font-weight:normal;">(Optional - PDF, JPG, PNG max 2MB)</span></label>
                                <div class="file-upload-wrapper">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div class="file-upload-text">Drag file here or click to browse</div>
                                    <input type="file" name="attachment" id="attachment" accept=".pdf,.png,.jpg,.jpeg" onchange="updateFileName(this)">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Reason / Comments</label>
                                <textarea name="reason" rows="3" placeholder="Provide a brief explanation for your leave request..."></textarea>
                            </div>

                            <button type="submit" style="width: 100%; height:44px; margin-top:8px;">
                                <i class="fas fa-paper-plane"></i> Submit Request
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php if ($message): ?>
    <!-- Success Modal -->
    <div class="success-modal-overlay show" id="successModal">
        <div class="success-modal">
            <div class="check-circle">
                <i class="fas fa-check"></i>
            </div>
            <h2>Request Submitted!</h2>
            <p>Your leave application has been sent for manager/admin review.</p>

            <div class="detail-box">
                <div>
                    <span>Leave Type</span>
                    <strong><?php echo e($_POST['leave_type'] ?? 'N/A'); ?></strong>
                </div>
                <div>
                    <span>Date Range</span>
                    <strong><?php echo e($_POST['start_date'] ?? 'N/A'); ?> to <?php echo e($_POST['end_date'] ?? 'N/A'); ?></strong>
                </div>
                <div>
                    <span>Initial Status</span>
                    <strong style="color: #f59e0b;"><i class="fas fa-hourglass-half"></i> Pending Review</strong>
                </div>
            </div>

            <div class="modal-actions">
                <a href="employee_dashboard.php" class="btn-dashboard">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <button class="btn-another" onclick="document.getElementById('successModal').classList.remove('show')">
                    <i class="fas fa-plus"></i> New Request
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function checkLeaveRules(val) {
        const alertBox = document.getElementById('dynamic-rule-alert');
        const alertText = document.getElementById('dynamic-rule-text');
        
        if (val === 'Vacation Leave') {
            alertText.textContent = "Notice: Vacation Leave requests must be submitted at least 5 days in advance.";
            alertBox.style.display = 'flex';
        } else if (val === 'Sick Leave') {
            alertText.textContent = "Notice: Sick Leave applications for 3 or more days require a medical certificate upload.";
            alertBox.style.display = 'flex';
        } else {
            alertBox.style.display = 'none';
        }
    }

    function calculateRequestedDays() {
        const startVal = document.getElementById('start_date').value;
        const endVal = document.getElementById('end_date').value;
        const calcRow = document.getElementById('calculated-days-row');
        const calcVal = document.getElementById('calc-days-val');

        if (startVal && endVal) {
            const start = new Date(startVal);
            const end = new Date(endVal);
            
            if (end >= start) {
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                calcVal.textContent = diffDays;
                calcRow.style.display = 'flex';
            } else {
                calcRow.style.display = 'none';
            }
        } else {
            calcRow.style.display = 'none';
        }
    }

    function updateFileName(input) {
        const label = input.previousElementSibling;
        if (input.files && input.files.length > 0) {
            label.textContent = "Selected: " + input.files[0].name;
            label.style.color = "var(--primary)";
        } else {
            label.textContent = "Drag file here or click to browse";
            label.style.color = "var(--text-muted)";
        }
    }

    // Trigger on load for default value
    document.addEventListener('DOMContentLoaded', () => {
        checkLeaveRules(document.getElementById('leave_type').value);
    });
    </script>
</body>
</html>
