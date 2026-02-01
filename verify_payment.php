<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enrollment_id = $_POST['enrollment_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if (!$enrollment_id || !in_array($action, ['approve', 'reject'])) {
        $_SESSION['error'] = "Invalid request.";
        header('Location: manage_payments.php');
        exit;
    }

    if ($action === 'approve') {
        // Mark payment and enrollment as approved
        $stmt = $pdo->prepare("UPDATE student_courses SET payment_status = 'approved', status = 'approved' WHERE id = ?");
        $stmt->execute([$enrollment_id]);
        $_SESSION['success'] = "Payment approved successfully.";
    } else if ($action === 'reject') {
        // Mark payment as rejected, optionally keep enrollment or remove it
        // Here, let's update payment_status only to rejected but keep enrollment record
        $stmt = $pdo->prepare("UPDATE student_courses SET payment_status = 'rejected' WHERE id = ?");
        $stmt->execute([$enrollment_id]);
        $_SESSION['success'] = "Payment rejected.";
    }

    header('Location: manage_payments.php');
    exit;
} else {
    header('Location: manage_payments.php');
    exit;
}
?>
