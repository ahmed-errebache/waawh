<?php
session_start();
require_once __DIR__ . '/config.php';
require_login('host');
$user = current_user();

// Get session_id from query
$session_id = $_GET['session_id'] ?? null;
if (!$session_id) {
    echo 'Session ID manquant.';
    exit;
}

$db = connect_db();
$stmt = $db->prepare('SELECT s.*, su.title FROM sessions s JOIN surveys su ON s.survey_id = su.id WHERE s.id = ? AND s.host_id = ?');
$stmt->execute([$session_id, $user['id']]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    echo 'Session introuvable ou non autorisée.';
    exit;
}

// Fetch total number of questions
$stmt = $db->prepare('SELECT COUNT(*) FROM questions WHERE survey_id = ?');
$stmt->execute([$session['survey_id']]);
$total_questions = (int)$stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrôle de la session – WAAWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: <?= esc($user['background_color'] ?: '#FFF9F2') ?>; }
        .navbar { background-color: <?= esc($user['primary_color'] ?: '#FFBF69') ?>; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="host_dashboard.php">
                <?php if ($user['logo']): ?>
                    <img src="<?= esc($user['logo']) ?>" alt="Logo" class="me-2" style="max-height:40px;">
                <?php else: ?>
                    <img src="assets/logo.png" alt="Logo" class="me-2" style="max-height:40px;">
                <?php endif; ?>
                <span class="fw-bold">Session</span>
            </a>
            <div class="d-flex">
                <a href="logout.php" class="btn btn-outline-light">Déconnexion</a>
            </div>
        </div>
    </nav>
    <div class="container py-4">
        <h2><?= esc($session['title']) ?></h2>
        <p>PIN: <strong><?= esc($session['pin']) ?></strong></p>
        <p>Question <span id="currentIndex"><?= esc($session['current_question_index'] + 1) ?></span>/<?= $total_questions ?></p>
        <p>État: <span id="revealState"><?= $session['reveal_state'] ? 'Révélée' : 'En cours' ?></span></p>
        <!-- Affichage de la question courante -->
        <div id="question-display" class="mb-3"></div>
        <div class="mb-3 d-flex flex-wrap">
            <?php if ($session['current_question_index'] == 0 && !$session['reveal_state']): ?>
            <form method="post" action="api/start_question.php" class="me-2 mb-2">
                    <?= csrf_token() ?>
                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                    <button type="submit" class="btn btn-primary" style="background-color:<?= esc($user['accent_color'] ?: '#2EC4B6') ?>;border-color:<?= esc($user['accent_color'] ?: '#2EC4B6') ?>;">Démarrer</button>
                </form>
            <?php endif; ?>
            <?php if ($session['current_question_index'] > 0): ?>
            <form method="post" action="api/previous_question.php" class="me-2 mb-2">
                <?= csrf_token() ?>
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <button type="submit" class="btn btn-secondary">Question précédente</button>
            </form>
            <?php endif; ?>
            <form method="post" action="api/reveal_question.php" class="me-2 mb-2">
                <?= csrf_token() ?>
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <button type="submit" class="btn btn-warning">Afficher la réponse</button>
            </form>
            <form method="post" action="api/next_question.php" class="me-2 mb-2">
                <?= csrf_token() ?>
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <button type="submit" class="btn btn-info">Question suivante</button>
            </form>
            <form method="post" action="api/end_session.php" class="me-2 mb-2" onsubmit="return confirm('Mettre fin à la session ?');">
                <?= csrf_token() ?>
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <button type="submit" class="btn btn-danger">Terminer</button>
            </form>
        </div>
        <hr>
        <h3>Statistiques en temps réel</h3>
        <div id="stats-container">
            <p>Aucun participant pour le moment.</p>
        </div>
        <?php if (!$session['is_active']): ?>
            <hr class="my-4">
            <h4>Export des résultats</h4>
            <div class="mb-3">
                <!-- Export individual formats as before -->
                <a href="api/export_results.php?session_id=<?= $session['id'] ?>&format=csv" class="btn btn-outline-primary me-2">Exporter CSV</a>
                <a href="api/export_results.php?session_id=<?= $session['id'] ?>&format=xls" class="btn btn-outline-secondary me-2">Exporter XLS</a>
                <!-- Enhanced export with charts and individual question files -->
                <a href="api/export_enhanced.php?session_id=<?= $session['id'] ?>" class="btn btn-outline-info me-2">Export avancé (avec graphiques)</a>
                <!-- New full export: zipped PDF + CSV -->
                <a href="api/export_full.php?session_id=<?= $session['id'] ?>" class="btn btn-outline-success">Exporter dossier complet</a>
            </div>
            <?php
            // Afficher l'historique des exports pour cette session
            $exportDir = __DIR__ . '/exports';
            $sessionId = $session['id'];
            $files = [];
            if (is_dir($exportDir)) {
                foreach (scandir($exportDir) as $file) {
                    if (strpos($file, 'session_' . $sessionId . '_') === 0 && substr($file, -4) === '.zip') {
                        $files[] = $file;
                    }
                }
            }
            if (!empty($files)):
            ?>
            <div class="mt-3">
                <h5>Exports précédents</h5>
                <ul class="list-group">
                    <?php foreach ($files as $fileName): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= esc($fileName) ?>
                            <a href="exports/<?= urlencode($fileName) ?>" class="btn btn-sm btn-link">Télécharger</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script>
    async function fetchStatus() {
        const res = await fetch('api/get_session_status.php?session_id=<?= $session['id'] ?>');
        if (res.ok) {
            const data = await res.json();
            // Update status display
            document.getElementById('currentIndex').textContent = data.current_question_index + 1;
            document.getElementById('revealState').textContent = data.reveal_state ? 'Révélée' : 'En cours';
            // Mettre à jour les statistiques de réponses pour la question en cours
            fetchStats();
        }
    }

    async function fetchStats() {
        try {
            const res = await fetch('api/get_question_stats.php?session_id=<?= $session['id'] ?>');
            if (!res.ok) return;
            const data = await res.json();
            const container = document.getElementById('stats-container');
            if (!container) return;
            // Mettre à jour l'affichage de la question et de la note animateur
            const qDisplay = document.getElementById('question-display');
            const total = data.total || 0;
            const options = Array.isArray(data.options) ? data.options : [];
            const counts = Array.isArray(data.counts) ? data.counts : [];
            const correctIndex = data.correct_index;
            const qtype = data.qtype || '';
            // Mettre à jour la note animateur
            const hostNote = data.host_note;
            if (qDisplay) {
                const qtext = data.qtext || '';
                let innerHtml = qtext ? `<h4>${qtext}</h4>` : '';
                if (hostNote) {
                    innerHtml += `<p class="small text-muted">${hostNote}</p>`;
                }
                qDisplay.innerHTML = innerHtml;
            }
            // Si type feedback, afficher simplement le nombre de réponses
            if (qtype === 'feedback') {
                container.innerHTML = `<p>${total} réponse${total > 1 ? 's' : ''} reçue${total > 1 ? 's' : ''}.</p>`;
                return;
            }
            // Pour les types opinion, quiz et truefalse, afficher la répartition
            let html = '';
            options.forEach((opt, idx) => {
                const cnt = counts[idx] || 0;
                const pct = total > 0 ? Math.round((cnt / total) * 100) : 0;
                const isCorrect = (idx === correctIndex);
                html += `
                    <div class="mb-2">
                        <div class="d-flex align-items-center mb-1">
                            <strong class="me-2">${String.fromCharCode(65 + idx)}.</strong>
                            <span class="flex-grow-1">${opt}${isCorrect ? ' <span class="text-success fw-bold">✓</span>' : ''}</span>
                            <span class="ms-2">${cnt}/${total} (${pct}%)</span>
                        </div>
                        <div style="height:8px; background:#eee; border-radius:4px; overflow:hidden;">
                            <div style="height:8px; width:${pct}%; background:#007bff; opacity:.6;"></div>
                        </div>
                    </div>
                `;
            });
            if (html === '') {
                container.innerHTML = '<p>Aucune option disponible pour cette question.</p>';
            } else {
                container.innerHTML = html;
            }
        } catch (err) {
            console.error(err);
        }
    }
    // Mise à jour initiale des statistiques et du statut
    // Appel immédiat pour ne pas attendre le premier poll
    fetchStatus();
    // Mettre à jour les statistiques séparément en cas de latence de fetchStatus
    fetchStats();
    // Poll status toutes les 3 secondes (qui déclenche aussi fetchStats)
    setInterval(fetchStatus, 3000);
    </script>
</body>
</html>