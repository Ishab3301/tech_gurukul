<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$course_id = $_POST['course_id'] ?? null;

if (!$course_id || !isset($_FILES['payment_screenshot'])) {
    die("Invalid request.");
}

// ===============================
// 1) FILE UPLOAD HANDLING
// ===============================

$uploadDir = __DIR__ . '/uploads/payments/';

// Create folder if missing
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$file = $_FILES['payment_screenshot'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    die("Error uploading file.");
}

if (!in_array($file['type'], $allowedTypes)) {
    die("Invalid file type. Only JPG, PNG or GIF allowed.");
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('payment_', true) . '.' . $ext;
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    die("Failed to save uploaded file.");
}

// ===============================
// 2) AUTO-ACTIVATE ENROLLMENT
// ===============================

$stmt = $pdo->prepare("SELECT * FROM student_courses WHERE student_id = ? AND course_id = ?");
$stmt->execute([$student_id, $course_id]);
$enrollment = $stmt->fetch();

if ($enrollment) {

    // Update existing record
    $update = $pdo->prepare("
        UPDATE student_courses 
        SET 
            payment_status = 'paid',
            status = 'active',
            payment_method = 'screenshot',
            payment_screenshot = ?, 
            enrolled_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$filename, $enrollment['id']]);

} else {

    // Create new enrollment
    $insert = $pdo->prepare("
        INSERT INTO student_courses 
        (student_id, course_id, status, payment_status, payment_method, payment_screenshot, enrolled_at)
        VALUES (?, ?, 'active', 'paid', 'screenshot', ?, NOW())
    ");
    $insert->execute([$student_id, $course_id, $filename]);

}

// ===============================
// 3) REDIRECT TO COURSE VIEW
// ===============================

header("Location: course_view.php?course_id=" . $course_id . "&msg=Payment successful! Course unlocked.");
exit;

?>
