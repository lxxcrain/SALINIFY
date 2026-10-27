<?php
header('Content-Type: application/json; charset=utf-8');

session_save_path(dirname(__DIR__) . '/php_sessions');
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

if (!is_array($data)) {
    respond(400, 'Invalid request data.');
}

$email = trim($data['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, 'Please enter a valid email address.');
}

// Clear previous recovery authorization.
unset(
    $_SESSION['pending_recovery_user_id'],
    $_SESSION['recovery_user_id'],
    $_SESSION['recovery_verified'],
    $_SESSION['recovery_verified_at']
);

try {
    $stmt = $conn->prepare(
        'SELECT
            user_id,
            recovery_question,
            recovery_question_2,
            recovery_question_3
         FROM users
         WHERE LOWER(email) = LOWER(:email)
         LIMIT 1'
    );

    $stmt->execute(['email' => $email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['recovery_question'])) {
        respond(404, 'No recovery information found for that account.');
    }

    $questions = [
        $user['recovery_question']
    ];

    // Include additional questions for newer accounts.
    if (!empty($user['recovery_question_2'])) {
        $questions[] = $user['recovery_question_2'];
    }

    if (!empty($user['recovery_question_3'])) {
        $questions[] = $user['recovery_question_3'];
    }

    session_regenerate_id(true);

    $_SESSION['pending_recovery_user_id'] = $user['user_id'];
    $_SESSION['recovery_started_at'] = time();

    respond(200, 'Recovery questions found.', [
        'questions' => $questions,
        'question_count' => count($questions)
    ]);

} catch (PDOException $e) {
    error_log('Recovery question error: ' . $e->getMessage());

    respond(500, 'A server error occurred.');
}