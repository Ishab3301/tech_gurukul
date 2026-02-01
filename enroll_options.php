<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
if ($course_id <= 0) die("Invalid course.");

// Fetch course
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) die("Course not found.");

// Decode files JSON safely
$files = $course['files'] ? json_decode($course['files'], true) : [];
if (!is_array($files)) $files = [];

// Reindex old 0-indexed arrays to week numbers (1..n)
if (!isset($files[1]) && isset($files[0]) && array_keys($files) === range(0, count($files)-1)) {
    $reindexed = [];
    $i = 1;
    foreach ($files as $f) {
        if (is_array($f) && isset($f[0]) && isset($f[0]['path'])) {
            $reindexed[$i] = $f;
        } elseif (is_array($f) && isset($f['path'])) {
            $reindexed[$i][] = $f;
        }
        $i++;
    }
    $files = $reindexed;
}

// Total weeks
$weeks = $course['duration_type'] === 'months' ? $course['duration_value'] * 4 : $course['duration_value'];

// Check enrollment
$stmt = $pdo->prepare("SELECT * FROM student_courses WHERE student_id = ? AND course_id = ?");
$stmt->execute([$student_id, $course_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if a week is unlocked
function weekUnlocked($weekNumber, $enrolled_at) {
    if (!$enrolled_at) return true;
    $unlockDate = strtotime($enrolled_at . " + " . ($weekNumber - 1) . " week");
    return time() >= $unlockDate;
}

// Handle enrollment request
if (isset($_GET['enroll'])) {
    $exists = $pdo->prepare("SELECT 1 FROM student_courses WHERE student_id = ? AND course_id = ?");
    $exists->execute([$student_id, $course_id]);
    if (!$exists->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO student_courses (student_id, course_id, status, enrolled_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->execute([$student_id, $course_id]);
        header("Location: enroll_options.php?course_id=$course_id&msg=applied");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($course['title']) ?> - Tech Gurukul</title>
<link rel="stylesheet" href="css/student_dashboard.css">
<style>
body { padding-top: 90px; background:#f5f5f5; font-family: Arial, sans-serif; color:#333; }
.course-container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.08); margin: 30px auto; max-width: 950px; }
.course-header { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; padding-bottom:20px; border-bottom:1px solid #eee; margin-bottom:30px; flex-wrap:wrap;}
.course-info h2 { margin:0 0 10px 0; color:#663399; font-size:1.8rem; }
.course-action { text-align:right; min-width:160px; }
.week-container { border:1px solid #ddd; margin-bottom:15px; border-radius:6px; padding:16px; background:#fff; }
.week-title { font-weight:bold; margin-bottom:12px; color:#663399; font-size:1.15rem; display:flex; justify-content:space-between; align-items:center; }
.locked { color:#e74c3c; font-weight:bold; }
.countdown { margin-top:8px; font-size:0.95em; color:#e67e22; font-weight:bold; }
.file-list { margin-top:12px; }
.file-item { margin-bottom:18px; }
.file-item iframe { width:100%; height:500px; border:none; }
.file-item video { width:100%; max-height:520px; }
.small-btn { display:inline-block; padding:8px 12px; border-radius:8px; background:#f1f1f1; border:1px solid #e0e0e0; text-decoration:none; color:#333; margin-right:8px; }
.lock-badge { font-size:0.9rem; color:#fff; background:#e67e22; padding:6px 10px; border-radius:999px; }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <div class="logo">
    <img src="uploads/logo.jpg" alt="Logo" style="height: 40px; vertical-align: middle; margin-right: 8px;" />
    <span style="font-weight: bold; font-size: 1.1em;">Tech Gurukul</span>
  </div>
  <ul>
    <li><a href="student_dashboard.php">Home</a></li>
    <li><a href="student_dashboard.php#courses">Courses</a></li>
    <li><a href="my_courses.php">My Courses</a></li>
    <li><a href="student_progress.php">My Profile</a></li>
    <li><a href="logout.php" class="logout-btn">Logout</a></li>
  </ul>
  <button class="nav-toggle" aria-label="Toggle menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<div class="course-container">
  <div class="course-header">
    <div class="course-info">
      <h2><?= htmlspecialchars($course['title']) ?></h2>
      <p><?= nl2br(htmlspecialchars($course['description'])) ?></p>
    </div>
    <div class="course-action">
      <?php if (!$enrollment): ?>
        <a href="?course_id=<?= $course_id ?>&enroll=1" class="small-btn">Enroll Now</a>
      <?php elseif ($enrollment['status'] === 'pending'): ?>
        <span class="small-btn" style="background:#f8f9fa;">Applied</span>
      <?php elseif ($enrollment['status'] === 'approved'): ?>
        <span class="small-btn" style="background:#dff0d8;color:#155724;">Enrolled</span>
      <?php endif; ?>
      <?php if (isset($_GET['msg']) && $_GET['msg'] === 'applied'): ?>
        <div style="color:#27ae60;font-weight:700;margin-top:8px;">Applied successfully!</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Weeks -->
  <?php if ($enrollment && $enrollment['status'] === 'approved'): ?>
    <?php for ($w=1; $w <= $weeks; $w++):
        $unlocked = weekUnlocked($w, $enrollment['enrolled_at']);
        $unlockDate = strtotime($enrollment['enrolled_at'] . " + " . ($w - 1) . " week");

        $weekFiles = [];
        if (isset($files[$w])) {
            if (isset($files[$w][0]) && isset($files[$w][0]['path'])) {
                $weekFiles = $files[$w]; // multiple files
            } elseif (isset($files[$w]['path'])) {
                $weekFiles[] = $files[$w]; // single file
            }
        }
    ?>
      <div class="week-container" id="week-<?= $w ?>">
        <div class="week-title">
          <div>Week <?= $w ?></div>
          <?php if (!$unlocked): ?>
            <div class="lock-badge">Locked</div>
          <?php else: ?>
            <div style="font-size:0.95rem;color:#2d2d2d;">Available</div>
          <?php endif; ?>
        </div>

        <?php if (!$unlocked): ?>
            <p class="locked">Locked</p>
            <div class="countdown" data-unlock="<?= $unlockDate ?>">Will unlock soon...</div>
        <?php else: ?>
            <?php if (empty($weekFiles)): ?>
                <p>No content uploaded yet for this week.</p>
            <?php else: ?>
                <div class="file-list">
                    <?php foreach ($weekFiles as $fileItem): ?>
                        <?php
                            $type = strtolower($fileItem['type'] ?? pathinfo($fileItem['path'], PATHINFO_EXTENSION));
                            $path = htmlspecialchars($fileItem['path']);
                        ?>
                        <div class="file-item">
                            <?php if ($type === 'pdf'): ?>
                                <iframe src="<?= $path ?>"></iframe>
                            <?php elseif (in_array($type, ['mp4','webm','ogg'])): ?>
                                <video controls controlsList="nodownload">
                                    <source src="<?= $path ?>" type="video/<?= $type ?>">
                                </video>
                            <?php elseif (in_array($type, ['jpg','jpeg','png','gif'])): ?>
                                <img src="<?= $path ?>" alt="" style="max-width:100%;border-radius:6px;">
                            <?php else: ?>
                                <a href="<?= $path ?>" target="_blank" rel="noopener noreferrer" class="small-btn">Download / Open</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  <?php else: ?>
    <div style="padding:20px;text-align:center;color:#666;">You need to be enrolled and approved to view course content.</div>
  <?php endif; ?>
</div>

<script>
// Countdown updater
function updateCountdowns() {
  document.querySelectorAll('.countdown').forEach(cd => {
    const unlockTime = parseInt(cd.getAttribute('data-unlock')) * 1000;
    const diff = unlockTime - Date.now();
    if (diff <= 0) {
      cd.innerHTML = "<strong style='color:green;'>Unlocked!</strong>";
    } else {
      const days = Math.floor(diff / 86400000);
      const hours = Math.floor((diff % 86400000) / 3600000);
      const mins = Math.floor((diff % 3600000) / 60000);
      const secs = Math.floor((diff % 60000) / 1000);
      cd.innerHTML = `Unlocks in <strong>${days}d ${hours}h ${mins}m ${secs}s</strong>`;
    }
  });
}
setInterval(updateCountdowns, 1000);
updateCountdowns();

// Mobile menu toggle
document.querySelector('.nav-toggle')?.addEventListener('click', () => {
  document.querySelector('.navbar ul').classList.toggle('show');
});
</script>

</body>
</html>
