<?php
include "db.php"; // assumes $pdo connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = $_POST["email"];
    $passwordRaw = $_POST["password"];

    // Validation
    if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
        $error = "Name can only contain letters and spaces.";
    } elseif (strlen($passwordRaw) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check for duplicate email
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already exists.";
        } else {
            // Hash password and insert
            $password = password_hash($passwordRaw, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $email, $password])) {
                header("Location: login.php");
                exit;
            } else {
                $error = "Error registering admin.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register Admin</title>
    <link rel="stylesheet" href="css/register.css">
</head>
<body>

<form method="POST" novalidate>
  <h2>Register Admin</h2>
  <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  
  <label for="name">Full Name:</label>
  <input type="text" id="name" name="name" required value="<?= isset($name) ? htmlspecialchars($name) : '' ?>">

  <label for="email">Email:</label>
  <input type="email" id="email" name="email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">

  <label for="password">Password:</label>
  <input type="password" id="password" name="password" required>

  <input type="submit" value="Register Admin">

  <p>Already have an account? <a href="login.php">Login here</a></p>
</form>

</body>
</html>
