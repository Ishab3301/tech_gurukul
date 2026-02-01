<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin'){
    header('Location: login.php');
    exit;
}

// Fix old NULL approved values
$pdo->query("UPDATE feedback SET approved = 0 WHERE approved IS NULL");

// Success message
$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);

// Actions
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $pdo->prepare("UPDATE feedback SET approved = 1 WHERE id = ?")->execute([$id]);
    $_SESSION['msg'] = "Review approved and now visible on the website!";
    header("Location: admin_feedback.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM feedback WHERE id = ?")->execute([$id]);
    $_SESSION['msg'] = "Review deleted permanently.";
    header("Location: admin_feedback.php" . (isset($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
    exit;
}

// Search
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        f.id, f.student_id, f.course_id, f.rating, f.comment,
        COALESCE(f.approved, 0) AS approved, f.created_at,
        s.name AS student_name, c.title AS course_title
    FROM feedback f
    LEFT JOIN students s ON f.student_id = s.id
    LEFT JOIN courses c ON f.course_id = c.id
";
$params = [];

if ($search !== '') {
    $sql .= " WHERE s.name LIKE ? OR s.email LIKE ? OR c.title LIKE ? OR f.comment LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$sql .= " ORDER BY f.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews - Tech Gurukul</title>
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

        .search-container {
            background: white;
            padding: 30px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 40px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-container input {
            flex: 1; min-width: 300px; padding: 18px; border: 2px solid #ddd; border-radius: 14px; font-size: 17px;
        }
        .search-container input:focus { outline:none; border-color:#663399; box-shadow:0 0 0 5px rgba(102,51,153,0.15); }
        .search-btn { background: linear-gradient(135deg,#663399,#552288); color:white; padding:18px 35px; border:none; border-radius:14px; font-weight:bold; cursor:pointer; }
        .search-btn:hover { transform:translateY(-4px); box-shadow:0 12px 25px rgba(102,51,153,0.4); }
        .clear-search { color:#dc3545; font-weight:bold; text-decoration:none; }
        .clear-search:hover { text-decoration:underline; }

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
            padding: 22px;
            text-align: left;
            font-size: 17px;
        }
        td { padding: 20px; border-bottom: 1px solid #eee; vertical-align: top; }
        tr:hover { background:#f8f4ff; }

        .rating {
            color: #ffc107;
            font-size: 20px;
        }
        .status-badge {
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 14px;
        }
        .pending   { background:#fff3cd; color:#856404; }
        .approved  { background:#d4edda; color:#155724; }

        .action-btn {
            padding: 12px 22px;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: bold;
            margin: 0 6px;
            display: inline-block;
            transition: all 0.3s;
        }
        .approve-btn { background:#28a745; }
        .delete-btn  { background:#dc3545; }
        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        .msg {
            padding: 20px;
            border-radius: 14px;
            text-align: center;
            font-weight: bold;
            margin: 30px 0;
            font-size: 18px;
            background:#d4edda;
            color:#155724;
            border: 2px solid #c3e6cb;
        }

        .no-data {
            text-align: center;
            padding: 100px 20px;
            color: #888;
            font-size: 22px;
        }

        /* SAME EPIC SIDEBAR */
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
    <a href="admin_feedback.php" class="active">Manage Reviews</a>
    <a href="manage_partnerships.php">Partnerships</a>
    <a href="manage_payments.php">Payments</a>
    <a href="publish_message.php">Publish Messages</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">

    <div class="welcome">
        <h1>Manage Student Reviews</h1>
        <p>Approve reviews to display on website • Delete spam • Search instantly</p>
    </div>

    <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- SEARCH BAR -->
    <div class="search-container">
        <form method="GET" style="display:flex; width:100%; gap:15px; align-items:center;">
            <input type="text" name="search" placeholder="Search by student name, course, or review text..." 
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn">Search</button>
            <?php if ($search !== ''): ?>
                <a href="admin_feedback.php" class="clear-search">Clear Search</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($feedbacks)): ?>
        <div class="no-data">
            <?= $search ? 'No reviews found matching your search.' : 'No student reviews yet.<br>They will appear here once submitted!' ?>
        </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Course</th>
                <th>Rating</th>
                <th>Review</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($feedbacks as $i => $f): ?>
            <tr>
                <td><strong><?= $i + 1 ?></strong></td>
                <td><strong><?= htmlspecialchars($f['student_name'] ?? 'Guest User') ?></strong></td>
                <td><?= htmlspecialchars($f['course_title'] ?? '<em>General Feedback</em>') ?></td>
                <td>
                    <span class="rating">
                        <?= str_repeat('Star', $f['rating']) ?>
                    </span>
                    <small style="color:#666; margin-left:8px;">(<?= $f['rating'] ?>/5)</small>
                </td>
                <td style="max-width:350px;">
                    <?= nl2br(htmlspecialchars($f['comment'])) ?>
                </td>
                <td>
                    <span class="status-badge <?= $f['approved'] ? 'approved' : 'pending' ?>">
                        <?= $f['approved'] ? 'Approved' : 'Pending' ?>
                    </span>
                </td>
                <td>
                    <?= date('d M Y', strtotime($f['created_at'])) ?><br>
                    <small style="color:#888;"><?= date('h:i A', strtotime($f['created_at'])) ?></small>
                </td>
                <td>
                    <?php if (!$f['approved']): ?>
                        <a href="?approve=<?= $f['id'] ?>&search=<?= urlencode($search) ?>" 
                           class="action-btn approve-btn"
                           onclick="return confirm('Approve this review? It will appear on the website.')">
                           Approve
                        </a>
                    <?php endif; ?>
                    <a href="?delete=<?= $f['id'] ?>&search=<?= urlencode($search) ?>" 
                       class="action-btn delete-btn"
                       onclick="return confirm('Delete this review permanently?')">
                       Delete
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

</div>
</body>
</html>