<?php

declare(strict_types=1);

namespace App\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

class Mailer
{
    private static function createTransport(): PHPMailer
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['GMAIL_USER'] ?? '';
        $mail->Password   = $_ENV['GMAIL_APP_PASS'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = PHPMailer::CHARSET_UTF8;

        $mail->setFrom($_ENV['GMAIL_USER'] ?? '', 'RailManager');

        return $mail;
    }

    /**
     * Send email verification link.
     */
    public static function sendVerificationEmail(string $toEmail, string $username, string $token): void
    {
        $clientUrl = rtrim($_ENV['CLIENT_URL'] ?? '', '/');
        $link      = "{$clientUrl}/verify-email?token={$token}";

        $mail = self::createTransport();
        $mail->addAddress($toEmail);
        $mail->Subject = 'Verify your RailManager email address';
        $mail->isHTML(true);
        $mail->Body = <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;background:#f6f7f9;border-radius:12px;overflow:hidden">
            <div style="background:linear-gradient(135deg,#072649,#0054a0);padding:2rem;text-align:center">
                <h1 style="color:white;margin:0;font-size:1.5rem">RailManager</h1>
                <p style="color:#7cc4fb;margin:0.5rem 0 0;font-size:0.85rem">Train Management System</p>
            </div>
            <div style="padding:2rem">
                <h2 style="color:#0a3c6d;margin-top:0">Hi {$username} 👋</h2>
                <p style="color:#444;line-height:1.7">
                    Thanks for registering! Please verify your email address by clicking the button below.
                    This link expires in <strong>24 hours</strong>.
                </p>
                <div style="text-align:center;margin:2rem 0">
                    <a href="{$link}"
                        style="background:linear-gradient(135deg,#0054a0,#0c87e8);color:white;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:600;font-size:1rem;display:inline-block">
                        Verify Email Address
                    </a>
                </div>
                <p style="color:#888;font-size:0.8rem">
                    Or copy this link into your browser:<br/>
                    <a href="{$link}" style="color:#006ac6;word-break:break-all">{$link}</a>
                </p>
                <p style="color:#aaa;font-size:0.75rem;margin-bottom:0">
                    If you didn't create an account, you can safely ignore this email.
                </p>
            </div>
        </div>
        HTML;

        $mail->send();
    }

    /**
     * Send password reset link.
     */
    public static function sendPasswordResetEmail(string $toEmail, string $username, string $token): void
    {
        $clientUrl = rtrim($_ENV['CLIENT_URL'] ?? '', '/');
        $link      = "{$clientUrl}/reset-password?token={$token}";

        $mail = self::createTransport();
        $mail->addAddress($toEmail);
        $mail->Subject = 'Reset your RailManager password';
        $mail->isHTML(true);
        $mail->Body = <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;background:#f6f7f9;border-radius:12px;overflow:hidden">
            <div style="background:linear-gradient(135deg,#072649,#0054a0);padding:2rem;text-align:center">
                <h1 style="color:white;margin:0;font-size:1.5rem">RailManager</h1>
                <p style="color:#7cc4fb;margin:0.5rem 0 0;font-size:0.85rem">Train Management System</p>
            </div>
            <div style="padding:2rem">
                <h2 style="color:#0a3c6d;margin-top:0">Password Reset Request</h2>
                <p style="color:#444;line-height:1.7">
                    Hi <strong>{$username}</strong>, we received a request to reset your password.
                    Click the button below to create a new one. This link expires in <strong>1 hour</strong>.
                </p>
                <div style="text-align:center;margin:2rem 0">
                    <a href="{$link}"
                        style="background:linear-gradient(135deg,#0054a0,#0c87e8);color:white;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:600;font-size:1rem;display:inline-block">
                        Reset Password
                    </a>
                </div>
                <p style="color:#888;font-size:0.8rem">
                    Or copy this link:<br/>
                    <a href="{$link}" style="color:#006ac6;word-break:break-all">{$link}</a>
                </p>
                <p style="color:#aaa;font-size:0.75rem;margin-bottom:0">
                    If you didn't request a password reset, ignore this email. Your password won't change.
                </p>
            </div>
        </div>
        HTML;

        $mail->send();
    }
}
