<?php
header('Content-Type: application/json');

session_save_path('C:/Users/PC/php_sessions');
session_start();

require_once __DIR__ . '/db.php';

function respond($status, $message)
{
    http_response_code($status);
    echo json_encode([
        'success' => $status >= 200 && $status < 300,
        'message' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Method not allowed.');
}

if (
    empty($_SESSION['recovery_verified']) ||
    empty($_SESSION['recovery_user_id'])
) {
    respond(403, 'Please verify your recovery answer first.');
}

$data = json_decode(file_get_contents('php://input'), true);
$password = $data['password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

if (strlen($password) < 8) {
    respond(400, 'Password must be at least 8 characters.');
}

if ($password !== $confirmPassword) {
    respond(400, 'Passwords do not match.');
}

try {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        'UPDATE users
         SET password_hash = :password_hash
         WHERE user_id = :user_id'
    );

    $stmt->execute([
        'password_hash' => $passwordHash,
        'user_id' => $_SESSION['recovery_user_id']
    ]);

    if ($stmt->rowCount() < 1) {
        $check = $conn->prepare(
            'SELECT user_id FROM users WHERE user_id = :user_id'
        );
        $check->execute(['user_id' => $_SESSION['recovery_user_id']]);

        if (!$check->fetchColumn()) {
            respond(404, 'Account not found.');
        }
    }

    unset($_SESSION['pending_recovery_user_id']);
    unset($_SESSION['recovery_user_id']);
    unset($_SESSION['recovery_verified']);

    respond(200, 'Password reset successfully.');

} catch (PDOException $e) {
    error_log('Password reset error: ' . $e->getMessage());
    respond(500, 'A server error occurred.');
}