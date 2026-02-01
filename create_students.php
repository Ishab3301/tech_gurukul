<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$error = $success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_name = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // === NAME VALIDATION (NEPAL-FRIENDLY) ===
    $name = preg_replace('/\s+/', ' ', $raw_name); // Remove multiple spaces

    if ($raw_name === '') {
        $error = "Name is required!";
    }
    // Allow only letters, spaces, and ONE dot (for Nepali caste like B.K., K.C.)
    elseif (!preg_match('/^[a-zA-Z\s]+(\.[a-zA-Z]+)?$/', $name)) {
        $error = "Invalid name! Only letters, single space, and one dot (.) allowed (e.g. Ram Bahadur Thapa or Sita K.C.)";
    }
    // Prevent multiple dots or dot at start/end
    elseif (substr_count($name, '.') > 1 || preg_match('/^\.|\.$/', $name)) {
        $error = "Only one dot (.) allowed, and not at start/end of name!";
    }
    // Email must be Gmail
    elseif (!str_ends_with($email, '@gmail.com') || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Only valid Gmail addresses allowed! (e.g. ram.thapa@gmail.com)";
    }
    // Password minimum 8 chars
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long!";
    }
    else {
        // Check duplicate email
        $check = $pdo->prepare("SELECT id FROM students WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = "This Gmail is already registered!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO students (name, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed])) {
                $success = "Student <strong>" . htmlspecialchars($name) . "</strong> added successfully!";
            } else {
                $error = "Failed to add student. Try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Student - Tech Gurukul</title>
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
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            max-width: 600px;
            margin: 0 auto;
        }

        label {
            display: block;
            margin: 20px 0 10px;
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
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

        .error-text {
            color: #dc3545;
            font-weight: bold;
            font-size: 14px;
            margin-top: 8px;
            display: block;
        }

        .submit-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            border: none;
            border-radius: 50px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 30px;
            transition: all 0.3s;
        }
        .submit-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(40,167,69,0.4);
        }

        .msg {
            padding: 20px;
            border-radius: 14px;
            text-align: center;
            font-weight: bold;
            margin: 30px 0;
            font-size: 18px;
        }
        .success { background:#d4edda; color:#155724; border:2px solid #c3e6cb; }
        .error   { background:#f8d7da; color:#721c24; border:2px solid #f5c6cb; }

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
    <a href="create_students.php" class="active">Add Student</a>
    <a href="manage_students.php">Manage Students</a>
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
        <h1>Add New Student</h1>
        <p>Create a new student account instantly</p>
    </div>

    <?php if ($success): ?>
        <div class="msg success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="msg error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" onsubmit="return validateForm()">
            <div>
                <label>Full Name <span style="color:red;">*</span></label>
                <input type="text" name="name" id="name" required placeholder="e.g. Ram Bahadur Thapa or Sita K.C." value="<?= htmlspecialchars($raw_name ?? '') ?>">
                <span id="nameError" class="error-text"></span>
            </div>

            <div>
                <label>Email Address <span style="color:red;">*</span></label>
                <input type="text" name="email" id="email" required placeholder="example@gmail.com" value="<?= htmlspecialchars($email ?? '') ?>">
                <span id="emailError" class="error-text"></span>
            </div>

            <div>
                <label>Password <span style="color:red;">*</span></label>
                <input type="password" name="password" id="password" required placeholder="Minimum 8 characters">
                <span id="passError" class="error-text"></span>
            </div>

            <button type="submit" class="submit-btn">Add Student</button>
        </form>
    </div>

</div>

<script>
function validateForm() {
    let valid = true;
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim().toLowerCase();
    const pass = document.getElementById('password').value;

    document.getElementById('nameError').textContent = '';
    document.getElementById('emailError').textContent = '';
    document.getElementById('passError').textContent = '';

    // Name validation
    if (!name) {
        document.getElementById('nameError').textContent = 'Name is required!';
        valid = false;
    }
    else if (!/^[a-zA-Z\s]+(\.[a-zA-Z]+)?$/.test(name)) {
        document.getElementById('nameError').textContent = 'Only letters, space, and one dot (.) allowed!';
        valid = false;
    }
    else if (/\s{2,}/.test(name)) {
        document.getElementById('nameError').textContent = 'No multiple spaces allowed!';
        valid = false;
    }
    else if (/\.$|^\./.test(name)) {
        document.getElementById('nameError').textContent = 'Dot (.) cannot be at start or end!';
        valid = false;
    }

    // Email
    if (!email.endsWith('@gmail.com')) {
        document.getElementById('emailError').textContent = 'Only Gmail allowed!';
        valid = false;
    }

    // Password
    if (pass.length < 8) {
        document.getElementById('passError').textContent = 'Password too short! Minimum 8 characters';
        valid = false;
    }

    return valid;
}

// Live validation
document.getElementById('name').addEventListener('input', function() {
    const val = this.value;
    if (val && !/^[a-zA-Z\s.]*$/.test(val)) {
        document.getElementById('nameError').textContent = 'Only letters, space, and one dot (.) allowed!';
    } else if (val && /\s{2,}/.test(val)) {
        document.getElementById('nameError').textContent = 'Remove extra spaces!';
    } else {
        document.getElementById('nameError').textContent = '';
    }
});
</script>

</body>
</html>