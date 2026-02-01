<?php
session_start();
require "db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: login.php");
    exit;
}

// Ensure ID is provided and is a valid number
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid student ID.");
}

$student_id = (int)$_GET['id'];

// Delete the student
$stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
if ($stmt->execute([$student_id])) {
    header("Location: manage_students.php?msg=Student deleted successfully");
    exit;
} else {
    die("Failed to delete student.");
}
