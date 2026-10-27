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

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    respond(400, 'Invalid request data.');
}

$answers = $data['answers'] ?? [];
$userId = $_SESSION['pending_recovery_user_id'] ?? null;
$startedAt = $_SESSION['recovery_started_at'] ?? 0;

if (!$userId) {
    respond(400, 'Please restart password recovery.');
}

// Recovery session expires after 10 minutes.
if (time() - $startedAt > 600) {
    unset(
        $_SESSION['pending_recovery_user_id'],
        $_SESSION['recovery_started_at']
    );

    respond(403, 'Recovery session expired. Please start again.');
}

// Limit incorrect attempts within this recovery session.
$attempts = $_SESSION['recovery_attempts'] ?? 0;

if ($attempts >= 5) {
    respond(429, 'Too many incorrect attempts. Please try again later.');
}

if (!is_array($answers)) {
    respond(400, 'Invalid recovery answers.');
}

try {
    $stmt = $conn->prepare(
        'SELECT
            recovery_question,
            recovery_answer_hash,
            recovery_question_2,
            recovery_answer_hash_2,
            recovery_question_3,
            recovery_answer_hash_3
         FROM users
         WHERE user_id = :user_id'
    );

    $stmt->execute(['user_id' => $userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        respond(404, 'Account not found.');
    }

    $storedHashes = [];

    if (
        !empty($user['recovery_question']) &&
        !empty($user['recovery_answer_hash'])
    ) {
        $storedHashes[] = $user['recovery_answer_hash'];
    }

    if (
        !empty($user['recovery_question_2']) &&
        !empty($user['recovery_answer_hash_2'])
    ) {
        $storedHashes[] = $user['recovery_answer_hash_2'];
    }

    if (
        !empty($user['recovery_question_3']) &&
        !empty($user['recovery_answer_hash_3'])
    ) {
        $storedHashes[] = $user['recovery_answer_hash_3'];
    }

    if (
        count($storedHashes) === 0 ||
        count($answers) !== count($storedHashes)
    ) {
        respond(400, 'Please answer all recovery questions.');
    }

    $allCorrect = true;

    foreach ($storedHashes as $index => $hash) {
        if (!is_string($answers[$index])) {
            respond(400, 'Invalid recovery answer.');
        }

        $normalizedAnswer = mb_strtolower(
            trim($answers[$index]),
            'UTF-8'
        );

        if (
            $normalizedAnswer === '' ||
            !password_verify($normalizedAnswer, $hash)
        ) {
            $allCorrect = false;
        }
    }

    if (!$allCorrect) {
        $_SESSION['recovery_attempts'] = $attempts + 1;

        respond(400, 'One or more recovery answers are incorrect.');
    }

    session_regenerate_id(true);

    $_SESSION['recovery_user_id'] = $userId;
    $_SESSION['recovery_verified'] = true;
    $_SESSION['recovery_verified_at'] = time();

    unset(
        $_SESSION['pending_recovery_user_id'],
        $_SESSION['recovery_started_at'],
        $_SESSION['recovery_attempts']
    );

    respond(200, 'Recovery answers verified successfully.');

} catch (PDOException $e) {
    error_log('Recovery verification error: ' . $e->getMessage());

    respond(500, 'A server error occurred.');
}