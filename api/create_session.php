<?php
session_start();
require_once __DIR__ . '/../config.php';
require_login('host');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
check_csrf();

$survey_id = $_POST['survey_id'] ?? null;
if (!$survey_id) {
    http_response_code(400);
    echo 'survey_id manquant';
    exit;
}
$user = current_user();

$db = connect_db();
// Ensure the survey belongs to this host
$stmt = $db->prepare('SELECT * FROM surveys WHERE id = ? AND owner_id = ?');
$stmt->execute([$survey_id, $user['id']]);
$survey = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$survey) {
    http_response_code(403);
    echo 'Sondage non autorisé';
    exit;
}
// Close any existing active session for this host
$db->prepare('UPDATE sessions SET is_active = 0, ended_at = CURRENT_TIMESTAMP WHERE host_id = ? AND is_active = 1')->execute([$user['id']]);
// Generate unique PIN
do {
    $pin = random_int(100000, 999999);
    $stmt = $db->prepare('SELECT COUNT(*) FROM sessions WHERE pin = ? AND is_active = 1');
    $stmt->execute([$pin]);
    $exists = $stmt->fetchColumn();
} while ($exists);
// Create new session
$db->prepare('INSERT INTO sessions (survey_id, host_id, pin, is_active, current_question_index, reveal_state) VALUES (?,?,?,?,0,0)')
    ->execute([$survey_id, $user['id'], $pin, 1]);
// Redirect back to host dashboard
header('Location: ../host_dashboard.php');
exit;