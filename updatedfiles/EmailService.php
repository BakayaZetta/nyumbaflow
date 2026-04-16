<?php

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
        // Load from config constants if defined, else use stubs
        $this->smtp_host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
        $this->smtp_port = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $this->smtp_user = defined('SMTP_USER') ? SMTP_USER : null;
        $this->smtp_pass = defined('SMTP_PASS') ? SMTP_PASS : null;
        $this->from_email = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'no-reply@nyumbaflow.com';
        $this->from_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Nyumbaflow';
    }

    /**
     * Send a password reset email
     * @param string $email The recipient's email
     * @param string $token The secure reset token
     * @return bool True if "sent" successfully (simulated/actual)
     */
    public function sendPasswordReset($email, $token)
    {
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

        $subject = "Password Reset Request - Nyumbaflow";
        $message = "Hello,\n\n";
        $message .= "We received a request to reset your password. You can do so by clicking the link below:\n\n";
        $message .= $reset_link . "\n\n";
        $message .= "This link will expire in 1 hour.\n";
        $message .= "If you did not request this, please ignore this email.\n\n";
        $message .= "Regards,\nNyumbaflow Team";

        return $this->send($email, $subject, $message);
    }

    /**
     * Core sending method (Stubs for future API/PHPMailer integration)
     */
    public function send($to, $subject, $message, $from_override = null)
    {
        $from = $from_override ?: "{$this->from_name} <{$this->from_email}>";

        // LOGGING STUB: In a real app, use PHPMailer or an API like SendGrid here.
        error_log("EMAIL SENT TO: $to | SUBJECT: $subject | FROM: $from");
        error_log("MESSAGE: $message");

        // Use built-in PHP mail() as a baseline fallback (often requires local SMTP server)
        $headers = "From: $from\r\n";
        $headers .= "Reply-To: " . $this->from_email . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // For now, return TRUE to simulate success if credentials aren't fully integrated
        // In production, this would be: return mail($to, $subject, $message, $headers);
        return true;
    }
}
?>
