<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle Add Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $link = trim($_POST['link']);
    $logo = '';

    if ($name === '') {
        $error = "Partner name is required.";
    } else {
        // Handle file upload
        if (!empty($_FILES['logo']['name'])) {
            $targetDir = "uploads/partners/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            $ext = pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION);
            $filename = time() . "_" . uniqid() . "." . strtolower($ext);
            $targetFile = $targetDir . $filename;

            $allowed = ['jpg','jpeg','png','gif','webp','svg'];
            if (in_array(strtolower($ext), $allowed) && $_FILES["logo"]["size"] < 5000000) {
                if (move_uploaded_file($_FILES["logo"]["tmp_name"], $targetFile)) {
                    $logo = $targetFile;
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO partnerships (name, link, logo, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$name, $link ?: null, $logo]);
        $success = "Partner added successfully!";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT logo FROM partnerships WHERE id = ?");
    $stmt->execute([$id]);
    $partner = $stmt->fetch();
    if ($partner && $partner['logo'] && file_exists($partner['logo'])) {
        unlink($partner['logo']);
    }
    $pdo->prepare("DELETE FROM partnerships WHERE id = ?")->execute([$id]);
    $success = "Partner deleted successfully.";
    header("Location: manage_partnerships.php");
    exit;
}

$partners = $pdo->query("SELECT * FROM partnerships ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Partnerships - Tech Gurukul</title>
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
            max-width: 700px;
            margin: 0 auto 50px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        .full-width { grid-column: 1 / -1; }

        label {
            display: block;
            margin: 20px 0 10px;
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        input[type="text"], input[type="url"], input[type="file"] {
            width: 100%;
            padding: 18px;
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            font-size: 17px;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #663399;
            box-shadow: 0 0 0 5px rgba(102,51,153,0.15);
        }

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
            transition: all 0.3s;
        }
        .submit-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(40,167,69,0.4);
        }

        .partners-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .partner-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 12px 35px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.4s;
            position: relative;
            overflow: hidden;
        }
        .partner-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 25px 50px rgba(102,51,153,0.25);
        }
        .partner-card img {
            height: 80px;
            max-width: 180px;
            object-fit: contain;
            margin-bottom: 20px;
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.1));
        }
        .partner-card h3 {
            color: #663399;
            margin: 15px 0;
            font-size: 22px;
        }
        .partner-card a.visit {
            color: #007bff;
            font-weight: bold;
            text-decoration: none;
        }
        .partner-card a.visit:hover { text-decoration: underline; }

        .delete-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #dc3545;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            text-decoration: none;
            opacity: 0;
            transition: all 0.3s;
        }
        .partner-card:hover .delete-btn {
            opacity: 1;
            transform: scale(1.1);
        }
        .delete-btn:hover {
            background: #c82333;
            transform: scale(1.2);
        }

        .message {
            padding: 20px;
            border-radius: 14px;
            text-align: center;
            font-weight: bold;
            margin: 30px auto;
            max-width: 700px;
            font-size: 18px;
        }
        .success { background:#d4edda; color:#155724; border:2px solid #c3e6cb; }
        .error   { background:#f8d7da; color:#721c24; border:2px solid #f5c6cb; }

        .no-partners {
            text-align: center;
            padding: 100px 20px;
            color: #888;
            font-size: 22px;
        }

        /* SAME LEGENDARY SIDEBAR */
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
    <a href="manage_partnerships.php" class="active">Manage Partnerships</a>
    <a href="manage_payments.php">Payments</a>
    <a href="publish_message.php">Messages</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">

    <div class="welcome">
        <h1>Manage Partnerships</h1>
        <p>Add or remove partner companies • Display logos on homepage</p>
    </div>

    <?php if (isset($success)): ?>
        <div class="message success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="message error"><?= $error ?></div>
    <?php endif; ?>

    <!-- ADD PARTNER FORM -->
    <div class="form-container">
        <h2 style="text-align:center; color:#663399; margin-bottom:30px;">Add New Partner</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div>
                    <label>Partner Name <span style="color:red;">*</span></label>
                    <input type="text" name="name" required placeholder="e.g. Google India">
                </div>
                <div>
                    <label>Website URL (optional)</label>
                    <input type="url" name="link" placeholder="https://example.com">
                </div>
                <div class="full-width">
                    <label>Company Logo (PNG, JPG, SVG)</label>
                    <input type="file" name="logo" accept="image/*">
                </div>
            </div>
            <button type="submit" class="submit-btn">Add Partner</button>
        </form>
    </div>

    <!-- PARTNERS GRID -->
    <h2 style="color:#663399; text-align:center; margin:50px 0 30px;">Current Partners</h2>

    <?php if (empty($partners)): ?>
        <div class="no-partners">
            No partners added yet.<br>
            Start building your network!
        </div>
    <?php else: ?>
    <div class="partners-grid">
        <?php foreach ($partners as $p): ?>
            <div class="partner-card">
                <?php if ($p['logo']): ?>
                    <img src="<?= htmlspecialchars($p['logo']) ?>" alt="<?= htmlspecialchars($p['name']) ?> logo">
                <?php else: ?>
                    <div style="height:80px; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:60px;">
                        
                    </div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($p['name']) ?></h3>
                <?php if ($p['link']): ?>
                    <a href="<?= htmlspecialchars($p['link']) ?>" target="_blank" class="visit">Visit Website</a>
                <?php endif; ?>
                <a href="?delete=<?= $p['id'] ?>" class="delete-btn"
                   onclick="return confirm('Delete <?= addslashes(htmlspecialchars($p['name'])) ?> permanently?')">
                   X
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</body>
</html>