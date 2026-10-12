<?php

require_once __DIR__ . '/sms_gateway.php';

function sendPersonnelCredentialNotifications(string $email, string $phone, string $name, string $username, string $password, string $role): array
{
    $result = ['email' => 'failed', 'sms' => 'failed', 'email_message' => '', 'sms_message' => ''];
    $emailFrom = trim((string) getenv('PERSONNEL_MAIL_FROM'));
    if ($emailFrom === '' || !filter_var($emailFrom, FILTER_VALIDATE_EMAIL)) {
        $result['email_message'] = 'Email sender is not configured. Set PERSONNEL_MAIL_FROM and configure PHP mail transport.';
    } else {
        $subject = 'Your InfoConnect account credentials';
        $body = "Hello {$name},\n\nYour {$role} InfoConnect account is ready.\nAccount ID: {$username}\nTemporary password: {$password}\n\nPlease sign in and change your password if prompted.\n\nInfoConnect";
        $headers = 'From: InfoConnect <' . $emailFrom . ">\r\n"
            . 'Reply-To: ' . $emailFrom . "\r\n"
            . 'Content-Type: text/plain; charset=UTF-8';
        $sent = @mail($email, $subject, $body, $headers);
        $result['email'] = $sent ? 'sent' : 'failed';
        $result['email_message'] = $sent ? 'Mail transport accepted the message.' : 'PHP mail transport could not send the message.';
    }

    try {
        $smsConfig = philSmsConfig();
        $normalizedPhone = normalizePhilippineSmsNumber($phone);
        if ($normalizedPhone === '') {
            throw new RuntimeException('Contact number is not a valid Philippine mobile number.');
        }
        $smsBody = "InfoConnect {$role} account\nAccount ID: {$username}\nTemporary password: {$password}\nPlease change it after signing in.";
        $responses = sendPhilSmsBatch([$normalizedPhone], $smsBody, $smsConfig);
        $response = $responses[0] ?? [];
        $result['sms'] = ($response['status'] ?? '') === 'sent' ? 'sent' : 'failed';
        $result['sms_message'] = $result['sms'] === 'sent' ? 'SMS accepted by PhilSMS.' : (string) ($response['message'] ?? 'PhilSMS did not accept the message.');
    } catch (Throwable $exception) {
        $result['sms_message'] = $exception->getMessage();
    }

    return $result;
}
