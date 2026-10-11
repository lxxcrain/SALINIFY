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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    respond(400, ['success' => false, 'message' => 'Invalid JSON body.']);
}

$quizId = filter_var($input['quiz_id'] ?? null, FILTER_VALIDATE_INT);
$answers = $input['answers'] ?? null;

if (!$quizId || !is_array($answers) || count($answers) === 0) {
    respond(400, [
        'success' => false,
        'message' => 'Provide a valid quiz_id and a non-empty answers array.'
    ]);
}

$userId = (int) $_SESSION['user_id'];

try {
    // Confirm the quiz exists.
    $quizStmt = $conn->prepare(
        'SELECT quiz_id FROM quizzes WHERE quiz_id = :quiz_id'
    );
    $quizStmt->execute(['quiz_id' => $quizId]);

    if (!$quizStmt->fetch()) {
        respond(404, ['success' => false, 'message' => 'Quiz not found.']);
    }

    $countStmt = $conn->prepare(
        'SELECT COUNT(*) FROM questions WHERE quiz_id = :quiz_id'
    );
    $countStmt->execute(['quiz_id' => $quizId]);
    $totalQuestions = (int) $countStmt->fetchColumn();

    if ($totalQuestions === 0 || count($answers) !== $totalQuestions) {
        respond(400, [
            'success' => false,
            'message' => 'Submit exactly one answer for every question in this quiz.',
            'expected_questions' => $totalQuestions
        ]);
    }

    $conn->beginTransaction();

    $validatedAnswers = [];
    $seenQuestions = [];
    $score = 0;

    foreach ($answers as $answer) {
        $questionId = filter_var($answer['question_id'] ?? null, FILTER_VALIDATE_INT);
        $optionId = filter_var($answer['selected_option_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$questionId || !$optionId) {
            throw new InvalidArgumentException(
                'Every answer must include a valid question_id and selected_option_id.'
            );
        }

        if (isset($seenQuestions[$questionId])) {
            throw new InvalidArgumentException('Duplicate question answers are not allowed.');
        }
        $seenQuestions[$questionId] = true;

        // Ensure this question belongs to the submitted quiz.
        $questionStmt = $conn->prepare(
            'SELECT question_id FROM questions
             WHERE question_id = :question_id AND quiz_id = :quiz_id'
        );
        $questionStmt->execute([
            'question_id' => $questionId,
            'quiz_id' => $quizId
        ]);

        if (!$questionStmt->fetch()) {
            throw new InvalidArgumentException(
                'A submitted question does not belong to this quiz.'
            );
        }

        // Whether the selected option is correct.
        $optionStmt = $conn->prepare(
            'SELECT option_id, is_correct
             FROM options
             WHERE option_id = :option_id
               AND question_id = :question_id'
        );
        $optionStmt->execute([
            'option_id' => $optionId,
            'question_id' => $questionId
        ]);
        $option = $optionStmt->fetch();

        if (!$option) {
            throw new InvalidArgumentException(
                'A selected option does not belong to its question.'
            );
        }

        $isCorrect = filter_var(
            $option['is_correct'],
            FILTER_VALIDATE_BOOLEAN
        );

        if ($isCorrect) {
            $score++;
        }

        // Optional word_id supports questions that are associated with a vocabulary word.
        $wordId = null;
        if (isset($answer['word_id']) && $answer['word_id'] !== '') {
            $wordId = filter_var($answer['word_id'], FILTER_VALIDATE_INT);

            if (!$wordId) {
                throw new InvalidArgumentException('Invalid word_id.');
            }

            $wordStmt = $conn->prepare(
                'SELECT word_id FROM words WHERE word_id = :word_id'
            );
            $wordStmt->execute(['word_id' => $wordId]);

            if (!$wordStmt->fetch()) {
                throw new InvalidArgumentException('A submitted word_id does not exist.');
            }
        }

        $validatedAnswers[] = [
            'question_id' => $questionId,
            'option_id' => $optionId,
            'word_id' => $wordId,
            'is_correct' => $isCorrect
        ];
    }

    // Store the overall attempt.
    $attemptStmt = $conn->prepare(
        'INSERT INTO quiz_attempts
            (user_id, quiz_id, score, total_questions, started_at, completed_at)
         VALUES
            (:user_id, :quiz_id, :score, :total_questions,
             CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
         RETURNING attempt_id'
    );

    $attemptStmt->execute([
        'user_id' => $userId,
        'quiz_id' => $quizId,
        'score' => $score,
        'total_questions' => $totalQuestions
    ]);

    $attemptId = (int) $attemptStmt->fetchColumn();

    $detailStmt = $conn->prepare(
        'INSERT INTO quiz_answer_details
            (attempt_id, question_id, selected_option_id, word_id, is_correct)
         VALUES
            (:attempt_id, :question_id, :option_id, :word_id, :is_correct)'
    );

    $progressStmt = $conn->prepare(
        "INSERT INTO user_word_progress
            (user_id, word_id, status, source, review_count, correct_count,
             manually_marked_learned, last_reviewed_at, updated_at)
         VALUES
            (:user_id, :word_id, 'learning', 'quiz', 1, :correct_count,
             FALSE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
         ON CONFLICT (user_id, word_id)
         DO UPDATE SET
            review_count = user_word_progress.review_count + 1,
            correct_count = user_word_progress.correct_count + EXCLUDED.correct_count,
            last_reviewed_at = CURRENT_TIMESTAMP,
            source = CASE
                WHEN user_word_progress.manually_marked_learned = TRUE THEN 'both'
                ELSE user_word_progress.source
            END,
            status = CASE
                WHEN user_word_progress.manually_marked_learned = TRUE THEN 'learned'
                ELSE user_word_progress.status
            END,
            updated_at = CURRENT_TIMESTAMP"
    );

    foreach ($validatedAnswers as $answer) {
        $detailStmt->execute([
            'attempt_id' => $attemptId,
            'question_id' => $answer['question_id'],
            'option_id' => $answer['option_id'],
            'word_id' => $answer['word_id'],
            'is_correct' => $answer['is_correct'] ? 'true' : 'false'
        ]);

        if ($answer['word_id'] !== null) {
            $progressStmt->execute([
                'user_id' => $userId,
                'word_id' => $answer['word_id'],
                'correct_count' => $answer['is_correct'] ? 1 : 0
            ]);
        }
    }

    $conn->commit();

    respond(201, [
        'success' => true,
        'message' => 'Quiz attempt saved successfully.',
        'attempt_id' => $attemptId,
        'score' => $score,
        'total_questions' => $totalQuestions
    ]);

} catch (InvalidArgumentException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    respond(400, ['success' => false, 'message' => $e->getMessage()]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('save_quiz_attempt.php: ' . $e->getMessage());
    respond(500, [
        'success' => false,
        'message' => 'Unable to save the quiz attempt. Check the server log.'
    ]);
}