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

if (empty($_SESSION['user_id'])) {
    respond(401, ['success' => false, 'message' => 'Please log in first.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$userId = (int) $_SESSION['user_id'];

try {
    // Overall quiz progress.
    $statsStmt = $conn->prepare(
        "SELECT
            COUNT(*) AS attempts_count,
            COALESCE(SUM(score), 0) AS total_correct,
            COALESCE(SUM(total_questions), 0) AS total_questions,
            COALESCE(ROUND(
                100.0 * SUM(score) / NULLIF(SUM(total_questions), 0),
                2
            ), 0) AS accuracy_percentage
         FROM quiz_attempts
         WHERE user_id = :user_id
           AND completed_at IS NOT NULL"
    );

    $statsStmt->execute(['user_id' => $userId]);
    $quizStats = $statsStmt->fetch();

    // Vocabulary/dialect progress.
    $wordStatsStmt = $conn->prepare(
        "SELECT
            COUNT(*) AS tracked_words,
            COUNT(*) FILTER (WHERE status = 'learned') AS learned_words,
            COUNT(*) FILTER (WHERE status = 'learning') AS learning_words,
            COALESCE(SUM(review_count), 0) AS total_reviews,
            COALESCE(SUM(correct_count), 0) AS total_correct_reviews
         FROM user_word_progress
         WHERE user_id = :user_id"
    );

    $wordStatsStmt->execute(['user_id' => $userId]);
    $wordStats = $wordStatsStmt->fetch();

    // Most recent completed quiz attempts.
    $historyStmt = $conn->prepare(
        "SELECT
            qa.attempt_id,
            qa.quiz_id,
            q.quiz_name,
            qa.score,
            qa.total_questions,
            ROUND(
                100.0 * qa.score / NULLIF(qa.total_questions, 0),
                2
            ) AS percentage,
            qa.started_at,
            qa.completed_at
         FROM quiz_attempts qa
         JOIN quizzes q ON q.quiz_id = qa.quiz_id
         WHERE qa.user_id = :user_id
           AND qa.completed_at IS NOT NULL
         ORDER BY qa.completed_at DESC, qa.attempt_id DESC
         LIMIT 20"
    );

    $historyStmt->execute(['user_id' => $userId]);
    $quizHistory = $historyStmt->fetchAll();

    // Word progress details.
    $progressStmt = $conn->prepare(
        "SELECT
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
         ORDER BY uwp.updated_at DESC"
    );

    $progressStmt->execute(['user_id' => $userId]);
    $wordProgress = $progressStmt->fetchAll();

    respond(200, [
        'success' => true,
        'quiz_statistics' => $quizStats,
        'word_statistics' => $wordStats,
        'recent_quiz_attempts' => $quizHistory,
        'word_progress' => $wordProgress
    ]);

} catch (PDOException $e) {
    error_log('get_progress.php: ' . $e->getMessage());
    respond(500, [
        'success' => false,
        'message' => 'Unable to retrieve progress. Check the server log.'
    ]);
}