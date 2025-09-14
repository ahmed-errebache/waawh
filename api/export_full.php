<?php
// Exporte un ZIP: PDF des statistiques + CSV des réponses (+ copie persistante dans /exports)
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php'; // même connexion DB

if (function_exists('require_login')) {
    require_login('host');
}

$session_id = (int)($_REQUEST['session_id'] ?? 0);
if ($session_id <= 0) {
    http_response_code(400);
    echo 'session_id manquant';
    exit;
}

$db = get_db();

/* --- Récup session --- */
$st = $db->prepare("SELECT s.*, v.title AS survey_title
                    FROM sessions s
                    LEFT JOIN surveys v ON v.id = s.survey_id
                    WHERE s.id = ?");
$st->execute([$session_id]);
$session = $st->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    http_response_code(404);
    echo "Session introuvable";
    exit;
}

/* -------------------- Helpers PDF (ultra-minimal) -------------------- */
function pdf_escape($t){ return str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], (string)$t); }
function make_minimal_pdf(string $title, array $lines, string $outFile): void {
    // PDF texte, 1 page, sans dépendance externe
    $y = 800; $leading = 16;
    $content = "BT /F1 14 Tf 50 $y Td (".pdf_escape($title).") Tj ET\n";
    foreach ($lines as $ln) {
        $y -= $leading;
        if ($y < 60) break; // simple: on coupe si dépasse
        $content .= "BT /F1 11 Tf 50 $y Td (".pdf_escape($ln).") Tj ET\n";
    }
    $len = strlen($content);
    $pdf  = "%PDF-1.4\n";
    $pdf .= "1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n";
    $pdf .= "2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n";
    $pdf .= "3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources <</Font <</F1 4 0 R>>>> /Contents 5 0 R>> endobj\n";
    $pdf .= "4 0 obj <</Type /Font /Subtype /Type1 /BaseFont /Helvetica>> endobj\n";
    $pdf .= "5 0 obj <</Length $len>> stream\n$content\nendstream endobj\n";
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i=1; $i<=5; $i++) $pdf .= "0000000000 00000 n \n";
    $pdf .= "trailer <</Size 6 /Root 1 0 R>>\nstartxref\n$xrefPos\n%%EOF";
    file_put_contents($outFile, $pdf);
}

/* -------------------- PDF statistiques -------------------- */
function generate_stats_pdf(PDO $db, int $session_id, string $survey_title, string $outPdf): void {
    $lines = [];
    $lines[] = "Sondage : " . ($survey_title ?: "Sans titre");
    $lines[] = "";

    $stmt = $db->prepare(
        "SELECT q.id AS qid, q.qtext, q.qtype, q.choices
         FROM sessions s
         JOIN questions q ON q.survey_id = s.survey_id
         WHERE s.id = ?
         ORDER BY q.id"
    );
    $stmt->execute([$session_id]);
    while ($q = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lines[] = "Q".$q['qid'].": ".($q['qtext'] ?? '');
        $qtype   = $q['qtype'] ?? '';
        $choices = $q['choices'] ? json_decode($q['choices'], true) : [];
        if (!is_array($choices)) $choices = [];

        if (in_array($qtype, ['quiz','truefalse','opinion'])) {
            $c = $db->prepare("SELECT answer_indices FROM responses WHERE session_id=? AND question_id=?");
            $c->execute([$session_id, (int)$q['qid']]);
            $counts = array_fill(0, max(1, count($choices)), 0);
            while ($r = $c->fetch(PDO::FETCH_ASSOC)) {
                $idxs = json_decode($r['answer_indices'], true);
                if (!is_array($idxs)) $idxs = [];
                foreach ($idxs as $i) if (isset($counts[$i])) $counts[$i]++;
            }
            foreach ($choices as $i=>$label) {
                $lines[] = "  - ".($label ?? "Choix $i").": ".$counts[$i]." réponse(s)";
            }
        } else {
            // short/long/date/rating/feedback : nombre de réponses
            $c = $db->prepare("SELECT COUNT(*) FROM responses WHERE session_id=? AND question_id=?");
            $c->execute([$session_id, (int)$q['qid']]);
            $total = (int)$c->fetchColumn();
            $lines[] = "  - Réponses: $total";
        }
        $lines[] = "";
    }

    make_minimal_pdf("Statistiques du sondage (session $session_id)", $lines, $outPdf);
}

/* -------------------- CSV réponses brutes -------------------- */
function generate_responses_csv(PDO $db, int $session_id, string $outCsv): void {
    // IMPORTANT: pas de colonne r.answer_text — tout est dans answer_indices
    $sql =
        "SELECT r.user_name, r.question_id, r.answer_indices, r.is_correct, r.created_at,
                q.qtext, q.qtype, q.choices
         FROM responses r
         JOIN questions q ON q.id = r.question_id
         WHERE r.session_id = ?
         ORDER BY r.user_name, r.question_id, r.created_at";
    $st = $db->prepare($sql);
    $st->execute([$session_id]);

    $fp = fopen($outCsv, 'w');
    fputcsv($fp, ['Participant','Question','Type','Réponse','Correct','Horodatage']);

    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $qtype   = $row['qtype'];
        $choices = $row['choices'] ? json_decode($row['choices'], true) : [];
        if (!is_array($choices)) $choices = [];
        $raw     = $row['answer_indices'];
        $answer  = '';

        if (in_array($qtype, ['quiz','truefalse','opinion'])) {
            $idxs = json_decode($raw, true);
            if (!is_array($idxs)) $idxs = [];
            $labels = [];
            foreach ($idxs as $i) $labels[] = $choices[$i] ?? ('#'.$i);
            $answer = implode(' | ', $labels);
        } else {
            // short/long/date/rating/feedback => texte/valeur brute
            $answer = (string)$raw;
        }

        $correct = ($qtype === 'opinion' || $qtype === 'feedback') ? '—' : ((int)$row['is_correct'] === 1 ? '1' : '0');

        fputcsv($fp, [
            $row['user_name'],
            $row['qtext'],
            $qtype,
            $answer,
            $correct,
            $row['created_at'] ?? ''
        ]);
    }
    fclose($fp);
}

/* ----------------------------- MAIN -------------------------- */
$base = sys_get_temp_dir();
$dir  = $base . '/export_' . $session_id . '_' . time();
if (!is_dir($dir)) mkdir($dir, 0777, true);

// Générer PDF + CSV
$pdfFile = $dir . '/stats.pdf';
$csvFile = $dir . '/responses.csv';
generate_stats_pdf($db, $session_id, (string)($session['survey_title'] ?? ''), $pdfFile);
generate_responses_csv($db, $session_id, $csvFile);

// ZIP
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo "Extension ZipArchive manquante (activez php_zip dans php.ini).";
    exit;
}
$zipName = 'export_session_' . $session_id . '.zip';
$zipPath = $dir . '/' . $zipName;

$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFile($pdfFile, 'stats.pdf');
$zip->addFile($csvFile, 'responses.csv');
$zip->close();

// Historique des exports dans /exports
$persistDir = realpath(__DIR__ . '/..') . '/exports';
if (!is_dir($persistDir)) @mkdir($persistDir, 0777, true);
$persistName = 'export_session_' . $session_id . '_' . date('Ymd_His') . '.zip';
@copy($zipPath, $persistDir . '/' . $persistName);

// Stream vers le navigateur
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$zipName.'"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);

// Nettoyage tmp
@unlink($pdfFile);
@unlink($csvFile);
@unlink($zipPath);
@rmdir($dir);
