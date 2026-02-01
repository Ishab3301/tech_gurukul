<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $duration_value = (int)($_POST['duration_value'] ?? 0);
    $duration_type = $_POST['duration_type'] ?? 'weeks';

    if ($title === '') {
        $error = "Course title is required.";
    } elseif (!is_numeric($price) || $price < 0) {
        $error = "Please enter a valid price.";
    } elseif ($duration_value <= 0) {
        $error = "Duration must be greater than 0.";
    } else {
        $weeks = ($duration_type === 'months') ? $duration_value * 4 : $duration_value;
        $weeklyFiles = [];

        $uploadDir = 'uploads/courses/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        // Handle Weekly File Uploads
        for ($i = 1; $i <= $weeks; $i++) {
            $inputName = "week_files_$i";
            if (!empty($_FILES[$inputName]['name'][0])) {
                foreach ($_FILES[$inputName]['name'] as $key => $name) {
                    if ($_FILES[$inputName]['error'][$key] === 0) {
                        $file = [
                            'name' => $name,
                            'tmp_name' => $_FILES[$inputName]['tmp_name'][$key],
                            'type' => $_FILES[$inputName]['type'][$key]
                        ];
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $allowed = ['pdf', 'mp4', 'mov', 'avi', 'mkv', 'jpg', 'png', 'jpeg', 'zip'];

                        if (in_array($ext, $allowed)) {
                            $newName = uniqid('week' . $i . '_') . '.' . $ext;
                            $target = $uploadDir . $newName;
                            if (move_uploaded_file($file['tmp_name'], $target)) {
                                $weeklyFiles[$i][] = [
                                    'original_name' => $file['name'],
                                    'path' => $target,
                                    'type' => $ext
                                ];
                            }
                        }
                    }
                }
            }
        }

        $filesJson = json_encode($weeklyFiles, JSON_UNESCAPED_SLASHES);

        // Insert Course
        $stmt = $pdo->prepare("INSERT INTO courses 
            (title, description, price, created_at, duration_value, duration_type, files) 
            VALUES (?, ?, ?, NOW(), ?, ?, ?)");

        if ($stmt->execute([$title, $description, $price, $duration_value, $duration_type, $filesJson])) {
            $course_id = $pdo->lastInsertId();

            // Handle Mock Test Questions
            if (!empty($_POST['mock_questions'])) {
                $insertStmt = $pdo->prepare("INSERT INTO mock_tests 
                    (course_id, week_number, question, option_a, option_b, option_c, option_d, correct_option, explanation)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($_POST['mock_questions'] as $week => $questions) {
                    if (!is_numeric($week) || $week < 1 || $week > $weeks) continue;

                    foreach ($questions as $q) {
                        $question = trim($q['question'] ?? '');
                        $a = trim($q['a'] ?? '');
                        $b = trim($q['b'] ?? '');
                        $c = trim($q['c'] ?? '');
                        $d = trim($q['d'] ?? '');
                        $correct = strtoupper(trim($q['correct'] ?? ''));
                        $explanation = trim($q['explanation'] ?? '');

                        if ($question === '' || $a === '' || $b === '' || $c === '' || $d === '' || !in_array($correct, ['A','B','C','D'])) {
                            continue;
                        }

                        $insertStmt->execute([
                            $course_id,
                            $week,
                            $question,
                            $a, $b, $c, $d,
                            $correct,
                            $explanation
                        ]);
                    }
                }
            }

            $success = "Course <strong>\"$title\"</strong> created successfully with materials and mock tests!";
        } else {
            $error = "Failed to save course. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Course - Tech Gurukul</title>
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
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            max-width: 900px;
            margin: 0 auto;
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
        input[type="text"], input[type="number"], textarea, select {
            width: 100%;
            padding: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #663399;
            box-shadow: 0 0 0 5px rgba(102,51,153,0.15);
        }
        textarea { resize: vertical; min-height: 120px; }

        .duration-row {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .duration-row select, .duration-row input { flex: 1; }

        .week-file {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin: 15px 0;
            border: 2px dashed #663399;
        }
        .file-input {
            padding: 12px;
            background: white;
            border: 2px dashed #ccc;
            border-radius: 10px;
            width: 100%;
        }

        .question-block {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 12px;
            margin: 15px 0;
            border: 1px dashed #007bff;
            position: relative;
        }
        .remove-question {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
        }
        .add-question-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
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
            margin-top: 40px;
            transition: all 0.3s;
        }
        .submit-btn:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(40,167,69,0.4); }

        .message {
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            font-weight: bold;
            margin: 25px 0;
            font-size: 17px;
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
    </style>
</head>
<body>

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
        <h1>Create New Course</h1>
        <p>Add professional course with weekly materials and mock tests</p>
    </div>

    <div class="form-container">
        <?php if ($success): ?>
            <div class="message success"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="message error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div>
                    <label>Course Title</label>
                    <input type="text" name="title" required placeholder="e.g. Advanced MS Excel">
                </div>
                <div>
                    <label>Price (₹)</label>
                    <input type="number" name="price" required min="0" step="1" placeholder="2999">
                </div>

                <div class="full-width">
                    <label>Course Description</label>
                    <textarea name="description" rows="5" placeholder="Detailed course overview..."></textarea>
                </div>

                <div class="full-width">
                    <label>Course Duration</label>
                    <div class="duration-row">
                        <input type="number" id="duration_value" name="duration_value" min="1" value="4" required>
                        <select id="duration_type" name="duration_type">
                            <option value="weeks">Weeks</option>
                            <option value="months">Months</option>
                        </select>
                    </div>
                </div>

                <div class="full-width">
                    <label>Weekly Materials (PDFs, Videos, Images, ZIP)</label>
                    <div id="week-files-container"></div>
                    <small style="color:#666;">Multiple files allowed per week</small>
                </div>

                <div class="full-width">
                    <label style="font-size:18px; color:#007bff;">Weekly Mock Tests (Required for Completion)</label>
                    <p style="color:#555;">Add 5–10 MCQs per week. Students need ≥70% to complete week.</p>
                    <div id="mock-tests-container"></div>
                </div>
            </div>

            <button type="submit" class="submit-btn">Create Course</button>
        </form>
    </div>
</div>

<script>
function updateForm() {
    const type = document.getElementById('duration_type').value;
    const value = parseInt(document.getElementById('duration_value').value) || 0;
    const weeks = type === 'months' ? value * 4 : value;

    const filesContainer = document.getElementById('week-files-container');
    const mockContainer = document.getElementById('mock-tests-container');
    filesContainer.innerHTML = '';
    mockContainer.innerHTML = '';

    if (weeks > 0) {
        for (let i = 1; i <= weeks; i++) {
            // File Upload Section
            const fileDiv = document.createElement('div');
            fileDiv.className = 'week-file';
            fileDiv.innerHTML = `
                <label>Week ${i} Materials</label>
                <input type="file" name="week_files_${i}[]" class="file-input" multiple accept=".pdf,.mp4,.mov,.avi,.mkv,.jpg,.png,.jpeg,.zip">
            `;
            filesContainer.appendChild(fileDiv);

            // Mock Test Section
            const mockDiv = document.createElement('div');
            mockDiv.className = 'week-file';
            mockDiv.innerHTML = `
                <label style="color:#007bff; font-weight:bold;">Week ${i} - Mock Test Questions</label>
                <div id="questions-week${i}"></div>
                <button type="button" class="add-question-btn" onclick="addQuestion(${i})">+ Add Question</button>
            `;
            mockContainer.appendChild(mockDiv);
        }
    }
}

function addQuestion(week) {
    const container = document.getElementById('questions-week' + week);
    const index = container.children.length;

    const block = document.createElement('div');
    block.className = 'question-block';
    block.innerHTML = `
        <button type="button" class="remove-question" onclick="this.parentElement.remove()">×</button>
        <strong>Question ${index + 1}</strong><br><br>
        <textarea name="mock_questions[${week}][${index}][question]" rows="3" placeholder="Enter question" style="width:100%; padding:12px;" required></textarea><br><br>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
            <div><label>A:</label> <input type="text" name="mock_questions[${week}][${index}][a]" placeholder="Option A" style="width:100%; padding:12px;" required></div>
            <div><label>B:</label> <input type="text" name="mock_questions[${week}][${index}][b]" placeholder="Option B" style="width:100%; padding:12px;" required></div>
            <div><label>C:</label> <input type="text" name="mock_questions[${week}][${index}][c]" placeholder="Option C" style="width:100%; padding:12px;" required></div>
            <div><label>D:</label> <input type="text" name="mock_questions[${week}][${index}][d]" placeholder="Option D" style="width:100%; padding:12px;" required></div>
        </div><br>

        <label>Correct Answer:</label>
        <select name="mock_questions[${week}][${index}][correct]" style="padding:12px; width:200px;" required>
            <option value="A">A</option>
            <option value="B">B</option>
            <option value="C">C</option>
            <option value="D">D</option>
        </select><br><br>

        <label>Explanation (Optional):</label><br>
        <textarea name="mock_questions[${week}][${index}][explanation]" rows="2" placeholder="Why is this correct?" style="width:100%; padding:12px;"></textarea>
    `;
    container.appendChild(block);
}

// Initialize
document.getElementById('duration_type').addEventListener('change', updateForm);
document.getElementById('duration_value').addEventListener('input', updateForm);
window.onload = updateForm;
</script>

</body>
</html>