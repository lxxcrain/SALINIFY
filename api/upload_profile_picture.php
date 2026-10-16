<?php

session_save_path(__DIR__ . '/../php_sessions');
session_start();

header('Content-Type: application/json; charset=utf-8');

function respond($status, $message, $extra = [])
{
    http_response_code($status);

    echo json_encode(array_merge([
        'success' => $status >= 200 && $status < 300,
        'message' => $message
    ], $extra));

    exit;
}

if (empty($_SESSION['user_id'])) {
    respond(401, 'Please log in again.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, 'Use POST to upload a picture.');
}

if (!isset($_FILES['profile_picture'])) {
    respond(400, 'Please choose an image first.');
}

$file = $_FILES['profile_picture'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    error_log('Profile upload error code: ' . $file['error']);

    if ($file['error'] === UPLOAD_ERR_NO_TMP_DIR) {
        respond(500, 'PHP upload temporary folder is missing.');
    }

    if ($file['error'] === UPLOAD_ERR_CANT_WRITE) {
        respond(500, 'PHP cannot write the temporary upload file.');
    }

    if ($file['error'] === UPLOAD_ERR_INI_SIZE ||
        $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        respond(400, 'The image exceeds the upload size limit.');
    }

    respond(400, 'The upload failed. Please try again.');
}

$maxSize = 2 * 1024 * 1024;

if ($file['size'] <= 0 || $file['size'] > $maxSize) {
    respond(400, 'Choose an image smaller than 2 MB.');
}

$imageInfo = @getimagesize($file['tmp_name']);

if ($imageInfo === false) {
    respond(400, 'The selected file is not a valid image.');
}

$allowedTypes = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_WEBP => 'webp'
];

$imageType = $imageInfo[2];

if (!isset($allowedTypes[$imageType])) {
    respond(400, 'Only JPEG, PNG, and WebP images are allowed.');
}

if (!is_uploaded_file($file['tmp_name'])) {
    respond(400, 'Invalid upload.');
}

$projectRoot = dirname(__DIR__);
$uploadDir = $projectRoot . '/uploads/profile_pictures';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        error_log('Could not create upload directory: ' . $uploadDir);
        respond(500, 'Unable to create the profile picture folder.');
    }
}

$testFile = $uploadDir . '/.write_test_' . bin2hex(random_bytes(4));

if (@file_put_contents($testFile, 'test') === false) {
    error_log('PHP cannot write to: ' . $uploadDir);
    respond(500, 'PHP cannot write to the upload folder.');
}

unlink($testFile);

try {
    // Connect to PostgreSQL only after validating the upload.
    require_once __DIR__ . '/db.php';

    $stmt = $conn->prepare(
        'SELECT profile_picture FROM users WHERE user_id = :user_id'
    );

    $stmt->execute([
        ':user_id' => $_SESSION['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        respond(404, 'Account not found.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$imageType];
    $destination = $uploadDir . '/' . $filename;
    $relativePath = 'uploads/profile_pictures/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        error_log('move_uploaded_file failed for: ' . $destination);
        respond(500, 'Unable to save the uploaded image.');
    }

    try {
        $stmt = $conn->prepare(
            'UPDATE users
             SET profile_picture = :profile_picture
             WHERE user_id = :user_id'
        );

        $stmt->execute([
            ':profile_picture' => $relativePath,
            ':user_id' => $_SESSION['user_id']
        ]);
    } catch (Throwable $e) {
        if (is_file($destination)) {
            unlink($destination);
        }

        throw $e;
    }

    $oldPath = $user['profile_picture'] ?? '';
    $prefix = 'uploads/profile_pictures/';

    if (
        $oldPath !== '' &&
        strpos($oldPath, $prefix) === 0 &&
        basename($oldPath) === substr($oldPath, strlen($prefix))
    ) {
        $oldFile = $projectRoot . '/' . $oldPath;

        if (
            is_file($oldFile) &&
            realpath(dirname($oldFile)) === realpath($uploadDir)
        ) {
            unlink($oldFile);
        }
    }

    respond(200, 'Profile picture updated successfully.', [
        'profile_picture' => $relativePath
    ]);

} catch (Throwable $e) {
    error_log('Profile picture upload error: ' . $e->getMessage());

    respond(500, 'Unable to update your profile picture. Please try again.');
}
