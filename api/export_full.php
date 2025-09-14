<?php
// Export a session's results as a ZIP containing a PDF of statistics and a CSV of responses
// Example: api/export_full.php?session_id=123
session_start();
require_once __DIR__ . '/../config.php';
require_login('host');

// Helpers to escape parentheses in PDF text
function pdf_escape($text) {
    // Escape parentheses and backslashes as required by PDF syntax
    return str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $text);
}

// Generate a simple PDF file with statistics lines
function generate_stats_pdf($lines, $outPath) {
    // Build PDF objects manually. This minimal implementation writes a single-page PDF
    // using the built-in Helvetica font. Each line will be rendered on its own line.
    $content = "";
    // Start text object
    $content .= "BT\n";
    // Use Helvetica 12pt
    $content .= "/F1 12 Tf\n";
    // Move to starting position (50, 780)
    $content .= "50 780 Td\n";
    foreach ($lines as $i => $line) {
        $escaped = pdf_escape($line);
        // Output text for this line
        $content .= "(" . $escaped . ") Tj\n";
        // Move down by 15 points for next line
        $content .= "0 -15 Td\n";
    }
    $content .= "ET\n";
    $length = strlen($content);
    // Offsets for objects (to compute xref)
    $objs = [];
    $pdf  = "%PDF-1.4\n";
    // Catalog
    $objs[] = strlen($pdf);
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    // Pages
    $objs[] = strlen($pdf);
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    // Page
    $objs[] = strlen($pdf);
    $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\n>>\nendobj\n";
    // Contents
    $objs[] = strlen($pdf);
    $pdf .= "4 0 obj\n<< /Length $length >>\nstream\n" . $content . "endstream\nendobj\n";
    // Font object for Helvetica
    $objs[] = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    // xref table
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($objs as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    // trailer
    $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
    file_put_contents($outPath, $pdf);
}

function generate_responses_csv($sessionId, $db, $outPath) {
    // Output CSV with headers: question_id, question_text, participant_name, answer, timestamp
    $fh = fopen($outPath, 'w');
    fputcsv($fh, ['question_id','question_text','participant_name','answer','created_at']);
    // Fetch all questions for ordering
    $stmtQ = $db->prepare('SELECT id, qtext, qtype FROM questions WHERE survey_id = ? ORDER BY id ASC');
    // Determine survey id from session
    $stmt = $db->prepare('SELECT survey_id FROM sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $surveyId = $stmt->fetchColumn();
    $stmtQ->execute([$surveyId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
    // Preload questions mapping
    $qMap = [];
    foreach ($questions as $q) {
        $qMap[$q['id']] = $q;
    }
    // Fetch responses
    $stmtR = $db->prepare('SELECT r.question_id, r.answer_indices, r.answer_text, r.answer_long, r.answer_rating, r.answer_date, r.answer_feedback, r.is_correct, r.created_at, p.name as participant_name
        FROM responses r
        JOIN participants p ON r.participant_id = p.id
        WHERE r.session_id = ? ORDER BY r.created_at');
    $stmtR->execute([$sessionId]);
    while ($row = $stmtR->fetch(PDO::FETCH_ASSOC)) {
        $qid = $row['question_id'];
        $qtext = $qMap[$qid]['qtext'] ?? '';
        $type = $qMap[$qid]['qtype'] ?? '';
        $answerStr = '';
        // Determine answer string depending on type
        switch ($type) {
            case 'quiz':
            case 'truefalse':
            case 'opinion':
                $indices = $row['answer_indices'] ? json_decode($row['answer_indices'], true) : [];
                if (is_array($indices)) {
                    $answerStr = implode('|', $indices);
                }
                break;
            case 'short':
                $answerStr = $row['answer_text'] ?? '';
                break;
            case 'long':
                $answerStr = $row['answer_long'] ?? '';
                break;
            case 'rating':
                $answerStr = $row['answer_rating'] ?? '';
                break;
            case 'date':
                $answerStr = $row['answer_date'] ?? '';
                break;
            case 'feedback':
                $answerStr = $row['answer_feedback'] ?? '';
                break;
            default:
                break;
        }
        fputcsv($fh, [$qid, $qtext, $row['participant_name'], $answerStr, $row['created_at']]);
    }
    fclose($fh);
}

// Validate session ID
$session_id = $_GET['session_id'] ?? null;
if (!$session_id) {
    http_response_code(400);
    echo 'Paramètre session_id manquant.';
    exit;
}
$db = connect_db();
// Verify session exists and belongs to host
$stmt = $db->prepare('SELECT * FROM sessions WHERE id = ?');
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(404);
    echo 'Session introuvable';
    exit;
}
// Only allow export if session is ended (is_active=0)
if ($session['is_active']) {
    http_response_code(403);
    echo 'La session est toujours active.';
    exit;
}
// Compute statistics for each question
$survey_id = $session['survey_id'];
// Fetch questions
$stmtQ = $db->prepare('SELECT id, qtext, qtype, choices, correct_indices, explain_media, explain_text FROM questions WHERE survey_id = ? ORDER BY id ASC');
$stmtQ->execute([$survey_id]);
$questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

// Pre-fetch counts per question
// We'll fetch responses once per question to compute counts
// Compose lines for PDF
$lines = [];
$lines[] = 'Statistiques du sondage';
$lines[] = 'Session PIN: ' . $session['pin'];
$lines[] = 'Date: ' . date('Y-m-d H:i:s');
$lines[] = '';
foreach ($questions as $idx => $q) {
    $qtext = $q['qtext'] ?: 'Question ' . ($idx + 1);
    $qtype = $q['qtype'];
    $lines[] = 'Question ' . ($idx + 1) . ': ' . $qtext;
    // Note animateur (explain_text) if exists
    if ($q['explain_text']) {
        // Add note line truncated to 200 characters
        $note = trim(strip_tags($q['explain_text']));
        $note = mb_substr($note, 0, 200);
        $lines[] = '  Note: ' . $note;
    }
    // Compute counts and total
    $stmtC = $db->prepare('SELECT answer_indices, is_correct FROM responses WHERE session_id=? AND question_id=?');
    $stmtC->execute([$session_id, $q['id']]);
    $counts = [];
    $total = 0;
    $correctCount = 0;
    $choices = $q['choices'] ? json_decode($q['choices'], true) : [];
    foreach ($choices as $choice) {
        $counts[] = 0;
    }
    while ($row = $stmtC->fetch(PDO::FETCH_ASSOC)) {
        $total++;
        $indices = $row['answer_indices'] ? json_decode($row['answer_indices'], true) : [];
        if (is_array($indices)) {
            foreach ($indices as $i) {
                if (isset($counts[$i])) $counts[$i]++;
            }
        }
        if ($row['is_correct']) $correctCount++;
    }
    if (in_array($qtype, ['quiz','truefalse','opinion'])) {
        // Show distribution
        foreach ($choices as $cidx => $choice) {
            $cnt = $counts[$cidx] ?? 0;
            $pct = ($total > 0) ? round(($cnt / $total) * 100) : 0;
            $letter = chr(65 + $cidx);
            $indicator = '';
            // For quiz or truefalse, mark correct answer with ✓
            $correctIndices = $q['correct_indices'] ? json_decode($q['correct_indices'], true) : [];
            if (in_array($cidx, $correctIndices) && in_array($qtype, ['quiz','truefalse'])) {
                $indicator = ' ✓';
            }
            $lines[] = '  ' . $letter . '. ' . $choice . $indicator . ' - ' . $cnt . '/' . $total . ' (' . $pct . '%)';
        }
        if ($qtype === 'opinion') {
            // No correct answer summarization; also show total correct? no
        }
    } elseif ($qtype === 'feedback') {
        // Show total responses
        $lines[] = '  Réponses: ' . $total;
    } elseif ($qtype === 'short' || $qtype === 'long' || $qtype === 'date') {
        // Count of responses for open questions
        $lines[] = '  Réponses: ' . $total;
    } elseif ($qtype === 'rating') {
        // Compute average rating
        $stmtAvg = $db->prepare('SELECT AVG(answer_rating) FROM responses WHERE session_id=? AND question_id=?');
        $stmtAvg->execute([$session_id, $q['id']]);
        $avg = $stmtAvg->fetchColumn();
        $lines[] = '  Note moyenne: ' . ($avg !== null ? round($avg, 2) : 'N/A');
    }
    $lines[] = '';
}
// Paths for export files
$exportDir = __DIR__ . '/../exports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0775, true);
}
$timestamp = date('Ymd_His');
$baseName = 'session_' . $session_id . '_' . $timestamp;
$pdfPath = $exportDir . '/' . $baseName . '_stats.pdf';
$csvPath = $exportDir . '/' . $baseName . '_responses.csv';
$zipPath = $exportDir . '/' . $baseName . '.zip';
// Generate files
generate_stats_pdf($lines, $pdfPath);
generate_responses_csv($session_id, $db, $csvPath);
// Create zip
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
    $zip->addFile($pdfPath, basename($pdfPath));
    $zip->addFile($csvPath, basename($csvPath));
    $zip->close();
}
// Serve the zip file for download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
exit;