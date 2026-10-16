<?php
header('Content-Type: application/json');

// Using standard relative pathing for the sessions folder
session_start(); 

// Corrected to use the magic constant __DIR__ pointing to db.php in the same folder
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
    header('Allow: POST');
    respond(405, 'Method not allowed.');
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    respond(400, 'Invalid request data.');
}

$identifier = trim($data['identifier'] ?? '');
$password = $data['password'] ?? '';

if ($identifier === '' || $password === '') {
    respond(400, 'Please enter your email/username and password.');
}

try {
    $sql = '
        SELECT user_id, username, email, password_hash
        FROM users
        WHERE LOWER(username) = LOWER(:username)
           OR LOWER(email) = LOWER(:email)
        LIMIT 1
    ';

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'username' => $identifier,
        'email' => $identifier
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        respond(401, 'Invalid email/username or password.');
    }

   session_regenerate_id(true);

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['username'] = $user['username'];

error_log('LOGIN SESSION');
error_log(print_r($_SESSION, true));

respond(200, 'Login successful.', [
        'user' => [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]
    ]);

} catch (PDOException $e) {
    error_log('Login database error: ' . $e->getMessage());
    respond(500, 'A server error occurred. Please try again.');
}
