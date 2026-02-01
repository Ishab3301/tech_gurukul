<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$course_id = (int)($_POST['course_id'] ?? 0);

if ($course_id <= 0) {
    die('Invalid course selection.');
}

// Check if already enrolled or pending
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = ? AND course_id = ?");
$stmt->execute([$student_id, $course_id]);
if ($stmt->fetchColumn() > 0) {
    header("Location: student_dashboard.php?msg=Already requested or enrolled in this course.");
    exit;
}

// Insert enrollment request as pending
$stmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_id, enrolled_at, progress, status) VALUES (?, ?, NOW(), 0, 'pending')");
$stmt->execute([$student_id, $course_id]);

header("Location: student_dashboard.php?msg=Enrollment request submitted! Awaiting admin approval.");
exit;
