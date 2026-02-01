<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch student
$stmt = $pdo->prepare("SELECT name, email, created_at FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) die("Student not found.");

// Fetch enrollments
$stmt = $pdo->prepare("
    SELECT 
        c.title, 
        c.description,
        c.duration_type, 
        c.duration_value,
        sc.enrolled_at,
        sc.completed_weeks
    FROM student_courses sc
    JOIN courses c ON sc.course_id = c.id
    WHERE sc.student_id = ? AND sc.payment_status = 'paid'
    ORDER BY sc.enrolled_at DESC
");
$stmt->execute([$student_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate progress
function calculateProgress($completedWeeksJson, $totalWeeks) {
    $completed = json_decode($completedWeeksJson ?? '[]', true);
    if (!is_array($completed)) $completed = [];
    $count = count($completed);
    if ($totalWeeks == 0) return 0;
    return round(($count / $totalWeeks) * 100);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Learning Progress Report - <?= htmlspecialchars($student['name']) ?></title>
<style>
    @page { margin: 20mm; size: A4 portrait; }
    body { 
        margin: 0; 
        padding: 0; 
        background: #f5f0ff; 
        font-family: 'Georgia', 'Times New Roman', serif; 
        color: #333;
    }
    .report {
        max-width: 210mm; 
        min-height: 297mm; /* A4 height */
        background: white; 
        margin: 20px auto; 
        padding: 40px 50px; 
        box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
        position: relative;
        border: 1px solid #eee;
    }
    .border {
        position: absolute;
        top: 20px; left: 20px; right: 20px; bottom: 20px;
        border: 8px double #663399;
        pointer-events: none;
    }
    .inner-border {
        position: absolute;
        top: 35px; left: 35px; right: 35px; bottom: 35px;
        border: 2px solid #d8b4fe;
        pointer-events: none;
    }
    .logo {
        text-align: center;
        margin-bottom: 30px;
    }
    .logo img {
        height: 80px;
    }
    .title {
        text-align: center;
        font-size: 42px;
        color: #663399;
        margin: 30px 0 20px;
        font-weight: bold;
    }
    .subtitle {
        text-align: center;
        font-size: 24px;
        color: #555;
        margin-bottom: 40px;
    }
    .student-info {
        text-align: center;
        font-size: 26px;
        margin: 40px 0;
    }
    .student-name {
        font-size: 38px;
        color: #28a745;
        font-weight: bold;
        margin: 15px 0;
    }
    .progress-table {
        width: 100%;
        border-collapse: collapse;
        margin: 40px 0;
        font-size: 18px;
    }
    .progress-table th {
        background: #663399;
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: bold;
    }
    .progress-table td {
        padding: 18px 15px;
        border-bottom: 1px solid #ddd;
        vertical-align: top;
    }
    .progress-bar {
        height: 28px;
        background: #eee;
        border-radius: 14px;
        overflow: hidden;
        margin: 10px 0;
    }
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #28a745, #20c997);
        text-align: center;
        color: white;
        font-weight: bold;
        line-height: 28px;
        border-radius: 14px;
        min-width: 50px; /* Ensures % visible even at 0% */
    }
    .footer {
        margin-top: 80px;
        text-align: center;
        font-size: 18px;
        color: #777;
    }
    .signature {
        margin-top: 60px;
        text-align: right;
        font-size: 20px;
    }
    .signature-line {
        border-top: 2px solid #333;
        width: 300px;
        margin: 20px 0 10px auto;
    }
    @media print {
        body { background: none; }
        .report { box-shadow: none; margin: 0; max-width: none; width: 100%; }
        @page { size: A4 portrait; margin: 20mm; }
    }
</style>
</head>
<body>

<div class="report">
    <div class="border"></div>
    <div class="inner-border"></div>

    <div class="logo">
        <img src="uploads/logo.jpg" alt="Tech Gurukul Logo">
    </div>

    <div class="title">Learning Progress Report</div>

    <div class="subtitle">Official Academic Progress Summary</div>

    <div class="student-info">
        This report summarizes the learning progress of<br>
        <div class="student-name"><?= htmlspecialchars($student['name']) ?></div>
        <div>Email: <?= htmlspecialchars($student['email']) ?></div>
        <div>Member Since: <?= date('F d, Y', strtotime($student['created_at'])) ?></div>
    </div>

    <table class="progress-table">
        <thead>
            <tr>
                <th style="width:35%;">Course</th>
                <th style="width:15%;">Enrolled On</th>
                <th style="width:20%;">Weeks Completed</th>
                <th style="width:30%;">Overall Progress</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($courses)): ?>
            <tr>
                <td colspan="4" style="text-align:center; padding:60px; color:#999; font-style:italic; font-size:20px;">
                    No courses enrolled yet. Start your learning journey today!
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($courses as $course):
                    $totalWeeks = ($course['duration_type'] === 'months') 
                        ? $course['duration_value'] * 4 
                        : $course['duration_value'];
                    $completedCount = count(json_decode($course['completed_weeks'] ?? '[]', true));
                    $progress = calculateProgress($course['completed_weeks'], $totalWeeks);
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($course['title']) ?></strong><br>
                        <small style="color:#666;"><?= htmlspecialchars($course['description']) ?></small>
                    </td>
                    <td><?= date('d M Y', strtotime($course['enrolled_at'])) ?></td>
                    <td><strong><?= $completedCount ?> / <?= $totalWeeks ?></strong></td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $progress ?>%">
                                <?= $progress ?>%
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Report Generated on <?= date('F d, Y') ?><br>
        <strong>Tech Gurukul</strong> — Empowering Lifelong Learning
    </div>

    <div class="signature">
        <div class="signature-line"></div>
        <div>Ishab<br>Director, Tech Gurukul</div>
    </div>
</div>

<script>
    // Auto open print dialog
    window.onload = function() {
        window.print();
    }
</script>

</body>
</html>