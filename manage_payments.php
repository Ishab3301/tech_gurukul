<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Handle manual payment addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $course_id = (int)($_POST['course_id'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? '');
    $payment_screenshot = null;

    if (!$student_id || !$course_id || !$payment_method) {
        $error = "Please fill all required fields.";
    } else {
        // Handle optional screenshot upload
        if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === 0) {
            $uploadDir = "uploads/payments/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $ext = strtolower(pathinfo($_FILES["payment_screenshot"]["name"], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) {
                $error = "Invalid file type. Only images allowed.";
            } elseif ($_FILES["payment_screenshot"]["size"] > 5000000) {
                $error = "File too large. Max 5MB.";
            } else {
                $newName = uniqid('pay_') . '.' . $ext;
                $dest = $uploadDir . $newName;
                if (move_uploaded_file($_FILES["payment_screenshot"]["tmp_name"], $dest)) {
                    $payment_screenshot = $newName;
                } else {
                    $error = "Failed to upload screenshot.";
                }
            }
        }

        if (!$error) {
            // Check if enrollment exists
            $check = $pdo->prepare("SELECT id FROM student_courses WHERE student_id = ? AND course_id = ?");
            $check->execute([$student_id, $course_id]);
            $enrollment = $check->fetch();

            if ($enrollment) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE student_courses 
                                       SET payment_method = ?, payment_screenshot = ?, payment_status = 'paid' 
                                       WHERE id = ?");
                $stmt->execute([$payment_method, $payment_screenshot, $enrollment['id']]);
            } else {
                // Create new
                $stmt = $pdo->prepare("INSERT INTO student_courses 
                                       (student_id, course_id, enrolled_at, payment_method, payment_screenshot, payment_status) 
                                       VALUES (?, ?, NOW(), ?, ?, 'paid')");
                $stmt->execute([$student_id, $course_id, $payment_method, $payment_screenshot]);
            }
            $success = "Payment recorded successfully! Course activated.";
        }
    }
}

// Fetch students & courses for form
$students = $pdo->query("SELECT id, name FROM students ORDER BY name")->fetchAll();
$courses = $pdo->query("SELECT id, title, price FROM courses ORDER BY title")->fetchAll();

