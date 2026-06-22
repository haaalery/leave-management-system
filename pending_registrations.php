<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

// Fetch all pending registrations
$stmt = $pdo->query("SELECT * FROM users WHERE status = 'Pending' ORDER BY id ASC");
$pending_users = $stmt->fetchAll();

// Calculate counts for summary cards
$pending_count = count($pending_users);
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Active'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Registrations - LMS</title>
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
            max-width: 500px;
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
            grid-template-columns: 120px 1fr;
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
            <h2><i class="fas fa-user-clock"></i> Pending User Registrations</h2>
            <div class="user-info">
                <span>Logged in as: <strong><?php echo e($_SESSION['name']); ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- Summary Stats -->
            <div class="stats-container" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Total Users</strong>
                        <div class="value"><?php echo $total_users; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.08); color: #3b82f6;">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Active Users</strong>
                        <div class="value"><?php echo $active_users; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.08); color: #10b981;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Pending Approvals</strong>
                        <div class="value"><?php echo $pending_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.08); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Registration Requests</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">Click on any row to view full details and perform approval actions.</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>First Name</th>
                                    <th>Middle Name</th>
                                    <th>Last Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_users)): ?>
                                    <tr><td colspan="6" style="text-align: center; padding: 30px;">No pending user registrations.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pending_users as $pu): ?>
                                        <tr class="clickable-row" onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($pu), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <td><strong><?php echo e($pu['first_name']); ?></strong></td>
                                            <td><?php echo e($pu['middle_name'] ?: '—'); ?></td>
                                            <td><strong><?php echo e($pu['last_name']); ?></strong></td>
                                            <td><?php echo e($pu['email']); ?></td>
                                            <td><?php echo e($pu['department'] ?: '—'); ?></td>
                                            <td><?php echo e($pu['position'] ?: '—'); ?></td>
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

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Verify User Registration</h3>
                <button class="close-btn" onclick="closeReviewModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="info-grid">
                    <div class="info-label">First Name:</div>
                    <div class="info-value" id="modal-first-name"></div>
                    
                    <div class="info-label">Middle Name:</div>
                    <div class="info-value" id="modal-middle-name"></div>
                    
                    <div class="info-label">Last Name:</div>
                    <div class="info-value" id="modal-last-name"></div>
                    
                    <div class="info-label">Email:</div>
                    <div class="info-value" id="modal-email"></div>
                    
                    <div class="info-label">Department:</div>
                    <div class="info-value" id="modal-department"></div>
                    
                    <div class="info-label">Position:</div>
                    <div class="info-value" id="modal-position"></div>
                </div>
                
                <form id="action-form" action="activate_user.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="user_id" id="modal-user-id" value="">
                    <input type="hidden" name="action" id="modal-action-val" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background: var(--text-muted);" onclick="closeReviewModal()">Cancel</button>
                <button type="button" class="btn" style="background: var(--danger);" onclick="submitAction('Rejected')">Reject</button>
                <button type="button" class="btn" style="background: var(--success);" onclick="submitAction('Active')">Approve & Activate</button>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('reviewModal');
        
        function openReviewModal(user) {
            document.getElementById('modal-user-id').value = user.id;
            document.getElementById('modal-first-name').textContent = user.first_name || '';
            document.getElementById('modal-middle-name').textContent = user.middle_name || '—';
            document.getElementById('modal-last-name').textContent = user.last_name || '';
            document.getElementById('modal-email').textContent = user.email || '';
            document.getElementById('modal-department').textContent = user.department || '—';
            document.getElementById('modal-position').textContent = user.position || '—';
            
            modal.classList.add('show');
        }

        function closeReviewModal() {
            modal.classList.remove('show');
        }

        function submitAction(status) {
            if (status === 'Rejected' && !confirm('Are you sure you want to reject this registration request?')) {
                return;
            }
            document.getElementById('modal-action-val').value = status;
            document.getElementById('action-form').submit();
        }

        // Close when clicking outside of modal
        window.onclick = function(event) {
            if (event.target == modal) {
                closeReviewModal();
            }
        }
    </script>
</body>
</html>
