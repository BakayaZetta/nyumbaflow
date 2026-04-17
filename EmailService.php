<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/vendor/autoload.php';

class EmailService
{
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_pass;
    private $from_email;
    private $from_name;

    public function __construct()
    {
        $this->smtp_host = defined('SMTP_HOST') ? SMTP_HOST : 'mail.nyumbaflow.com';
        $this->smtp_port = defined('SMTP_PORT') ? (int) SMTP_PORT : 465;
        $this->smtp_user = defined('SMTP_USER') ? SMTP_USER : 'noreply@nyumbaflow.com';
        $this->smtp_pass = defined('SMTP_PASS') ? SMTP_PASS : '';
        $this->from_email = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@nyumbaflow.com';
        $this->from_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Nyumbaflow';
    }

    public function sendEmailVerification($email, $token)
    {
        $verify_link = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? '/') . "/verify_email.php?token=" . urlencode($token);

        $subject = "Verify Your Email - Nyumbaflow";
        $message = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .button { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Verify Your Email</h1>
        </div>
        <div class='content'>
            <p>Hello,</p>
            <p>Thank you for signing up with Nyumbaflow! Please verify your email address by clicking the button below:</p>
            <a href='" . $verify_link . "' class='button'>Verify Email Address</a>
            <p>Or copy this link in your browser:</p>
            <p>" . $verify_link . "</p>
            <p>This link will expire in 24 hours.</p>
            <p>If you did not sign up for this account, please ignore this email.</p>
        </div>
        <div class='footer'>
            <p>Nyumbaflow - Property Management Made Easy</p>
        </div>
    </div>
</body>
</html>";

        return $this->send($email, $subject, $message);
    }

    public function sendApprovalNotification($admin_email, $user_name, $user_email, $user_phone = null)
    {
        $subject = "New Account Pending Approval - Nyumbaflow";
        $message = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background-color: #ffc107; color: black; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .user-info { background-color: #f9f9f9; padding: 15px; border-left: 4px solid #ffc107; }
        .button { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>New Account Pending Approval</h1>
        </div>
        <div class='content'>
            <p>Hello Admin,</p>
            <p>A new user has completed email verification and is awaiting account approval.</p>
            <div class='user-info'>
                <p><strong>User Details:</strong></p>
                <p>Name: " . htmlspecialchars($user_name) . "</p>
                <p>Email: " . htmlspecialchars($user_email) . "</p>
                <p>Phone: " . htmlspecialchars($user_phone ?? 'Not provided') . "</p>
            </div>
            <p>Please review and approve or reject this account in the super admin dashboard.</p>
            <a href='http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? '/') . "/super_admin_approvals.php' class='button'>Review Pending Approvals</a>
        </div>
        <div class='footer'>
            <p>Nyumbaflow - Property Management Made Easy</p>
        </div>
    </div>
</body>
</html>";

        return $this->send($admin_email, $subject, $message);
    }

    public function sendApprovalConfirmation($email, $name, $approve_date)
    {
        $subject = "Your Account Has Been Approved - Nyumbaflow";
        $message = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .button { display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Account Approved!</h1>
        </div>
        <div class='content'>
            <p>Hello " . htmlspecialchars($name) . ",</p>
            <p>Great news! Your Nyumbaflow account has been approved by our team.</p>
            <p>You can now log in to your dashboard and start managing your properties.</p>
            <a href='http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? '/') . "/index.html' class='button'>Log In Now</a>
            <p>Approval Date: " . htmlspecialchars($approve_date) . "</p>
            <p>If you have any questions, please contact our support team.</p>
        </div>
        <div class='footer'>
            <p>Nyumbaflow - Property Management Made Easy</p>
        </div>
    </div>
</body>
</html>";

        return $this->send($email, $subject, $message);
    }

    public function sendRejectionNotification($email, $name, $reason, $rejection_date)
    {
        $subject = "Your Account Application - Nyumbaflow";
        $message = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .rejection-reason { background-color: #fff3cd; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
        .footer { background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Account Application Status</h1>
        </div>
        <div class='content'>
            <p>Hello " . htmlspecialchars($name) . ",</p>
            <p>Thank you for applying for a Nyumbaflow account. Unfortunately, your application has not been approved at this time.</p>
            <div class='rejection-reason'>
                <p><strong>Reason for Rejection:</strong></p>
                <p>" . htmlspecialchars($reason) . "</p>
            </div>
            <p>Rejection Date: " . htmlspecialchars($rejection_date) . "</p>
            <p>You may re-apply after addressing the concerns mentioned above.</p>
            <p>If you believe this was in error or need clarification, please contact our support team.</p>
        </div>
        <div class='footer'>
            <p>Nyumbaflow - Property Management Made Easy</p>
        </div>
    </div>
</body>
</html>";

        return $this->send($email, $subject, $message);
    }

    public function sendPasswordReset($email, $token)
    {
        $reset_link = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? '/') . "/reset_password.php?token=" . urlencode($token);

        $subject = "Password Reset Request - Nyumbaflow";
        $message = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .button { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { background-color: #f5f5f5; padding: 10px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Password Reset Request</h1>
        </div>
        <div class='content'>
            <p>Hello,</p>
            <p>We received a request to reset your password. Click the button below:</p>
            <a href='" . $reset_link . "' class='button'>Reset Password</a>
            <p>Or copy this link in your browser:</p>
            <p>" . $reset_link . "</p>
            <p>This link will expire in 1 hour.</p>
            <p>If you did not request this, please ignore this email.</p>
        </div>
        <div class='footer'>
            <p>Nyumbaflow - Property Management Made Easy</p>
        </div>
    </div>
</body>
</html>";

        return $this->send($email, $subject, $message);
    }

    public function send($to, $subject, $message, $from_override = null)
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->Port = $this->smtp_port;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_user;
            $mail->Password = $this->smtp_pass;

            if ($this->smtp_port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($this->smtp_port === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $message;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], PHP_EOL, $message));

            if (!empty($from_override) && preg_match('/^\s*(.*?)\s*<([^>]+)>\s*$/', $from_override, $parts)) {
                $mail->setFrom(trim($parts[2]), trim($parts[1]));
            }

            $mail->send();
            error_log("EMAIL SENT via PHPMailer TO: $to | SUBJECT: $subject");
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            return $this->sendViaPhpMail($to, $subject, $message, $from_override ?: ($this->from_name . " <" . $this->from_email . ">"));
        }
    }

    private function sendViaPhpMail($to, $subject, $message, $from)
    {
        $headers = "From: {$from}\r\n";
        $headers .= "Reply-To: " . $this->from_email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        $result = @mail($to, $subject, $message, $headers);

        if ($result) {
            error_log("EMAIL SENT via PHP mail() TO: $to");
        } else {
            error_log("EMAIL FAILED via PHP mail() TO: $to");
        }

        return $result;
    }
}
