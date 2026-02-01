<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? null;

if (!$course_id) {
    die("Course ID missing!");
}

// Fetch course - this must come BEFORE using $course or generating UUID
$stmt = $pdo->prepare("SELECT title, price FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found!");
}

// Now safe to use $course['price'] and generate UUID
$base_transaction_uuid = 'TG-' . $course_id . '-' . $student_id . '-' . time();

// Generate signature server-side (correct & secure way)
$esewa_secret = '8gBm/:&EnhH.1/q';  // Official eSewa test secret key
$message = "total_amount={$course['price']},transaction_uuid={$base_transaction_uuid},product_code=EPAYTEST";
$signature = base64_encode(hash_hmac('sha256', $message, $esewa_secret, true));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay for <?= htmlspecialchars($course['title']) ?> - Tech Gurukul</title>
    <link rel="stylesheet" href="css/student_dashboard.css">
    <style>
        body { background:#f5f5f5; padding-top:90px; }
        .payment-container {
            max-width: 620px;
            margin: 40px auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #663399, #552288);
            color: white;
            padding: 35px;
            text-align: center;
        }
        .header h2 { margin:0; font-size:28px; }
        .header .price { font-size:34px; font-weight:bold; margin:15px 0; }

        .options { padding: 30px; }
        .method-option {
            margin-bottom: 25px;
        }
        .method-option label {
            display: block;
            padding: 22px;
            border: 2px solid #ddd;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 19px;
            font-weight: 600;
            text-align: center;
        }
        .method-option input[type="radio"] {
            margin-right: 14px;
            transform: scale(1.5);
        }
        .method-option label:hover,
        .method-option input:checked + label {
            border-color: #663399;
            background: #f8f4ff;
            box-shadow: 0 8px 20px rgba(102,51,153,0.15);
        }

        .payment-form { display:none; margin-top:30px; padding:35px; background:#f9f9f9; border-radius:14px; }
        .payment-form.active { display:block; }

        .esewa-theme {
            border-left: 8px solid #28a745;
            background: #f1fdf1;
        }

        .qr-section { text-align:center; margin:30px 0; }
        .qr-section img {
            width: 280px;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        button.submit-btn {
            width:100%;
            padding:18px;
            background:#28a745;
            color:white;
            border:none;
            border-radius:12px;
            font-size:18px;
            font-weight:bold;
            cursor:pointer;
            margin-top:20px;
        }
        button.submit-btn:hover { background:#218838; }

        .esewa-notice {
            text-align:center;
            font-size:16px;
            color:#28a745;
            font-weight:bold;
            margin:20px 0;
        }

        .info {
            font-size:14px;
            color:#666;
            text-align:center;
            margin-top:20px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="logo">
        <img src="uploads/logo.jpg" alt="Logo" style="height:40px; margin-right:8px;">
        Tech Gurukul
    </div>
    <ul>
        <li><a href="student_dashboard.php">Home</a></li>
        <li><a href="student_dashboard.php#courses">Courses</a></li>
        <li><a href="my_courses.php">My Courses</a></li>
        <li><a href="student_progress.php">My Profile</a></li>
        <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
</nav>

<div class="payment-container">
    <div class="header">
        <h2><?= htmlspecialchars($course['title']) ?></h2>
        <div class="price">Rs. <?= number_format($course['price']) ?></div>
        <p>Choose your payment method</p>
    </div>

    <div class="options">
        <!-- OFFLINE FIRST (Default selected) -->
        <div class="method-option">
            <label>
                <input type="radio" name="payment_method" value="offline" checked>
                Offline Payment (QR Code + Screenshot) – Recommended
            </label>
        </div>

        <!-- eSEWA SECOND -->
        <div class="method-option">
            <label>
                <input type="radio" name="payment_method" value="esewa">
                Pay with <strong>eSewa</strong>
            </label>
        </div>

        <!-- Offline QR Form (Default visible) -->
        <div id="offline-form" class="payment-form active">
            <div class="qr-section">
                <h3>Scan QR Code to Pay</h3>
                <img src="uploads/qr_code.png" alt="QR Code">
                <p><strong>Amount: Rs. <?= number_format($course['price']) ?></strong></p>
            </div>

            <form action="upload_payment.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="course_id" value="<?= $course_id ?>">
                <input type="hidden" name="payment_method" value="offline">

                <label><strong>Upload Payment Screenshot (Required):</strong></label>
                <input type="file" name="payment_screenshot" accept="image/*" required
                       style="width:100%; padding:15px; border:2px dashed #ccc; border-radius:12px; margin:15px 0;">

                <button type="submit" class="submit-btn">Submit Screenshot</button>
            </form>

            <p class="info">Admin will verify and activate your course within 24 hours.</p>
        </div>

        <!-- eSewa Form (Server-side signature - NO ES104 ERROR) -->
        <div id="esewa-form" class="payment-form esewa-theme">
            <div class="esewa-notice">
                Redirecting to eSewa test gateway...<br>
                <small>Test ID: 9806800001 • Password: Nepal@123 • OTP: 123456</small>
            </div>

            <form action="https://rc-epay.esewa.com.np/api/epay/main/v2/form" method="POST" id="esewa-real-form">
                <input type="hidden" name="amount" value="<?= $course['price'] ?>" required>
                <input type="hidden" name="tax_amount" value="0" required>
                <input type="hidden" name="total_amount" value="<?= $course['price'] ?>" required>
                <input type="hidden" name="transaction_uuid" value="<?= $base_transaction_uuid ?>" required>
                <input type="hidden" name="product_code" value="EPAYTEST" required>
                <input type="hidden" name="product_service_charge" value="0" required>
                <input type="hidden" name="product_delivery_charge" value="0" required>
                <input type="hidden" name="success_url"
       value="http://localhost/tech_gurukul/esewa_success.php">
<input type="hidden" name="failure_url"
       value="http://localhost/tech_gurukul/esewa_failure.php">
 <input type="hidden" name="signed_field_names" value="total_amount,transaction_uuid,product_code" required>
                <input type="hidden" name="signature" value="<?= $signature ?>" required>
            </form>

            <script>
                // Auto-submit when eSewa is selected
                document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        document.querySelectorAll('.payment-form').forEach(form => form.classList.remove('active'));
                        document.getElementById(this.value + '-form').classList.add('active');

                        if (this.value === 'esewa') {
                            setTimeout(() => {
                                document.getElementById('esewa-real-form').submit();
                            }, 1800);
                        }
                    });
                });
            </script>
        </div>
    </div>
</div>

</body>
</html>