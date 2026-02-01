<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['user_name']);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);

    if ($title === '') {
        $error = "Title is required.";
    } elseif (!is_numeric($price)) {
        $error = "Price must be a number.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO courses (title, description, price) VALUES (?, ?, ?)");
        if ($stmt->execute([$title, $description, $price])) {
            // Redirect to courses page on success
            header('Location: admin_courses.php');
            exit;
        } else {
            $error = "Failed to add course.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Add New Course</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, sans-serif;
      background: #f7f7f7;
      display: flex;
      min-height: 100vh;
    }

    .sidebar {
      position: fixed;
      left: 0;
      top: 0;
      width: 300px;
      height: 100vh;
      background-color: rgb(150, 52, 180);
      padding-top: 20px;
      display: flex;
      flex-direction: column;
      box-shadow: 2px 0 5px rgba(0,0,0,0.1);
      transition: width 0.3s ease;
    }
    .sidebar a {
      color: white;
      padding: 15px 20px;
      font-weight: 600;
      border-left: 4px solid transparent;
      transition: background-color 0.3s, border-left-color 0.3s;
      text-decoration: none;
    }
    .sidebar a:hover {
      background-color: #4b2e83; /* darker purple */
      border-left-color: #ffc107;
    }
    .sidebar a.active {
      background-color: #4b2e83;
      border-left-color: #ffc107;
    }
    .sidebar .logout {
      margin-top: auto;
      background: #dc3545;
      border-radius: 0 4px 4px 0;
      padding: 15px 20px;
      text-align: center;
      font-weight: 700;
      border-left-color: transparent !important;
    }
    .sidebar .logout:hover {
      background: #b02a37;
    }

    .main {
      margin-left: 300px;
      padding: 40px 30px;
      flex: 1;
      background: #f7f7f7;
      min-height: 100vh;
    }

    .container {
      max-width: 500px;
      background: white;
      padding: 30px 25px;
      border-radius: 8px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
      margin: 0 auto;
    }

    h2 {
      text-align: center;
      margin-bottom: 25px;
      color: #333;
      font-weight: 700;
    }

    form label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #4b2e83;
    }

    form input[type="text"],
    form textarea {
      width: 100%;
      padding: 12px;
      margin-bottom: 20px;
      border: 1px solid #ccc;
      border-radius: 6px;
      resize: vertical;
      font-family: inherit;
      font-size: 14px;
      transition: border-color 0.3s;
    }
    form input[type="text"]:focus,
    form textarea:focus {
      border-color: #9553c1;
      outline: none;
    }

    form input[type="submit"] {
      width: 100%;
      padding: 14px;
      background: rgb(150, 52, 180);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      cursor: pointer;
      font-weight: 600;
      transition: background-color 0.3s;
    }

    form input[type="submit"]:hover {
      background: #4b2e83;
    }

    .message {
      padding: 12px 15px;
      margin-bottom: 20px;
      border-radius: 6px;
      text-align: center;
      font-weight: 600;
    }

    .error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .back-link {
      display: inline-block;
      margin-top: 15px;
      color: rgb(150, 52, 180);
      text-decoration: none;
      font-weight: 700;
      transition: color 0.3s;
    }
    .back-link:hover {
      color: #4b2e83;
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      body {
        flex-direction: column;
      }

      .sidebar {
        width: 100%;
        height: auto;
        position: relative;
      }

      .main {
        margin-left: 0;
        padding: 20px 15px;
      }

      .container {
        margin-top: 20px;
      }
    }
  </style>
</head>
<body>

<div class="sidebar">
    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="create_students.php">➕ Add Student</a>
    <a href="manage_students.php">👥 Manage Students</a>
    <a href="admin_courses.php" class="active">🎓 Manage Courses</a>
    <a href="manage_enrollment.php">📝 Manage Enrollment</a>
    <a href="feedback_page.php">💬 Manage Feedback</a>
    <a href="manage_partnerships.php">🤝 Manage Partnerships</a>  
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main">
  <div class="container">
    <h2>Add New Course</h2>

    <?php if ($error): ?>
      <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <label for="title">Title:</label>
      <input type="text" id="title" name="title" required>

      <label for="description">Description:</label>
      <textarea id="description" name="description" rows="5"></textarea>

      <label for="price">Price (Rs.):</label>
      <input type="text" id="price" name="price" required>

      <input type="submit" value="Add Course">
    </form>

    <a href="admin_courses.php" class="back-link">⬅ Back to Courses</a>
  </div>
</div>

</body>
</html>
