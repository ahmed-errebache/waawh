<?php
// API endpoint to export session results in CSV or XLS format.
// Only the host who owns the session (or an administrator) may download the results.
session_start();
require_once __DIR__ . '/../config.php';
require_login(null); // allow both admin and host but validate below

$session_id = $_GET['session_id'] ?? null;
$format = strtolower($_GET['format'] ?? 'csv');
if (!$session_id) {
    http_response_code(400);
    echo 'Paramètre session_id manquant.';
    exit;
}
// Validate format
if (!in_array($format, ['csv','xls'])) {
    http_response_code(400);
    echo 'Format invalide.';
    exit;
}

$db = connect_db();
// Load session and related survey
$stmt = $db->prepare('SELECT s.*, su.title, s.host_id FROM sessions s JOIN surveys su ON s.survey_id = su.id WHERE s.id = ?');
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(404);
    echo 'Session introuvable.';
    exit;
}
$user = current_user();
// Only allow export if current user is admin or the host of this session
if ($user['role'] !== 'admin' && $session['host_id'] != $user['id']) {
    http_response_code(403);
    echo 'Accès refusé.';
    exit;
}

// Fetch responses with question and participants, include explanation as host note
$query = "SELECT r.user_name, q.qtext, q.explain_text, r.answer_indices, r.is_correct, q.points, q.choices, q.qtype
          FROM responses r
          JOIN questions q ON r.question_id = q.id
          WHERE r.session_id = ?
          ORDER BY r.user_name, q.id";
$stmt = $db->prepare($query);
$stmt->execute([$session_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare output
$filename = 'session_' . $session_id . '_results.' . $format;

// Build header row; include note animateur
$headers = ['Participant','Question','Note animateur','Réponse','Correct','Score'];

// Open output buffer
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    // Write UTF-8 BOM for Excel compatibility
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        // Determine answer display: decode indices to values when possible
        $answerDisplay = '';
        if ($r['answer_indices'] !== null && $r['answer_indices'] !== '') {
            // Try to decode JSON
            $decoded = json_decode($r['answer_indices'], true);
            if (is_array($decoded)) {
                // For quizzes, truefalse or opinion, decode choices
                if (in_array($r['qtype'], ['quiz','truefalse','opinion'])) {
                    $choices = json_decode($r['choices'], true) ?: [];
                    $labels = [];
                    foreach ($decoded as $idx) {
                        if (isset($choices[$idx])) {
                            $labels[] = $choices[$idx];
                        }
                    }
                    $answerDisplay = join(' | ', $labels);
                } else {
                    // For other types, encode as JSON string (or plain text for feedback)
                    $answerDisplay = json_encode($decoded);
                }
            } else {
                $answerDisplay = $r['answer_indices'];
            }
        }
        $score = ($r['is_correct'] ? (int)$r['points'] : 0);
        fputcsv($out, [$r['user_name'], $r['qtext'], $r['explain_text'], $answerDisplay, $r['is_correct'] ? 'Oui' : 'Non', $score]);
    }
    fclose($out);
    exit;
} else {
    // XLS: we use tab-delimited output with Excel MIME type
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // Write header
    echo implode("\t", $headers) . "\n";
    foreach ($rows as $r) {
        $answerDisplay = '';
        if ($r['answer_indices'] !== null && $r['answer_indices'] !== '') {
            $decoded = json_decode($r['answer_indices'], true);
            if (is_array($decoded)) {
                if (in_array($r['qtype'], ['quiz','truefalse','opinion'])) {
                    $choices = json_decode($r['choices'], true) ?: [];
                    $labels = [];
                    foreach ($decoded as $idx) {
                        if (isset($choices[$idx])) {
                            $labels[] = $choices[$idx];
                        }
                    }
                    $answerDisplay = join(' | ', $labels);
                } else {
                    $answerDisplay = json_encode($decoded);
                }
            } else {
                $answerDisplay = $r['answer_indices'];
            }
        }
        $score = ($r['is_correct'] ? (int)$r['points'] : 0);
        $line = [$r['user_name'], $r['qtext'], $r['explain_text'], $answerDisplay, $r['is_correct'] ? 'Oui' : 'Non', $score];
        echo implode("\t", $line) . "\n";
    }
    exit;
}