<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();

$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Calendar - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- FullCalendar CDN -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <style>
        #calendar {
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 15px;
            border-radius: 12px;
        }
        .fc-header-toolbar {
            margin-bottom: 20px !important;
        }
        .fc-toolbar-title {
            font-size: 1.3rem !important;
            font-weight: 700;
            color: var(--text);
        }
        .fc-button-primary {
            background-color: var(--primary) !important;
            border-color: var(--primary) !important;
        }
        .fc-button-primary:hover {
            opacity: 0.9;
        }
        .calendar-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-calendar-alt"></i> Leave Calendar</h2>
            <div class="user-info">
                <span>Logged in as: <strong><?php echo e($_SESSION['name']); ?></strong> (<?php echo e($role); ?>)</span>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="card">
                <div class="card-body">
                    <div id="calendar"></div>

                    <!-- Legend -->
                    <div class="calendar-legend">
                        <div class="legend-item"><div class="legend-color" style="background:#10b981;"></div> Vacation Leave</div>
                        <div class="legend-item"><div class="legend-color" style="background:#ef4444;"></div> Sick Leave</div>
                        <div class="legend-item"><div class="legend-color" style="background:#f59e0b;"></div> Emergency Leave</div>
                        <div class="legend-item"><div class="legend-color" style="background:#ec4899;"></div> Maternity Leave</div>
                        <div class="legend-item"><div class="legend-color" style="background:#06b6d4;"></div> Paternity Leave</div>
                        <div class="legend-item"><div class="legend-color" style="background:#6b7280;"></div> Bereavement Leave</div>
                        <div class="legend-item"><div class="legend-color" style="background:#8b5cf6;"></div> Study Leave</div>
                        <div class="legend-item"><div class="legend-color" style="background:#3b82f6;"></div> Compensatory Leave</div>
                        <div class="legend-item"><div class="legend-color" style="background:#64748b;"></div> Unpaid Leave</div>
                        <div class="legend-item"><div class="legend-color" style="background:#a855f7;"></div> Special Leave</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            events: 'get_leaves_feed.php',
            height: 'auto',
            firstDay: 1, // Start week on Monday
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                meridiem: false
            }
        });
        calendar.render();
    });
    </script>
</body>
</html>
