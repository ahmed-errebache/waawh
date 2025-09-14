<?php
// API endpoint to start the quiz session (reset question index and reveal state)
session_start();
require_once __DIR__ . '/../config.php';
require_login('host');

// Only allow POST requests
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

// Verify that the session belongs to the host and is active
$stmt = $db->prepare('SELECT * FROM sessions WHERE id = ? AND host_id = ? AND is_active = 1');
$stmt->execute([$session_id, $user['id']]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(403);
    exit;
}

// Reset the current question index to 0 and reveal state to 0, and record the start time
$db->prepare('UPDATE sessions SET current_question_index = 0, reveal_state = 0, started_at = CURRENT_TIMESTAMP WHERE id = ?')
    ->execute([$session_id]);

// Redirect back to host session control page
header('Location: ../host_session.php?session_id=' . $session_id);
exit;