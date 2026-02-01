<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_courses.php');
    exit;
}

$course_id = (int)$_GET['id'];
$error = '';
$success = '';

// Fetch course
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: admin_courses.php');
    exit;
}

// Decode existing files
$existingFiles = $course['files'] ? json_decode($course['files'], true) : [];

// Fetch existing mock test questions grouped by week
$mockStmt = $pdo->prepare("SELECT * FROM mock_tests WHERE course_id = ? ORDER BY week_number, id");
$mockStmt->execute([$course_id]);
$existingQuestions = [];
while ($q = $mockStmt->fetch(PDO::FETCH_ASSOC)) {
    $existingQuestions[$q['week_number']][] = $q;
}

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
        $files = $existingFiles; // Start with existing files

        $uploadDir = 'uploads/courses/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        // Handle new file uploads
        for ($w = 1; $w <= $weeks; $w++) {
            $inputName = "week_$w";
            if (!empty($_FILES[$inputName]['name'][0])) {
                foreach ($_FILES[$inputName]['name'] as $key => $name) {
                    if ($_FILES[$inputName]['error'][$key] === 0) {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $allowed = ['pdf','mp4','mov','avi','mkv','jpg','png','jpeg','zip'];
                        if (in_array($ext, $allowed)) {
                            $newName = uniqid("course{$course_id}_week{$w}_") . '.' . $ext;
                            $target = $uploadDir . $newName;
                            if (move_uploaded_file($_FILES[$inputName]['tmp_name'][$key], $target)) {
                                $files[$w][] = [
                                    'original_name' => $name,
                                    'path' => $target,
                                    'type' => $ext
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Update course basic info and files
        $update = $pdo->prepare("UPDATE courses SET 
            title=?, description=?, price=?, duration_value=?, duration_type=?, files=? 
            WHERE id=?");

        if ($update->execute([
            $title, $description, $price, $duration_value, $duration_type,
            json_encode($files, JSON_UNESCAPED_SLASHES), $course_id
        ])) {
            // === Handle Mock Test Questions ===
            // First: Delete all existing questions for this course
            $pdo->prepare("DELETE FROM mock_tests WHERE course_id = ?")->execute([$course_id]);

            // Then: Insert updated ones
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
                            $course_id, $week, $question, $a, $b, $c, $d, $correct, $explanation
                        ]);
                    }
                }
            }

            $success = "Course updated successfully with materials and mock tests!";
            
            // Refresh data
            $stmt->execute([$course_id]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            $existingFiles = json_decode($course['files'], true);
            
            // Refresh questions
            $mockStmt->execute([$course_id]);
            $existingQuestions = [];
            while ($q = $mockStmt->fetch(PDO::FETCH_ASSOC)) {
                $existingQuestions[$q['week_number']][] = $q;
            }
        } else {
            $error = "Failed to update course.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - Tech Gurukul</title>
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
            gap: 30px;
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
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #663399;
            box-shadow: 0 0 0 5px rgba(102,51,153,0.15);
        }

        .duration-row {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .week-file {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 22px;
            border-radius: 14px;
            margin: 18px 0;
            border: 2px dashed #663399;
        }
        .week-file:hover { background: #f0e6ff; border-style: solid; }
        .current-file {
            background: #d4edda;
            color: #155724;
            padding: 8px 14px;
            border-radius: 30px;
            font-size: 14px;
            display: inline-block;
            margin: 8px 8px 8px 0;
        }
        .file-input {
            padding: 14px;
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
            background: linear-gradient(135deg, #ffc107, #ff8f00);
            color: #212529;
            padding: 20px 40px;
            border: none;
            border-radius: 50px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 40px;
        }

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

        .back-link {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: #663399;
            font-weight: bold;
            font-size: 17px;
            text-decoration: none;
        }

        .sidebar {
            position: fixed;
            left: 0; top: 0; width: 300px; height: 100vh;
            background: linear-gradient(135deg, #663399, #552288);
            padding-top: 30px;
            box-shadow: 5px 0 15px rgba(0,0,0,0.2);
        }
        .sidebar a {
            display: block;
            color: white;
            padding: 18px 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            border-left: 5px solid transparent;
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
    <a href="publish_message.php">Messages</a>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">
    <div class="welcome">
        <h1>Edit Course</h1>
        <p>Update "<strong><?= htmlspecialchars($course['title']) ?></strong>"</p>
    </div>

    <div class="form-container">
        <?php if ($success): ?>
            <div class="message success"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div>
                    <label>Course Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($course['title']) ?>" required>
                </div>
                <div>
                    <label>Price (₹)</label>
                    <input type="number" name="price" value="<?= $course['price'] ?>" required min="0">
                </div>

                <div class="full-width">
                    <label>Description</label>
                    <textarea name="description" rows="6"><?= htmlspecialchars($course['description'] ?? '') ?></textarea>
                </div>

                <div class="full-width">
                    <label>Course Duration</label>
                    <div class="duration-row">
                        <input type="number" id="duration_value" name="duration_value" 
                               value="<?= $course['duration_value'] ?>" min="1" required>
                        <select id="duration_type" name="duration_type">
                            <option value="weeks" <?= $course['duration_type']==='weeks'?'selected':'' ?>>Weeks</option>
                            <option value="months" <?= $course['duration_type']==='months'?'selected':'' ?>>Months</option>
                        </select>
                    </div>
                </div>

                <div class="full-width">
                    <label>Weekly Materials</label>
                    <p style="color:#666;">Current files shown. Upload new ones to add more.</p>
                    <div id="week-files-container"></div>
                </div>

                <div class="full-width">
                    <label style="font-size:18px; color:#007bff;">Weekly Mock Tests</label>
                    <p style="color:#555;">Edit or add MCQs. Students need ≥70% to complete week.</p>
                    <div id="mock-tests-container"></div>
                </div>
            </div>

            <button type="submit" class="submit-btn">Update Course</button>
        </form>

        <a href="admin_courses.php" class="back-link">← Back to Courses List</a>
    </div>
</div>

<script>
const existingFiles = <?= json_encode($existingFiles) ?>;
const existingQuestions = <?= json_encode($existingQuestions) ?>;

function updateForm() {
    const type = document.getElementById('duration_type').value;
    const value = parseInt(document.getElementById('duration_value').value) || 0;
    const weeks = type === 'months' ? value * 4 : value;

    const filesContainer = document.getElementById('week-files-container');
    const mockContainer = document.getElementById('mock-tests-container');
    filesContainer.innerHTML = '';
    mockContainer.innerHTML = '';

    for (let i = 1; i <= weeks; i++) {
        // Weekly Files
        const fileDiv = document.createElement('div');
        fileDiv.className = 'week-file';

        let currentFilesHtml = '';
        if (existingFiles[i]) {
            existingFiles[i].forEach(f => {
                currentFilesHtml += `<span class="current-file">${f.original_name}</span>`;
            });
        }

        fileDiv.innerHTML = `
            <label>Week ${i} Materials ${currentFilesHtml ? '<br>' + currentFilesHtml : ''}</label>
            <input type="file" name="week_${i}[]" class="file-input" multiple 
                   accept=".pdf,.mp4,.mov,.avi,.mkv,.jpg,.png,.jpeg,.zip">
        `;
        filesContainer.appendChild(fileDiv);

        // Mock Tests
        const mockDiv = document.createElement('div');
        mockDiv.className = 'week-file';
        mockDiv.innerHTML = `
            <label style="color:#007bff; font-weight:bold;">Week ${i} - Mock Test Questions</label>
            <div id="questions-week${i}"></div>
            <button type="button" class="add-question-btn" onclick="addQuestion(${i})">+ Add Question</button>
        `;
        mockContainer.appendChild(mockDiv);

        // Load existing questions
        const qContainer = document.getElementById('questions-week' + i);
        if (existingQuestions[i]) {
            existingQuestions[i].forEach((q, idx) => {
                addQuestion(i, q, idx);
            });
        }
    }
}

function addQuestion(week, questionData = null, index = null) {
    const container = document.getElementById('questions-week' + week);
    const qIndex = index !== null ? index : container.children.length;

    const block = document.createElement('div');
    block.className = 'question-block';

    const qText = questionData ? questionData.question : '';
    const a = questionData ? questionData.option_a : '';
    const b = questionData ? questionData.option_b : '';
    const c = questionData ? questionData.option_c : '';
    const d = questionData ? questionData.option_d : '';
    const correct = questionData ? questionData.correct_option : 'A';
    const exp = questionData ? questionData.explanation : '';

    block.innerHTML = `
        <button type="button" class="remove-question" onclick="this.parentElement.remove()">×</button>
        <strong>Question ${qIndex + 1}</strong><br><br>
        <textarea name="mock_questions[${week}][${qIndex}][question]" rows="3" 
                  placeholder="Enter question" style="width:100%; padding:12px;" required>${qText}</textarea><br><br>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
            <div><label>A:</label> <input type="text" name="mock_questions[${week}][${qIndex}][a]" value="${a}" required></div>
            <div><label>B:</label> <input type="text" name="mock_questions[${week}][${qIndex}][b]" value="${b}" required></div>
            <div><label>C:</label> <input type="text" name="mock_questions[${week}][${qIndex}][c]" value="${c}" required></div>
            <div><label>D:</label> <input type="text" name="mock_questions[${week}][${qIndex}][d]" value="${d}" required></div>
        </div><br>

        <label>Correct Answer:</label>
        <select name="mock_questions[${week}][${qIndex}][correct]" required>
            <option value="A" ${correct==='A'?'selected':''}>A</option>
            <option value="B" ${correct==='B'?'selected':''}>B</option>
            <option value="C" ${correct==='C'?'selected':''}>C</option>
            <option value="D" ${correct==='D'?'selected':''}>D</option>
        </select><br><br>

        <label>Explanation (Optional):</label><br>
        <textarea name="mock_questions[${week}][${qIndex}][explanation]" rows="2" 
                  style="width:100%; padding:12px;">${exp}</textarea>
    `;

    container.appendChild(block);
}

// Initialize
window.onload = updateForm;
document.getElementById('duration_type').addEventListener('change', updateForm);
document.getElementById('duration_value').addEventListener('input', updateForm);
</script>

</body>
</html>