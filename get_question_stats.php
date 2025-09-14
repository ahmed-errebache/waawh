<?php
// Endpoint pour renvoyer les statistiques de réponses pour la question en cours d'une session
// Paramètres : session_id (obligatoire)

session_start();
require_once __DIR__ . '/config.php';

$session_id = $_GET['session_id'] ?? null;
if (!$session_id) {
    http_response_code(400);
    echo json_encode(['error' => 'session_id manquant']);
    exit;
}

// Connexion BD
$db = connect_db();

// Récupérer la session
$stmt = $db->prepare('SELECT * FROM sessions WHERE id = ?');
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(404);
    echo json_encode(['error' => 'Session introuvable']);
    exit;
}

// Vérifier la progression (index de la question en cours)
$currentIndex = (int)$session['current_question_index'];

// Si l'index est négatif, retourner vide
if ($currentIndex < 0) {
    echo json_encode(['counts' => [], 'total' => 0, 'correct_count' => 0, 'correct_index' => null, 'options' => []]);
    exit;
}

// Récupérer la question courante avec les champs nécessaires
$stmt = $db->prepare('SELECT id, qtype, choices, correct_indices FROM questions WHERE survey_id = ? ORDER BY id LIMIT 1 OFFSET ?');
$stmt->execute([$session['survey_id'], $currentIndex]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$question) {
    // Pas de question disponible (peut-être terminé)
    echo json_encode(['counts' => [], 'total' => 0, 'correct_count' => 0, 'correct_index' => null, 'options' => []]);
    exit;
}

// Décoder les choix et la bonne réponse
$choices = $question['choices'] ? json_decode($question['choices'], true) : [];
$correct_indices = $question['correct_indices'] ? json_decode($question['correct_indices'], true) : [];
$correct_index = null;
if (is_array($correct_indices) && count($correct_indices) > 0) {
    $correct_index = $correct_indices[0];
}

// Initialiser les compteurs
$counts = array_fill(0, count($choices), 0);
$total = 0;
$correct_count = 0;

// Récupérer les réponses pour la question actuelle
$stmt = $db->prepare('SELECT answer_indices, is_correct FROM responses WHERE session_id = ? AND question_id = ?');
$stmt->execute([$session_id, $question['id']]);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($responses as $r) {
    $total++;
    $ans = $r['answer_indices'];
    // answer_indices peut être un tableau JSON ou null
    $indices = [];
    if ($ans !== null) {
        $decoded = json_decode($ans, true);
        if (is_array($decoded)) {
            $indices = $decoded;
        }
    }
    // Incrémenter chaque index choisi
    foreach ($indices as $idx) {
        $i = intval($idx);
        if (isset($counts[$i])) {
            $counts[$i]++;
        }
    }
    // Compter les bonnes réponses
    if ($r['is_correct']) {
        $correct_count++;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'counts' => $counts,
    'total' => $total,
    'correct_count' => $correct_count,
    'correct_index' => $correct_index,
    'options' => $choices
]);
?>