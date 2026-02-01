<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['user_name']);

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
    $_SESSION['msg'] = "Course deleted successfully!";
    header("Location: admin_courses.php");
    exit;
}

// Search
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM courses";
$params = [];

if ($search !== '') {
    $sql .= " WHERE title LIKE ? OR description LIKE ?";
    $params = ["%$search%", "%$search%"];
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Tech Gurukul</title>
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

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .search-box {
            flex: 1;
            min-width: 300px;
            display: flex;
            gap: 12px;
        }
        .search-box input {
            flex: 1;
            padding: 16px;
            border: 2px solid #ddd;
            border-radius: 12px;
            font-size: 16px;
        }
        .search-box input:focus {
            outline: none;
            border-color: #663399;
            box-shadow: 0 0 0 4px rgba(102,51,153,0.15);
        }
        .search-btn, .clear-btn {
            padding: 16px 28px;
            border: none;
            border-radius: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .search-btn {
            background: linear-gradient(135deg, #663399, #552288);
            color: white;
        }
        .clear-btn {
            background: #6c757d;
            color: white;
        }
        .search-btn:hover, .clear-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .add-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            font-size: 16px;
            box-shadow: 0 8px 20px rgba(40,167,69,0.3);
            transition: all 0.3s;
        }
        .add-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(40,167,69,0.4);
        }

        table {
            width: 100%;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 35px rgba(0,0,0,0.1);
            border-collapse: collapse;
        }
        th {
            background: linear-gradient(135deg, #663399, #552288);
            color: white;
            padding: 22px;
            text-align: left;
            font-size: 16px;
        }
        td {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        tr:hover {
            background: #f8f4ff;
        }

        .actions a {
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            margin: 0 6px;
            display: inline-block;
            transition: all 0.3s;
        }
        .edit-btn   { background:#ffc107; color:#212529; }
        .delete-btn { background:#dc3545; }
        .edit-btn:hover   { background:#e0a800; transform:translateY(-2px); }
        .delete-btn:hover { background:#c82333; transform:translateY(-2px); }

        .duration {
            font-weight: bold;
            color: #663399;
        }

        .no-courses {
            text-align: center;
            padding: 80px;
            color: #666;
            font-size: 20px;
        }

        .msg {
            padding: 18px;
            border-radius: 12px;
            text-align: center;
            font-weight: bold;
            margin: 20px 0;
            font-size: 16px;
        }
        .success { background:#d4edda; color:#155724; }

        /* SAME PREMIUM SIDEBAR */
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

<!-- SAME GORGEOUS SIDEBAR -->
<div class="sidebar">
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="create_students.php">Add Student</a>
    <a href="manage_students.php">Manage Students</a>
    <a href="admin_courses.php" class="active">Manage Courses</a>
    <a href="manage_enrollment.php">Manage Enrollment</a>
    <a href="admin_feedback.php">Manage Feedback</a>
    <a href="manage_partnerships.php">Partnerships</a>
    <a href="manage_payments.php">Payments</a>
    <a href="publish_message.php">Publish Messages</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">

    <div class="welcome">
        <h1>Manage Courses</h1>
        <p>Add, edit, or remove courses from Tech Gurukul</p>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="msg success"><?= htmlspecialchars($_SESSION['msg']) ?></div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>

    <div class="top-bar">
        <div class="search-box">
            <form method="GET" style="display:flex; width:100%; gap:12px;">
                <input type="text" name="search" placeholder="Search by title or description..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn">Search</button>
                <?php if ($search): ?>
                    <a href="admin_courses.php" class="clear-btn">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <a href="create_course.php" class="add-btn">Add New Course</a>
    </div>

    <?php if (empty($courses)): ?>
        <div class="no-courses">
            <?php if ($search): ?>
                No courses found for "<?= htmlspecialchars($search) ?>"
            <?php else: ?>
                No courses available yet. Click "Add New Course" to get started!
            <?php endif; ?>
        </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Course Title</th>
                <th>Description</th>
                <th>Price</th>
                <th>Created</th>
                <th>Duration</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($courses as $i => $c): ?>
            <tr>
                <td><strong><?= $i + 1 ?></strong></td>
                <td><strong><?= htmlspecialchars($c['title']) ?></strong></td>
                <td><?= nl2br(htmlspecialchars(substr($c['description'], 0, 100))) ?>...</td>
                <td><strong>Rs.<?= number_format($c['price']) ?></strong></td>
                <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                <td class="duration">
                    <?php
                    if ($c['duration_type'] === 'months') {
                        $weeks = $c['duration_value'] * 4;
                        echo "$c[duration_value] months (~$weeks weeks)";
                    } else {
                        echo "$c[duration_value] weeks";
                    }
                    ?>
                </td>
                <td class="actions">
                    <a href="edit_course.php?id=<?= $c['id'] ?>" class="edit-btn">Edit</a>
                    <a href="?delete=<?= $c['id'] ?>" class="delete-btn"
                       onclick="return confirm('⚠️ Delete \"<?= addslashes($c['title']) ?>\"? This cannot be undone.')">
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