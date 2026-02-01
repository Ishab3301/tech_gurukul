<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$course_id) die("Invalid course ID.");

// Fetch course
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) die("Course not found.");

// Decode files JSON safely
$files = $course['files'] ? json_decode($course['files'], true) : [];
if (!is_array($files)) $files = [];

// Reindex to 1-based weeks (legacy support)
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
$weeks = ($course['duration_type'] === 'months')
    ? $course['duration_value'] * 4
    : $course['duration_value'];

// Check enrollment
$stmt = $pdo->prepare("SELECT payment_status, completed_weeks FROM student_courses WHERE student_id = ? AND course_id = ?");
$stmt->execute([$student_id, $course_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

$isActive = $enrollment && $enrollment['payment_status'] === 'paid';
$completed_weeks = $isActive ? json_decode($enrollment['completed_weeks'] ?? '[]', true) : [];
if (!is_array($completed_weeks)) $completed_weeks = [];

// Unlock logic
function weekUnlocked($weekNumber, $completed_weeks) {
    $max = !empty($completed_weeks) ? max($completed_weeks) : 0;
    return $max >= $weekNumber - 1;
}

// Handle Mock Test Submission
if ($isActive && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['take_mock_test'])) {
    $week = (int)$_POST['take_mock_test'];

    if (!weekUnlocked($week, $completed_weeks)) {
        $_SESSION['error_msg'] = "Week $week is locked.";
        header("Location: course_view.php?course_id=$course_id");
        exit;
    }

    // Fetch questions
    $stmt = $pdo->prepare("SELECT id, correct_option FROM mock_tests WHERE course_id = ? AND week_number = ?");
    $stmt->execute([$course_id, $week]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($questions)) {
        $_SESSION['error_msg'] = "No mock test available for Week $week.";
        header("Location: course_view.php?course_id=$course_id#week$week");
        exit;
    }

    $correct = 0;
    $total = count($questions);

    foreach ($questions as $q) {
        if (isset($_POST['answer'][$q['id']]) && $_POST['answer'][$q['id']] === $q['correct_option']) {
            $correct++;
        }
    }

    $score = $total > 0 ? round(($correct / $total) * 100) : 0;
    $passed = $score >= 70;

    // Save attempt
    $pdo->prepare("INSERT INTO mock_test_attempts 
        (student_id, course_id, week_number, score, total_questions, passed)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            score = VALUES(score), total_questions = VALUES(total_questions), passed = VALUES(passed), attempted_at = NOW()")
        ->execute([$student_id, $course_id, $week, $score, $total, $passed ? 1 : 0]);

    if ($passed) {
        $_SESSION['success_msg'] = "Excellent! You passed Week $week mock test with $score%. You can now complete the week.";
    } else {
        $_SESSION['error_msg'] = "You scored $score%. Need 70% to complete Week $week. Please try again!";
    }

    header("Location: course_view.php?course_id=$course_id#week$week");
    exit;
}

// Handle week completion (only if passed mock test)
if ($isActive && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_week'])) {
    $week = (int)$_POST['complete_week'];
    $current_max = !empty($completed_weeks) ? max($completed_weeks) : 0;

    // Check if passed mock test
    $attempt = $pdo->prepare("SELECT passed FROM mock_test_attempts WHERE student_id = ? AND course_id = ? AND week_number = ? ORDER BY attempted_at DESC LIMIT 1");
    $attempt->execute([$student_id, $course_id, $week]);
    $result = $attempt->fetch();

    $hasPassedTest = $result && $result['passed'] == 1;

    if (!$hasPassedTest) {
        $_SESSION['error_msg'] = "You must pass the mock test (≥70%) before completing Week $week.";
    } elseif ($week === $current_max + 1 && $week <= $weeks && !in_array($week, $completed_weeks)) {
        $completed_weeks[] = $week;
        $json_weeks = json_encode($completed_weeks);

        $update = $pdo->prepare("UPDATE student_courses SET completed_weeks = ? WHERE student_id = ? AND course_id = ?");
        $update->execute([$json_weeks, $student_id, $course_id]);

        $_SESSION['success_msg'] = "Week $week completed successfully! Week " . ($week + 1) . " unlocked.";
    } else {
        $_SESSION['error_msg'] = "You must complete weeks in order.";
    }
    header("Location: course_view.php?course_id=$course_id");
    exit;
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
.course-container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 0 20px rgba(0,0,0,0.08); margin: 30px auto; max-width: 1000px; }
.course-header { display:flex; justify-content:space-between; align-items:flex-start; gap:30px; padding-bottom:25px; border-bottom:1px solid #eee; margin-bottom:40px; flex-wrap:wrap; }
.course-info h2 { margin:0 0 15px 0; color:#663399; font-size:2rem; }
.course-action { text-align:right; min-width:200px; }

.week-container { border:1px solid #ddd; margin-bottom:20px; border-radius:10px; padding:20px; background:#fff; }
.week-title { font-weight:bold; margin-bottom:15px; color:#663399; font-size:1.3rem; display:flex; justify-content:space-between; align-items:center; }
.file-list { margin-top:15px; }
.file-item { margin-bottom:25px; }
.file-item iframe { width:100%; height:600px; border:none; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1); }
.file-item video { width:100%; max-height:600px; border-radius:10px; background:#000; box-shadow:0 4px 15px rgba(0,0,0,0.1); }

.small-btn {
    display:inline-block; padding:12px 24px; border-radius:10px; color:white; text-decoration:none;
    font-weight:bold; font-size:1rem; transition:0.3s;
}
.small-btn:hover { transform:translateY(-3px); box-shadow:0 8px 20px rgba(0,0,0,0.2); }
.complete-btn { background:#28a745; }
.complete-btn:hover { background:#218838; }
.lock-badge { font-size:1rem; color:#fff; background:#e67e22; padding:8px 16px; border-radius:999px; }

.message { padding: 20px; border-radius: 12px; text-align: center; font-weight: bold; margin: 20px auto; max-width: 800px; font-size: 18px; }
.success { background:#d4edda; color:#155724; border:2px solid #c3e6cb; }
.error   { background:#f8d7da; color:#721c24; border:2px solid #f5c6cb; }

.mock-test-section {
    margin: 40px 0;
    padding: 30px;
    background: #e3f2fd;
    border-radius: 12px;
    border: 2px solid #2196F3;
}
.mock-test-section h3 { color: #1976d2; margin-top: 0; }

.question-box {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.question-box label {
    display: block;
    margin: 12px 0;
    font-size: 16px;
    cursor: pointer;
}
.question-box strong { color: #333; }

/* Feedback Section */
.feedback-section {
    margin-top:60px; padding:30px; background:#f8f9fa; border-radius:12px; border:2px dashed #663399;
}
.rating-stars { display:flex; justify-content:center; gap:12px; }
.rating-stars label { font-size:50px; color:#ddd; cursor:pointer; transition:color 0.2s; }
</style>
</head>
<body>

<!-- Messages -->
<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="message success"><?= htmlspecialchars($_SESSION['success_msg']) ?></div>
    <?php unset($_SESSION['success_msg']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_msg'])): ?>
    <div class="message error"><?= htmlspecialchars($_SESSION['error_msg']) ?></div>
    <?php unset($_SESSION['error_msg']); ?>
<?php endif; ?>

<!-- Navbar -->
<nav class="navbar">
  <div class="logo">
    <img src="uploads/logo.jpg" alt="Logo" style="height:40px; margin-right:8px;">
    Tech Gurukul
  </div>
  <ul>
    <li><a href="student_dashboard.php">Home</a></li>
    <li><a href="student_dashboard.php#courses">Courses</a></li>
    <li><a href="student_progress.php#progress">My Courses</a></li>
    <li><a href="student_progress.php">My Profile</a></li>
    <li><a href="logout.php" class="logout-btn">Logout</a></li>
  </ul>
</nav>

<div class="course-container">
  <div class="course-header">
    <div class="course-info">
      <h2><?= htmlspecialchars($course['title']) ?></h2>
      <p><?= nl2br(htmlspecialchars($course['description'])) ?></p>
    </div>

    <div class="course-action">
      <?php if (!$enrollment): ?>
        <a href="payment.php?course_id=<?= $course_id ?>" class="small-btn" style="background:#663399;">Pay & Enroll Now</a>
      <?php elseif ($enrollment['payment_status'] === 'paid'): ?>
        <a href="#week1" class="small-btn" style="background:#28a745;">Continue Learning</a>
      <?php else: ?>
        <a href="payment.php?course_id=<?= $course_id ?>" class="small-btn" style="background:#f0ad4e;">Complete Payment</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isActive): ?>
    <?php for ($w = 1; $w <= $weeks; $w++):
        $unlocked = weekUnlocked($w, $completed_weeks);
        $isCompleted = in_array($w, $completed_weeks);

        $weekFiles = $files[$w] ?? [];
        if (isset($weekFiles['path'])) $weekFiles = [$weekFiles];

        // Fetch mock questions
        $qStmt = $pdo->prepare("SELECT * FROM mock_tests WHERE course_id = ? AND week_number = ? ORDER BY id");
        $qStmt->execute([$course_id, $w]);
        $mockQuestions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

        // Check latest attempt
        $attemptStmt = $pdo->prepare("SELECT score, passed FROM mock_test_attempts WHERE student_id = ? AND course_id = ? AND week_number = ? ORDER BY attempted_at DESC LIMIT 1");
        $attemptStmt->execute([$student_id, $course_id, $w]);
        $attempt = $attemptStmt->fetch();
        $hasPassedTest = $attempt && $attempt['passed'] == 1;
        $lastScore = $attempt ? $attempt['score'] : null;
    ?>
      <div class="week-container" id="week<?= $w ?>">
        <div class="week-title">
          <div>Week <?= $w ?></div>
          <?php if ($isCompleted): ?>
            <div style="color:green; font-weight:bold;">✓ Completed</div>
          <?php elseif ($unlocked): ?>
            <div style="color:#007bff; font-weight:bold;">In Progress</div>
          <?php else: ?>
            <div class="lock-badge">Locked</div>
          <?php endif; ?>
        </div>

        <?php if (!$unlocked): ?>
          <p style="text-align:center; padding:30px; background:#f9f9f9; border-radius:10px; color:#777;">
            Complete Week <?= $w - 1 === 0 ? 'previous weeks' : ($w - 1) ?> to unlock this week.
          </p>
        <?php else: ?>
          <!-- Materials -->
          <?php if (empty($weekFiles)): ?>
            <p style="text-align:center; padding:40px; color:#999; font-style:italic;">No content uploaded for this week yet.</p>
          <?php else: ?>
            <div class="file-list">
              <?php foreach ($weekFiles as $fileItem):
                  $path = $fileItem['path'] ?? '';
                  $type = strtolower($fileItem['type'] ?? pathinfo($path, PATHINFO_EXTENSION));
              ?>
                <div class="file-item">
                  <?php if ($type === 'pdf'): ?>
                    <iframe src="<?= htmlspecialchars($path) ?>" allowfullscreen></iframe>
                  <?php elseif (in_array($type, ['mp4', 'webm', 'ogg'])): ?>
                    <video controls controlsList="nodownload">
                      <source src="<?= htmlspecialchars($path) ?>" type="video/<?= $type ?>">
                      Your browser does not support video.
                    </video>
                  <?php else: ?>
                    <a href="<?= htmlspecialchars($path) ?>" target="_blank" class="small-btn" style="background:#663399;">Open / Download</a>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- Mock Test -->
          <?php if (!empty($mockQuestions)): ?>
            <div class="mock-test-section">
              <h3>Weekly Mock Test - Week <?= $w ?> (Required to Complete)</h3>

              <?php if ($hasPassedTest): ?>
                <div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin:20px 0; font-weight:bold; text-align:center;">
                  Congratulations! You passed with <?= $lastScore ?>%
                </div>
              <?php elseif ($lastScore !== null): ?>
                <div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; margin:20px 0; font-weight:bold;">
                  Last attempt: <?= $lastScore ?>% — Need 70% to pass. You can retake the test.
                </div>
              <?php endif; ?>

              <?php if (!$isCompleted): ?>
                <form method="POST">
                  <input type="hidden" name="take_mock_test" value="<?= $w ?>">
                  <?php foreach ($mockQuestions as $i => $q): ?>
                    <div class="question-box">
                      <strong><?= $i + 1 ?>. <?= htmlspecialchars($q['question']) ?></strong>
                      <label><input type="radio" name="answer[<?= $q['id'] ?>]" value="A" required> A) <?= htmlspecialchars($q['option_a']) ?></label>
                      <label><input type="radio" name="answer[<?= $q['id'] ?>]" value="B"> B) <?= htmlspecialchars($q['option_b']) ?></label>
                      <label><input type="radio" name="answer[<?= $q['id'] ?>]" value="C"> C) <?= htmlspecialchars($q['option_c']) ?></label>
                      <label><input type="radio" name="answer[<?= $q['id'] ?>]" value="D"> D) <?= htmlspecialchars($q['option_d']) ?></label>
                    </div>
                  <?php endforeach; ?>

                  <div style="text-align:center; margin-top:30px;">
                    <button type="submit" class="small-btn" style="background:#1976d2; padding:14px 32px; font-size:1.1rem;">
                      Submit Mock Test
                    </button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <!-- Complete Button - Only if passed test -->
          <?php if (!$isCompleted): ?>
            <div style="text-align:center; margin-top:30px;">
              <?php if ($hasPassedTest): ?>
                <form method="POST">
                  <input type="hidden" name="complete_week" value="<?= $w ?>">
                  <button type="submit" class="small-btn complete-btn">
                    Mark Week <?= $w ?> as Completed
                  </button>
                </form>
              <?php else: ?>
                <div style="padding:20px; background:#fff3cd; border-radius:10px; color:#856404; font-weight:bold;">
                  Complete the mock test above and score at least 70% to unlock this button.
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endfor; ?>

    <!-- Feedback Form (unchanged) -->
    <?php
    $feedbackCheck = $pdo->prepare("SELECT id FROM feedback WHERE student_id = ? AND course_id = ?");
    $feedbackCheck->execute([$student_id, $course_id]);
    $hasFeedback = $feedbackCheck->fetch();

    $feedbackError = $feedbackSuccess = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $feedbackError = "Please select a valid rating (1-5 stars).";
        } elseif (empty($comment)) {
            $feedbackError = "Please write a review comment.";
        } elseif ($hasFeedback) {
            $feedbackSuccess = "You have already submitted feedback for this course.";
        } else {
            $insert = $pdo->prepare("INSERT INTO feedback (student_id, course_id, rating, comment, approved, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $insert->execute([$student_id, $course_id, $rating, $comment]);
            $feedbackSuccess = "Thank you! Your feedback has been submitted and is awaiting approval.";
            $hasFeedback = true;
        }
    }
    ?>

    <div class="feedback-section">
        <h2 style="text-align:center; color:#663399; margin-bottom:20px;">Rate This Course</h2>

        <?php if ($feedbackSuccess): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; text-align:center; margin-bottom:20px; font-weight:bold;">
                <?= htmlspecialchars($feedbackSuccess) ?>
            </div>
        <?php endif; ?>

        <?php if ($feedbackError): ?>
            <div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; text-align:center; margin-bottom:20px; font-weight:bold;">
                <?= htmlspecialchars($feedbackError) ?>
            </div>
        <?php endif; ?>

        <?php if (!$hasFeedback): ?>
            <form method="POST">
                <input type="hidden" name="submit_feedback" value="1">
                <div style="text-align:center; margin:25px 0;">
                    <label style="font-size:18px; margin-bottom:15px; display:block;">Your Rating <span style="color:red;">*</span></label>
                    <div class="rating-stars">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>" required style="display:none;">
                            <label for="star<?= $i ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div>
                    <label>Your Review <span style="color:red;">*</span></label>
                    <textarea name="comment" rows="6" required placeholder="Share your experience..." style="width:100%; padding:15px; border:2px solid #e0e0e0; border-radius:10px; font-size:16px;"></textarea>
                </div>

                <div style="text-align:center; margin-top:30px;">
                    <button type="submit" style="padding:16px 40px; background:#663399; color:white; border:none; border-radius:50px; font-size:18px; font-weight:bold;">Submit Feedback</button>
                </div>
            </form>

            <script>
            document.querySelectorAll('.rating-stars label').forEach(label => {
                label.addEventListener('mouseenter', function() {
                    this.style.color = '#f39c12';
                    let prev = this.previousElementSibling;
                    while (prev && prev.tagName === 'LABEL') { prev.style.color = '#f39c12'; prev = prev.previousElementSibling; }
                });
                label.addEventListener('click', function() {
                    document.querySelectorAll('.rating-stars label').forEach(l => l.style.color = '#f39c12');
                    let next = this.nextElementSibling;
                    while (next && next.tagName === 'LABEL') { next.style.color = '#ddd'; next = next.nextElementSibling; }
                });
            });
            document.querySelector('.rating-stars').addEventListener('mouseleave', function() {
                const checked = document.querySelector('.rating-stars input:checked');
                document.querySelectorAll('.rating-stars label').forEach(l => {
                    l.style.color = (l.previousElementSibling && l.previousElementSibling.checked) ? '#f39c12' : '#ddd';
                });
            });
            </script>
        <?php else: ?>
            <p style="text-align:center; color:#28a745; font-size:20px; font-weight:bold; padding:30px;">
                Thank you! You have already submitted your feedback.
            </p>
        <?php endif; ?>
    </div>

  <?php else: ?>
    <div style="text-align:center; padding:80px; background:#f9f9f9; border-radius:12px;">
      <h2 style="color:#663399;">Ready to Start?</h2>
      <p style="font-size:1.2rem; color:#666; margin:30px 0;">Complete payment to access all weeks and materials.</p>
      <a href="payment.php?course_id=<?= $course_id ?>" class="small-btn" style="background:#663399; padding:16px 32px; font-size:1.2rem;">Go to Payment</a>
    </div>
  <?php endif; ?>
</div>

</body>
</html>