<?php

function generateAccountId(mysqli $connection, string $personId): string
{
    $accountId = trim($personId);
    if ($accountId === '' || strlen($accountId) > 50) {
        throw new RuntimeException('A valid account ID is required.');
    }

    $lookup = $connection->prepare('SELECT id FROM users WHERE account_id = ? LIMIT 1 FOR UPDATE');
    if (!$lookup) {
        throw new RuntimeException('Unable to verify username availability.');
    }

    $lookup->bind_param('s', $accountId);
    $lookup->execute();
    $lookup->store_result();
    $exists = $lookup->num_rows > 0;
    $lookup->free_result();
    $lookup->close();
    if ($exists) {
        throw new RuntimeException('This account ID is already assigned to a login account.');
    }

    return $accountId;
}

function createSeniorLoginAccount(mysqli $connection, string $seniorCitizenId): array
{
    $accountId = generateAccountId($connection, $seniorCitizenId);
    $temporaryPassword = 'infoconnect';
    $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
    $role = 'senior';
    $status = 'active';
    $mustChangePassword = 1;
    $statement = $connection->prepare(
        'INSERT INTO users (account_id, password, role, status, must_change_password) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$statement) {
        throw new RuntimeException('Unable to prepare senior account creation.');
    }
    $statement->bind_param('ssssi', $accountId, $passwordHash, $role, $status, $mustChangePassword);
    $statement->execute();
    $userId = (int) $connection->insert_id;
    $statement->close();

    return [
        'user_id' => $userId,
        'account_id' => $accountId,
        'temporary_password' => $temporaryPassword,
    ];
}
