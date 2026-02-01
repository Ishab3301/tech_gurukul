<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: student_dashboard.php');
    exit;
}

$course_id = (int)($_POST['course_id'] ?? 0);
$rating    = (int)($_POST['rating'] ?? 0);
$comment   = trim($_POST['comment'] ?? '');

// === VALIDATION ===
if ($course_id <= 0) {
    $_SESSION['error'] = 'Please select a course.';           // ← FIXED HERE
} elseif ($rating < 1 || $rating > 5) {
    $_SESSION['error'] = 'Please select a rating from 1 to 5 stars.';
} elseif (empty($comment) || strlen($comment) < 10) {
    $_SESSION['error'] = 'Review must be at least 10 characters.';
}

if (isset($_SESSION['error'])) {
    header('Location: student_dashboard.php#submit-review');
    exit;
}

// === CHECK ENROLLMENT ===
$stmt = $pdo->prepare("SELECT 1 FROM student_courses WHERE student_id = ? AND course_id = ? AND status = 'approved'");
$stmt->execute([$student_id, $course_id]);
if (!$stmt->fetch()) {
    $_SESSION['error'] = 'You can only review courses you are fully enrolled in.';
    header('Location: student_dashboard.php#submit-review');
    exit;
}

// === PREVENT DUPLICATE REVIEW ===
$check = $pdo->prepare("SELECT 1 FROM feedback WHERE student_id = ? AND course_id = ?");
$check->execute([$student_id, $course_id]);
if ($check->fetch()) {
    $_SESSION['error'] = 'You have already reviewed this course!';
    header('Location: student_dashboard.php#submit-review');
    exit;
}

// === INSERT REVIEW AS PENDING ===
try {
    $insert = $pdo->prepare("
        INSERT INTO feedback 
        (student_id, course_id, rating, comment, approved, created_at) 
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $insert->execute([$student_id, $course_id, $rating, $comment]);

    // === TRIGGER ACHIEVEMENT (First Review) ===
    if (file_exists('includes/achievement_utils.php')) {
        require_once 'includes/achievement_utils.php';
        if (function_exists('checkAndUnlockAchievements')) {
            checkAndUnlockAchievements($student_id, $pdo);
        }
    }

    $_SESSION['success'] = 'Your review has been submitted and is waiting for admin approval. Thank you!';

} catch (Exception $e) {
    $_SESSION['error'] = 'Something went wrong. Please try again later.';
}

header('Location: student_dashboard.php#submit-review');
exit;