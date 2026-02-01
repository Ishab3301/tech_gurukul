<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['user_name']);

// Approve Feedback
if (isset($_GET['approve_review'])) {
    $id = (int)$_GET['approve_review'];
    $pdo->prepare("UPDATE feedback SET approved = 1 WHERE id = ?")->execute([$id]);
    header("Location: feedback_page.php");
    exit;
}

// Delete Feedback
if (isset($_GET['delete_review'])) {
    $id = (int)$_GET['delete_review'];
    $pdo->prepare("DELETE FROM feedback WHERE id = ?")->execute([$id]);
    header("Location: feedback_page.php");
    exit;
}

$reviews = $pdo->query("
    SELECT f.id, f.student_id, f.rating, f.comment, f.approved, f.created_at,
           s.name AS student_name
    FROM feedback f
    LEFT JOIN students s ON f.student_id = s.id
    ORDER BY f.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Feedback</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f9f9f9;
        }

        .main-content {
            margin-left: 300px;
            padding: 30px;
        }

        h2 {
            margin-top: 0;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #007bff;
            color: white;
            font-weight: 600;
            letter-spacing: 0.03em;
        }

        tr:hover {
            background-color: #f1faff;
        }

        .approved {
            color: green;
            font-weight: bold;
        }

        .not-approved {
            color: red;
            font-weight: bold;
        }

        a.button {
            padding: 6px 12px;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            margin-right: 5px;
            transition: background-color 0.3s ease;
        }

        a.button.approve {
            background-color: #28a745;
        }

        a.button.approve:hover {
            background-color: #218838;
        }

        a.button.delete {
            background-color: #dc3545;
        }

        a.button.delete:hover {
            background-color: #c82333;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 300px;
            height: 100vh;
            background-color: rgb(150, 52, 180);
            padding-top: 30px;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .sidebar a {
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            border-left: 4px solid transparent;
            transition: background-color 0.3s, border-left-color 0.3s;
        }

        .sidebar a:hover {
            background-color: #0056b3;
            border-left-color: #ffc107;
        }

        .sidebar .logout {
            margin-top: auto;
            background: #dc3545;
            border-radius: 0 4px 4px 0;
            padding: 15px 20px;
            text-align: center;
            font-weight: 700;
        }

        .sidebar .logout:hover {
            background: #b02a37;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="create_students.php">➕ Add Student</a>
    <a href="manage_students.php">👥 Manage Students</a>
    <a href="admin_courses.php">🎓 Manage Courses</a>
    <a href="manage_enrollment.php">📝 Manage Enrollment</a>
    <a href="feedback_page.php">💬 Manage Feedback</a>
    <a href="manage_partnerships.php">🤝 Manage Partnerships</a> 
    <a href="manage_payments.php" class="active">💰 Manage Payments</a> 
    <a href="publish_message.php">📢 Publish Messages</a>
    
    <a href="logout.php" class="logout">Logout</a>
</div>
<div class="main-content">
    <h2>Manage Feedback</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Rating</th>
                <th>Comment</th>
                <th>Approved?</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($reviews) > 0): ?>
            <?php foreach ($reviews as $r): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['student_name'] ?? 'Unknown') ?></td>
                    <td><?= str_repeat('⭐', $r['rating']) ?></td>
                    <td><?= nl2br(htmlspecialchars($r['comment'])) ?></td>
                    <td class="<?= $r['approved'] ? 'approved' : 'not-approved' ?>">
                        <?= $r['approved'] ? 'Yes' : 'No' ?>
                    </td>
                    <td>
                        <?php if (!$r['approved']): ?>
                            <a href="?approve_review=<?= $r['id'] ?>" class="button approve" onclick="return confirm('Approve this feedback?')">Approve</a>
                        <?php endif; ?>
                        <a href="?delete_review=<?= $r['id'] ?>" class="button delete" onclick="return confirm('Delete this feedback?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6">No feedback found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
