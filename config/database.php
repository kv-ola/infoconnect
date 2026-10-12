<?php
date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'infoconnect';

function getDbConnection(): mysqli {
    static $connection = null;

    if ($connection === null) {
        global $dbHost, $dbUser, $dbPass, $dbName;

        try {
            $newConnection = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
            $newConnection->set_charset('utf8mb4');
            $newConnection->query("SET time_zone = '+08:00'");
            $connection = $newConnection;
        } catch (mysqli_sql_exception $exception) {
            error_log('Database connection initialization failed: ' . $exception->getMessage());
            http_response_code(500);
            exit('Database connection failed. Please try again later.');
        }
    }

    return $connection;
}

$databaseConnection = getDbConnection();

/**
 * Reject event requests that are still awaiting review on the event date.
 * This runs idempotently whenever the application opens a database connection.
 */
function expireUnreviewedEventRequests(mysqli $connection): void
{
    try {
        $connection->query(
            "UPDATE approval_requests ar
             INNER JOIN events e ON e.id = ar.reference_id
             SET ar.status = 'rejected',
                 ar.reviewed_by = NULL,
                 ar.reviewed_at = NOW(),
                 ar.remarks = 'Automatically rejected because the event date was reached before review.',
                 e.status = 'rejected'
             WHERE ar.request_type = 'event'
               AND ar.status = 'pending'
               AND e.status = 'pending'
               AND e.event_date <= CURRENT_DATE"
        );

        $connection->query(
            "UPDATE approval_requests ar
             INNER JOIN announcements a ON a.id = ar.reference_id
             SET ar.status = 'rejected',
                 ar.reviewed_by = NULL,
                 ar.reviewed_at = NOW(),
                 ar.remarks = 'Automatically rejected because the announcement expiry date was reached before review.',
                 a.status = 'rejected'
             WHERE ar.request_type = 'announcement'
               AND ar.status = 'pending'
               AND a.status = 'pending'
               AND a.expires_at IS NOT NULL
               AND a.expires_at < DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)"
        );

        $connection->query(
            "UPDATE events
             SET status = 'completed'
             WHERE status IN ('approved', 'published')
               AND (event_date < CURRENT_DATE
                    OR (event_date = CURRENT_DATE AND end_time IS NOT NULL AND end_time <= CURRENT_TIME))"
        );
    } catch (mysqli_sql_exception $exception) {
        error_log('Unable to expire unreviewed post requests: ' . $exception->getMessage());
    }
}

expireUnreviewedEventRequests($databaseConnection);
