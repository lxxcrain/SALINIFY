<?php
header('Content-Type: application/json; charset=utf-8');

session_save_path(dirname(__DIR__) . '/php_sessions');
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
    empty($_SESSION['recovery_user_id']) ||
    empty($_SESSION['recovery_verified_at'])
) {
    respond(403, 'Please verify your recovery answers first.');
}

// Verification authorization expires after 5 minutes.
if (time() - $_SESSION['recovery_verified_at'] > 300) {
    unset(
        $_SESSION['recovery_user_id'],
        $_SESSION['recovery_verified'],
        $_SESSION['recovery_verified_at']
    );

    respond(403, 'Verification expired. Please start password recovery again.');
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    respond(400, 'Invalid request data.');
}

$password = $data['password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

if (
    !is_string($password) ||
    !is_string($confirmPassword)
) {
    respond(400, 'Invalid password data.');
}

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
        respond(404, 'Account not found.');
    }

    unset(
        $_SESSION['pending_recovery_user_id'],
        $_SESSION['recovery_user_id'],
        $_SESSION['recovery_verified'],
        $_SESSION['recovery_verified_at'],
        $_SESSION['recovery_started_at'],
        $_SESSION['recovery_attempts']
    );

    respond(200, 'Password reset successfully.');

} catch (PDOException $e) {
    error_log('Password reset error: ' . $e->getMessage());

    respond(500, 'A server error occurred.');
}