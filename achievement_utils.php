<?php
// includes/achievement_utils.php
if (!function_exists('checkAndUnlockAchievements')) {

    function checkAndUnlockAchievements($student_id, $pdo)
    {
        if (!$student_id || !$pdo) return;
        $student_id = (int)$student_id;

        // === ACHIEVEMENT: First Review ===
        $achievement_id = 1; // You can change this ID later

        // Check if already unlocked
        $check = $pdo->prepare("SELECT id FROM student_achievements WHERE student_id = ? AND achievement_id = ?");
        $check->execute([$student_id, $achievement_id]);
        if ($check->fetch()) {
            return; // Already has it
        }

        // Count total reviews
        $count = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE student_id = ?");
        $count->execute([$student_id]);
        if ($count->fetchColumn() == 1) { // First review!
            $pdo->prepare("
                INSERT INTO student_achievements 
                (student_id, achievement_id, title, description, icon) 
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $student_id,
                $achievement_id,
                'Helpful Reviewer',
                'Submitted your first course review!',
                'trophy'
            ]);

            // Show toast notification
            $_SESSION['new_achievement'] = [
                'title' => 'Helpful Reviewer',
                'message' => 'Achievement Unlocked: You submitted your first review!',
                'icon' => 'trophy'
            ];
        }
    }
}