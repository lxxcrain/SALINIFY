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

$data = json_decode(file_get_contents('php://input'), true);
$answer = trim($data['answer'] ?? '');
$userId = $_SESSION['pending_recovery_user_id'] ?? null;

if (!$userId || $answer === '') {
    respond(400, 'Please restart password recovery and enter your answer.');
}

try {
    $stmt = $conn->prepare(
        'SELECT recovery_answer_hash
         FROM users
         WHERE user_id = :user_id'
    );
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user ||
        empty($user['recovery_answer_hash']) ||
        !password_verify(mb_strtolower($answer, 'UTF-8'), $user['recovery_answer_hash'])
    ) {
        respond(400, 'Incorrect recovery answer. Please try again.');
    }

    session_regenerate_id(true);
    $_SESSION['recovery_user_id'] = $userId;
    $_SESSION['recovery_verified'] = true;
    unset($_SESSION['pending_recovery_user_id']);

    respond(200, 'Recovery answer verified.');

} catch (PDOException $e) {
    error_log('Recovery verification error: ' . $e->getMessage());
    respond(500, 'A server error occurred.');
}
