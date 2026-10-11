<?php

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

$username = trim($data['username'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

// Must fill required fields.
if (
    $username === '' ||
    $email === '' ||
    $password === '' ||
    $confirmPassword === ''
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please complete all required fields.'
    ]);
    exit;
}

if (strlen($username) > 50) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username must not exceed 50 characters.'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Passwords do not match.'
    ]);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters long.'
    ]);
    exit;
}

// Hash the password before storing it.
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $sql = '
        INSERT INTO users (username, email, password_hash)
        VALUES (:username, :email, :password_hash)
        RETURNING user_id
    ';

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully.',
        'user_id' => $stmt->fetchColumn()
    ]);

} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Username or email is already registered.'
        ]);
        exit;
    }

    error_log('Registration failed: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to create your account right now.'
    ]);
}
?>
