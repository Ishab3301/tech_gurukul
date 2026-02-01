<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];

/* --------------------------------------------------
   STEP 1: Read & decode eSewa v2 response
-------------------------------------------------- */
if (!isset($_GET['data'])) {
    $_SESSION['error_msg'] = "Invalid payment response from eSewa.";
    header("Location: student_dashboard.php");
    exit;
}

$decoded = base64_decode($_GET['data'], true);
if ($decoded === false) {
    $_SESSION['error_msg'] = "Invalid payment data format.";
    header("Location: student_dashboard.php");
    exit;
}

$response = json_decode($decoded, true);
if (!$response || !isset($response['status'])) {
    $_SESSION['error_msg'] = "Invalid response from eSewa.";
    header("Location: student_dashboard.php");
    exit;
}

/* --------------------------------------------------
   STEP 2: Validate status
-------------------------------------------------- */
if ($response['status'] !== 'COMPLETE') {
    $_SESSION['error_msg'] = "Payment not completed: " . htmlspecialchars($response['status']);
    header("Location: student_dashboard.php");
    exit;
}

/* --------------------------------------------------
   STEP 3: Extract values
-------------------------------------------------- */
$transaction_uuid     = $response['transaction_uuid'] ?? null;
$refId                = $response['transaction_code'] ?? null;
$total_amount         = $response['total_amount'] ?? null;
$signed_field_names   = $response['signed_field_names'] ?? null;
$received_signature   = $response['signature'] ?? null;

if (!$transaction_uuid || !$refId || !$total_amount || !$signed_field_names || !$received_signature) {
    $_SESSION['error_msg'] = "Missing required payment details.";
    header("Location: student_dashboard.php");
    exit;
}

/* --------------------------------------------------
   STEP 4: Verify signature (Security Critical)
-------------------------------------------------- */
$esewa_secret = '8gBm/:&EnhH.1/q';  // eSewa test secret key

$message = "transaction_code={$refId},status=COMPLETE,total_amount={$total_amount},transaction_uuid={$transaction_uuid},product_code=EPAYTEST,signed_field_names={$signed_field_names}";

$expected_signature = base64_encode(hash_hmac('sha256', $message, $esewa_secret, true));

if (!hash_equals($expected_signature, $received_signature)) {
    error_log("eSewa signature verification failed | Ref: $refId | UUID: $transaction_uuid | User: $user_id");
    $_SESSION['error_msg'] = "Payment verification failed. Please contact support.";
    header("Location: student_dashboard.php");
    exit;
}

/* --------------------------------------------------
   STEP 5: Extract course_id from UUID (TG-courseId-userId-time)
-------------------------------------------------- */
$parts = explode('-', $transaction_uuid);
if (count($parts) < 4 || $parts[0] !== 'TG') {
    $_SESSION['error_msg'] = "Invalid payment reference.";
    header("Location: student_dashboard.php");
    exit;
}
$course_id = (int)$parts[1];

/* --------------------------------------------------
   STEP 6: Activate enrollment (insert or update safely)
-------------------------------------------------- */
// First check if enrollment exists
$check = $pdo->prepare("SELECT payment_status FROM student_courses WHERE student_id = ? AND course_id = ?");
$check->execute([$user_id, $course_id]);
$existing = $check->fetch();

if ($existing) {
    if ($existing['payment_status'] === 'paid') {
        // Already paid — just redirect
        $_SESSION['success_msg'] = "Course already activated! Enjoy learning.";
    } else {
        // Update to paid
        $update = $pdo->prepare("
            UPDATE student_courses 
            SET payment_status = 'paid', payment_method = 'esewa' 
            WHERE student_id = ? AND course_id = ?
        ");
        $update->execute([$user_id, $course_id]);
        $_SESSION['success_msg'] = "Payment successful! 🎉 Your course is now active.";
    }
} else {
    // Create new enrollment
    $insert = $pdo->prepare("
        INSERT INTO student_courses (student_id, course_id, payment_status, payment_method, enrolled_at) 
        VALUES (?, ?, 'paid', 'esewa', NOW())
    ");
    $insert->execute([$user_id, $course_id]);
    $_SESSION['success_msg'] = "Payment successful! 🎉 Welcome to your new course.";
}

/* --------------------------------------------------
   STEP 7: Redirect to course
-------------------------------------------------- */
header("Location: course_view.php?course_id=" . $course_id);
exit;
?>