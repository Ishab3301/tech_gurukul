<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$adminName = htmlspecialchars($_SESSION['user_name']);
$error = $success = '';

// Handle Delete
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND admin_id = ?");
    if ($stmt->execute([$deleteId, $admin_id])) {
        $success = "Message deleted successfully.";
    } else {
        $error = "Failed to delete message.";
    }
    header("Location: publish_message.php");
    exit;
}

// Handle New Message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        $error = "Message cannot be empty.";
    } elseif (strlen($message) > 1000) {
        $error = "Message too long. Max 1000 characters.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO messages (admin_id, message, created_at) VALUES (?, ?, NOW())");
        if ($stmt->execute([$admin_id, $message])) {
            $success = "Message published successfully! All students can now see it.";
        } else {
            $error = "Failed to publish message.";
        }
    }
}

// Fetch all messages
$messages = $pdo->query("
    SELECT m.*, a.name AS admin_name 
    FROM messages m 
    JOIN admins a ON m.admin_id = a.id 
    ORDER BY m.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Message - Tech Gurukul</title>
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
            max-width: 900px;
            margin: 0 auto 50px;
        }

        textarea {
            width: 100%;
            padding: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            font-size: 18px;
            font-family: inherit;
            resize: vertical;
            min-height: 160px;
            transition: all 0.3s;
        }
        textarea:focus {
            outline: none;
            border-color: #663399;
            box-shadow: 0 0 0 5px rgba(102,51,153,0.15);
        }

        .char-count {
            text-align: right;
            color: #888;
            font-size: 14px;
            margin-top: 8px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px 50px;
            border: none;
            border-radius: 50px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .submit-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,123,255,0.4);
        }

        .messages-grid {
            display: grid;
            gap: 30px;
            margin-top: 30px;
        }
        .message-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 12px 35px rgba(0,0,0,0.1);
            transition: all 0.4s;
            position: relative;
            border-left: 6px solid #663399;
        }
        .message-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 50px rgba(102,51,153,0.25);
        }
        .message-card .admin {
            color: #663399;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .message-card .text {
            font-size: 17px;
            line-height: 1.7;
            color: #333;
            margin: 15px 0;
        }
        .message-card .date {
            color: #888;
            font-size: 15px;
        }
        .delete-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            text-decoration: none;
            opacity: 0;
            transition: all 0.3s;
        }
        .message-card:hover .delete-btn {
            opacity: 1;
            transform: scale(1.1);
        }
        .delete-btn:hover {
            background: #c82333;
            transform: scale(1.2);
        }

        .message {
            padding: 20px;
            borderٔ-radius: 14px;
            text-align: center;
            font-weight: bold;
            margin: 30px auto;
            max-width: 900px;
            font-size: 18px;
        }
        .success { background:#d4edda; color:#155724; border:2px solid #c3e6cb; }
        .error   { background:#f8d7da; color:#721c24; border:2px solid #f5c6cb; }

        .no-messages {
            text-align: center;
            padding: 100px 20px;
            color: #888;
            font-size: 22px;
        }

        /* LEGENDARY SIDEBAR */
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
    <a href="manage_payments.php">Payments</a>
    <a href="publish_message.php" class="active">Messages</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">

    <div class="welcome">
        <h1>Publish Message</h1>
        <p>Send important updates, announcements, or greetings to all students</p>
    </div>

    <?php if ($success): ?>
        <div class="message success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?= $error ?></div>
    <?php endif; ?>

    <!-- PUBLISH FORM -->
    <div class="form-container">
        <h2 style="text-align:center; color:#663399; margin-bottom:30px;">Write a New Message</h2>
        <form method="POST" onsubmit="return validateForm()">
            <textarea name="message" id="message" placeholder="Type your message here..." required maxlength="1000"></textarea>
            <div class="char-count">
                <span id="charCount">0</span>/1000 characters
            </div>
            <button type="submit" class="submit-btn">Publish Message to All Students</button>
        </form>
    </div>

    <!-- ALL MESSAGES -->
    <h2 style="color:#663399; text-align:center; margin:50px 0 30px;">Published Messages</h2>

    <?php if (empty($messages)): ?>
        <div class="no-messages">
            No messages published yet.<br>
            Be the first to inspire your students!
        </div>
    <?php else: ?>
        <div class="messages-grid">
            <?php foreach ($messages as $m): ?>
                <div class="message-card">
                    <div class="admin"><?= htmlspecialchars($m['admin_name']) ?></div>
                    <div class="text"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                    <div class="date">
                        <?= date('d M Y', strtotime($m['created_at'])) ?> at <?= date('h:i A', strtotime($m['created_at'])) ?>
                    </div>
                    <?php if ($m['admin_id'] == $admin_id): ?>
                        <a href="?delete_id=<?= $m['id'] ?>" class="delete-btn"
                           onclick="return confirm('Delete this message permanently?')">
                           X
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
const textarea = document.getElementById('message');
const counter = document.getElementById('charCount');
textarea.addEventListener('input', () => {
    counter.textContent = textarea.value.length;
});

function validateForm() {
    if (textarea.value.trim() === '') {
        alert('Message cannot be empty!');
        return false;
    }
    return true;
}
</script>

</body>
</html>