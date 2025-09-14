<?php
// API endpoint for participants to submit answers
// Expects POST: session_id, question_id, user_name, and answer payload depending on question type
session_start();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$session_id = $_POST['session_id'] ?? null;
$question_id = $_POST['question_id'] ?? null;
$user_name = trim($_POST['user_name'] ?? '');

if (!$session_id || !$question_id || $user_name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

$db = connect_db();

// Verify session exists and active
$stmt = $db->prepare('SELECT * FROM sessions WHERE id = ? AND is_active = 1');
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(403);
    echo json_encode(['error' => 'Session terminée']);
    exit;
}

// Verify question belongs to survey
$stmt = $db->prepare('SELECT * FROM questions WHERE id = ? AND survey_id = ?');
$stmt->execute([$question_id, $session['survey_id']]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$question) {
    http_response_code(400);
    echo json_encode(['error' => 'Question invalide']);
    exit;
}

// Check if reveal_state is 1 (answers locked)
if ($session['reveal_state']) {
    echo json_encode(['error' => 'Réponses verrouillées']);
    exit;
}

// Check if user already answered this question
$stmt = $db->prepare('SELECT 1 FROM responses WHERE session_id=? AND question_id=? AND user_name=?');
$stmt->execute([$session_id, $question_id, $user_name]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'Déjà répondu']);
    exit;
}

// Determine the answer correctness and payload based on question type
$qtype = $question['qtype'];
$choices = $question['choices'] ? json_decode($question['choices'], true) : [];
$correct_indices = $question['correct_indices'] ? json_decode($question['correct_indices'], true) : [];
$is_correct = 0;
$payload = null;

switch ($qtype) {
    case 'quiz':
        // Accept either single or multiple indices
        $ans = $_POST['answer_indices'] ?? ($_POST['answer_index'] ?? []);
        $indices = is_array($ans) ? array_map('intval', $ans) : [intval($ans)];
        sort($indices);
        $payload = $indices;
        if ($indices === $correct_indices) {
            $is_correct = 1;
        }
        break;
    case 'truefalse':
        $ans = (int)($_POST['answer_index'] ?? 0);
        $payload = [$ans];
        if ($ans === ($correct_indices[0] ?? 0)) {
            $is_correct = 1;
        }
        break;
    case 'short':
        $ans = trim($_POST['answer_text'] ?? '');
        $payload = $ans;
        foreach ($choices as $c) {
            if (strcasecmp($ans, $c) == 0) {
                $is_correct = 1;
                break;
            }
        }
        break;
    case 'long':
        $ans = trim($_POST['answer_long'] ?? '');
        $payload = $ans;
        break;
    case 'rating':
        $ans = (int)($_POST['answer_rating'] ?? 0);
        $payload = $ans;
        break;
    case 'date':
        $ans = trim($_POST['answer_date'] ?? '');
        $payload = $ans;
        foreach ($choices as $c) {
            if ($ans === $c) {
                $is_correct = 1;
                break;
            }
        }
        break;
}

// Insert participant record if not exists
$db->prepare('INSERT OR IGNORE INTO participants (session_id, name) VALUES (?,?)')->execute([$session_id, $user_name]);

// Insert response into table
$db->prepare('INSERT INTO responses (session_id, question_id, user_name, answer_indices, is_correct) VALUES (?,?,?,?,?)')
    ->execute([$session_id, $question_id, $user_name, json_encode($payload), $is_correct]);

// If correct and points field exists, update score; we ignore points if null
if ($is_correct && $question['points']) {
    $db->prepare('UPDATE participants SET score = score + ? WHERE session_id = ? AND name = ?')
       ->execute([(int)$question['points'], $session_id, $user_name]);
}

// Return JSON result
$result = [
    'ok' => true,
    'correct' => (bool)$is_correct,
    'confirm_text' => $is_correct ? $question['confirm_text'] : $question['wrong_text'],
    'explain_text' => $question['explain_text'],
];
header('Content-Type: application/json');
echo json_encode($result);
?>