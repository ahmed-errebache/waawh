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
    $db = Database::getInstance()->getPDO();
    
    // Générer un PIN unique à 5 chiffres
    do {
        $pin = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT COUNT(*) FROM sessions WHERE pin = ? AND status = 'open'");
        $stmt->execute([$pin]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    
    // Créer la session
    $stmt = $db->prepare("
        INSERT INTO sessions (pin, status, current_question_index, created_at) 
        VALUES (?, 'open', -1, datetime('now'))
    ");
    $stmt->execute([$pin]);
    
    $sessionId = $db->lastInsertId();
    
    // Récupérer la session créée
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    echo json_encode([
        'ok' => true,
        'session' => $session
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>