<?php

namespace AEIMS\Lib;

/**
 * Site Mail Integration
 * Handles email functionality for customer sites
 */
class SiteMailIntegration {
    private $domain;
    private $fromEmail;
    private $fromName;

    public function __construct($domain) {
        $this->domain = $domain;
        $this->fromEmail = "noreply@{$domain}";
        $this->fromName = $this->getSiteName($domain);
    }

    /**
     * Validate email address
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Send welcome email to new customer
     */
    public function sendWelcomeEmail($customerId, $data) {
        $to = $data['email'];
        $name = $data['name'] ?? $data['username'];
        $username = $data['username'];

        $subject = "Welcome to {$this->fromName}!";

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
                .content { background: #f5f5f5; padding: 30px; }
                .footer { text-align: center; padding: 20px; color: #666; }
                .button { background: #ef4444; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to {$this->fromName}</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$name}!</h2>
                    <p>Thank you for joining {$this->fromName}. Your account has been successfully created.</p>
                    <p><strong>Username:</strong> {$username}</p>
                    <p>You can now log in and start exploring our platform.</p>
                    <a href='https://{$this->domain}/' class='button'>Go to {$this->fromName}</a>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " {$this->fromName}. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->sendEmail($to, $subject, $message);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($userId, $token, $data) {
        $to = $data['email'];
        $username = $data['username'];

        $resetUrl = "https://{$this->domain}/auth.php?action=reset&token={$token}";

        $subject = "Password Reset Request - {$this->fromName}";

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
                .content { background: #f5f5f5; padding: 30px; }
                .footer { text-align: center; padding: 20px; color: #666; }
                .button { background: #ef4444; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
                .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$username}!</h2>
                    <p>We received a request to reset your password for your {$this->fromName} account.</p>
                    <p>Click the button below to reset your password:</p>
                    <a href='{$resetUrl}' class='button'>Reset Password</a>
                    <div class='warning'>
                        <p><strong>Note:</strong> This link will expire in 24 hours. If you didn't request a password reset, please ignore this email.</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " {$this->fromName}. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->sendEmail($to, $subject, $message);
    }

    /**
     * Send email using PHP mail() function
     * In production, this should be replaced with a proper email service (SendGrid, AWS SES, etc.)
     */
    private function sendEmail($to, $subject, $htmlMessage) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            "From: {$this->fromName} <{$this->fromEmail}>",
            "Reply-To: {$this->fromEmail}",
            'X-Mailer: PHP/' . phpversion()
        ];

        // For development/testing, log emails instead of sending
        if (getenv('APP_ENV') === 'development') {
            error_log("EMAIL TO: {$to}");
            error_log("EMAIL SUBJECT: {$subject}");
            error_log("EMAIL BODY: " . strip_tags($htmlMessage));
            return true;
        }

        return mail($to, $subject, $htmlMessage, implode("\r\n", $headers));
    }

    /**
     * Get site display name from domain
     */
    private function getSiteName($domain) {
        $siteNames = [
            'nycflirts.com' => 'NYC Flirts',
            'flirts.nyc' => 'Flirts NYC',
            'sexacomms.com' => 'SexaComms',
            'aeims.app' => 'AEIMS Platform'
        ];

        return $siteNames[$domain] ?? ucfirst(str_replace(['.com', '.nyc', '.app'], '', $domain));
    }
}
