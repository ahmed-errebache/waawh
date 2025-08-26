<?php
require_once '../config.php';
require_once '../db.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['id'] ?? null;
    
    if (!$sessionId) {
        throw new Exception('ID de session manquant');
    }
    
    $db = Database::getInstance()->getPDO();
    
    // Récupérer la session
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception('Session introuvable');
    }
    
    // Compter les participants uniques
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT user_name) as participant_count 
        FROM responses 
        WHERE session_id = ?
    ");
    $stmt->execute([$sessionId]);
    $participantCount = $stmt->fetchColumn();
    $session['participant_count'] = $participantCount;
    
    $question = null;
    $results = null;
    
    // Si une question est active, la récupérer
    if ($session['current_question_index'] >= 0) {
        $stmt = $db->prepare("SELECT * FROM questions LIMIT 1 OFFSET ?");
        $stmt->execute([$session['current_question_index']]);
        $question = $stmt->fetch();
        
        if ($question) {
            // Récupérer les statistiques de réponses
            $stmt = $db->prepare("
                SELECT answer_indices, COUNT(*) as count
                FROM responses 
                WHERE session_id = ? AND question_id = ?
                GROUP BY answer_indices
            ");
            $stmt->execute([$sessionId, $question['id']]);
            $responses = $stmt->fetchAll();
            
            // Préparer les compteurs par choix
            $choices = json_decode($question['choices'], true);
            $counts = array_fill(0, count($choices), 0);
            
            foreach ($responses as $response) {
                $answerIndices = json_decode($response['answer_indices'], true);
                if (is_array($answerIndices)) {
                    foreach ($answerIndices as $index) {
                        if (isset($counts[$index])) {
                            $counts[$index] += $response['count'];
                        }
                    }
                }
            }
            
            $results = ['counts' => $counts];
        }
    }
    
    echo json_encode([
        'ok' => true,
        'session' => $session,
        'question' => $question,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>