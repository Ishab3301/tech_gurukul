<?php
session_start();
require 'db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: student_dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $referral_code = trim(strtoupper($_POST['referral_code'] ?? ''));

    // Server-side validation
    if (empty($name) || !preg_match("/^[a-zA-Z\s]+(\.[a-zA-Z]+)?$/", $name) || preg_match("/\s{2,}/", $name) || preg_match("/^\.|\.$/", $name)) {
        $errors[] = "Invalid name format. Use: Ram Bahadur or Sita K.C.";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@gmail.com')) {
        $errors[] = "Please enter a valid Gmail address.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "This email is already registered.";
        }
    }

    // Validate referral code if provided
    $referrer_id = null;
    if (!empty($referral_code)) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE referral_code = ?");
        $stmt->execute([$referral_code]);
        $referrer = $stmt->fetch();
        if (!$referrer) {
            $errors[] = "Invalid referral code.";
        } else {
            $referrer_id = $referrer['id'];
        }
    }

    // If no errors → register and AUTO LOGIN
    if (empty($errors)) {
        // Generate unique referral code
        do {
            $new_referral_code = 'TG' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $stmt = $pdo->prepare("SELECT id FROM students WHERE referral_code = ?");
            $stmt->execute([$new_referral_code]);
        } while ($stmt->fetch());

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO students (name, email, password, referral_code, referrer_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        if ($stmt->execute([$name, $email, $hashed_password, $new_referral_code, $referrer_id])) {
            // === AUTO LOGIN AFTER REGISTRATION ===
            $stmt = $pdo->prepare("SELECT id, name FROM students WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = 'student';

                // Optional: Set a welcome flash message
                $_SESSION['success_msg'] = "Welcome, {$user['name']}! Your account has been created.";

                // Redirect to dashboard
                header('Location: login.php');
                exit;
            }
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Tech Gurukul</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        .container {
            background: white;
            padding: 50px 40px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(102,51,153,0.4);
            max-width: 480px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        .container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 8px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        h1 { text-align: center; color: #663399; font-size: 32px; margin-bottom: 10px; }
        p.subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 16px; }
        label { display: block; margin: 20px 0 8px; font-weight: 600; color: #333; }

        .input-group {
            position: relative;
            margin: 12px 0;
        }
        input {
            width: 100%;
            padding: 16px 50px 16px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            font-size: 16px;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #663399;
            box-shadow: 0 0 0 5px rgba(102,51,153,0.15);
        }

        .show-pass {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #663399;
            font-weight: 600;
            font-size: 14px;
            user-select: none;
        }

        .error-text {
            color: #dc3545;
            font-size: 14px;
            font-weight: bold;
            margin-top: 6px;
            display: block;
        }
        .msg { padding: 16px; border-radius: 12px; text-align: center; font-weight: bold; margin: 20px 0; font-size: 16px; }
        .error { background:#f8d7da; color:#721c24; border:2px solid #f5c6cb; }

        button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s;
        }
        button:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(40,167,69,0.4); }

        .login-link { text-align: center; margin-top: 25px; font-size: 15px; }
        .login-link a { color: #663399; font-weight: bold; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }

        @media (max-width: 480px) {
            .container { padding: 40px 25px; }
            h1 { font-size: 28px; }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Join Tech Gurukul</h1>
    <p class="subtitle">Create your student account</p>

    <?php if (!empty($errors)): ?>
        <div class="msg error">
            <?php foreach ($errors as $error): ?>
                • <?= htmlspecialchars($error) ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" onsubmit="return validateForm()">
        <div>
            <label>Full Name <span style="color:red;">*</span></label>
            <input type="text" name="name" id="name" required placeholder="e.g. Ram Bahadur Thapa or Sita K.C." value="<?= htmlspecialchars($name ?? '') ?>">
            <span id="nameError" class="error-text"></span>
        </div>

        <div>
            <label>Email Address <span style="color:red;">*</span></label>
            <input type="text" name="email" id="email" required placeholder="yourname@gmail.com" value="<?= htmlspecialchars($email ?? '') ?>">
            <span id="emailError" class="error-text"></span>
        </div>

        <div class="input-group">
            <label>Password <span style="color:red;">*</span></label>
            <input type="password" name="password" id="password" required placeholder="Minimum 8 characters">
            <span class="show-pass" onclick="togglePass('password', this)">Show</span>
            <span id="passError" class="error-text"></span>
        </div>

        <div class="input-group">
            <label>Confirm Password <span style="color:red;">*</span></label>
            <input type="password" name="confirm_password" id="confirm_password" required placeholder="Re-type your password">
            <span class="show-pass" onclick="togglePass('confirm_password', this)">Show</span>
            <span id="confirmError" class="error-text"></span>
        </div>

        <div>
            <label>Referral Code (Optional)</label>
            <input type="text" name="referral_code" placeholder="Enter code if referred" value="<?= htmlspecialchars($referral_code ?? '') ?>">
        </div>

        <button type="submit">Create Account</button>
    </form>

    <div class="login-link">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>

<script>
function togglePass(id, element) {
    const field = document.getElementById(id);
    if (field.type === 'password') {
        field.type = 'text';
        element.textContent = 'Hide';
    } else {
        field.type = 'password';
        element.textContent = 'Show';
    }
}

function validateForm() {
    let valid = true;
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim().toLowerCase();
    const pass = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;

    document.getElementById('nameError').textContent = '';
    document.getElementById('emailError').textContent = '';
    document.getElementById('passError').textContent = '';
    document.getElementById('confirmError').textContent = '';

    if (!/^[a-zA-Z\s]+(\.[a-zA-Z]+)?$/.test(name) || /\s{2,}/.test(name) || /\.$|^\./.test(name)) {
        document.getElementById('nameError').textContent = 'Invalid name! e.g. Ram Bahadur or Sita K.C.';
        valid = false;
    }
    if (!email.endsWith('@gmail.com')) {
        document.getElementById('emailError').textContent = 'Only Gmail addresses allowed!';
        valid = false;
    }
    if (pass.length < 8) {
        document.getElementById('passError').textContent = 'Password too short! Need 8+ characters';
        valid = false;
    }
    if (pass !== confirm) {
        document.getElementById('confirmError').textContent = 'Passwords do not match!';
        valid = false;
    }

    return valid;
}
</script>

</body>
</html>