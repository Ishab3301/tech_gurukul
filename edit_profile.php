<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$student_name = htmlspecialchars($_SESSION['user_name']);

// Handle profile + password update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update name + email
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);

    if ($name && $email) {
        $stmt = $pdo->prepare("UPDATE students SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $student_id]);
        $_SESSION['user_name'] = $name;
    }

    // Password update
    if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {

        $stmt = $pdo->prepare("SELECT password FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $hashed = $stmt->fetchColumn();

        if (password_verify($_POST['current_password'], $hashed)) {

            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $new_hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $updatePwd = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
                $updatePwd->execute([$new_hashed, $student_id]);
                $message = "Password changed successfully.";
            } else {
                $message = "New passwords do not match.";
            }

        } else {
            $message = "Current password is incorrect.";
        }

    }

    header("Location: student_progress.php?msg=Profile updated successfully");
    exit;
}


// Fetch student
$student = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$student->execute([$student_id]);
$student = $student->fetch(PDO::FETCH_ASSOC);

// Fetch courses
$enrollments = $pdo->prepare("
    SELECT sc.*, 
           c.title,
           c.price,
           CONCAT(c.duration_value, ' ', c.duration_type) AS duration
    FROM student_courses sc
    JOIN courses c ON sc.course_id = c.id
    WHERE sc.student_id = ? AND sc.status = 'approved'
");

$enrollments->execute([$student_id]);
$courses = $enrollments->fetchAll(PDO::FETCH_ASSOC);


// Progress calculator
function calculateProgress($enrolledAt) {
    $start = new DateTime($enrolledAt);
    $now = new DateTime();
    $diff = $start->diff($now);
    $daysPassed = $diff->days;
    $totalDays = 180;
    return min(100, round(($daysPassed / $totalDays) * 100));
}
?>
<?php if (isset($_GET['msg'])): ?>
    <div style="background: #e6f9e6; border: 1px solid #28a745; padding: 10px; border-radius: 5px; color: #155724; margin-bottom: 15px;">
        <?= htmlspecialchars($_GET['msg']) ?>
    </div>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile & Progress</title>
    <link rel="stylesheet" href="css/student_dashboard.css">
    <style>
        .profile-section, .progress-section {
            background: #fff;
            padding: 20px;
            margin: 20px auto;
            border-radius: 10px;
            max-width: 900px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .profile-info p { margin: 8px 0; }
        .progress-bar {
            height: 18px;
            background: #ccc;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        .progress-fill {
            height: 100%;
            background: #28a745;
            width: 0;
            transition: width 0.5s ease-in-out;
        }
        .certificate-btn {
            background: #ffc107;
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            margin-left: 10px;
        }
        .certificate-btn:disabled {
            background: #ccc;
            color: #666;
        }
        .download-btn {
            background: #17a2b8;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
        }
        form.update-profile input[type="text"],
        form.update-profile input[type="email"] {
            padding: 8px;
            margin-bottom: 10px;
            display: block;
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        form.update-profile button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
        }
        .update-profile h3 {
            margin-top: 30px;
            font-size: 1.2em;
            color: #444;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .password-section {
            margin-top: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
        }
        .password-section label { font-weight: bold; margin-top: 10px; display: block; }
        .password-section input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo">
        <img src="uploads/logo.jpg" alt="Logo" style="height: 40px; vertical-align: middle; margin-right: 8px;">
        <span style="font-weight: bold; font-size: 1.2em;">Tech Gurukul</span>
    </div>
    <ul>
        <li><a href="student_dashboard.php">Home</a></li>
        <li><a href="student_progress.php">My Progress</a></li>
        <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
</nav>

<div class="profile-section">
    <h2>My Profile</h2>
    <form class="update-profile" method="POST">
        <label>Name:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($student['name']) ?>" required>

        <label>Email:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>

        <h3>Change Password</h3>

        <div class="password-section">
            <label>Current Password:</label>
            <input type="password" name="current_password" placeholder="Enter current password">

            <label>New Password:</label>
            <input type="password" name="new_password" placeholder="Enter new password">

            <label>Confirm New Password:</label>
            <input type="password" name="confirm_password" placeholder="Confirm new password">
        </div>

        <button type="submit">Save Changes</button>
    </form>
</div>

</body>
</html>
