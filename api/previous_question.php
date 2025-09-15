<?php
// API endpoint to go back to the previous question in the session
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

// Verify the session belongs to this host and is active
$stmt = $db->prepare('SELECT * FROM sessions WHERE id = ? AND host_id = ? AND is_active = 1');
$stmt->execute([$session_id, $user['id']]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(403);
    exit;
}

// Calculate previous index (0-based) and ensure it doesn't go below 0
$prevIndex = $session['current_question_index'] - 1;
if ($prevIndex < 0) {
    $prevIndex = 0; // Stay on the first question
}

// Update current_question_index and reset reveal_state to 0
$db->prepare('UPDATE sessions SET current_question_index = ?, reveal_state = 0 WHERE id = ?')
    ->execute([$prevIndex, $session_id]);

header('Location: ../host_session.php?session_id=' . $session_id);
exit;
?>