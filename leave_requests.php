<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

// Fetch all leave requests
$stmt = $pdo->query("SELECT lr.*, u.name as employee_name, u.email as employee_email, u.department as employee_dept 
                     FROM leave_requests lr 
                     JOIN users u ON lr.user_id = u.id 
                     ORDER BY lr.created_at DESC");
$requests = $stmt->fetchAll();

// Calculate counts for summary cards
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
foreach ($requests as $r) {
    if ($r['status'] === 'Pending') $pending_count++;
    elseif ($r['status'] === 'Approved') $approved_count++;
    elseif ($r['status'] === 'Rejected') $rejected_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .clickable-row {
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .clickable-row:hover {
            background-color: #f1f5f9 !important;
        }
        
        /* Filter tabs styling */
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
        }
        .filter-tab {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            background: #e2e8f0;
            color: var(--text-muted);
            border: none;
        }
        .filter-tab.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
        }
        .filter-tab:hover:not(.active) {
            background: #cbd5e1;
            color: var(--text-main);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background-color: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 550px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border);
            overflow: hidden;
            animation: modalSlide 0.3s ease-out;
        }
        @keyframes modalSlide {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            padding: 20px 24px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
        }
        .close-btn:hover {
            color: var(--text);
        }
        .modal-body {
            padding: 24px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 12px 16px;
            margin-bottom: 24px;
        }
        .info-label {
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .info-value {
            color: var(--text);
            font-size: 0.9rem;
        }
        .modal-footer {
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-calendar-alt"></i> Leave Requests</h2>
            <div class="user-info">
                <span>Logged in as: <strong><?php echo e($_SESSION['name']); ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- Summary Stats -->
            <div class="stats-container" style="margin-bottom: 24px;">
                <div class="stat-card" onclick="filterByStatus('Pending')" style="cursor: pointer;">
                    <div class="stat-info">
                        <strong>Pending Requests</strong>
                        <div class="value"><?php echo $pending_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.08); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="filterByStatus('Approved')" style="cursor: pointer;">
                    <div class="stat-info">
                        <strong>Approved Requests</strong>
                        <div class="value"><?php echo $approved_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.08); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card" onclick="filterByStatus('Rejected')" style="cursor: pointer;">
                    <div class="stat-info">
                        <strong>Rejected Requests</strong>
                        <div class="value"><?php echo $rejected_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.08); color: var(--danger);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Leave Requests Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Leave Applications</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">Click on any row to view full details and perform actions.</p>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterByStatus('All', this)">All (<?php echo count($requests); ?>)</button>
                        <button class="filter-tab" onclick="filterByStatus('Pending', this)">Pending (<?php echo $pending_count; ?>)</button>
                        <button class="filter-tab" onclick="filterByStatus('Approved', this)">Approved (<?php echo $approved_count; ?>)</button>
                        <button class="filter-tab" onclick="filterByStatus('Rejected', this)">Rejected (<?php echo $rejected_count; ?>)</button>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th>Submitted At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr class="no-requests-row"><td colspan="7" style="text-align: center; padding: 30px;">No leave requests found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $r): 
                                        $start = new DateTime($r['start_date']);
                                        $end = new DateTime($r['end_date']);
                                        $days = $start->diff($end)->days + 1;
                                        // Include days in request array for JS access
                                        $r['duration_days'] = $days;
                                    ?>
                                        <tr class="clickable-row request-row" data-status="<?php echo e($r['status']); ?>" onclick="openRequestModal(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <td><strong><?php echo e($r['employee_name']); ?></strong></td>
                                            <td><?php echo e($r['leave_type']); ?></td>
                                            <td><?php echo e($r['start_date']); ?></td>
                                            <td><?php echo e($r['end_date']); ?></td>
                                            <td><?php echo $days; ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower(e($r['status'])); ?>">
                                                    <?php echo e($r['status']); ?>
                                                </span>
                                            </td>
                                            <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('M d, Y h:i A', strtotime($r['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Details & Action Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Leave Request Details</h3>
                <button class="close-btn" onclick="closeRequestModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="info-grid">
                    <div class="info-label">Employee Name:</div>
                    <div class="info-value" id="modal-emp-name"></div>
                    
                    <div class="info-label">Email Address:</div>
                    <div class="info-value" id="modal-emp-email"></div>

                    <div class="info-label">Department:</div>
                    <div class="info-value" id="modal-emp-dept"></div>
                    
                    <div class="info-label">Leave Type:</div>
                    <div class="info-value" id="modal-leave-type"></div>
                    
                    <div class="info-label">Duration:</div>
                    <div class="info-value" id="modal-duration"></div>
                    
                    <div class="info-label">Reason:</div>
                    <div class="info-value" id="modal-reason" style="white-space: pre-line;"></div>
                    
                    <div class="info-label">Attachment:</div>
                    <div class="info-value" id="modal-attachment"></div>

                    <div class="info-label">Current Status:</div>
                    <div class="info-value" id="modal-status-badge"></div>

                    <div class="info-label admin-comment-section">Admin Comment:</div>
                    <div class="info-value admin-comment-section" id="modal-admin-comment-display"></div>
                </div>
                
                <!-- Action Form (Only shown or used when pending) -->
                <form id="action-form" action="approve_deny.php" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="id" id="modal-request-id" value="">
                    <input type="hidden" name="action" id="modal-action-val" value="">
                    <input type="hidden" name="redirect" value="leave_requests.php">
                    
                    <div class="form-group" id="comment-group" style="margin-bottom: 0;">
                        <label for="admin_comment">Reviewer Feedback / Comments</label>
                        <textarea name="admin_comment" id="admin_comment" rows="3" placeholder="Enter comments here... (optional)"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background: var(--text-muted);" onclick="closeRequestModal()">Close</button>
                <button type="button" class="btn" id="reject-btn" style="background: var(--danger);" onclick="submitAction('Rejected')">Reject</button>
                <button type="button" class="btn" id="approve-btn" style="background: var(--success);" onclick="submitAction('Approved')">Approve</button>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('requestModal');
        
        function filterByStatus(status, tabElement) {
            // Update active class on tab buttons
            if (tabElement) {
                document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
                tabElement.classList.add('active');
            } else {
                // If clicked from summary cards, find matching tab
                document.querySelectorAll('.filter-tab').forEach(tab => {
                    if (tab.textContent.includes(status)) {
                        tab.classList.add('active');
                    } else {
                        tab.classList.remove('active');
                    }
                });
            }

            const rows = document.querySelectorAll('.request-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                if (status === 'All' || rowStatus === status) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Handle no requests scenario
            let noReqRow = document.querySelector('.no-requests-row');
            if (visibleCount === 0) {
                if (!noReqRow) {
                    const tbody = document.querySelector('tbody');
                    noReqRow = document.createElement('tr');
                    noReqRow.className = 'no-requests-row';
                    noReqRow.innerHTML = `<td colspan="7" style="text-align: center; padding: 30px;">No ${status !== 'All' ? status.toLowerCase() : ''} leave requests found.</td>`;
                    tbody.appendChild(noReqRow);
                } else {
                    noReqRow.style.display = '';
                    noReqRow.querySelector('td').textContent = `No ${status !== 'All' ? status.toLowerCase() : ''} leave requests found.`;
                }
            } else if (noReqRow) {
                noReqRow.style.display = 'none';
            }
        }

        function openRequestModal(req) {
            document.getElementById('modal-request-id').value = req.id;
            document.getElementById('modal-emp-name').textContent = req.employee_name || '';
            document.getElementById('modal-emp-email').textContent = req.employee_email || '';
            document.getElementById('modal-emp-dept').textContent = req.employee_dept || '—';
            document.getElementById('modal-leave-type').textContent = req.leave_type || '';
            
            const startStr = req.start_date;
            const endStr = req.end_date;
            document.getElementById('modal-duration').innerHTML = `<strong>${startStr}</strong> to <strong>${endStr}</strong> (${req.duration_days} ${req.duration_days > 1 ? 'days' : 'day'})`;
            document.getElementById('modal-reason').textContent = req.reason || '—';
            
            // Attachment
            const attachVal = document.getElementById('modal-attachment');
            if (req.attachment) {
                attachVal.innerHTML = `<a href="${req.attachment}" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 600;"><i class="fas fa-paperclip"></i> View Attachment</a>`;
            } else {
                attachVal.textContent = 'None';
            }

            // Status Badge
            const badgeClass = `badge badge-${req.status.toLowerCase()}`;
            document.getElementById('modal-status-badge').innerHTML = `<span class="${badgeClass}">${req.status}</span>`;

            // Admin feedback logic depending on status
            const commentGroup = document.getElementById('comment-group');
            const commentDisplay = document.getElementById('modal-admin-comment-display');
            const commentSections = document.querySelectorAll('.admin-comment-section');
            const approveBtn = document.getElementById('approve-btn');
            const rejectBtn = document.getElementById('reject-btn');

            if (req.status === 'Pending') {
                commentGroup.style.display = 'block';
                commentSections.forEach(sec => sec.style.display = 'none');
                approveBtn.style.display = 'inline-flex';
                rejectBtn.style.display = 'inline-flex';
                document.getElementById('admin_comment').value = '';
            } else {
                commentGroup.style.display = 'none';
                commentSections.forEach(sec => sec.style.display = 'block');
                commentDisplay.textContent = req.admin_comment || 'No feedback provided.';
                approveBtn.style.display = 'none';
                rejectBtn.style.display = 'none';
            }
            
            modal.classList.add('show');
        }

        function closeRequestModal() {
            modal.classList.remove('show');
        }

        function submitAction(action) {
            if (action === 'Rejected' && !confirm('Are you sure you want to reject this leave request?')) {
                return;
            }
            if (action === 'Approved' && !confirm('Are you sure you want to approve this leave request?')) {
                return;
            }
            document.getElementById('modal-action-val').value = action;
            document.getElementById('action-form').submit();
        }

        // Close when clicking outside of modal
        window.onclick = function(event) {
            if (event.target == modal) {
                closeRequestModal();
            }
        }
    </script>
</body>
</html>
