<?php
session_start();
require_once __DIR__ . '/../config.php';
require_login('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
check_csrf();
$survey_id = $_POST['survey_id'] ?? null;
if (!$survey_id) {
    http_response_code(400);
    exit;
}
$db = connect_db();
$db->prepare('DELETE FROM surveys WHERE id = ?')->execute([$survey_id]);
header('Location: ../admin.php');
exit;