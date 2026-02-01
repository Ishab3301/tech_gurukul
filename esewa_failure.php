<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Default message
$message = "Your payment could not be completed.";
$course_id = null;

// Try to extract info from eSewa response (if available)
if (isset($_GET['data'])) {
    $decoded = base64_decode($_GET['data'], true);
    if ($decoded !== false) {
        $response = json_decode($decoded, true);
        if ($response && isset($response['status'])) {
            $status = $response['status'];
            if ($status === 'User canceled') {
                $message = "You cancelled the payment. No charges were made.";
            } elseif ($status === 'Pending') {
                $message = "Payment is pending. Please complete it in eSewa app.";
            } else {
                $message = "Payment failed. Status: " . htmlspecialchars($status);
            }

            // Try to get course_id from transaction_uuid
            if (isset($response['transaction_uuid'])) {
                $parts = explode('-', $response['transaction_uuid']);
                if (count($parts) >= 4 && $parts[0] === 'TG') {
                    $course_id = $parts[1];
                }
            }
        }
    }
}

// Fallback: try to get course_id from query string (in case of direct failure URL)
if (!$course_id && isset($_GET['course_id'])) {
    $course_id = $_GET['course_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - Tech Gurukul</title>
    <link rel="stylesheet" href="css/student_dashboard.css">
    <style>
        body { 
            background: #f5f5f5; 
            padding-top: 90px; 
            font-family: 'Poppins', sans-serif;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
        }
        .icon {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        h1 {
            color: #dc3545;
            font-size: 32px;
            margin-bottom: 15px;
        }
        p {
            color: #666;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 14px 30px;
            background: #663399;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            font-size: 18px;
            transition: 0.3s;
        }
        .btn:hover {
            background: #552288;
            transform: translateY(-3px);
        }
        .dashboard-link {
            margin-top: 20px;
            display: block;
            color: #663399;
            text-decoration: none;
            font-size: 16px;
        }
        .dashboard-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<!-- Navbar (keep consistent) -->
<nav class="navbar">
    <div class="logo">
        <img src="uploads/logo.jpg" alt="Logo" style="height:40px; margin-right:8px;">
        Tech Gurukul
    </div>
    <ul>
        <li><a href="student_dashboard.php">Home</a></li>
        <li><a href="student_dashboard.php#courses">Courses</a></li>
        <li><a href="my_courses.php">My Courses</a></li>
        <li><a href="student_progress.php">My Profile</a></li>
        <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="icon">✗</div>
    <h1>Payment Failed</h1>
    <p><?= nl2br(htmlspecialchars($message)) ?></p>

    <?php if ($course_id): ?>
        <a href="payment.php?course_id=<?= $course_id ?>" class="btn">
            Try Payment Again
        </a>
        <br><br>
    <?php endif; ?>

    <a href="student_dashboard.php" class="dashboard-link">
        ← Back to Dashboard
    </a>
</div>

</body>
</html>