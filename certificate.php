<?php
session_start();
require 'db.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    die("Access denied.");
}

$student_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$course_id) {
    die("Invalid course ID.");
}

// Fetch enrollment data
$stmt = $pdo->prepare("
    SELECT s.name AS student_name, c.title AS course_title, c.duration_value, c.duration_type, sc.enrolled_at
    FROM student_courses sc
    JOIN students s ON sc.student_id = s.id
    JOIN courses c ON sc.course_id = c.id
    WHERE sc.student_id = ? AND sc.course_id = ? AND sc.payment_status = 'paid'
");
$stmt->execute([$student_id, $course_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$enrollment) {
    die("You are not enrolled in this course or payment is incomplete.");
}

// Check if course duration is completed
$start = new DateTime($enrollment['enrolled_at']);
$now = new DateTime();
$diff = $start->diff($now);
$daysPassed = $diff->days;

$totalDays = $enrollment['duration_type'] === 'months'
    ? $enrollment['duration_value'] * 30
    : $enrollment['duration_value'] * 7;

if ($daysPassed < $totalDays) {
    $_SESSION['error_msg'] = "Certificate is available only after completing the full course duration.";
    header("Location: student_progress.php");
    exit;
}

// Certificate details
$completionDate = date('F d, Y');
$certID = "TG-" . $course_id . "-" . $student_id . "-" . date('Y');

// Load TCPDF
require_once('tcpdf/tcpdf.php');

// Create PDF in A5 Landscape (smaller, elegant size)
$pdf = new TCPDF('L', PDF_UNIT, 'A5', true, 'UTF-8', false);

// Set document info
$pdf->SetCreator('Tech Gurukul');
$pdf->SetAuthor('Tech Gurukul');
$pdf->SetTitle('Certificate of Completion');
$pdf->SetSubject('Course Completion Certificate');

// Remove header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Add a page
$pdf->AddPage();

// Smaller margins for better fit
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(false);

// HTML content - adjusted for smaller A5 size
$html = '
<style>
    body { font-family: times; font-size: 13pt; color: #333; }
    .border { 
        position: absolute; top: 15px; left: 15px; right: 15px; bottom: 15px;
        border: 6px double #663399; 
    }
    .inner-border { 
        position: absolute; top: 25px; left: 25px; right: 25px; bottom: 25px;
        border: 2px solid #d8b4fe; 
    }
    .logo { text-align: center; margin: 15px 0; }
    .logo img { height: 60px; }
    .title { text-align: center; font-size: 36px; color: #663399; margin: 25px 0 15px; font-weight: bold; }
    .subtitle { text-align: center; font-size: 20px; color: #444; margin-bottom: 30px; }
    .student-name { text-align: center; font-size: 32px; color: #28a745; font-weight: bold; margin: 30px 0; }
    .course { text-align: center; font-size: 20px; color: #333; margin: 20px 0; line-height: 1.5; }
    .date { text-align: center; font-size: 18px; color: #555; margin: 40px 0 60px; }
    .signature { display: flex; justify-content: space-around; margin-top: 40px; font-size: 16px; }
    .signature-line { border-top: 2px solid #333; width: 220px; margin: 15px auto 8px; }
    .cert-id { position: absolute; bottom: 25px; right: 40px; font-size: 13px; color: #777; }
</style>

<div style="position:relative; width:100%; height:100%;">
    <div class="border"></div>
    <div class="inner-border"></div>

    <div class="logo">
        <img src="uploads/logo.jpg" alt="Tech Gurukul">
    </div>

    <div class="title">Certificate of Completion</div>
    <div class="subtitle">This is to certify that</div>
    <div class="student-name">' . htmlspecialchars($enrollment['student_name']) . '</div>

    <div class="course">
        has successfully completed the course<br>
        <strong>"' . htmlspecialchars($enrollment['course_title']) . '"</strong>
    </div>

    <div class="date">on ' . $completionDate . '</div>

    <div class="signature">
        <div style="text-align:center;">
            <div class="signature-line"></div>
            <div>Ishab<br>Director, Tech Gurukul</div>
        </div>
        <div style="text-align:center;">
            <div class="signature-line"></div>
            <div>Tech Gurukul<br>Official Seal</div>
        </div>
    </div>

    <div class="cert-id">Certificate ID: ' . $certID . '</div>
</div>
';

// Write HTML to PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$filename = "Certificate_" . preg_replace('/[^a-zA-Z0-9]/', '_', $enrollment['course_title']) . "_" . $enrollment['student_name'] . ".pdf";
$pdf->Output($filename, 'D'); // 'D' = Download

exit;