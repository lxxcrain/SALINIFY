<?php

session_save_path('C:/Users/PC/php_sessions');
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Please log in again.'
    ]);
    exit;
}

try {
    $sql = "SELECT username, email, created_at, profile_picture
            FROM users
            WHERE user_id = :user_id";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':user_id' => $_SESSION['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Account not found.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'username' => $user['username'],
        'email' => $user['email'],
        'created_at' => $user['created_at'],
        'profile_picture' => $user['profile_picture']
    ]);

} catch (PDOException $e) {
    error_log('Account API error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load account details.'
    ]);
}