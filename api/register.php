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

function registrationError($message, $status = 400) {
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

$username = trim($data['username'] ?? '');
$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

$recoveryQuestions = $data['recovery_questions'] ?? [];
$recoveryAnswers = $data['recovery_answers'] ?? [];

$allowedQuestions = [
    "What is the name of your first pet?",
    "What is your mother's maiden name?",
    "What was your childhood nickname?",
    "In what town were you born?",
    "What was the name of your first school?",
    "What is the name of your favorite childhood teacher?",
    "What was your favorite childhood game?",
    "What was the name of your childhood best friend?",
    "What is the name of the street you grew up on?",
    "What was your favorite subject in elementary school?",
    "What was the name of your first stuffed toy?",
    "What was your childhood dream job?",
    "What is the name of your favorite childhood cartoon?",
    "What was the first movie you watched in a cinema?",
    "What was your favorite childhood book?"
];

if (
    $username === '' ||
    $email === '' ||
    $password === '' ||
    $confirmPassword === ''
) {
    registrationError('Please complete all required fields.');
}

if (strlen($username) > 50) {
    registrationError('Username must not exceed 50 characters.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    registrationError('Please enter a valid email address.');
}

if ($password !== $confirmPassword) {
    registrationError('Passwords do not match.');
}

if (strlen($password) < 8) {
    registrationError('Password must be at least 8 characters long.');
}

if (
    !is_array($recoveryQuestions) ||
    !is_array($recoveryAnswers) ||
    count($recoveryQuestions) !== 3 ||
    count($recoveryAnswers) !== 3
) {
    registrationError('Please provide three security questions and answers.');
}

foreach ($recoveryQuestions as $question) {
    if (
        !is_string($question) ||
        !in_array($question, $allowedQuestions, true)
    ) {
        registrationError('Please select valid security questions.');
    }
}

if (count(array_unique($recoveryQuestions)) !== 3) {
    registrationError('Please select three different security questions.');
}

$answerHashes = [];

foreach ($recoveryAnswers as $answer) {
    if (!is_string($answer)) {
        registrationError('Invalid security answer.');
    }

    $answer = trim($answer);

    if ($answer === '') {
        registrationError('Please answer all three security questions.');
    }

    if (strlen($answer) > 255) {
        registrationError('Each security answer must not exceed 255 characters.');
    }

    $normalizedAnswer = mb_strtolower($answer, 'UTF-8');

    $answerHashes[] = password_hash(
        $normalizedAnswer,
        PASSWORD_DEFAULT
    );
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {

    $sql = '
        INSERT INTO users (
            username,
            email,
            password_hash,
            recovery_question,
            recovery_answer_hash,
            recovery_question_2,
            recovery_answer_hash_2,
            recovery_question_3,
            recovery_answer_hash_3
        )
        VALUES (
            :username,
            :email,
            :password_hash,
            :recovery_question,
            :recovery_answer_hash,
            :recovery_question_2,
            :recovery_answer_hash_2,
            :recovery_question_3,
            :recovery_answer_hash_3
        )
        RETURNING user_id
    ';

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,

        ':recovery_question' => $recoveryQuestions[0],
        ':recovery_answer_hash' => $answerHashes[0],

        ':recovery_question_2' => $recoveryQuestions[1],
        ':recovery_answer_hash_2' => $answerHashes[1],

        ':recovery_question_3' => $recoveryQuestions[2],
        ':recovery_answer_hash_3' => $answerHashes[2]
    ]);

    http_response_code(201);

    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully.',
        'user_id' => $stmt->fetchColumn()
    ]);

} catch (PDOException $e) {

    if ($e->getCode() === '23505') {
        registrationError(
            'Username or email is already registered.',
            409
        );
    }

    error_log('Registration failed: ' . $e->getMessage());

    registrationError(
        'Unable to create your account right now.',
        500
    );
}

?>