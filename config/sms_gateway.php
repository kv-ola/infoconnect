<?php

function philSmsConfig(): array
{
    $apiToken = getenv('PHILSMS_API_TOKEN');
    $senderId = getenv('PHILSMS_SENDER_ID');

    return [
        'api_token' => is_string($apiToken) ? trim($apiToken) : '',
        'sender_id' => is_string($senderId) ? trim($senderId) : '',
        'endpoint' => 'https://app.philsms.com/api/v3/sms/send',
    ];
}

function normalizePhilippineSmsNumber(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if (preg_match('/^09\d{9}$/', $digits)) {
        return $digits;
    }

    if (preg_match('/^639\d{9}$/', $digits)) {
        return '0' . substr($digits, 2);
    }

    if (preg_match('/^9\d{9}$/', $digits)) {
        return '0' . $digits;
    }

    return '';
}

function sendPhilSmsBatch(
    array $numbers,
    string $message,
    array $config
): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL is required to send SMS messages.'
        );
    }

    if ($config['api_token'] === '') {
        throw new RuntimeException(
            'The PhilSMS API token is not configured.'
        );
    }

    if (!$numbers) {
        return [];
    }

    $results = [];

    foreach ($numbers as $number) {
        $parameters = [
            'recipient' => $number,
            'sender_id' => $config['sender_id'],
            'type' => 'plain',
            'message' => $message,
        ];

        $curl = curl_init($config['endpoint']);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($parameters),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $config['api_token'],
            ],
        ]);

        $responseBody = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($responseBody === false) {
            $results[] = [
                'recipient' => $number,
                'status' => 'failed',
                'message' => $curlError !== ''
                    ? 'PhilSMS connection failed: ' . $curlError
                    : 'PhilSMS connection failed.',
            ];

            continue;
        }

        $decoded = json_decode($responseBody, true);

        if (
            $httpStatus < 200 ||
            $httpStatus >= 300 ||
            !is_array($decoded)
        ) {
            $errorMessage = is_array($decoded)
                ? (string) (
                    $decoded['message']
                    ?? $decoded['error']
                    ?? 'PhilSMS rejected the request.'
                )
                : 'PhilSMS returned an invalid response.';

            $results[] = [
                'recipient' => $number,
                'status' => 'failed',
                'message' => 'PhilSMS request failed (HTTP '
                    . $httpStatus . '): ' . $errorMessage,
            ];

            continue;
        }

        $providerStatus = strtolower(
            (string) (
                $decoded['status']
                ?? $decoded['message_status']
                ?? 'accepted'
            )
        );

        $isAccepted = in_array(
            $providerStatus,
            [
                'accepted',
                'queued',
                'pending',
                'sent',
                'success',
                'successful'
            ],
            true
        );

        $results[] = [
            'recipient' => $number,
            'status' => $isAccepted ? 'sent' : 'failed',
            'message' => $isAccepted
                ? null
                : (string) (
                    $decoded['message']
                    ?? $decoded['error']
                    ?? 'PhilSMS rejected the SMS.'
                ),
            'provider_response' => $decoded,
        ];
    }

    return $results;
}