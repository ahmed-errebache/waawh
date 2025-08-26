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
    
    // Vérifier que la session existe et est ouverte
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND status = 'open'");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception('Session introuvable ou fermée');
    }
    
    // Démarrer à la première question
    $stmt = $db->prepare("UPDATE sessions SET current_question_index = 0 WHERE id = ?");
    $stmt->execute([$sessionId]);
    
    echo json_encode(['ok' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>