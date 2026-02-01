<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$student_name = htmlspecialchars($_SESSION['user_name'] ?? 'Student');

$student = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$student->execute([$student_id]);
$student = $student->fetch(PDO::FETCH_ASSOC);

$referralCode = $student['referral_code'] ?? 'N/A';

// Referral count & list
$referralCountStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE referrer_id = ?");
$referralCountStmt->execute([$student_id]);
$referralCount = $referralCountStmt->fetchColumn();

$referredStudentsStmt = $pdo->prepare("SELECT name, created_at FROM students WHERE referrer_id = ? ORDER BY created_at DESC");
$referredStudentsStmt->execute([$student_id]);
$referredStudents = $referredStudentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Enrollments with completed_weeks
$enrollments = $pdo->prepare("
    SELECT sc.*, c.title, c.description, c.duration_type, c.duration_value, c.price 
    FROM student_courses sc 
    JOIN courses c ON sc.course_id = c.id 
    WHERE sc.student_id = ? AND sc.payment_status = 'paid'
    ORDER BY sc.enrolled_at DESC
");
$enrollments->execute([$student_id]);
$courses = $enrollments->fetchAll(PDO::FETCH_ASSOC);

// Calculate progress
function calculateProgress($completedWeeksJson, $totalWeeks) {
    $completed = json_decode($completedWeeksJson ?? '[]', true);
    if (!is_array($completed)) $completed = [];
    $completedCount = count($completed);
    if ($totalWeeks == 0) return 0;
    return min(100, round(($completedCount / $totalWeeks) * 100));
}

// Achievements
$achieveStmt = $pdo->prepare("
    SELECT title, description, icon, unlocked_at 
    FROM student_achievements 
    WHERE student_id = ? 
    ORDER BY unlocked_at DESC
");
$achieveStmt->execute([$student_id]);
$achievements = $achieveStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile & Progress - Tech Gurukul</title>
<link rel="stylesheet" href="css/student_dashboard.css">
<style>
    /* Your existing styles remain unchanged */
    .navbar { 
        display:flex; justify-content:space-between; align-items:center; 
        background:#663399; padding:15px 20px; color:white; 
        position:fixed; top:0; left:0; width:100%; z-index:1000; 
        box-shadow:0 4px 15px rgba(0,0,0,0.1);
    }
    .navbar .logo { font-weight:bold; font-size:1.3rem; display:flex; align-items:center; }
    .navbar .logo img { height:40px; margin-right:10px; }
    .navbar ul { display:flex; list-style:none; margin:0; padding:0; gap:20px; }
    .navbar ul li a { color:white; text-decoration:none; padding:8px 16px; border-radius:8px; transition:0.3s; font-weight:500; }
    .navbar ul li a:hover { background:#551a8b; }

    .nav-toggle { display:none; background:none; border:none; cursor:pointer; padding:10px; }
    .nav-toggle span { display:block; width:25px; height:3px; background:white; margin:5px 0; transition:0.3s; }

    .sub-navbar { background:#552288; padding:15px 30px; display:flex; justify-content:center; gap:30px; flex-wrap:wrap; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
    .sub-navbar ul { list-style:none; margin:0; padding:0; display:flex; gap:20px; flex-wrap:wrap; justify-content:center; }
    .sub-navbar ul li a { color:white; text-decoration:none; font-weight:600; padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.1); transition:0.3s; }
    .sub-navbar ul li a:hover { background:#663399; transform:translateY(-3px); }
    .sub-nav-toggle { display:none; background:none; border:none; cursor:pointer; }
    .sub-nav-toggle span { display:block; width:30px; height:4px; background:white; margin:6px 0; border-radius:2px; }

    body { padding-top:90px; background:#f5f7fa; font-family:'Segoe UI',Arial,sans-serif; margin:0; color:#333; }
    .container { max-width:1100px; margin:20px auto; padding:0 20px; }
    .section { background:white; margin:30px 0; padding:30px; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,0.08); }
    h2 { color:#663399; border-bottom:4px solid #e0c4ff; padding-bottom:15px; margin-bottom:25px; font-size:1.8rem; }

    .progress-bar { height:30px; background:#eee; border-radius:15px; overflow:hidden; margin:20px 0; }
    .progress-fill { height:100%; background:linear-gradient(90deg,#28a745,#20c997); width:0%; transition:width 1.8s ease; border-radius:15px; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; }

    .btn { padding:12px 24px; border:none; border-radius:50px; font-weight:bold; cursor:pointer; text-decoration:none; display:inline-block; margin:8px 10px 8px 0; transition:all 0.3s; font-size:1rem; }
    .certificate-btn { background:#ffc107; color:#000; }
    .certificate-btn:hover { background:#e0a800; }
    .certificate-btn:disabled { background:#ccc; cursor:not-allowed; opacity:0.7; }
    .enrolled-btn { background:#28a745; color:white; }

    .achievements-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:25px; margin-top:20px; }
    .achievement-card { background:linear-gradient(135deg,#f8f4ff,#e8dcff); border:2px solid #d8b4fe; border-radius:16px; padding:25px; text-align:center; transition:all 0.4s ease; box-shadow:0 6px 20px rgba(0,0,0,0.08); }
    .achievement-card:hover { transform:translateY(-10px); box-shadow:0 15px 35px rgba(102,51,153,0.2); }
    .achievement-card .icon { font-size:4rem; margin-bottom:15px; }

    @media (max-width: 768px) {
        .nav-toggle, .sub-nav-toggle { display:block; }
        .navbar ul { position:absolute; top:100%; left:0; width:100%; background:#663399; flex-direction:column; padding:20px; display:none; box-shadow:0 10px 20px rgba(0,0,0,0.2); }
        .navbar ul.show { display:flex; }
        .sub-navbar ul { display:none; flex-direction:column; width:100%; background:#552288; margin-top:15px; border-radius:10px; padding:15px 0; }
        .sub-navbar ul.show { display:flex; }
    }
</style>
</head>
<body>

<!-- Main Navbar -->
<nav class="navbar">
  <div class="logo">
    <img src="uploads/logo.jpg" alt="Tech Gurukul Logo"> Tech Gurukul
  </div>

  <!-- Hamburger Menu Button (visible on mobile) -->
  <button class="nav-toggle" aria-label="Toggle menu">
    <span></span><span></span><span></span>
  </button>

  <ul>
    <li><a href="student_dashboard.php">Home</a></li>
    <li><a href="student_dashboard.php#courses">Courses</a></li>
    <li><a href="student_dashboard.php#reviews">Reviews</a></li>
    <li><a href="student_progress.php">My Profile</a></li>
    <li><a href="logout.php" class="logout-btn">Logout</a></li>
  </ul>
</nav>

<!-- Sub Navbar -->
<nav class="sub-navbar">
  <button class="sub-nav-toggle" aria-label="Toggle menu">
    <span></span><span></span><span></span>
  </button>
  <ul>
    <li><a href="#progress">My Progress</a></li>
    <li><a href="#referral">Referral</a></li>
    <li><a href="#achievements">Achievements</a></li>
    <li><a href="edit_profile.php">Edit Profile</a></li>
    <li><a href="download_progress.php" target="_blank">Download Report</a></li>
  </ul>
</nav>

<div class="container">

  <!-- Profile Section -->
  <div class="section" id="profile">
    <h2>My Profile</h2>
    <p><strong>Name:</strong> <?= htmlspecialchars($student['name']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
    <p><strong>Member Since:</strong> <?= date('F d, Y', strtotime($student['created_at'])) ?></p>
  </div>

  <!-- Referral Section -->
  <div class="section" id="referral">
    <h2>Referral Program</h2>
    <p>
      <strong>Your Referral Code:</strong>
      <span id="referralCode" style="font-size:1.3rem; font-weight:bold; color:#663399; background:#f0e8ff; padding:8px 16px; border-radius:10px;">
        <?= htmlspecialchars($referralCode) ?>
      </span>
      <button onclick="copyReferralCode()" class="btn" style="background:#28a745;color:white; margin-left:15px;">
        Copy Code
      </button>
    </p>
    <p><strong>Total Referrals:</strong> <?= $referralCount ?></p>

    <?php if ($referralCount > 0): ?>
      <div style="background:#f8f9fa; padding:20px; border-radius:12px; margin-top:20px;">
        <strong>Referred Students:</strong>
        <ul style="margin-top:10px; padding-left:20px;">
          <?php foreach ($referredStudents as $ref): ?>
            <li>• <?= htmlspecialchars($ref['name']) ?> (Joined: <?= date('M d, Y', strtotime($ref['created_at'])) ?>)</li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

<!-- Activity Chart Section - GitHub Style (Fixed) -->
<section id="activity-chart" class="section">
    <h2>Your Learning Activity</h2>
    
    <div style="background:#f8f9fa; padding:30px; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,0.1);">
        <!-- Legend + Stats -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
            <div style="font-size:0.95rem; color:#666;">
                <span style="display:inline-block; width:13px; height:13px; background:#ebedf0; border-radius:3px; margin-right:6px;"></span> None
                <span style="display:inline-block; width:13px; height:13px; background:#9be9a8; border-radius:3px; margin:0 6px;"></span> Login Once to get
            </div>
            <div style="font-size:1rem; color:#663399; font-weight:bold;">
                Total Active Days: <span id="total-active-days">0</span>
            </div>
        </div>

        <!-- Chart Wrapper -->
        <div style="overflow-x:auto; padding:10px 0;">
            <div style="min-width:780px;">
                <div id="activity-grid" style="display:grid; grid-template-columns:repeat(53,13px); gap:3px; justify-content:start;"></div>
                <div id="month-labels" style="margin-top:12px; display:grid; grid-template-columns:repeat(53,13px); gap:3px; justify-content:start; font-size:0.8rem; color:#555; pointer-events:none;"></div>
            </div>
        </div>
    </div>
</section>

  <!-- Progress Section -->
  <div class="section" id="progress">
    <h2>My Learning Progress</h2>
    <?php if (empty($courses)): ?>
      <p style="text-align:center; padding:60px; color:#777;">
        No courses enrolled yet. <a href="student_dashboard.php#courses">Explore courses</a>
      </p>
    <?php else: ?>
      <?php foreach ($courses as $course):
          $totalWeeks = ($course['duration_type'] === 'months') ? $course['duration_value'] * 4 : $course['duration_value'];
          $progress = calculateProgress($course['completed_weeks'], $totalWeeks);
      ?>
        <div class="course-item">
          <h3><?= htmlspecialchars($course['title']) ?></h3>
          <p><?= htmlspecialchars($course['description']) ?></p>
          <small>Enrolled: <?= date('M d, Y', strtotime($course['enrolled_at'])) ?></small>

          <div class="progress-bar">
            <div class="progress-fill" style="width: <?= $progress ?>%">
              <?= $progress ?>%
            </div>
          </div>
          <p><strong><?= $progress ?>% Complete</strong></p>

          <div style="margin-top:20px;">
            <a href="course_view.php?course_id=<?= $course['course_id'] ?>" class="btn enrolled-btn">
              Continue Learning
            </a>
            <?php if ($progress >= 100): ?>
              <a href="certificate.php?course_id=<?= $course['course_id'] ?>" class="btn certificate-btn">
                Download Certificate
              </a>
            <?php else: ?>
              <button class="btn certificate-btn" disabled>Certificate (100% Required)</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Achievements Section -->
  <?php if (!empty($achievements)): ?>
  <div class="section" id="achievements">
    <h2>My Achievements</h2>
    <div class="achievements-grid">
      <?php foreach ($achievements as $ach): ?>
        <div class="achievement-card">
          <div class="icon">
            <?= $ach['icon'] === 'trophy' ? '🏆' : ($ach['icon'] === 'medal' ? '🥇' : '⭐') ?>
          </div>
          <h4><?= htmlspecialchars($ach['title']) ?></h4>
          <p><?= htmlspecialchars($ach['description']) ?></p>
          <small>Unlocked: <?= date('M d, Y', strtotime($ach['unlocked_at'])) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
// Copy Referral Code
function copyReferralCode() {
  const code = document.getElementById('referralCode').innerText.trim();
  navigator.clipboard.writeText(code).then(() => {
    alert('Referral code copied: ' + code);
  }).catch(() => {
    prompt('Copy this code:', code);
  });
}

// Mobile Nav Toggles
document.querySelector('.nav-toggle')?.addEventListener('click', () => {
  document.querySelector('.navbar ul').classList.toggle('show');
});

document.querySelector('.sub-nav-toggle')?.addEventListener('click', () => {
  document.querySelector('.sub-navbar ul').classList.toggle('show');
});

// GitHub-style Activity Chart - Perfect Alignment & Colors
document.addEventListener('DOMContentLoaded', function() {
    const grid = document.getElementById('activity-grid');
    const labelsContainer = document.getElementById('month-labels');
    const totalDaysEl = document.getElementById('total-active-days');
    
    const today = new Date();
    const activityMap = new Map();

    <?php
    $oneYearAgo = date('Y-m-d', strtotime('-365 days'));
    $activityStmt = $pdo->prepare("SELECT activity_date FROM student_activity WHERE student_id = ? AND activity_date >= ?");
    $activityStmt->execute([$student_id, $oneYearAgo]);
    $activeDates = $activityStmt->fetchAll(PDO::FETCH_COLUMN);
    echo "const activeDates = " . json_encode($activeDates) . ";";
    ?>

    // Build activity map
    activeDates.forEach(date => {
        activityMap.set(date, (activityMap.get(date) || 0) + 1);
    });

    let totalActive = 0;
    let currentMonth = '';
    const monthLabels = new Array(53).fill('');

    // Clear containers
    grid.innerHTML = '';
    labelsContainer.innerHTML = '';

    // Generate 371 squares (53 weeks × 7 days) to cover full year + alignment
    for (let i = 370; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(today.getDate() - i);
        const dateStr = date.toISOString().split('T')[0];
        const dayOfWeek = date.getDay(); // 0=Sunday ... 6=Saturday
        const monthName = date.toLocaleDateString('en-US', { month: 'short' });

        const count = activityMap.get(dateStr) || 0;
        if (count > 0) totalActive++;

        // Correct color tiers
        let color = '#ebedf0';
        if (count >= 1 && count <= 2) color = '#9be9a8';
        else if (count >= 3 && count <= 5) color = '#40c463';
        else if (count >= 6 && count <= 9) color = '#30a14e';
        else if (count >= 10) color = '#216e39';

        // Create square
        const square = document.createElement('div');
        square.style.width = '13px';
        square.style.height = '13px';
        square.style.backgroundColor = color;
        square.style.borderRadius = '3px';
        square.title = `${date.toLocaleDateString('en-US', {weekday:'long', year:'numeric', month:'long', day:'numeric'})}: ${count} login${count !== 1 ? 's' : ''}`;
        square.style.cursor = 'pointer';
        square.style.transition = 'all 0.2s';

        square.addEventListener('mouseenter', () => {
            square.style.transform = 'scale(1.6)';
            square.style.boxShadow = '0 0 10px rgba(0,0,0,0.3)';
        });
        square.addEventListener('mouseleave', () => {
            square.style.transform = 'scale(1)';
            square.style.boxShadow = 'none';
        });

        grid.appendChild(square);

        // Month labels: place on first Sunday of the month
        const weekIndex = Math.floor(i / 7);
        if (currentMonth !== monthName && dayOfWeek === 0) { // Sunday = start of new week
            currentMonth = monthName;
            monthLabels[52 - weekIndex] = monthName; // reverse index for left-to-right
        }
    }

    // Render month labels
    monthLabels.forEach((label, index) => {
        if (label) {
            const labelEl = document.createElement('div');
            labelEl.textContent = label;
            labelEl.style.gridColumn = index + 1;
            labelEl.style.textAlign = 'center';
            labelsContainer.appendChild(labelEl);
        }
    });

    totalDaysEl.textContent = totalActive;
});

</script>

</body>
</html>