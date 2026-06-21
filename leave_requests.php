<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

// Read & clear flash session set by approve_deny.php
$flash_action = $_SESSION['flash_action'] ?? null;
$flash_type   = $_SESSION['flash_type']   ?? null;
unset($_SESSION['flash_action'], $_SESSION['flash_type'], $_SESSION['flash_name']);

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

        /* Confirmation & Result modal (layered on top) */
        .lms-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,0.6); backdrop-filter: blur(6px);
            z-index: 2000; align-items: center; justify-content: center;
        }
        .lms-modal-overlay.show { display: flex; }
        .lms-modal {
            background: #fff; border-radius: 20px; padding: 40px 36px;
            max-width: 400px; width: 90%; text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.2);
            animation: lmsPop 0.35s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes lmsPop {
            from { transform: scale(0.75); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        .lms-modal .m-icon {
            width: 70px; height: 70px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px; font-size: 1.9rem; color: #fff;
        }
        .m-icon.approve { background: linear-gradient(135deg,#10b981,#059669); box-shadow: 0 0 0 12px rgba(16,185,129,0.12); }
        .m-icon.reject  { background: linear-gradient(135deg,#ef4444,#dc2626); box-shadow: 0 0 0 12px rgba(239,68,68,0.12); }
        .lms-modal h2 { font-size:1.35rem; font-weight:700; margin-bottom:8px; color:var(--text); }
        .lms-modal p  { color:var(--text-muted); font-size:0.9rem; line-height:1.6; margin-bottom:0; }
        .lms-modal .m-detail {
            background:#f8fafc; border:1px solid var(--border); border-radius:10px;
            padding:12px 16px; margin:16px 0; font-size:0.86rem; text-align:left;
        }
        .lms-modal .m-detail div { display:flex; justify-content:space-between; padding:3px 0; }
        .lms-modal .m-detail div span:first-child { color:var(--text-muted); }
        .m-btn-row { display:flex; gap:10px; margin-top:20px; }
        .m-btn-row button {
            flex:1; padding:11px; border-radius:10px; font-size:0.9rem;
            font-weight:600; cursor:pointer; border:none; transition:opacity 0.2s;
            display:inline-flex; align-items:center; justify-content:center; gap:6px;
        }
        .m-btn-row button:hover { opacity:0.85; }
        .m-btn-cancel  { background:#f1f5f9; color:var(--text); border:1px solid var(--border) !important; }
        .m-btn-approve { background:var(--success); color:#fff; }
        .m-btn-reject  { background:var(--danger);  color:#fff; }
        .m-btn-ok      { background:var(--primary);  color:#fff; }
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
                <button type="button" class="btn" id="reject-btn" style="background: var(--danger);"  onclick="openConfirm('Rejected')">Reject</button>
                <button type="button" class="btn" id="approve-btn" style="background: var(--success);" onclick="openConfirm('Approved')">Approve</button>
            </div>
        </div>
    </div>

    <!-- ===== CONFIRMATION MODAL ===== -->
    <div class="lms-modal-overlay" id="confirmOverlay">
        <div class="lms-modal">
            <div class="m-icon" id="conf-icon"><i id="conf-icon-i" class="fas fa-question"></i></div>
            <h2 id="conf-title">Confirm</h2>
            <p  id="conf-msg">Are you sure?</p>
            <div class="m-detail">
                <div><span>Employee</span><span id="conf-emp"></span></div>
                <div><span>Leave Type</span><span id="conf-type"></span></div>
                <div><span>Duration</span><span id="conf-dur"></span></div>
            </div>
            <div class="m-btn-row">
                <button class="m-btn-cancel" onclick="closeConfirm()"><i class="fas fa-times"></i> Cancel</button>
                <button id="conf-do-btn" class="m-btn-approve" onclick="doSubmitAction()"><i class="fas fa-check"></i> Confirm</button>
            </div>
        </div>
    </div>

    <!-- ===== RESULT / FLASH MODAL ===== -->
    <?php if ($flash_action && $flash_type === 'leave'): ?>
    <div class="lms-modal-overlay show" id="resultOverlay">
        <div class="lms-modal">
            <?php if ($flash_action === 'Approved'): ?>
                <div class="m-icon approve"><i class="fas fa-check"></i></div>
                <h2>Leave Approved!</h2>
                <p>The leave request has been <strong>approved</strong> and the employee's balance has been updated.</p>
            <?php else: ?>
                <div class="m-icon reject"><i class="fas fa-times"></i></div>
                <h2>Leave Rejected</h2>
                <p>The leave request has been <strong>rejected</strong>. The employee will see this decision.</p>
            <?php endif; ?>
            <div class="m-btn-row">
                <button class="m-btn-ok" onclick="document.getElementById('resultOverlay').classList.remove('show')">
                    <i class="fas fa-check"></i> Got it
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const modal = document.getElementById('requestModal');
        let _pendingAction = null;
        let _currentReq    = null;

        function filterByStatus(status, tabElement) {
            if (tabElement) {
                document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
                tabElement.classList.add('active');
            } else {
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
            _currentReq = req;
            document.getElementById('modal-request-id').value = req.id;
            document.getElementById('modal-emp-name').textContent  = req.employee_name || '';
            document.getElementById('modal-emp-email').textContent = req.employee_email || '';
            document.getElementById('modal-emp-dept').textContent  = req.employee_dept || '—';
            document.getElementById('modal-leave-type').textContent = req.leave_type || '';

            const startStr = req.start_date;
            const endStr   = req.end_date;
            document.getElementById('modal-duration').innerHTML = `<strong>${startStr}</strong> to <strong>${endStr}</strong> (${req.duration_days} ${req.duration_days > 1 ? 'days' : 'day'})`;
            document.getElementById('modal-reason').textContent = req.reason || '—';

            const attachVal = document.getElementById('modal-attachment');
            if (req.attachment) {
                attachVal.innerHTML = `<a href="${req.attachment}" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 600;"><i class="fas fa-paperclip"></i> View Attachment</a>`;
            } else {
                attachVal.textContent = 'None';
            }

            const badgeClass = `badge badge-${req.status.toLowerCase()}`;
            document.getElementById('modal-status-badge').innerHTML = `<span class="${badgeClass}">${req.status}</span>`;

            const commentGroup    = document.getElementById('comment-group');
            const commentDisplay  = document.getElementById('modal-admin-comment-display');
            const commentSections = document.querySelectorAll('.admin-comment-section');
            const approveBtn = document.getElementById('approve-btn');
            const rejectBtn  = document.getElementById('reject-btn');

            if (req.status === 'Pending') {
                commentGroup.style.display = 'block';
                commentSections.forEach(sec => sec.style.display = 'none');
                approveBtn.style.display = 'inline-flex';
                rejectBtn.style.display  = 'inline-flex';
                document.getElementById('admin_comment').value = '';
            } else {
                commentGroup.style.display = 'none';
                commentSections.forEach(sec => sec.style.display = 'block');
                commentDisplay.textContent = req.admin_comment || 'No feedback provided.';
                approveBtn.style.display = 'none';
                rejectBtn.style.display  = 'none';
            }

            modal.classList.add('show');
        }

        function closeRequestModal() {
            modal.classList.remove('show');
        }

        // Opens the styled confirmation modal
        function openConfirm(action) {
            _pendingAction = action;
            const req = _currentReq;
            const isApprove = action === 'Approved';

            document.getElementById('conf-icon').className   = 'm-icon ' + (isApprove ? 'approve' : 'reject');
            document.getElementById('conf-icon-i').className = 'fas ' + (isApprove ? 'fa-check' : 'fa-times');
            document.getElementById('conf-title').textContent = isApprove ? 'Approve Leave Request?' : 'Reject Leave Request?';
            document.getElementById('conf-msg').textContent   = isApprove
                ? 'This will approve the request and deduct from the employee\'s leave balance.'
                : 'This will reject the request. The employee will see this decision.';
            document.getElementById('conf-emp').textContent  = req.employee_name || '';
            document.getElementById('conf-type').textContent = req.leave_type || '';
            document.getElementById('conf-dur').textContent  = req.start_date + ' → ' + req.end_date + ' (' + req.duration_days + ' day' + (req.duration_days > 1 ? 's' : '') + ')';

            const doBtn = document.getElementById('conf-do-btn');
            doBtn.className = isApprove ? 'm-btn-approve' : 'm-btn-reject';
            doBtn.innerHTML = '<i class="fas ' + (isApprove ? 'fa-check' : 'fa-times') + '"></i> ' + (isApprove ? 'Yes, Approve' : 'Yes, Reject');

            document.getElementById('confirmOverlay').classList.add('show');
        }

        function closeConfirm() {
            document.getElementById('confirmOverlay').classList.remove('show');
            _pendingAction = null;
        }

        // Actually submits the form
        function doSubmitAction() {
            if (!_pendingAction) return;
            document.getElementById('modal-action-val').value = _pendingAction;
            document.getElementById('action-form').submit();
        }

        // Close details modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) closeRequestModal();
            if (event.target == document.getElementById('confirmOverlay')) closeConfirm();
        };
    </script>
</body>
</html>
