<?php
// includes/send_mail.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

function sendEnrollmentEmail($to_email, $student_name, $course_title, $status, $course_id = null)
{
    $mail = new PHPMailer(true);

    try {
        // Gmail Settings (change only these 2 lines)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ishab3301@gmail.com';        
        $mail->Password   = 'hoxx jsuy glhx vtpz';           
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('yourgmail@gmail.com', 'Tech Gurukul');
        $mail->addAddress($to_email, $student_name);

        $mail->isHTML(true);

        if ($status === 'approved') {
            $mail->Subject = "Approved! Welcome to \"$course_title\"";
            $mail->Body    = "
                <h2 style='color:green;'>Congratulations $student_name!</h2>
                <p>Your enrollment request has been <strong>APPROVED</strong>!</p>
                <h3>You are now enrolled in: <strong style='color:#663399;'>$course_title</strong></h3>
                <p>Start learning right now!</p>
                <div style='text-align:center;margin:30px 0;'>
                    <a href='http://localhost/tech_gurukul/course.php?id=$course_id' 
                       style='background:#28a745;color:white;padding:15px 35px;text-decoration:none;border-radius:50px;font-weight:bold;font-size:18px;'>
                       Start Course Now
                    </a>
                </div>
                <p>Login: <a href='http://localhost/tech_gurukul/student_dashboard.php'>Student Dashboard</a></p>
                <hr>
                <p>Keep shining!<br><strong>Team Tech Gurukul</strong></p>
            ";
        } 
        else { // rejected
            $mail->Subject = "Enrollment Update – \"$course_title\"";
            $mail->Body    = "
                <h2 style='color:#dc3545;'>Hello $student_name,</h2>
                <p>We’re sorry to inform you that your enrollment request for:</p>
                <h3><strong>$course_title</strong></h3>
                <p>has been <strong>REJECTED</strong> at this time.</p>
                <p>Possible reasons:</p>
                <ul>
                    <li>Payment not completed</li>
                    <li>Seats full</li>
                    <li>Documents missing</li>
                </ul>
                <p>You can try applying again later or contact us for help.</p>
                <hr>
                <p>Thank you for your interest!<br><strong>Team Tech Gurukul</strong></p>
            ";
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>