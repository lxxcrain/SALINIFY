<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

function respond($status, $data)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Require login.
if (empty($_SESSION['user_id'])) {
    respond(401, ['success' => false, 'message' => 'Please log in first.']);
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $sql = "
            SELECT
                uwp.progress_id,
                uwp.word_id,
                uwp.status,
                uwp.source,
                uwp.review_count,
                uwp.correct_count,
                uwp.manually_marked_learned,
                uwp.last_reviewed_at,
                uwp.updated_at
            FROM user_word_progress uwp
            WHERE uwp.user_id = :user_id
            ORDER BY uwp.updated_at DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        respond(200, [
            'success' => true,
            'progress' => $stmt->fetchAll()
        ]);
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            respond(400, ['success' => false, 'message' => 'Invalid JSON body.']);
        }

        $wordId = filter_var($input['word_id'] ?? null, FILTER_VALIDATE_INT);
        $action = $input['action'] ?? '';

        if (!$wordId || !in_array($action, ['mark_learned', 'mark_learning'], true)) {
            respond(400, [
                'success' => false,
                'message' => 'Provide a valid word_id and action.'
            ]);
        }

        // Check that the word exists.
        $check = $conn->prepare('SELECT word_id FROM words WHERE word_id = :word_id');
        $check->execute(['word_id' => $wordId]);

        if (!$check->fetch()) {
            respond(404, ['success' => false, 'message' => 'Word not found.']);
        }

        $learned = ($action === 'mark_learned');

        $sql = "
            INSERT INTO user_word_progress
                (user_id, word_id, status, source, manually_marked_learned,
                 review_count, correct_count, updated_at)
            VALUES
                (:user_id, :word_id, :status, 'manual', :learned, 0, 0, CURRENT_TIMESTAMP)
            ON CONFLICT (user_id, word_id)
            DO UPDATE SET
                manually_marked_learned = EXCLUDED.manually_marked_learned,
                status = CASE
                    WHEN EXCLUDED.manually_marked_learned = TRUE THEN 'learned'
                    ELSE 'learning'
                END,
                source = CASE
                    WHEN user_word_progress.source = 'quiz'
                         AND EXCLUDED.manually_marked_learned = TRUE THEN 'both'
                    WHEN user_word_progress.source = 'both'
                         AND EXCLUDED.manually_marked_learned = FALSE THEN 'quiz'
                    ELSE user_word_progress.source
                END,
                updated_at = CURRENT_TIMESTAMP
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'word_id' => $wordId,
            'status' => $learned ? 'learned' : 'learning',
            'learned' => $learned ? 'true' : 'false'
        ]);

        respond(200, [
            'success' => true,
            'message' => $learned
                ? 'Word marked as learned.'
                : 'Word returned to learning status.'
        ]);
    }

    header('Allow: GET, POST');
    respond(405, ['success' => false, 'message' => 'Method not allowed.']);

} catch (PDOException $e) {
    error_log('word_progress.php: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Unable to process word progress.']);
}