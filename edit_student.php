<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['user_name']);
$message = "";
$messageType = "";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $message = "Invalid student ID.";
    $messageType = "error";
} else {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $message = "Student not found.";
        $messageType = "error";
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
            $message = "Name can only contain letters and spaces.";
            $messageType = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
            $messageType = "error";
        } else {
            $update = $pdo->prepare("UPDATE students SET name = ?, email = ? WHERE id = ?");
            if ($update->execute([$name, $email, $id])) {
                $message = "Student <strong>" . htmlspecialchars($name) . "</strong> updated successfully!";
                $messageType = "success";
                // Refresh student data
                $stmt->execute([$id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = "Email already exists or update failed.";
                $messageType = "error";
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
    <title>Edit Student - Tech Gurukul</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin:0; background:#f4f6f9; }
        .main-content { margin-left: 300px; padding: 40px; }

        .welcome {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 35px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(102,51,153,0.3);
        }
        .welcome h1 { margin:0; font-size:34px; }
        .welcome p { margin:12px 0 0; font-size:19px; opacity:0.95; }

        .form-container {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            max-width: 600px;
            margin: 0 auto;
        }
        .form-container h3 {
            color: #663399;
            text-align: center;
            margin-bottom: 35px;
            font-size: 28px;
        }

        label {
            display: block;
            margin: 20px 0 10px;
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #663399;
            box-shadow: 0 0 0 5px rgba(102,51,153,0.15);
        }

        .submit-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 18px 40px;
            border: none;
            border-radius: 50px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 30px;
            transition: all 0.3s;
        }
        .submit-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(40,167,69,0.4);
        }

        .message {
            padding: 18px;
            border-radius: 12px;
            text-align: center;
            font-weight: bold;
            margin: 25px 0;
            font-size: 16px;
        }
        .success { background:#d4edda; color:#155724; border:2px solid #c3e6cb; }
        .error   { background:#f8d7da; color:#721c24; border:2px solid #f5c6cb; }

        /* SAME EPIC SIDEBAR */
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

        .back-link {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: #663399;
            font-weight: bold;
            text-decoration: none;
            font-size: 16px;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<!-- SAME BEAUTIFUL SIDEBAR -->
<div class="sidebar">
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="create_students.php">Add Student</a>
    <a href="manage_students.php" class="active">Manage Students</a>
    <a href="admin_courses.php">Manage Courses</a>
    <a href="manage_enrollment.php">Manage Enrollment</a>
    <a href="admin_feedback.php">Manage Feedback</a>
    <a href="manage_partnerships.php">Partnerships</a>
    <a href="manage_payments.php">Payments</a>
    <a href="publish_message.php">Messages</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">

    <div class="welcome">
        <h1>Edit Student Details</h1>
        <p>Update student information securely</p>
    </div>

    <div class="form-container">

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>

        <?php if (isset($student)): ?>
            <form method="POST">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($student['name']) ?>" required 
                       placeholder="Enter full name">

                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required 
                       placeholder="student@example.com">

                <button type="submit" class="submit-btn">
                    Update Student
                </button>
            </form>

            <a href="manage_students.php" class="back-link">Back to Manage Students</a>
        <?php else: ?>
            <p style="text-align:center; color:#dc3545; font-size:18px;">
                Student not found or invalid ID.
            </p>
            <a href="manage_students.php" class="back-link">Back to Students List</a>
        <?php endif; ?>

    </div>
</div>

</body>
</html>