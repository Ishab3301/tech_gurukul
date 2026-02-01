<?php
session_start();
require "db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$adminName = htmlspecialchars($_SESSION["user_name"]);
$search = $_GET['search'] ?? '';

// Search query
$sql = "
    SELECT 
        s.id AS student_id, 
        s.name AS student_name, 
        s.email, 
        GROUP_CONCAT(c.title SEPARATOR ', ') AS courses,
        GROUP_CONCAT(sc.payment_status SEPARATOR ', ') AS payment_statuses
    FROM students s
    LEFT JOIN student_courses sc ON s.id = sc.student_id
    LEFT JOIN courses c ON sc.course_id = c.id
";

$params = [];
if ($search !== '') {
    $sql .= " WHERE s.name LIKE ? OR s.email LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY s.id ORDER BY s.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Tech Gurukul</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin:0; background:#f4f6f9; }
        .main-content { margin-left: 300px; padding: 40px; }

        .welcome {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 35px;
            border-radius: 16px;
            margin-bottom: 35px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(102,51,153,0.3);
        }
        .welcome h1 { margin:0; font-size:34px; }
        .welcome p { margin:12px 0 0; font-size:19px; opacity:0.95; }

        /* Search Bar */
        .search-container {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .search-container input {
            flex: 1;
            min-width: 300px;
            padding: 16px;
            border: 2px solid #ddd;
            border-radius: 12px;
            font-size: 16px;
        }
        .search-container input:focus {
            outline: none;
            border-color: #663399;
            box-shadow: 0 0 0 4px rgba(102,51,153,0.15);
        }
        .search-btn {
            background: linear-gradient(135deg, #663399, #552288);
            color: white;
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .search-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102,51,153,0.3);
        }
        .clear-search {
            color: #dc3545;
            text-decoration: none;
            font-weight: bold;
        }
        .clear-search:hover { text-decoration: underline; }

        /* Table */
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
            padding: 20px;
            text-align: left;
            font-size: 16px;
            font-weight: 600;
        }
        td {
            padding: 18px 20px;
            border-bottom: 1px solid #eee;
        }
        tr:hover {
            background: #f8f4ff;
        }

        /* Payment Badges */
        .badge {
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 13px;
            display: inline-block;
            margin: 4px 6px 4px 0;
            min-width: 80px;
            text-align: center;
        }
        .badge.paid    { background:#d4edda; color:#155724; }
        .badge.pending { background:#fff3cd; color:#856404; }
        .badge.approved{ background:#d1ecf1; color:#0c5460; }

        /* Action Buttons */
        .actions a {
            padding: 10px 18px;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            margin: 0 6px;
            display: inline-block;
            transition: all 0.3s;
        }
        .edit-btn   { background:#28a745; }
        .delete-btn { background:#dc3545; }
        .edit-btn:hover   { background:#218838; transform:translateY(-2px); }
        .delete-btn:hover { background:#c82333; transform:translateY(-2px); }

        /* No students message */
        .no-data {
            text-align: center;
            padding: 80px 20px;
            color: #666;
            font-size: 20px;
        }

        /* SAME SIDEBAR AS DASHBOARD */
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

<!-- BEAUTIFUL SIDEBAR -->
<div class="sidebar">
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="create_students.php">Add Student</a>
    <a href="manage_students.php" class="active">Manage Students</a>
    <a href="admin_courses.php">Manage Courses</a>
    <a href="manage_enrollment.php">Manage Enrollment</a>
    <a href="admin_feedback.php">Manage Reviews</a>
    <a href="manage_partnerships.php">Partnerships</a>
    <a href="manage_payments.php">Payments</a>
    <a href="publish_message.php">Messages</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">

    <div class="welcome">
        <h1>Manage Students</h1>
        <p>View, search, edit or delete student accounts</p>
    </div>

    <!-- Search Bar -->
    <div class="search-container">
        <form method="GET" style="display:flex; gap:15px; align-items:center; width:100%;">
            <input type="text" name="search" placeholder="Search by name or email..." 
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn">Search</button>
            <?php if ($search !== ''): ?>
                <a href="manage_students.php" class="clear-search">Clear Search</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Students Table -->
    <?php if (empty($students)): ?>
        <div class="no-data">
            No students found
            <?php if ($search): ?> matching "<?= htmlspecialchars($search) ?>"<?php endif; ?>
        </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student Name</th>
                <th>Email</th>
                <th>Enrolled Courses</th>
                <th>Payment Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $i => $s): ?>
            <tr>
                <td><strong><?= $i + 1 ?></strong></td>
                <td><strong><?= htmlspecialchars($s['student_name']) ?></strong></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td>
                    <?= $s['courses'] ? htmlspecialchars($s['courses']) : '<em style="color:#999">Not enrolled</em>' ?>
                </td>
                <!-- Enrolled Courses + Status (PARALLEL & BEAUTIFUL) -->
<td style="vertical-align: top; padding: 18px 20px;">
    <?php
    if ($s['courses'] && $s['payment_statuses']) {
        $courseList = explode(',', $s['courses']);
        $statusList = explode(',', $s['payment_statuses']);
        $courses = array_map('trim', $courseList);
        $statuses = array_map('trim', $statusList);
        $max = max(count($courses), count($statuses));

        for ($j = 0; $j < $max; $j++) {
            $courseName = $courses[$j] ?? '<em style="color:#aaa">—</em>';
            $statusRaw = $statuses[$j] ?? '—';
            $statusLower = strtolower($statusRaw);

            $badgeClass = match($statusLower) {
                'paid', 'completed' => 'paid',
                'pending' => 'pending',
                'approved' => 'approved',
                default => 'pending'
            };
            $statusText = match($statusLower) {
                'paid', 'completed' => 'Paid',
                'pending' => 'Pending',
                'approved' => 'Approved',
                default => ucfirst($statusRaw)
            };

            echo '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; padding: 4px 0;">';
            echo '  <span style="font-weight: 500; color: #333; flex: 1;">' . htmlspecialchars($courseName) . '</span>';
            echo '  <span class="badge ' . $badgeClass . '" style="font-size: 12px; padding: 6px 14px; min-width: 82px; text-align: center;">' . $statusText . '</span>';
            echo '</div>';
        }
    } else {
        echo '<em style="color:#999">Not enrolled</em>';
    }
    ?>
</td>

<!-- Actions Column (keep as is) -->
<td class="actions">
    <a href="edit_student.php?id=<?= $s['student_id'] ?>" class="edit-btn">Edit</a>
    <a href="delete_student.php?id=<?= $s['student_id'] ?>" 
       class="delete-btn" 
       onclick="return confirm('Delete <?= addslashes($s['student_name']) ?>?')">
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