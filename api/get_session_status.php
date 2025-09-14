<?php
// Endpoint pour obtenir l'état actuel d'une session (question index, état de révélation, participants)
// Paramètres : session_id (obligatoire)

session_start();
require_once __DIR__ . '/../config.php';

$session_id = $_GET['session_id'] ?? null;
if (!$session_id) {
    http_response_code(400);
    exit;
}

$db = connect_db();
$stmt = $db->prepare('SELECT * FROM sessions WHERE id = ?');
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(404);
    exit;
}

// Fetch participants and scores
$pstmt = $db->prepare('SELECT name, score FROM participants WHERE session_id = ? ORDER BY score DESC, joined_at ASC');
$pstmt->execute([$session_id]);
$participants = $pstmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'current_question_index' => (int)$session['current_question_index'],
    'reveal_state' => (int)$session['reveal_state'],
    'is_active' => (int)$session['is_active'],
    'participants' => $participants,
]);
?>