<?php
// Toggle the active status of a host account.
// Only administrators may perform this action.
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
// Do not allow toggling your own admin account or a non-host user
if ($host_id == $_SESSION['user_id']) {
    http_response_code(403);
    exit;
}
$stmt = $db->prepare('SELECT id, role, is_active FROM users WHERE id = ?');
$stmt->execute([$host_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || $row['role'] !== 'host') {
    http_response_code(403);
    exit;
}
$newStatus = ($row['is_active'] ?? 1) ? 0 : 1;
$db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newStatus, $host_id]);
header('Location: ../host_manage.php');
exit;