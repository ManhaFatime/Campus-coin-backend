<?php

declare(strict_types=1);

function sendPasswordResetEmail(string $email, string $token): bool
{
    global $appConfig;

    $resetUrl = $appConfig['base_url'] . '/reset-password?token=' . urlencode($token);
    $subject = 'Reset your Campus Coin password';
    $message = "A password reset was requested for your Campus Coin account.\n\n";
    $message .= "Open this link to reset your password:\n{$resetUrl}\n\n";
    $message .= "This link expires in 60 minutes. If you did not request it, ignore this email.";
    $headers = 'From: ' . $appConfig['mail_from'];

    if ($appConfig['env'] !== 'production') {
        error_log('Campus Coin password reset URL: ' . $resetUrl);
        return true;
    }

    return @mail($email, $subject, $message, $headers);
}
