<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php'; // assure la même connexion que le reste
if (function_exists('require_login')) {
    require_login('host');
}

/**
 * Récupère l'ID de la session :
 * - d'abord via GET/POST (input hidden),
 * - sinon via la session serveur (si tu as choisi l'option sans passer l'ID dans le bouton).
 */
$session_id = (int)($_REQUEST['session_id'] ?? ($_SESSION['current_session_id'] ?? 0));
if ($session_id <= 0) {
    http_response_code(400);
    echo 'session_id manquant';
    exit;
}

$db = getDatabase();

/* Clôture la session (pas d’export auto) */
$stmt = $db->prepare('UPDATE sessions SET is_active = 0, ended_at = CURRENT_TIMESTAMP WHERE id = ?');
$stmt->execute([$session_id]);

/* Retour sur l’écran animateur de la session (où tu as les boutons d’export) */
header('Location: ../host_session.php?session_id=' . $session_id);
exit;
