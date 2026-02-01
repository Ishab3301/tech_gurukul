<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// === ALL COUNTS ===
$totalAdmins     = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$totalStudents   = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalUsers      = $totalAdmins + $totalStudents;

$totalCourses    = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalFeedback   = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$approvedFeedback = $pdo->query("SELECT COUNT(*) FROM feedback WHERE approved = 1")->fetchColumn();

// Active (paid) enrollments
$totalPaidEnrollments = $pdo->query("
    SELECT COUNT(*) 
    FROM student_courses 
    WHERE payment_status = 'paid'
")->fetchColumn();

// Total Revenue
$totalRevenue = $pdo->query("
    SELECT COALESCE(SUM(c.price), 0) 
    FROM student_courses sc
    JOIN courses c ON sc.course_id = c.id
    WHERE sc.payment_status = 'paid'
")->fetchColumn();

// Today's Payments (new revenue today)
$todayRevenue = $pdo->query("
    SELECT COALESCE(SUM(c.price), 0) 
    FROM student_courses sc
    JOIN courses c ON sc.course_id = c.id
    WHERE sc.payment_status = 'paid' 
      AND DATE(sc.enrolled_at) = CURDATE()
")->fetchColumn();

// Today's new enrollments
$todayEnrollments = $pdo->query("
    SELECT COUNT(*) 
    FROM student_courses 
    WHERE payment_status = 'paid' 
      AND DATE(enrolled_at) = CURDATE()
")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Tech Gurukul</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin:0; background:#f4f6f9; }
        .main-content { margin-left: 300px; padding: 40px; }
        h2 { color:#663399; font-size:28px; margin-bottom:30px; }

        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 6px solid #663399;
        }
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(102, 51, 153, 0.2);
        }
        .card a { text-decoration: none; color: inherit; display: block; }
        .card h4 {
            margin: 0 0 15px 0;
            color: #663399;
            font-size: 16px;
            font-weight: 600;
        }
        .card p {
            font-size: 36px;
            font-weight: bold;
            margin: 0;
            color: #333;
        }
        .card small { color:#888; font-size:14px; }

        /* Color variations */
        .card:nth-child(1) { border-left-color: #007bff; }
        .card:nth-child(2) { border-left-color: #28a745; }
        .card:nth-child(3) { border-left-color: #17a2b8; }
        .card:nth-child(4) { border-left-color: #ffc107; }
        .card:nth-child(5) { border-left-color: #fd7e14; }
        .card:nth-child(6) { border-left-color: #dc3545; }

        .welcome {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(102,51,153,0.3);
        }
        .welcome h1 { margin:0; font-size:32px; }
        .welcome p { margin:10px 0 0; font-size:18px; opacity:0.9; }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0; top: 0; width: 300px; height: 100vh;
            background: linear-gradient(135deg, #663399, #552288);
            padding-top: 30px;
            box-shadow: 5px 0 15px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        .sidebar a {
            display: block;
            color: white;
            padding: 18px 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            border-left: 5px solid transparent;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.15);
            border-left-color: #ffc107;
            padding-left: 40px;
        }
        .sidebar .logout {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: #dc3545;
            text-align: center;
            padding: 20px;
            font-weight: bold;
        }
        .sidebar .logout:hover { background:#c82333; }
        /*          Floating Chat Bubble         */
        .chat-bubble {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            box-shadow: 0 8px 25px rgba(102, 51, 153, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 999;
            text-decoration: none;
            color: white;
        }

        .chat-bubble:hover {
            transform: scale(1.15) translateY(-5px);
            box-shadow: 0 15px 35px rgba(102, 51, 153, 0.5);
        }

        .chat-bubble svg {
            width: 38px;
            height: 38px;
            fill: white;
        }

        .chat-bubble .emoji {
            font-size: 42px;
            line-height: 1;
        }

        /* Optional: small badge / notification dot */
        .chat-bubble::after {
            content: '';
            position: absolute;
            top: 8px;
            right: 8px;
            width: 14px;
            height: 14px;
            background: #ff4757;
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            display: none;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="admin_dashboard.php" class="active">Dashboard</a>
    <a href="create_students.php">Add Student</a>
    <a href="manage_students.php">Manage Students</a>
    <a href="admin_courses.php">Manage Courses</a>
    <a href="manage_enrollment.php">Manage Enrollment</a>
    <a href="admin_feedback.php">Manage Reviews</a>
    <a href="manage_partnerships.php">Manage Partnerships</a>
    <a href="manage_payments.php">Payments</a>
    <a href="publish_message.php">Publish Messages</a>
    <a href=".php">Chat Here</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">

    <div class="welcome">
        <h1>Welcome back, <?= $adminName ?>!</h1>
        <p>Managing <strong>Tech Gurukul</strong> — Empowering students with modern computer education</p>
    </div>

    <!-- Stats Cards -->
    <div class="card-container">
        <div class="card">
            <a href="manage_students.php">
                <h4>Total Students</h4>
                <p><?= $totalStudents ?></p>
                <small>Registered learners</small>
            </a>
        </div>

        <div class="card">
            <a href="admin_courses.php">
                <h4>Total Courses</h4>
                <p><?= $totalCourses ?></p>
                <small>Available programs</small>
            </a>
        </div>

        <div class="card">
            <a href="manage_payments.php">
                <h4>Total Revenue</h4>
                <p>Rs. <?= number_format($totalRevenue) ?></p>
                <small>All time earnings</small>
            </a>
        </div>

        <div class="card">
            <a href="manage_enrollment.php">
                <h4>Active Enrollments</h4>
                <p><?= $totalPaidEnrollments ?></p>
                <small>Students currently learning</small>
            </a>
        </div>

        <div class="card">
            <a href="admin_feedback.php">
                <h4>Total Reviews</h4>
                <p><?= $totalFeedback ?></p>
                <small><?= $approvedFeedback ?> approved & shown</small>
            </a>
        </div>

        <div class="card">
            <a href="manage_payments.php">
                <h4>Today's Revenue</h4>
                <p>Rs. <?= number_format($todayRevenue) ?></p>
                <small>New earnings today</small>
            </a>
        </div>

        <div class="card">
            <a href="manage_enrollment.php">
                <h4>Today's Enrollments</h4>
                <p><?= $todayEnrollments ?></p>
                <small>New students today</small>
            </a>
        </div>

        <div class="card">
            <h4>Total Users</h4>
            <p><?= $totalUsers ?></p>
            <small>Admins + Students</small>
        </div>
    </div>

    <!-- ─────────────────────────────── -->
<!--        Floating Chat Icon      -->
<!-- ─────────────────────────────── -->
<a href="chatdemoone/index.php" class="chat-bubble" title="Open Chat">
    <!-- Option 1: SVG chat icon (recommended - clean & scalable) -->
    <svg viewBox="0 0 24 24">
        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
    </svg>

    <!-- Option 2: Simple emoji version (if you prefer no SVG) -->
    <!-- <span class="emoji">💬</span> -->
</a>

</div>
</body>
</html>