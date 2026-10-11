<?php

$sessionPath = 'C:/Users/PC/php_sessions';

if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
    error_log('Session directory is missing or not writable.');
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Session storage is unavailable.'
    ]);
    exit;
}

session_save_path($sessionPath);
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

require_once __DIR__ . '/db.php';

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$data = [];

if (stripos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request data.'
        ]);
        exit;
    }
} else {
    $data = $_POST;
}

$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required.'
    ]);
    exit;
}

try {
    $sql = '
        SELECT user_id, username, email, password_hash
        FROM users
        WHERE email = :email
        LIMIT 1
    ';

    $stmt = $conn->prepare($sql);
    $stmt->execute([':email' => $email]);

    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password.'
        ]);
        exit;
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to regenerate session ID.');
    }

    $_SESSION['user_id'] = (int) $user['user_id'];

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'user_id' => (int) $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]
    ]);

} catch (Throwable $e) {
    error_log('Login failed: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to log in right now.'
    ]);
}
?>