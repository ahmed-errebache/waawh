<?php
require_once '../config.php';
require_once '../db.php';

header('Content-Type: application/json');

// Vérifier l'authentification
if (!isHostLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non autorisé']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? null;
    
    if (!$sessionId) {
        throw new Exception('ID de session manquant');
    }
    
    $db = Database::getInstance()->getPDO();
    
    // Terminer la session
    $stmt = $db->prepare("UPDATE sessions SET status = 'ended', current_question_index = -1 WHERE id = ?");
    $stmt->execute([$sessionId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Session introuvable');
    }
    
    echo json_encode(['ok' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>