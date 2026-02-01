<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Unenroll action
$searchParam = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';

if (isset($_GET['unenroll'])) {
    $id = (int)$_GET['unenroll'];
    $pdo->prepare("DELETE FROM student_courses WHERE id = ?")->execute([$id]);
    $_SESSION['msg'] = "Student has been unenrolled from the course.";
    header("Location: manage_enrollment.php" . $searchParam);
    exit;
}

// Search
$search = trim($_GET['search'] ?? '');

$sql = "SELECT sc.id, sc.payment_status, sc.enrolled_at, s.name AS student_name, c.title AS course_title
        FROM student_courses sc
        JOIN students s ON sc.student_id = s.id
        JOIN courses c ON sc.course_id = c.id";
$params = [];

if ($search !== '') {
    $sql .= " WHERE s.name LIKE ? OR c.title LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$sql .= " ORDER BY sc.enrolled_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$enrollments = $stmt->fetchAll();

// Summary: Active (paid) students per course
$summary = $pdo->query("SELECT c.title, COUNT(sc.id) AS enroll_count
                        FROM courses c
                        LEFT JOIN student_courses sc ON c.id = sc.course_id AND sc.payment_status = 'paid'
                        GROUP BY c.id, c.title 
                        ORDER BY enroll_count DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Enrollments - Tech Gurukul</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 28px;
            margin-bottom: 50px;
        }
        .stat-card {
            background: white;
            padding: 32px;
            border-radius: 20px;
            box-shadow: 0 12px 35px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.4s;
            border-left: 6px solid #663399;
        }
        .stat-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 50px rgba(102,51,153,0.25);
        }
        .stat-card h3 { color: #663399; margin: 0 0 15px; font-size: 19px; }
        .stat-card .count { font-size: 48px; font-weight: bold; color: #28a745; margin: 12px 0; }

        table { width:100%; background:white; border-radius:18px; overflow:hidden; box-shadow:0 12px 40px rgba(0,0,0,0.12); border-collapse:collapse; }
        th { background:linear-gradient(135deg,#663399,#552288); color:white; padding:22px; text-align:left; font-size:17px; }
        td { padding:20px; border-bottom:1px solid #eee; }
        tr:hover { background:#f8f4ff; }

        .status-badge { padding:8px 18px; border-radius:50px; font-weight:bold; font-size:14px; }
        .paid    { background:#d4edda; color:#155724; }
        .pending { background:#fff3cd; color:#856404; }

        .action-btn { padding:10px 20px; color:white; text-decoration:none; border-radius:12px; font-weight:bold; margin:0 5px; display:inline-block; transition:all 0.3s; }
        .unenroll { background:#dc3545; }
        .action-btn:hover { transform:translateY(-3px); box-shadow:0 8px 20px rgba(0,0,0,0.2); }

        .msg { padding:20px; border-radius:14px; text-align:center; font-weight:bold; margin:30px 0; font-size:18px; background:#d4edda; color:#155724; border:2px solid #c3e6cb; }

        .no-data { text-align:center; padding:80px 20px; color:#888; font-size:22px; }

        .sidebar { position:fixed; left:0; top:0; width:300px; height:100vh; background:linear-gradient(135deg,#663399,#552288); padding-top:30px; box-shadow:5px 0 15px rgba(0,0,0,0.2); z-index:1000; }
        .sidebar a { display:block; color:white; padding:18px 30px; text-decoration:none; font-weight:600; font-size:16px; border-left:5px solid transparent; transition:all 0.3s; }
        .sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,0.15); border-left-color:#ffc107; padding-left:40px; }
        .sidebar .logout { position:absolute; bottom:0; width:100%; background:#dc3545; text-align:center; padding:20px; font-weight:bold; }
        .sidebar .logout:hover { background:#c82333; }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="create_students.php">Add Student</a>
    <a href="manage_students.php">Manage Students</a>
    <a href="admin_courses.php">Manage Courses</a>
    <a href="manage_enrollment.php" class="active">Manage Enrollment</a>
    <a href="admin_feedback.php">Manage Feedback</a>
    <a href="manage_partnerships.php">Partnerships</a>
    <a href="manage_payments.php">Payments</a>
    <a href="publish_message.php">Messages</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">

    <div class="welcome">
        <h1>Enrollment Overview</h1>
        <p>View all paid enrollments • Search • Unenroll if needed • Fully Automatic Activation</p>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="msg"><?= htmlspecialchars($_SESSION['msg']) ?></div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>

    <!-- Active Students Per Course -->
    <h2 style="color:#663399; margin-bottom:25px;">Active Students Per Course</h2>
    <?php if (empty($summary)): ?>
        <p style="text-align:center; color:#888; font-size:18px;">No paid enrollments yet.</p>
    <?php else: ?>
    <div class="stats-grid">
        <?php foreach ($summary as $s): ?>
            <div class="stat-card">
                <h3><?= htmlspecialchars($s['title']) ?></h3>
                <div class="count"><?= $s['enroll_count'] ?></div>
                <small>Active (Paid) Students</small>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="search-container">
        <form method="GET" style="display:flex; width:100%; gap:15px; align-items:center;">
            <input type="text" name="search" placeholder="Search by student name or course..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn">Search</button>
            <?php if ($search !== ''): ?>
                <a href="manage_enrollment.php" class="clear-search">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- All Enrollments -->
    <h2 style="color:#663399; margin:50px 0 25px;">
        All Enrollments 
        <?php if ($search): ?><small style="color:#888;">— found "<?= htmlspecialchars($search) ?>"</small><?php endif; ?>
    </h2>

    <?php if (empty($enrollments)): ?>
        <div class="no-data">
            <?= $search ? 'No results found.' : 'No enrollments yet.' ?>
        </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Course</th>
                <th>Payment</th>
                <th>Enrolled On</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($enrollments as $i => $e): ?>
            <tr>
                <td><strong><?= $i + 1 ?></strong></td>
                <td><strong><?= htmlspecialchars($e['student_name']) ?></strong></td>
                <td><?= htmlspecialchars($e['course_title']) ?></td>
                <td><span class="status-badge <?= $e['payment_status'] === 'paid' ? 'paid' : 'pending' ?>">
                    <?= ucfirst($e['payment_status']) ?>
                </span></td>
                <td><?= date('d M Y, h:i A', strtotime($e['enrolled_at'])) ?></td>
                <td>
                    <a href="?unenroll=<?= $e['id'] ?><?= $searchParam ?>" class="action-btn unenroll"
                       onclick="return confirm('Unenroll this student? They will lose access.')">
                        Unenroll
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