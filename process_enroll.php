<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $enroll_type = $_POST['enroll_type'] ?? '';

    if ($course_id <= 0 || !in_array($enroll_type, ['trial', 'full'])) {
        die("Invalid enrollment data.");
    }

    // Check if already enrolled or pending
    $stmt = $pdo->prepare("SELECT * FROM student_courses WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    if ($stmt->fetch()) {
        die("You have already applied or enrolled for this course.");
    }

    $status = 'pending';  // Waiting for admin approval
    $trial_end = ($enroll_type === 'trial') ? date('Y-m-d', strtotime('+7 days')) : null;

    $stmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_id, status, enrolled_at, trial_end) VALUES (?, ?, ?, NOW(), ?)");
    $stmt->execute([$student_id, $course_id, $status, $trial_end]);

    header('Location: student_dashboard.php?msg=applied');
    exit;
} else {
    header('Location: student_dashboard.php');
    exit;
}
