<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch enrollment + payment status
$enrolledStmt = $pdo->prepare("SELECT course_id, payment_status FROM student_courses WHERE student_id = ?");
$enrolledStmt->execute([$student_id]);
$enrolledCourses = [];
while ($row = $enrolledStmt->fetch(PDO::FETCH_ASSOC)) {
    $enrolledCourses[$row['course_id']] = $row['payment_status'];
}

// Fetch ALL COURSES with rating
$coursesStmt = $pdo->query("
    SELECT 
        c.id, c.title, c.description, c.price,
        COALESCE(AVG(f.rating), 0) AS avg_rating,
        COUNT(f.id) AS review_count
    FROM courses c
    LEFT JOIN feedback f ON c.id = f.course_id AND f.approved = 1
    GROUP BY c.id
    ORDER BY c.title
");
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch RECOMMENDED COURSES
$recStmt = $pdo->prepare("
    SELECT 
        c.id, c.title, c.description, c.price,
        COALESCE(AVG(f.rating), 0) AS avg_rating,
        COUNT(f.id) AS review_count
    FROM recommendations r
    JOIN courses c ON r.course_id = c.id
    LEFT JOIN feedback f ON c.id = f.course_id AND f.approved = 1
    WHERE r.user_id = ?
    GROUP BY c.id
    LIMIT 3
");
$recStmt->execute([$student_id]);
$recommendedCourses = $recStmt->fetchAll(PDO::FETCH_ASSOC);

// Approved reviews
$reviews = $pdo->query("
    SELECT f.rating, f.comment, c.title AS course_title, s.name AS student_name
    FROM feedback f
    JOIN courses c ON f.course_id = c.id
    JOIN students s ON f.student_id = s.id
    WHERE f.approved = 1
    ORDER BY f.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Marquee messages
$marqueeMsgStmt = $pdo->query("SELECT message FROM messages ORDER BY created_at DESC LIMIT 3");
$marqueeMsgs = $marqueeMsgStmt->fetchAll(PDO::FETCH_COLUMN);
$marqueeMsg = implode("  |  ", $marqueeMsgs);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Tech Gurukul</title>
    <link rel="stylesheet" href="css/student_dashboard.css">
    <link rel="stylesheet" href="https://unpkg.com/flickity@2/dist/flickity.min.css">
    <style>
        /* Navbar - Clean and Simple */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #663399;
            padding: 15px 30px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .navbar .logo {
            font-weight: bold;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
        }

        .navbar .logo img {
            height: 45px;
            margin-right: 12px;
        }

        .navbar ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 20px;
        }

        .navbar ul li a {
            color: white;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 30px;
            font-weight: 500;
            transition: 0.3s;
        }

        .navbar ul li a:hover {
            background: rgba(255,255,255,0.2);
        }

        .btn-log{
          background-color:red;
        }

        /* Mobile Menu Toggle */
        .nav-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

        /* SEARCH BAR - Placed Below Navbar */
        .search-section {
            background: linear-gradient(135deg, #f8f4ff, #e8dcff);
            padding: 40px 20px;
            text-align: center;
            box-shadow: inset 0 4px 10px rgba(0,0,0,0.05);
        }

        .search-bar {
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-bar input {
            width: 100%;
            padding: 18px 70px 18px 25px;
            font-size: 18px;
            border: 3px solid #663399;
            border-radius: 50px;
            background: white;
            box-shadow: 0 8px 25px rgba(102,51,153,0.1);
            transition: all 0.3s;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #552288;
            box-shadow: 0 12px 35px rgba(102,51,153,0.2);
        }

        .search-bar input::placeholder {
            color: #999;
        }

        .search-btn {
            position: absolute;
            right: 8px;
            background: #ffcc00;
            color: #333;
            border: none;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            transition: all 0.3s;
        }

        .search-btn:hover {
            background: #ffd633;
            transform: scale(1.1);
        }

        .search-title {
            font-size: 24px;
            color: #663399;
            margin-bottom: 15px;
            font-weight: 600;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .navbar {
                padding: 15px 20px;
                flex-wrap: wrap;
                gap: 15px;
            }
            .navbar ul {
                flex-wrap: wrap;
                justify-content: center;
                width: 100%;
            }
            .nav-toggle {
                display: block;
            }
            .navbar ul {
                display: none;
                flex-direction: column;
                width: 100%;
            }
            .navbar ul.show {
                display: flex;
            }
            .search-bar input {
                font-size: 16px;
                padding: 16px 60px 16px 20px;
            }
            .search-btn {
                width: 48px;
                height: 48px;
                font-size: 20px;
            }
            
        }

        /* Rest of your existing styles (hero, cards, etc.) */
        #hero-parallax { position:relative; height:500px; overflow:hidden; margin-bottom:50px; }
        .hero-slider { height:100%; }
        .carousel-cell { height:100%; width:100%; background-size:cover; background-position:center; position:relative; }
        .overlay { position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
        .inner { position:absolute; bottom:50px; left:50px; color:white; max-width:600px; }
        .inner .subtitle { font-size:1.4rem; margin-bottom:10px; }
        .inner .title { font-size:3rem; margin:15px 0; line-height:1.2; }
        .inner .btn { padding:14px 30px; background:#ffcc00; color:#000; text-decoration:none; border-radius:50px; font-weight:bold; font-size:1.1rem; display:inline-block; transition:0.3s; }
        .inner .btn:hover { background:#ffd633; transform:translateY(-5px); }

        .card-container { display:flex; flex-wrap:wrap; gap:25px; justify-content:center; padding:20px 0; }
        .card { background:#fff; padding:25px; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.1); max-width:320px; text-align:center; transition:0.3s; }
        .card:hover { transform:translateY(-10px); box-shadow:0 15px 35px rgba(0,0,0,0.15); }
        .card h3 { margin:0 0 15px 0; color:#663399; font-size:1.3rem; }

        .enroll-btn, .start-btn { display:inline-block; margin-top:15px; padding:12px 28px; border-radius:50px; text-decoration:none; color:#fff; font-weight:bold; transition:0.3s; }
        .enroll-btn { background:#28a745; }
        .enroll-btn:hover { background:#218838; }
        .start-btn { background:#007bff; }
        .start-btn:hover { background:#0056b3; }

        .review { background:#f9f9f9; padding:20px; margin-bottom:20px; border-radius:12px; border-left:5px solid #663399; }
        .stars { color:#f39c12; font-size:1.4rem; margin:10px 0; letter-spacing:3px; }
        .hidden-review { display:none; }
        .show-more-btn { padding:14px 34px; background:#007bff; color:white; border:none; border-radius:50px; cursor:pointer; font-size:1.1rem; font-weight:bold; transition:0.3s; }
        .show-more-btn:hover { background:#0056b3; }

        /* Top Rated Section */
        #top-rated {
            background: linear-gradient(135deg, #fff8e1, #fff3e0);
            padding: 60px 20px;
            margin: 80px 0 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(243, 156, 18, 0.15);
            text-align: center;
        }
        #top-rated h2 {
            font-size: 2.2rem;
            color: #663399;
            margin-bottom: 40px;
        }
        #top-rated .top-rated-cards {
            display: flex;
            gap: 30px;
            overflow-x: auto;
            padding: 20px 0;
        }
        #top-rated .card {
            min-width: 340px;
            border: 4px solid #f39c12;
            position: relative;
        }
        #top-rated .top-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #f39c12;
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
            box-shadow: 0 6px 20px rgba(243,156,18,0.4);
            z-index: 10;
        }
        .view-btn, .enroll-btn, .start-btn {
    padding: 10px 18px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: bold;
    font-size: 0.95rem;
    transition: background-color 0.3s;
    text-align: center;
    min-width: 120px;
}

.view-btn {
    background-color: #3498db;
    color: white;
}

.view-btn:hover {
    background-color: #2980b9;
}

.enroll-btn {
    background-color: #27ae60;
    color: white;
}

.enroll-btn:hover {
    background-color: #219a52;
}

.start-btn {
    background-color: #9b59b6;
    color: white;
}

.start-btn:hover {
    background-color: #8e44ad;
}
        /*          Floating Chat Bubble         */
        .chat-bubble {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            box-shadow: 0 8px 25px rgba(102, 51, 153, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 999;
            text-decoration: none;
            color: white;
        }

        .chat-bubble:hover {
            transform: scale(1.15) translateY(-5px);
            box-shadow: 0 15px 35px rgba(102, 51, 153, 0.5);
        }

        .chat-bubble svg {
            width: 38px;
            height: 38px;
            fill: white;
        }

        .chat-bubble .emoji {
            font-size: 42px;
            line-height: 1;
        }

        /* Optional: small badge / notification dot */
        .chat-bubble::after {
            content: '';
            position: absolute;
            top: 8px;
            right: 8px;
            width: 14px;
            height: 14px;
            background: #ff4757;
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            display: none;
        }
    </style>
</head>
<body>

<!-- Simple Navbar -->
<nav class="navbar">
    <div class="logo">
        <img src="uploads/logo.jpg" alt="Tech Gurukul Logo"> Tech Gurukul
    </div>

    <button class="nav-toggle" aria-label="Toggle menu">☰</button>

    <ul>
        <li><a href="#hero-parallax">Home</a></li>
        <li><a href="#courses">Courses</a></li>
        <li><a href="#reviews">Reviews</a></li>
        <li><a href="student_progress.php">My Profile</a></li>
        <li><a class="btn-log" href="logout.php">Logout</a></li>
    </ul>
</nav>

<!-- Marquee -->
<?php if ($marqueeMsg): ?>
<div class="marquee-bar" role="alert" aria-live="polite">
    <marquee behavior="scroll" direction="left" scrollamount="6"><?= htmlspecialchars($marqueeMsg) ?></marquee>
</div>
<?php endif; ?>

<!-- Hero Slider -->
<header id="hero-parallax">
    <div class="hero-slider">
        <div class="carousel-cell" style="background-image:url('uploads/slide_one.jpg');">
            <div class="overlay"></div>
            <div class="inner">
                <h3 class="subtitle">No Experience? No Problem.</h3>
                <h2 class="title">Beginner to Advanced Computer Classes</h2>
                <a href="#courses" class="btn">Enroll Today</a>
            </div>
        </div>
        <div class="carousel-cell" style="background-image:url('uploads/slide_two.jpg');">
            <div class="overlay"></div>
            <div class="inner">
                <h3 class="subtitle">Learn Practical Skills</h3>
                <h2 class="title">From Experts in the Field</h2>
                <a href="#courses" class="btn">Explore Courses</a>
            </div>
        </div>
        <div class="carousel-cell" style="background-image:url('uploads/slide_three.jpg');">
            <div class="overlay"></div>
            <div class="inner">
                <h3 class="subtitle">Join Thousands of Students</h3>
                <h2 class="title">Start Your Journey Today</h2>
                <a href="#courses" class="btn">Get Started</a>
            </div>
        </div>
    </div>
</header>

<!--        Floating Chat Icon      -->
<a href="chatdemoone/index.php" class="chat-bubble" title="Open Chat">
    <svg viewBox="0 0 24 24">
        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
    </svg>
</a>

<!-- SEARCH BAR - Prominent Section Below Navbar -->
<section class="search-section">
    <h2 class="search-title">Find Your Perfect Course</h2>
    <div class="search-bar">
        <input type="text" id="courseSearch" placeholder="Search by title or description...">
        <button type="button" class="search-btn" aria-label="Search">
            🔍
        </button>
    </div>
</section>

<!-- Recommended Courses -->
<?php if ($recommendedCourses): ?>
<section id="recommendations">
    <h2 style="text-align:center; color:#663399; margin:40px 0;">Recommended for You</h2>
    <div class="card-container">
        <?php foreach ($recommendedCourses as $rec): 
            $payment_status = $enrolledCourses[$rec['id']] ?? null;
            $avg = round($rec['avg_rating'], 1);
            $hasReviews = $rec['review_count'] > 0;
        ?>
            <div class="card">
                <h3><?= htmlspecialchars($rec['title']) ?></h3>
                <?php if ($hasReviews): ?>
                    <div style="margin:8px 0; font-size:1.1rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span style="color:<?= $i <= $avg ? '#f39c12' : '#ddd' ?>; font-size:1.2rem;">★</span>
                        <?php endfor; ?>
                        <small style="color:#666; margin-left:8px; font-size:0.9rem;">(<?= $avg ?> / <?= $rec['review_count'] ?> reviews)</small>
                    </div>
                <?php else: ?>
                    <div style="margin:8px 0; color:#888; font-style:italic; font-size:0.9rem;">No reviews yet</div>
                <?php endif; ?>
                <p><?= htmlspecialchars($rec['description']) ?></p>
                <p><strong>Price: Rs.<?= number_format($rec['price']) ?></strong></p>

                <!-- Buttons: View Course + Enroll/Start -->
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 15px;">
                    <a href="course_view.php?course_id=<?= $rec['id'] ?>" class="view-btn">View Course</a>

                    <?php if ($payment_status === 'paid'): ?>
                        <a href="course_view.php?course_id=<?= $rec['id'] ?>" class="start-btn">Start Course</a>
                    <?php else: ?>
                        <a href="payment.php?course_id=<?= $rec['id'] ?>" class="enroll-btn">Enroll Now</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- All Courses -->
<section id="courses">
    <h2 style="text-align:center; color:#663399; margin:40px 0;">All Courses</h2>
    <div class="card-container">
        <?php foreach ($courses as $course): 
            $payment_status = $enrolledCourses[$course['id']] ?? null;
            $avg = round($course['avg_rating'], 1);
            $hasReviews = $course['review_count'] > 0;
        ?>
            <div class="card">
                <h3><?= htmlspecialchars($course['title']) ?></h3>
                <?php if ($hasReviews): ?>
                    <div style="margin:8px 0; font-size:1.1rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span style="color:<?= $i <= $avg ? '#f39c12' : '#ddd' ?>; font-size:1.2rem;">★</span>
                        <?php endfor; ?>
                        <small style="color:#666; margin-left:8px; font-size:0.9rem;">(<?= $avg ?> / <?= $course['review_count'] ?> reviews)</small>
                    </div>
                <?php else: ?>
                    <div style="margin:8px 0; color:#888; font-style:italic; font-size:0.9rem;">No reviews yet</div>
                <?php endif; ?>
                <p><?= htmlspecialchars($course['description']) ?></p>
                <p><strong>Price: Rs.<?= number_format($course['price']) ?></strong></p>

                <!-- Buttons: View Course + Enroll/Start -->
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 15px;">
                    <a href="course_view.php?course_id=<?= $course['id'] ?>" class="view-btn">View Course</a>

                    <?php if ($payment_status === 'paid'): ?>
                        <a href="course_view.php?course_id=<?= $course['id'] ?>" class="start-btn">Start Course</a>
                    <?php else: ?>
                        <a href="payment.php?course_id=<?= $course['id'] ?>" class="enroll-btn">Enroll Now</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>


<!-- Reviews Section -->
<section id="reviews">
    <h2 style="text-align:center; color:#663399; margin:40px 0;">What Our Students Say</h2>
    <div class="review-list" style="max-width:900px; margin:0 auto; padding:0 20px;">
        <?php foreach ($reviews as $index => $review): ?>
            <div class="review <?= $index >= 4 ? 'hidden-review' : '' ?>">
                <strong><?= htmlspecialchars($review['course_title']) ?> by <?= htmlspecialchars($review['student_name']) ?></strong>
                <div class="stars"><?= str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) ?></div>
                <p><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($reviews) > 4): ?>
        <div style="text-align:center; margin:50px 0;">
            <button id="showMoreReviewsBtn" class="show-more-btn">Show More Reviews ↓</button>
            <button id="showLessReviewsBtn" class="show-more-btn" style="display:none; background:#6c757d;">Show Less ↑</button>
        </div>
    <?php endif; ?>
</section>


<!-- Top Rated Courses -->
<section id="top-rated">
    <?php
    // Fetch Top Rated Courses (only once!)
    $topRatedStmt = $pdo->query("
        SELECT 
            c.id, c.title, c.description, c.price,
            COALESCE(AVG(f.rating), 0) AS avg_rating,
            COUNT(f.id) AS review_count
        FROM courses c
        LEFT JOIN feedback f ON c.id = f.course_id AND f.approved = 1
        GROUP BY c.id
        HAVING review_count > 0
        ORDER BY avg_rating DESC, review_count DESC
        LIMIT 3
    ");
    $topRatedCourses = $topRatedStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (!empty($topRatedCourses)): ?>
        <h2 style="text-align:center; color:#663399; margin:40px 0; font-size:2.4rem;">⭐ Top Rated Courses</h2>
        
        <div class="scroll-container" style="display:flex; justify-content:center; overflow-x:auto; padding:20px 0;">
            <div class="top-rated-cards" style="display:flex; gap:30px; padding:0 20px;">
                <?php foreach ($topRatedCourses as $index => $tr): 
                    $avg = round($tr['avg_rating'], 1);
                    $payment_status = $enrolledCourses[$tr['id']] ?? null;
                ?>
                    <div class="card" style="min-width:340px; text-align:center; position:relative; overflow:hidden; border:4px solid #f39c12; border-radius:16px; box-shadow:0 12px 30px rgba(243,156,18,0.2);">
                        <div class="top-badge" style="position:absolute; top:-15px; left:50%; transform:translateX(-50%); background:#f39c12; color:white; padding:10px 28px; border-radius:50px; font-weight:bold; font-size:1.1rem; box-shadow:0 6px 20px rgba(243,156,18,0.4); z-index:10;">
                            #<?= $index + 1 ?> Top Rated
                        </div>
                        
                        <div style="padding-top:50px;">
                            <h3 style="color:#663399; margin:10px 0;"><?= htmlspecialchars($tr['title']) ?></h3>
                            
                            <div style="font-size:1.6rem; margin:20px 0;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span style="color:<?= $i <= $avg ? '#f39c12' : '#ddd' ?>;">★</span>
                                <?php endfor; ?>
                            </div>

                            <div style="color:#f39c12; font-weight:bold; margin-bottom:15px;">
                                <?= $avg ?> (<?= $tr['review_count'] ?> reviews)
                            </div>
                            
                            <p style="color:#666; padding:0 15px; min-height:80px;"><?= htmlspecialchars(substr($tr['description'], 0, 120)) ?>...</p>
                            
                            <p style="font-size:1.3rem; margin:20px 0;"><strong>Price: Rs.<?= number_format($tr['price']) ?></strong></p>
                            
                            <!-- Side-by-side buttons -->
                            <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 20px;">
                                <a href="course_view.php?course_id=<?= $tr['id'] ?>" class="view-btn">View Course</a>

                                <?php if ($payment_status === 'paid'): ?>
                                    <a href="course_view.php?course_id=<?= $tr['id'] ?>" class="start-btn">Start Course</a>
                                <?php else: ?>
                                    <a href="payment.php?course_id=<?= $tr['id'] ?>" class="enroll-btn">Enroll Now</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <p style="text-align:center; color:#888; font-size:1.2rem; padding:40px;">No rated courses yet. Be the first to review!</p>
    <?php endif; ?>
</section>

<!-- Footer -->
<footer style="background:#663399; color:white; padding:40px 20px; text-align:center; margin-top:80px;">
    <div><strong>Partnership with:</strong>
        <div style="margin:20px 0;">
            <?php
            $partners = $pdo->query("SELECT * FROM partnerships ORDER BY created_at DESC LIMIT 5")->fetchAll();
            foreach ($partners as $p):
                $logo = htmlspecialchars($p['logo'] ?? '');
                $name = htmlspecialchars($p['name'] ?? 'Partner');
                $link = htmlspecialchars($p['link'] ?? '#');
            ?>
                <a href="<?= $link ?>" target="_blank" title="<?= $name ?>">
                    <?php if ($logo): ?><img src="<?= $logo ?>" alt="<?= $name ?>" style="height:50px; margin:0 15px;" /><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <p style="margin-top: 30px;">&copy; <?= date('Y') ?> <strong>Tech Gurukul</strong> — All rights reserved. Built with ❤️ by Ishab.</p>
</footer>
<script src="https://unpkg.com/flickity@2/dist/flickity.pkgd.min.js"></script>
<script>
// Hero Slider
document.addEventListener('DOMContentLoaded', () => {
    const carousel = document.querySelector('.hero-slider');
    if (carousel) {
        new Flickity(carousel, {
            accessibility: true,
            prevNextButtons: true,
            pageDots: true,
            setGallerySize: false,
            wrapAround: true,
            autoPlay: 5000
        });
    }

    // Mobile Menu
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.navbar ul');
    if (navToggle) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('show');
        });
    }

    // Search Functionality
    const searchInput = document.getElementById('courseSearch');
    const searchBtn = document.querySelector('.search-btn');
    const courseCards = document.querySelectorAll('#courses .card, #recommendations .card');

    function performSearch() {
        const query = searchInput.value.toLowerCase().trim();
        let visible = false;

        courseCards.forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const desc = card.querySelector('p').textContent.toLowerCase();

            if (query === '' || title.includes(query) || desc.includes(query)) {
                card.style.display = 'block';
                visible = true;
            } else {
                card.style.display = 'none';
            }
        });

        // No results message
        let noMsg = document.getElementById('noResults');
        if (query && !visible) {
            if (!noMsg) {
                noMsg = document.createElement('p');
                noMsg.id = 'noResults';
                noMsg.innerHTML = `No courses found for "<strong>${query}</strong>"`;
                noMsg.style.textAlign = 'center';
                noMsg.style.color = '#999';
                noMsg.style.fontSize = '1.4rem';
                noMsg.style.margin = '60px 0';
                document.querySelector('#courses').appendChild(noMsg);
            }
        } else if (noMsg) {
            noMsg.remove();
        }
    }

    searchInput.addEventListener('input', performSearch);
    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keypress', e => {
        if (e.key === 'Enter') performSearch();
    });

    // Show More Reviews
    const showMoreBtn = document.getElementById('showMoreReviewsBtn');
    const showLessBtn = document.getElementById('showLessReviewsBtn');
    const hiddenReviews = document.querySelectorAll('.hidden-review');

    if (showMoreBtn) {
        showMoreBtn.addEventListener('click', () => {
            hiddenReviews.forEach(r => r.style.display = 'block');
            showMoreBtn.style.display = 'none';
            showLessBtn.style.display = 'inline-block';
        });
    }
    if (showLessBtn) {
        showLessBtn.addEventListener('click', () => {
            hiddenReviews.forEach(r => r.style.display = 'none');
            showLessBtn.style.display = 'none';
            showMoreBtn.style.display = 'inline-block';
            document.getElementById('reviews').scrollIntoView({ behavior: 'smooth' });
        });
    }
});
</script>

</body>
</html>