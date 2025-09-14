<?php
// API endpoint to reveal the current question's correct answer
session_start();
require_once __DIR__ . '/../config.php';
require_login('host');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

check_csrf();

$session_id = $_POST['session_id'] ?? null;
if (!$session_id) {
    http_response_code(400);
    exit;
}

$user = current_user();
$db = connect_db();

// Verify session belongs to this host and is active
$stmt = $db->prepare('SELECT * FROM sessions WHERE id = ? AND host_id = ? AND is_active = 1');
$stmt->execute([$session_id, $user['id']]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(403);
    exit;
}

// Set reveal_state to 1 so that answers are locked and explanation can be shown
// Set reveal_state to 1 so that answers are locked and explanation can be shown
$db->prepare('UPDATE sessions SET reveal_state = 1 WHERE id = ?')->execute([$session_id]);

// Recompute correctness for the current question based on the latest selected answers.
// We recalc is_correct for each response because participants may have changed their selection
// multiple times prior to reveal. At reveal, the last selection counts as final.
// First, find the current question for this session
$curIndex = (int)$session['current_question_index'];
if ($curIndex >= 0) {
    // Load question id and correct indices
    $qStmt = $db->prepare('SELECT id, qtype, correct_indices, points FROM questions WHERE survey_id = ? ORDER BY id LIMIT 1 OFFSET ?');
    $qStmt->execute([$session['survey_id'], $curIndex]);
    $qRow = $qStmt->fetch(PDO::FETCH_ASSOC);
    if ($qRow) {
        $correctIndices = [];
        if (!empty($qRow['correct_indices'])) {
            $decoded = json_decode($qRow['correct_indices'], true);
            if (is_array($decoded)) {
                $correctIndices = $decoded;
                sort($correctIndices);
            }
        }
        $qtype = $qRow['qtype'];
        // Fetch all responses for this session/question
        $respStmt = $db->prepare('SELECT id, answer_indices, user_name FROM responses WHERE session_id = ? AND question_id = ?');
        $respStmt->execute([$session_id, $qRow['id']]);
        $responses = $respStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($responses as $resp) {
            $indices = [];
            if ($resp['answer_indices'] !== null) {
                $decoded = json_decode($resp['answer_indices'], true);
                if (is_array($decoded)) {
                    $indices = $decoded;
                    sort($indices);
                }
            }
            $isCorrect = 0;
            switch ($qtype) {
                case 'quiz':
                    if ($indices === $correctIndices) {
                        $isCorrect = 1;
                    }
                    break;
                case 'truefalse':
                    $ans = isset($indices[0]) ? $indices[0] : null;
                    if ($ans !== null && $ans === ($correctIndices[0] ?? null)) {
                        $isCorrect = 1;
                    }
                    break;
                default:
                    // For other question types, we do not recompute here
                    break;
            }
            // Update is_correct in responses table
            $upd = $db->prepare('UPDATE responses SET is_correct = ? WHERE id = ?');
            $upd->execute([$isCorrect, $resp['id']]);
            // If we were scoring points, we would update participant scores here. Points are neutralised.
        }
    }
}

// Redirect back to host session control page
header('Location: ../host_session.php?session_id=' . $session_id);
exit;