// Fetch completed payment history WITH screenshot path
$history = $pdo->query("
    SELECT 
        s.name AS student_name,
        c.title AS course_title,
        c.price,
        sc.payment_method,
        sc.enrolled_at AS payment_date,
        sc.payment_screenshot
    FROM student_courses sc
    JOIN students s ON sc.student_id = s.id
    JOIN courses c ON sc.course_id = c.id
    WHERE sc.payment_status = 'paid'
    ORDER BY sc.enrolled_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Tech Gurukul</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin:0; background:#f4f6f9; }
        .main-content { margin-left: 300px; padding: 40px; }

        .welcome {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 40px;
            text-align: center;
            box-shadow: 0 12px 35px rgba(102,51,153,0.3);
        }
        .welcome h1 { margin:0; font-size:36px; }
        .welcome p { margin:15px 0 0; font-size:19px; opacity:0.95; }

        .form-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            max-width: 800px;
            margin: 0 auto 50px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        .full-width { grid-column: 1/-1; }

        label { display: block; margin: 20px 0 10px; font-weight: 600; color: #333; font-size: 16px; }
        select, input[type="file"] {
            width: 100%;
            padding: 18px;
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            font-size: 17px;
            background: white;
        }
        select:focus, input:focus { outline:none; border-color:#663399; box-shadow:0 0 0 5px rgba(102,51,153,0.15); }

        .submit-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px 40px;
            border: none;
            border-radius: 50px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }
        .submit-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(40,167,69,0.4);
        }

        .message {
            padding: 20px;
            border-radius: 14px;
            text-align: center;
            font-weight: bold;
            margin: 30px auto;
            max-width: 800px;
            font-size: 18px;
        }
        .success { background:#d4edda; color:#155724; border:2px solid #c3e6cb; }
        .error   { background:#f8d7da; color:#721c24; border:2px solid #f5c6cb; }

        /* Payment History */
        .history-section {
            margin-top: 60px;
        }
        .history-section h2 {
            color: #663399;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }
        table {
            width: 100%;
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-collapse: collapse;
        }
        th {
            background: linear-gradient(135deg, #663399, #552288);
            color: white;
            padding: 20px;
            text-align: left;
            font-size: 17px;
        }
        td {
            padding: 18px 20px;
            border-bottom: 1px solid #eee;
        }
        tr:hover { background:#f8f4ff; }

        .method-badge {
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 14px;
        }
        .esewa { background:#d4edda; color:#155724; }
        .offline { background:#fff3cd; color:#856404; }
        .cash { background:#d1ecf1; color:#0c5460; }

        /* Screenshot Thumbnail */
        .screenshot-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .screenshot-thumb:hover {
            border-color: #663399;
            transform: scale(1.05);
        }
        .no-screenshot {
            color: #999;
            font-style: italic;
            font-size: 14px;
        }

        .no-history {
            text-align: center;
            padding: 80px 20px;
            color: #888;
            font-size: 22px;
        }

        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .close-modal {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 10001;
        }
        .close-modal:hover { color: #ccc; }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0; top: 0; width: 300px; height: 100vh;
            background: linear-gradient(135deg, #663399, #552288);
            padding-top: 30px;
            box-shadow: 5px 0 15px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        .sidebar a {
            display: block;
            color: white;
            padding: 18px 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            border-left: 5px solid transparent;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.15);
            border-left-color: #ffc107;
            padding-left: 40px;
        }
        .sidebar .logout {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: #dc3545;
            text-align: center;
            padding: 20px;
            font-weight: bold;
        }
        .sidebar .logout:hover { background:#c82333; }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="create_students.php">Add Student</a>
    <a href="manage_students.php">Manage Students</a>
    <a href="admin_courses.php">Manage Courses</a>
    <a href="manage_enrollment.php">Manage Enrollment</a>
    <a href="admin_feedback.php">Manage Reviews</a>
    <a href="manage_partnerships.php">Partnerships</a>
    <a href="manage_payments.php" class="active">Payments</a>
    <a href="publish_message.php">Messages</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">

    <div class="welcome">
        <h1>Manage Payments</h1>
        <p>Record offline payments • View complete history</p>
    </div>

    <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Record Offline Payment -->
    <div class="form-container">
        <h2 style="text-align:center; color:#663399; margin-bottom:30px;">Record Offline Payment</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="add_payment" value="1">
            <div class="form-grid">
                <div>
                    <label>Student</label>
                    <select name="student_id" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Course</label>
                    <select name="course_id" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?> (Rs. <?= number_format($c['price']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Payment Method</label>
                    <select name="payment_method" required onchange="toggleScreenshot(this.value)">
                        <option value="">Choose Method</option>
                        <option value="cash">Cash</option>
                        <option value="qr">QR Code / UPI</option>
                        <option value="esewa">eSewa (Manual)</option>
                        <option value="screenshot">Other (Screenshot)</option>
                    </select>
                </div>
                <div class="full-width" id="screenshot-field" style="display:none;">
                    <label>Upload Proof (Optional)</label>
                    <input type="file" name="payment_screenshot" accept="image/*">
                    <small style="color:#666;">For record-keeping</small>
                </div>
            </div>
            <button type="submit" class="submit-btn">Record Payment & Activate Course</button>
        </form>
    </div>

    <!-- Payment History -->
    <div class="history-section">
        <h2>Payment History</h2>

        <?php if (empty($history)): ?>
            <div class="no-history">
                No completed payments yet.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Date</th>
                        <th>Proof</th> <!-- New column for screenshot -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $i => $h): ?>
                    <tr>
                        <td><strong><?= $i + 1 ?></strong></td>
                        <td><?= htmlspecialchars($h['student_name']) ?></td>
                        <td><?= htmlspecialchars($h['course_title']) ?></td>
                        <td><strong>Rs. <?= number_format($h['price']) ?></strong></td>
                        <td>
                            <span class="method-badge <?= 
                                $h['payment_method'] === 'esewa' ? 'esewa' : 
                                ($h['payment_method'] === 'cash' ? 'cash' : 'offline') 
                            ?>">
                                <?= ucfirst(str_replace('_', ' ', $h['payment_method'])) ?>
                            </span>
                        </td>
                        <td><?= date('d M Y, h:i A', strtotime($h['payment_date'])) ?></td>
                        <td>
                            <?php if (!empty($h['payment_screenshot'])): ?>
                                <img src="uploads/payments/<?= htmlspecialchars($h['payment_screenshot']) ?>" 
                                     alt="Payment Proof" 
                                     class="screenshot-thumb" 
                                     onclick="openModal('uploads/payments/<?= htmlspecialchars($h['payment_screenshot']) ?>')">
                            <?php else: ?>
                                <span class="no-screenshot">No proof</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" onclick="closeModal(event)">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <img id="modalImage" class="modal-content" src="" alt="Payment Proof">
    </div>

</div>

<script>
function toggleScreenshot(val) {
    document.getElementById('screenshot-field').style.display = 
        val === 'screenshot' ? 'block' : 'none';
}

function openModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    modal.style.display = 'flex';
    modalImg.src = imageSrc;
}

function closeModal(event) {
    const modal = document.getElementById('imageModal');
    if (event && event.target === modal) return; // Allow clicking outside to close
    modal.style.display = 'none';
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>