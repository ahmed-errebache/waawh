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
    
    // Récupérer la session
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND status = 'open'");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception('Session introuvable ou fermée');
    }
    
    // Compter le nombre total de questions
    $stmt = $db->query("SELECT COUNT(*) FROM questions");
    $totalQuestions = $stmt->fetchColumn();
    
    $nextIndex = $session['current_question_index'] + 1;
    
    if ($nextIndex >= $totalQuestions) {
        // Fin du quiz
        $stmt = $db->prepare("UPDATE sessions SET status = 'ended', current_question_index = -1 WHERE id = ?");
        $stmt->execute([$sessionId]);
        
        $session['status'] = 'ended';
        $session['current_question_index'] = -1;
    } else {
        // Passer à la question suivante
        $stmt = $db->prepare("UPDATE sessions SET current_question_index = ? WHERE id = ?");
        $stmt->execute([$nextIndex, $sessionId]);
        
        $session['current_question_index'] = $nextIndex;
    }
    
    echo json_encode([
        'ok' => true,
        'session' => $session
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>