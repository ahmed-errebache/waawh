<?php
// Enhanced export functionality with individual question files and charts
session_start();
require_once __DIR__ . '/../config.php';
require_login();

// Helpers to escape parentheses in PDF text
function pdf_escape($text) {
    return str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $text);
}

// Generate a simple chart as ASCII art for PDF
function generate_ascii_chart($choices, $counts, $total) {
    $chart = [];
    $maxCount = max($counts);
    $barWidth = 30; // Maximum bar width in characters
    
    foreach ($choices as $idx => $choice) {
        $count = $counts[$idx] ?? 0;
        $percentage = $total > 0 ? round(($count / $total) * 100) : 0;
        $barLength = $maxCount > 0 ? round(($count / $maxCount) * $barWidth) : 0;
        $bar = str_repeat('■', $barLength);
        $letter = chr(65 + $idx);
        $chart[] = sprintf("  %s. %s", $letter, substr($choice, 0, 20));
        $chart[] = sprintf("     %s %d (%d%%)", $bar, $count, $percentage);
    }
    return $chart;
}

// Generate enhanced PDF with charts for statistics
function generate_enhanced_stats_pdf($lines, $chartData, $outPath) {
    $content = "";
    $content .= "BT\n";
    $content .= "/F1 12 Tf\n";
    $content .= "50 750 Td\n";
    
    $lineHeight = 15;
    $yPos = 0;
    
    foreach ($lines as $line) {
        if (strpos($line, 'CHART:') === 0) {
            // Handle chart insertion
            $questionId = intval(substr($line, 6));
            if (isset($chartData[$questionId])) {
                $chart = $chartData[$questionId];
                foreach ($chart as $chartLine) {
                    $escaped = pdf_escape($chartLine);
                    $content .= "(" . $escaped . ") Tj\n";
                    $yPos += $lineHeight;
                    $content .= "0 -" . $lineHeight . " Td\n";
                }
            }
        } else {
            $escaped = pdf_escape($line);
            $content .= "(" . $escaped . ") Tj\n";
            $yPos += $lineHeight;
            $content .= "0 -" . $lineHeight . " Td\n";
        }
    }
    
    $content .= "ET\n";
    $length = strlen($content);
    
    // Build PDF structure
    $objs = [];
    $pdf = "%PDF-1.4\n";
    
    // Catalog
    $objs[] = strlen($pdf);
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    
    // Pages
    $objs[] = strlen($pdf);
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    
    // Page
    $objs[] = strlen($pdf);
    $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
    
    // Contents
    $objs[] = strlen($pdf);
    $pdf .= "4 0 obj\n<< /Length $length >>\nstream\n" . $content . "endstream\nendobj\n";
    
    // Font
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

// Generate individual question CSV
function generate_question_csv($sessionId, $questionId, $db, $outPath) {
    $fh = fopen($outPath, 'w');
    fputcsv($fh, ['participant_name', 'answer', 'is_correct', 'created_at']);
    
    // Get question info
    $stmtQ = $db->prepare('SELECT qtext, qtype, choices FROM questions WHERE id = ?');
    $stmtQ->execute([$questionId]);
    $question = $stmtQ->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        fclose($fh);
        return;
    }
    
    // Get responses for this specific question
    $stmtR = $db->prepare('SELECT user_name, answer_indices, is_correct, created_at 
        FROM responses WHERE session_id = ? AND question_id = ? ORDER BY created_at');
    $stmtR->execute([$sessionId, $questionId]);
    
    while ($row = $stmtR->fetch(PDO::FETCH_ASSOC)) {
        $answerStr = '';
        if ($row['answer_indices'] !== null && $row['answer_indices'] !== '') {
            $decoded = json_decode($row['answer_indices'], true);
            switch ($question['qtype']) {
                case 'quiz':
                case 'truefalse':
                case 'opinion':
                    if (is_array($decoded)) {
                        $choices = json_decode($question['choices'], true) ?: [];
                        $labels = [];
                        foreach ($decoded as $idx) {
                            if (isset($choices[$idx])) {
                                $labels[] = $choices[$idx];
                            }
                        }
                        $answerStr = implode(' | ', $labels);
                    }
                    break;
                default:
                    if (!is_array($decoded) && $decoded !== null) {
                        $answerStr = (string)$decoded;
                    } elseif (is_array($decoded) && count($decoded) > 0) {
                        $answerStr = (string)$decoded[0];
                    }
                    break;
            }
        }
        fputcsv($fh, [$row['user_name'], $answerStr, $row['is_correct'] ? 'Oui' : 'Non', $row['created_at']]);
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

$user = current_user();
// Only allow export if current user is admin or the host of this session
if ($user['role'] !== 'admin' && $session['host_id'] != $user['id']) {
    http_response_code(403);
    echo 'Accès refusé.';
    exit;
}

// Only allow export if session is ended
if ($session['is_active']) {
    http_response_code(403);
    echo 'La session est toujours active.';
    exit;
}

// Get survey info
$survey_id = $session['survey_id'];
$stmtQ = $db->prepare('SELECT id, qtext, qtype, choices, correct_indices, explain_text FROM questions WHERE survey_id = ? ORDER BY id ASC');
$stmtQ->execute([$survey_id]);
$questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

// Create export directory
$exportDir = __DIR__ . '/../exports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0775, true);
}

$timestamp = date('Ymd_His');
$baseName = 'session_' . $session_id . '_enhanced_' . $timestamp;
$sessionDir = $exportDir . '/' . $baseName;
mkdir($sessionDir, 0775, true);

// Generate overall statistics with charts
$lines = [];
$chartData = [];
$lines[] = 'Statistiques détaillées du sondage';
$lines[] = 'Session PIN: ' . $session['pin'];
$lines[] = 'Date: ' . date('Y-m-d H:i:s');
$lines[] = '';

foreach ($questions as $idx => $q) {
    $qtext = $q['qtext'] ?: 'Question ' . ($idx + 1);
    $qtype = $q['qtype'];
    $lines[] = 'Question ' . ($idx + 1) . ': ' . $qtext;
    
    if ($q['explain_text']) {
        $note = trim(strip_tags($q['explain_text']));
        $note = mb_substr($note, 0, 200);
        $lines[] = '  Note: ' . $note;
    }
    
    // Get response statistics
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
    
    if (in_array($qtype, ['quiz','truefalse','opinion']) && !empty($choices)) {
        // Generate ASCII chart
        $chart = generate_ascii_chart($choices, $counts, $total);
        $chartData[$q['id']] = $chart;
        $lines[] = 'CHART:' . $q['id'];
        
        // Also add text statistics
        $correctIndices = $q['correct_indices'] ? json_decode($q['correct_indices'], true) : [];
        foreach ($choices as $cidx => $choice) {
            $cnt = $counts[$cidx] ?? 0;
            $pct = ($total > 0) ? round(($cnt / $total) * 100) : 0;
            $letter = chr(65 + $cidx);
            $indicator = '';
            if (in_array($cidx, $correctIndices) && in_array($qtype, ['quiz','truefalse'])) {
                $indicator = ' ✓';
            }
            $lines[] = '  ' . $letter . '. ' . $choice . $indicator . ' - ' . $cnt . '/' . $total . ' (' . $pct . '%)';
        }
    } elseif ($qtype === 'rating') {
        // Compute average rating
        $stmtAvg = $db->prepare('SELECT answer_indices FROM responses WHERE session_id=? AND question_id=?');
        $stmtAvg->execute([$session_id, $q['id']]);
        $ratings = [];
        while ($ratingRow = $stmtAvg->fetch(PDO::FETCH_ASSOC)) {
            if ($ratingRow['answer_indices'] !== null) {
                $decoded = json_decode($ratingRow['answer_indices'], true);
                $rating = null;
                if (!is_array($decoded) && is_numeric($decoded)) {
                    $rating = floatval($decoded);
                } elseif (is_array($decoded) && count($decoded) > 0 && is_numeric($decoded[0])) {
                    $rating = floatval($decoded[0]);
                }
                if ($rating !== null) {
                    $ratings[] = $rating;
                }
            }
        }
        $avg = count($ratings) > 0 ? array_sum($ratings) / count($ratings) : null;
        $lines[] = '  Note moyenne: ' . ($avg !== null ? round($avg, 2) : 'N/A');
        $lines[] = '  Nombre de réponses: ' . count($ratings);
    } else {
        $lines[] = '  Réponses: ' . $total;
    }
    
    // Generate individual question CSV
    $questionCsvPath = $sessionDir . '/question_' . ($idx + 1) . '_' . $q['id'] . '.csv';
    generate_question_csv($session_id, $q['id'], $db, $questionCsvPath);
    
    $lines[] = '';
}

// Generate enhanced PDF with charts
$pdfPath = $sessionDir . '/stats_with_charts.pdf';
generate_enhanced_stats_pdf($lines, $chartData, $pdfPath);

// Generate overall responses CSV (same as before)
$csvPath = $sessionDir . '/all_responses.csv';
$fh = fopen($csvPath, 'w');
fputcsv($fh, ['question_id','question_text','participant_name','answer','created_at']);

$qMap = [];
foreach ($questions as $q) {
    $qMap[$q['id']] = $q;
}

$stmtR = $db->prepare('SELECT r.question_id, r.answer_indices, r.created_at, r.user_name as participant_name
    FROM responses r WHERE r.session_id = ? ORDER BY r.created_at');
$stmtR->execute([$session_id]);

while ($row = $stmtR->fetch(PDO::FETCH_ASSOC)) {
    $qid = $row['question_id'];
    $qtext = $qMap[$qid]['qtext'] ?? '';
    $type = $qMap[$qid]['qtype'] ?? '';
    $answerStr = '';
    
    if ($row['answer_indices'] !== null && $row['answer_indices'] !== '') {
        $decoded = json_decode($row['answer_indices'], true);
        switch ($type) {
            case 'quiz':
            case 'truefalse':
            case 'opinion':
                if (is_array($decoded)) {
                    $choices = json_decode($qMap[$qid]['choices'], true) ?: [];
                    $labels = [];
                    foreach ($decoded as $idx) {
                        if (isset($choices[$idx])) {
                            $labels[] = $choices[$idx];
                        }
                    }
                    $answerStr = implode('|', $labels);
                }
                break;
            case 'short':
            case 'long':
            case 'date':
            case 'feedback':
            case 'rating':
                if (!is_array($decoded) && $decoded !== null) {
                    $answerStr = (string)$decoded;
                } elseif (is_array($decoded) && count($decoded) > 0) {
                    $answerStr = (string)$decoded[0];
                }
                break;
            default:
                $answerStr = $row['answer_indices'];
                break;
        }
    }
    fputcsv($fh, [$qid, $qtext, $row['participant_name'], $answerStr, $row['created_at']]);
}
fclose($fh);

// Create summary file
$summaryPath = $sessionDir . '/README.txt';
file_put_contents($summaryPath, "Export Enhanced - Session $session_id\n" .
    "=====================================\n\n" .
    "Ce dossier contient:\n" .
    "- stats_with_charts.pdf: Statistiques avec graphiques ASCII\n" .
    "- all_responses.csv: Toutes les réponses dans un fichier\n" .
    "- question_X_Y.csv: Fichier individuel pour chaque question\n" .
    "  (X = numéro de question, Y = ID de la question)\n\n" .
    "Généré le: " . date('Y-m-d H:i:s') . "\n" .
    "Questions totales: " . count($questions) . "\n"
);

// Create ZIP file
$zipPath = $exportDir . '/' . $baseName . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sessionDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sessionDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();
}

// Serve the zip file for download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
exit;
?>