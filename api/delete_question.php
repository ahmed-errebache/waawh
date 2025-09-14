<?php
session_start();
require_once __DIR__ . '/../config.php';
require_login('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
check_csrf();
$question_id = $_POST['question_id'] ?? null;
if (!$question_id) {
    http_response_code(400);
    exit;
}
$db = connect_db();
// Get survey id for redirect
$stmt = $db->prepare('SELECT survey_id FROM questions WHERE id = ?');
$stmt->execute([$question_id]);
$survey_id = $stmt->fetchColumn();
$db->prepare('DELETE FROM questions WHERE id = ?')->execute([$question_id]);
header('Location: ../builder.php?id=' . $survey_id);
exit;