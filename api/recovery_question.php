<?php
header('Content-Type: application/json');

session_save_path('C:/Users/PC/php_sessions');
session_start();

require_once __DIR__ . '/db.php';

function respond($status, $message, $extra = [])
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $status >= 200 && $status < 300,
        'message' => $message
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Method not allowed.');
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, 'Please enter a valid email address.');
}

try {
    $stmt = $conn->prepare(
        'SELECT user_id, recovery_question
         FROM users
         WHERE LOWER(email) = LOWER(:email)
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['recovery_question'])) {
        respond(404, 'No recovery information found for that account.');
    }

    unset($_SESSION['recovery_user_id']);
    unset($_SESSION['recovery_verified']);

    $_SESSION['pending_recovery_user_id'] = $user['user_id'];

    respond(200, 'Recovery question found.', [
        'question' => $user['recovery_question']
    ]);

} catch (PDOException $e) {
    error_log('Recovery question error: ' . $e->getMessage());
    respond(500, 'A server error occurred.');
}
