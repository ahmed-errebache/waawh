<?php
require_once '../config.php';
require_once '../db.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $sessionId = $input['session_id'] ?? null;
    $questionId = $input['question_id'] ?? null;
    $userName = $input['user_name'] ?? null;
    $answerIndices = $input['answer_indices'] ?? null;
    
    if (!$sessionId || !$questionId || !$userName || !is_array($answerIndices)) {
        throw new Exception('Paramètres manquants');
    }
    
    $db = Database::getInstance()->getPDO();
    
    // Vérifier que la session est active
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ? AND status = 'open'");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception('Session introuvable ou fermée');
    }
    
    // Récupérer la question
    $stmt = $db->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$questionId]);
    $question = $stmt->fetch();
    
    if (!$question) {
        throw new Exception('Question introuvable');
    }
    
    // Vérifier si l'utilisateur a déjà répondu à cette question
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM responses 
        WHERE session_id = ? AND question_id = ? AND user_name = ?
    ");
    $stmt->execute([$sessionId, $questionId, $userName]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Vous avez déjà répondu à cette question');
    }
    
    // Vérifier si la réponse est correcte
    $correctIndices = json_decode($question['correct_indices'], true);
    $isCorrect = (count($answerIndices) === count($correctIndices) && 
                  array_diff($answerIndices, $correctIndices) === array_diff($correctIndices, $answerIndices));
    
    // Enregistrer la réponse
    $stmt = $db->prepare("
        INSERT INTO responses (session_id, question_id, user_name, answer_indices, is_correct, created_at)
        VALUES (?, ?, ?, ?, ?, datetime('now'))
    ");
    $stmt->execute([
        $sessionId,
        $questionId,
        $userName,
        json_encode($answerIndices),
        $isCorrect ? 1 : 0
    ]);
    
    echo json_encode([
        'ok' => true,
        'correct' => $isCorrect,
        'confirm_text' => $question['confirm_text'],
        'explain_text' => $question['explain_text']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>