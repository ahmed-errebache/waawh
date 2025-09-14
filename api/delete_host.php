<?php
session_start();
require_once __DIR__ . '/../config.php';
require_login('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
check_csrf();
$host_id = $_POST['host_id'] ?? null;
if (!$host_id) {
    http_response_code(400);
    exit;
}
$db = connect_db();
// Prevent deleting admin or self
if ($host_id == $_SESSION['user_id']) {
    http_response_code(403);
    exit;
}
$stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$host_id]);
$role = $stmt->fetchColumn();
if ($role !== 'host') {
    http_response_code(403);
    exit;
}
// Check if host has assigned surveys either as owner or in survey_hosts table
$stmt = $db->prepare('SELECT COUNT(*) FROM surveys WHERE owner_id = ?');
$stmt->execute([$host_id]);
$countOwner = (int)$stmt->fetchColumn();
$stmt2 = $db->prepare('SELECT COUNT(*) FROM survey_hosts WHERE host_id = ?');
$stmt2->execute([$host_id]);
$countAssign = (int)$stmt2->fetchColumn();
if ($countOwner > 0 || $countAssign > 0) {
    // Cannot delete host assigned to surveys
    $_SESSION['host_delete_error'] = 'Impossible de supprimer cet animateur car il est assigné à un ou plusieurs sondages.';
    header('Location: ../host_manage.php');
    exit;
}
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$host_id]);
header('Location: ../host_manage.php');
exit;