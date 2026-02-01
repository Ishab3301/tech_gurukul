<?php
session_start();
require 'db.php';

if ($_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$studentId = $_SESSION['user_id'];
$courseId = (int)$_GET['course_id'];

$stmt = $pdo->prepare("DELETE FROM student_courses WHERE student_id = ? AND course_id = ?");
$stmt->execute([$studentId, $courseId]);

header("Location: student_dashboard.php");
exit;
?>
