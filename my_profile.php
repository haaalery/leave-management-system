<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkEmployee();

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT u.*, d.name AS dept_name, d.description AS dept_desc,
           p.title AS pos_title, p.job_description AS pos_jd
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN positions   p ON u.position_id   = p.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-hero {
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            border-radius: 18px;
            padding: 36px 40px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 28px;
            margin-bottom: 28px;
            box-shadow: 0 8px 32px rgba(79,70,229,0.25);
        }
        .profile-avatar {
            width: 90px; height: 90px; border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.4rem; color: #fff;
            border: 3px solid rgba(255,255,255,0.4);
            flex-shrink: 0;
        }
        .profile-hero h2 { font-size: 1.6rem; font-weight: 700; margin-bottom: 4px; }
        .profile-hero .subtitle { font-size: 0.95rem; opacity: 0.85; }
        .profile-hero .badge-gender {
            display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.2); border-radius: 20px;
            padding: 3px 12px; font-size: 0.82rem; margin-top: 8px;
        }
        .info-section { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .info-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: 14px; padding: 22px 24px;
            box-shadow: var(--shadow);
        }
        .info-card h4 {
            font-size: 0.82rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .info-row { display: flex; flex-direction: column; margin-bottom: 14px; }
        .info-row:last-child { margin-bottom: 0; }
        .info-row .label { font-size: 0.78rem; color: var(--text-muted); font-weight: 600; margin-bottom: 3px; }
        .info-row .value { font-size: 0.95rem; color: var(--text); font-weight: 500; }
        .jd-box {
            background: #f8fafc; border: 1px solid var(--border);
            border-radius: 10px; padding: 14px 16px;
            font-size: 0.88rem; color: var(--text);
            white-space: pre-wrap; line-height: 1.7;
        }
        @media (max-width: 640px) {
            .info-section { grid-template-columns: 1fr; }
            .profile-hero { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-user-circle"></i> My Profile</h2>
            <div class="user-info"><span>Welcome, <strong><?php echo e($_SESSION['name']); ?></strong></span></div>
        </div>

        <div class="content-wrapper">
            <!-- Hero Card -->
            <div class="profile-hero">
                <div class="profile-avatar">
                    <i class="fas <?php echo $user['gender'] === 'Female' ? 'fa-user-tie' : 'fa-user-tie'; ?>"></i>
                </div>
                <div>
                    <h2><?php echo e($user['name']); ?></h2>
                    <div class="subtitle">
                        <?php echo e($user['pos_title'] ?? ($user['position'] ?? 'No position assigned')); ?>
                        <?php if ($user['dept_name'] ?? $user['department']): ?>
                        &nbsp;·&nbsp; <?php echo e($user['dept_name'] ?? $user['department']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="badge-gender">
                        <i class="fas <?php echo $user['gender'] === 'Female' ? 'fa-venus' : 'fa-mars'; ?>"></i>
                        <?php echo e($user['gender']); ?>
                    </div>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="info-section">
                <!-- Personal Info -->
                <div class="info-card">
                    <h4><i class="fas fa-id-card" style="color: var(--primary);"></i> Personal Information</h4>
                    <div class="info-row">
                        <span class="label">Full Name</span>
                        <span class="value"><?php echo e($user['name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email Address</span>
                        <span class="value"><?php echo e($user['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Gender</span>
                        <span class="value">
                            <i class="fas <?php echo $user['gender'] === 'Female' ? 'fa-venus' : 'fa-mars'; ?>"
                               style="color: <?php echo $user['gender'] === 'Female' ? '#ec4899' : '#06b6d4'; ?>"></i>
                            <?php echo e($user['gender']); ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Account Status</span>
                        <span class="value">
                            <?php 
                            $status_class = 'pending';
                            if ($user['status'] === 'Active') $status_class = 'approved';
                            if ($user['status'] === 'Rejected') $status_class = 'rejected';
                            ?>
                            <span class="badge badge-<?php echo $status_class; ?>"><?php echo e($user['status']); ?></span>
                        </span>
                    </div>
                </div>

                <!-- Department Info -->
                <div class="info-card">
                    <h4><i class="fas fa-building" style="color: #8b5cf6;"></i> Department & Position</h4>
                    <?php if ($user['dept_name'] || $user['department']): ?>
                    <div class="info-row">
                        <span class="label">Department</span>
                        <span class="value"><?php echo e($user['dept_name'] ?? $user['department']); ?></span>
                    </div>
                    <?php if ($user['dept_desc']): ?>
                    <div class="info-row">
                        <span class="label">Department Description</span>
                        <span class="value" style="font-size: 0.87rem; color: var(--text-muted);"><?php echo e($user['dept_desc']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="label">Job Title</span>
                        <span class="value"><?php echo e($user['pos_title'] ?? $user['position'] ?? '—'); ?></span>
                    </div>
                    <?php else: ?>
                    <p style="color: var(--text-muted); font-style: italic; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> No department assigned yet. Contact your administrator.
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Job Description -->
            <?php if ($user['pos_jd']): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-alt"></i> Job Description — <?php echo e($user['pos_title']); ?></h3>
                </div>
                <div class="card-body">
                    <div class="jd-box"><?php echo e($user['pos_jd']); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <p style="text-align: center; color: var(--text-muted); font-size: 0.82rem; margin-top: 20px;">
                <i class="fas fa-lock"></i> Profile information is managed by your administrator. Contact HR to request changes.
            </p>
        </div>
    </div>
</body>
</html>
