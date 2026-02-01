<?php
session_start();
require "db.php";

$error = '';
$email = ''; // Initialize to avoid undefined variable warning

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';

    if ($email === '' || $password === '') {
        $error = "Please fill in both fields.";
    } else {
        // Check Admin First
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION["user_id"]    = $admin['id'];
            $_SESSION["user_name"]  = $admin['name'];
            $_SESSION["user_email"] = $admin['email'];
            $_SESSION["user_role"]  = "admin";
            header("Location: admin_dashboard.php");
            exit;
        }

        // Check Student
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM students WHERE email = ?");
        $stmt->execute([$email]);
        $student = $stmt->fetch();

        if ($student && password_verify($password, $student['password'])) {
            // Successful Student Login
            $_SESSION["user_id"]    = $student['id'];
            $_SESSION["user_name"]  = $student['name'];
            $_SESSION["user_email"] = $student['email'];
            $_SESSION["user_role"]  = "student";

            // === ACTIVITY TRACKING - NOW SAFE HERE ===
            $student_id = $student['id'];  // Define it properly
            $today = date('Y-m-d');

            try {
                $checkStmt = $pdo->prepare("SELECT id FROM student_activity WHERE student_id = ? AND activity_date = ?");
                $checkStmt->execute([$student_id, $today]);

                if ($checkStmt->rowCount() == 0) {
                    $insertStmt = $pdo->prepare("INSERT INTO student_activity (student_id, activity_date) VALUES (?, ?)");
                    $insertStmt->execute([$student_id, $today]);
                }
            } catch (PDOException $e) {
                // Optional: log error silently (don't break login)
                error_log("Activity tracking failed: " . $e->getMessage());
            }
            // === END ACTIVITY TRACKING ===

            // AI Recommendation Engine
            $userId = (int)$student['id'];
            $python = "C:/Users/DELL/AppData/Local/Programs/Python/Python313/python.exe";
            $script = "C:/xampp/htdocs/tech_gurukul/recommend.py";
            
            if (file_exists($python) && file_exists($script)) {
                $command = "\"$python\" \"$script\" $userId";
                // Run in background (Windows)
                pclose(popen("start /B " . $command, "r"));
            }

            header("Location: student_dashboard.php");
            exit;
        }

        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login • Tech Gurukul</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            padding: 50px 40px;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(102, 51, 153, 0.35);
            max-width: 460px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 8px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .logo {
            font-size: 42px;
            margin-bottom: 10px;
            color: #663399;
        }

        .input-group {
            position: relative;
            margin: 12px 0;
        }

        input {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            font-size: 16px;
            transition: all 0.3s;
            padding-right: 50px;
        }

        input:focus {
            outline: none;
            border-color: #663399;
            box-shadow: 0 0 0 5px rgba(102, 51, 153, 0.15);
        }

        .show-pass {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #663399;
            font-size: 18px;
            user-select: none;
        }

        button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 19px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s;
        }

        button:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(40, 167, 69, 0.4);
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            font-weight: bold;
            border: 2px solid #f5c6cb;
        }

        .links {
            margin-top: 30px;
            font-size: 15px;
        }

        .links a {
            color: #663399;
            font-weight: 600;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .divider {
            margin: 25px 0;
            color: #aaa;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0; right: 0;
            height: 1px;
            background: #ddd;
        }

        .divider span {
            background: rgba(255,255,255,0.95);
            padding: 0 15px;
        }

        @media (max-width: 480px) {
            .login-card { padding: 40px 25px; }
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo">Tech Gurukul</div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background:#d4edda; color:#155724; padding:20px; border-radius:12px; text-align:center; margin:20px; font-weight:bold;">
            <?= htmlspecialchars($_SESSION['success_msg']) ?>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <input 
                type="email" 
                name="email" 
                placeholder="you@example.com" 
                required 
                value="<?= htmlspecialchars($email) ?>"
                autocomplete="email"
            >
        </div>

        <div class="input-group">
            <input 
                type="password" 
                name="password" 
                id="password" 
                placeholder="Enter your password" 
                required 
                autocomplete="current-password"
            >
            <span class="show-pass" onclick="togglePass()">Show</span>
        </div>

        <button type="submit">Login Now</button>
    </form>

    <div class="divider"><span>or</span></div>

    <div class="links">
        New student? <a href="register_student.php">Register here</a>
    </div>
</div>

<script>
function togglePass() {
    const pass = document.getElementById('password');
    const toggle = document.querySelector('.show-pass');
    
    if (pass.type === 'password') {
        pass.type = 'text';
        toggle.textContent = 'Hide';
    } else {
        pass.type = 'password';
        toggle.textContent = 'Show';
    }
}
</script>

</body>
</html>