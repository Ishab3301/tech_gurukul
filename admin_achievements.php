<?php
session_start();
require 'db.php'; // Make sure path is correct

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
  header('Location: login.php');
  exit;
}

// Handle add, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        if (!empty($_POST['title']) && !empty($_POST['description']) && isset($_POST['icon'])) {
            $title = $_POST['title'];
            $description = $_POST['description'];
            $icon = $_POST['icon'];
            $stmt = $pdo->prepare("INSERT INTO achievements (title, description, icon) VALUES (?, ?, ?)");
            $stmt->execute([$title, $description, $icon]);
            header("Location: admin_achievements.php?msg=Achievement added");
            exit;
        } else {
            header("Location: admin_achievements.php?msg=Please fill all fields");
            exit;
        }
    } elseif (isset($_POST['update'])) {
        if (!empty($_POST['id']) && !empty($_POST['title']) && !empty($_POST['description']) && isset($_POST['icon'])) {
            $id = $_POST['id'];
            $title = $_POST['title'];
            $description = $_POST['description'];
            $icon = $_POST['icon'];
            $stmt = $pdo->prepare("UPDATE achievements SET title = ?, description = ?, icon = ? WHERE id = ?");
            $stmt->execute([$title, $description, $icon, $id]);
            header("Location: admin_achievements.php?msg=Achievement updated");
            exit;
        } else {
            header("Location: admin_achievements.php?msg=Please fill all fields");
            exit;
        }
    }
}

if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $stmt = $pdo->prepare("DELETE FROM achievements WHERE id = ?");
  $stmt->execute([$id]);
  header("Location: admin_achievements.php?msg=Achievement deleted");
  exit;
}

// Fetch achievements
$achievements = $pdo->query("SELECT * FROM achievements ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Achievements</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background: #f5f5f5;
    }

    /* Sidebar fixed width and full height */
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
      box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
      z-index: 10000;
    }

    .sidebar a {
      color: white;
      padding: 15px 20px;
      font-weight: 600;
      border-left: 4px solid transparent;
      transition: background-color 0.3s, border-left-color 0.3s;
      text-decoration: none;
    }

    .sidebar a:hover,
    .sidebar a.active {
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
      text-decoration: none;
      color: white;
    }

    .sidebar .logout:hover {
      background: #b02a37;
    }

    /* Main content pushed right by sidebar width */
    .main-content {
      margin-left: 300px;
      padding: 30px;
      background: #f5f5f5;
      min-height: 100vh;
      box-sizing: border-box;
    }

    @media (max-width: 768px) {
      .sidebar {
        position: relative;
        width: 100%;
        height: auto;
      }

      .main-content {
        margin-left: 0;
        padding: 20px;
      }
    }

    h2 {
      margin-top: 0;
      color: #333;
    }

    table {
      width: 100%;
      background: #fff;
      border-collapse: collapse;
      margin-top: 20px;
    }

    th,
    td {
      padding: 12px;
      border: 1px solid #ccc;
      text-align: left;
      vertical-align: middle;
    }

    th {
      background: #663399;
      color: white;
    }

    .form-box {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .form-box input,
    .form-box textarea {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      font-size: 1rem;
      border: 1px solid #ccc;
      border-radius: 4px;
      box-sizing: border-box;
    }

    .form-box button {
      background: #663399;
      color: white;
      border: none;
      padding: 10px 18px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 1rem;
      margin-top: 10px;
      transition: background-color 0.3s ease;
    }

    .form-box button:hover {
      background: #5a2e8a;
    }

    .button {
      padding: 6px 12px;
      border-radius: 4px;
      font-size: 14px;
      text-decoration: none;
      color: white;
      cursor: pointer;
      margin-right: 5px;
      border: none;
      display: inline-block;
    }

    .edit {
      background: #17a2b8;
    }

    .delete {
      background: #dc3545;
    }

    .msg {
      background: #e0f7e9;
      color: #2d7a44;
      padding: 10px;
      margin: 15px 0;
      border-left: 5px solid #28a745;
      border-radius: 4px;
    }

    table tbody input[type="text"] {
      width: 100%;
      font-size: 0.9rem;
      padding: 6px;
    }

    table tbody textarea {
      width: 100%;
      font-size: 0.9rem;
      padding: 6px;
      resize: vertical;
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
    <a href="manage_payments.php">💰 Manage Payments</a>
    <a href="publish_message.php">📢 Publish Messages</a>
    <a href="admin_achievements.php" class="active">🏅 Manage Achievements</a>
    
    <a href="logout.php" class="logout">Logout</a>
  </div>

  <div class="main-content">
    <h2>Manage Achievements</h2>

    <?php if (isset($_GET['msg'])) : ?>
      <div class="msg">✅ <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <div class="form-box">
      <h3>Add New Achievement</h3>
      <form method="POST" novalidate>
        <input type="text" name="title" placeholder="Title" required />
        <input type="text" name="icon" placeholder="Icon (e.g., 🎓)" required />
        <textarea name="description" placeholder="Description" required></textarea>
        <button type="submit" name="add">Add Achievement</button>
      </form>
    </div>

    <h3>All Achievements</h3>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Icon</th>
          <th>Title</th>
          <th>Description</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($achievements as $ach) : ?>
          <tr>
            <td><?= $ach['id'] ?></td>
            <td style="font-size: 1.5rem;"><?= htmlspecialchars($ach['icon']) ?></td>
            <td>
              <form method="POST" style="display:inline-block; min-width: 180px;">
                <input type="hidden" name="id" value="<?= $ach['id'] ?>">
                <input type="text" name="title" value="<?= htmlspecialchars($ach['title']) ?>" required>
            </td>
            <td>
                <textarea name="description" rows="2" required><?= htmlspecialchars($ach['description']) ?></textarea>
            </td>
            <td>
                <input type="text" name="icon" value="<?= htmlspecialchars($ach['icon']) ?>" required style="width: 50px; font-size:1.3rem;">
                <button type="submit" name="update" class="button edit">Update</button>
              </form>
              <a href="?delete=<?= $ach['id'] ?>" class="button delete" onclick="return confirm('Delete this achievement?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($achievements) === 0) : ?>
          <tr><td colspan="5" style="text-align:center;">No achievements found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</body>

</html>
