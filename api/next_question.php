<?php
// API endpoint to advance to the next question in the session
session_start();
require_once __DIR__ . '/../config.php';
require_login(); // Allow both admin and host access

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

// Verify the session belongs to this host and is active
$stmt = $db->prepare('SELECT * FROM sessions WHERE id = ? AND (host_id = ? OR ? = "admin") AND is_active = 1');
$stmt->execute([$session_id, $user['id'], $user['role']]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(403);
    exit;
}

// Count the total number of questions in the survey
$stmt2 = $db->prepare('SELECT COUNT(*) FROM questions WHERE survey_id = ?');
$stmt2->execute([$session['survey_id']]);
$total = (int)$stmt2->fetchColumn();

// Compute next index; if beyond total, keep at last index
// Calculate next index (0-based) and ensure it does not exceed last question index
$nextIndex = $session['current_question_index'] + 1;
// $total is the number of questions; valid indices are 0..$total-1
if ($nextIndex >= $total) {
    // Stay on the last question instead of incrementing beyond
    $nextIndex = max(0, $total - 1);
}

// Update current_question_index and reset reveal_state to 0
$db->prepare('UPDATE sessions SET current_question_index = ?, reveal_state = 0 WHERE id = ?')
    ->execute([$nextIndex, $session_id]);

header('Location: ../host_session.php?session_id=' . $session_id);
exit;