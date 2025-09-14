<?php
// API endpoint to record a participant's selection for a question without final submission
// It allows updating the selection multiple times until the question is revealed.
// Expects POST: session_id, question_id, user_name, and answer_indices[] (array of indices) or answer_index (single index)

session_start();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Get parameters
$session_id = $_POST['session_id'] ?? null;
$question_id = $_POST['question_id'] ?? null;
$user_name = trim($_POST['user_name'] ?? '');

if (!$session_id || !$question_id || $user_name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

$db = connect_db();

// Verify session exists and is active
$stmt = $db->prepare('SELECT * FROM sessions WHERE id = ? AND is_active = 1');
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(403);
    echo json_encode(['error' => 'Session terminée']);
    exit;
}

// Do not allow updates if reveal_state = 1 (answers locked)
if ($session['reveal_state']) {
    http_response_code(403);
    echo json_encode(['error' => 'Réponses verrouillées']);
    exit;
}

// Verify question belongs to this survey
$stmt = $db->prepare('SELECT * FROM questions WHERE id = ? AND survey_id = ?');
$stmt->execute([$question_id, $session['survey_id']]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$question) {
    http_response_code(400);
    echo json_encode(['error' => 'Question invalide']);
    exit;
}

// Determine indices from POST data (supports both answer_indices[] and single answer_index)
$indices = [];
if (isset($_POST['answer_indices'])) {
    $ans = $_POST['answer_indices'];
    if (!is_array($ans)) {
        $ans = [$ans];
    }
    foreach ($ans as $val) {
        $indices[] = intval($val);
    }
} elseif (isset($_POST['answer_index'])) {
    $indices[] = intval($_POST['answer_index']);
}

// Sort indices for consistent comparison
sort($indices);

// Compute correctness based on question type and correct_indices
$qtype = $question['qtype'];
$choices = $question['choices'] ? json_decode($question['choices'], true) : [];
$correct_indices = $question['correct_indices'] ? json_decode($question['correct_indices'], true) : [];
$is_correct = 0;

switch ($qtype) {
    case 'quiz':
        // For quiz, compare entire array of indices
        if ($indices === $correct_indices) {
            $is_correct = 1;
        }
        break;
    case 'truefalse':
        $ans = isset($indices[0]) ? $indices[0] : null;
        if ($ans !== null && $ans === ($correct_indices[0] ?? 0)) {
            $is_correct = 1;
        }
        break;
    case 'short':
    case 'long':
    case 'rating':
    case 'date':
        // For these types, we do not handle via this API
        break;
    case 'opinion':
        // Opinion questions have no correct answer. Still store selected indices as payload.
        $is_correct = 0;
        break;
}

// Encode payload as JSON for answer_indices column; for other types, we leave null
$payload = null;
// Store answer indices for quiz, truefalse and opinion types
if ($qtype === 'quiz' || $qtype === 'truefalse' || $qtype === 'opinion') {
    $payload = json_encode($indices);
}

// Insert or update response record
$stmt = $db->prepare('SELECT 1 FROM responses WHERE session_id = ? AND question_id = ? AND user_name = ?');
$stmt->execute([$session_id, $question_id, $user_name]);
$exists = $stmt->fetchColumn();
if ($exists) {
    // Update existing response
    $upd = $db->prepare('UPDATE responses SET answer_indices = ?, is_correct = ? WHERE session_id = ? AND question_id = ? AND user_name = ?');
    $upd->execute([$payload, $is_correct, $session_id, $question_id, $user_name]);
} else {
    // Insert new response (and insert participant if necessary)
    // Ensure participant exists
    $db->prepare('INSERT OR IGNORE INTO participants (session_id, name) VALUES (?,?)')->execute([$session_id, $user_name]);
    $ins = $db->prepare('INSERT INTO responses (session_id, question_id, user_name, answer_indices, is_correct) VALUES (?,?,?,?,?)');
    $ins->execute([$session_id, $question_id, $user_name, $payload, $is_correct]);
}

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
?>