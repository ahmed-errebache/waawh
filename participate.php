<?php
require_once __DIR__ . '/config.php';
// Participant page to answer questions in a session
if (empty($_SESSION['participant_session_id']) || empty($_SESSION['participant_name'])) {
    header('Location: join.php');
    exit;
}
$sessionId = (int)$_SESSION['participant_session_id'];
$participantName = $_SESSION['participant_name'];
$db = connect_db();
// Fetch session info with survey and host for theming
$stmt = $db->prepare('SELECT s.id AS session_id, s.pin, s.survey_id, s.host_id, s.is_active,
    u.primary_color AS host_primary, u.accent_color AS host_accent, u.background_color AS host_bg, u.background_image AS host_bg_image, u.logo AS host_logo,
    sur.theme_json
    FROM sessions s
    JOIN users u ON s.host_id = u.id
    JOIN surveys sur ON s.survey_id = sur.id
    WHERE s.id = ?');
$stmt->execute([$sessionId]);
$sessInfo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sessInfo || !(int)$sessInfo['is_active']) {
    echo 'Session terminée ou introuvable.';
    exit;
}
// Ensure a CSRF token exists for answer submission
$tokenForForm = csrf_token();
// Determine theme (survey theme overrides host theme)
$theme = [
    'primary' => $sessInfo['host_primary'] ?: '#FFBF69',
    'accent' => $sessInfo['host_accent'] ?: '#2EC4B6',
    'background' => $sessInfo['host_bg'] ?: '#FFF9F2',
    'background_image' => $sessInfo['host_bg_image'],
    'logo' => $sessInfo['host_logo']
];
if ($sessInfo['theme_json']) {
    $dec = json_decode($sessInfo['theme_json'], true);
    if (is_array($dec)) {
        $theme = array_merge($theme, $dec);
    }
}
// Prepare CSS inline variables
$styleBg = $theme['background'] ? 'background-color:' . htmlspecialchars($theme['background']) . ';' : '';
if (!empty($theme['background_image'])) {
    $styleBg .= 'background-image:url(' . htmlspecialchars($theme['background_image']) . ');background-size:cover;background-position:center center;';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participation – WAAWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            <?= $styleBg ?>
            min-height: 100vh;
        }
        .btn-primary {
            background-color: <?= htmlspecialchars($theme['primary']) ?>;
            border-color: <?= htmlspecialchars($theme['primary']) ?>;
        }
        .btn-accent {
            background-color: <?= htmlspecialchars($theme['accent']) ?>;
            border-color: <?= htmlspecialchars($theme['accent']) ?>;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center">
                <?php if (!empty($theme['logo'])): ?>
                    <img src="<?= htmlspecialchars($theme['logo']) ?>" alt="Logo" style="max-height:50px;" class="me-2">
                <?php else: ?>
                    <img src="assets/logo.png" alt="Logo" style="max-height:50px;" class="me-2">
                <?php endif; ?>
                <h4 class="mb-0">Session PIN: <?= htmlspecialchars($sessInfo['pin']) ?></h4>
            </div>
            <div><strong><?= htmlspecialchars($participantName) ?></strong></div>
        </div>
        <div id="question-container" class="mb-3">
            <!-- Question will be injected here via JS -->
        </div>
        <div id="status-container" class="mb-3"></div>
        <div id="score-container" class="mb-3"></div>
    </div>
    <script>
    const sessionId = <?= (int)$sessInfo['session_id'] ?>;
    const csrfToken = '<?= esc($_SESSION['csrf_token'] ?? '') ?>';
    let currentQuestionId = null;
    let countdownInterval;
    async function fetchStatus() {
        const resp = await fetch('get_session_status.php?session_id=' + sessionId);
        const data = await resp.json();
        if (!data.success) {
            document.getElementById('question-container').innerHTML = '<div class="alert alert-warning">Session terminée.</div>';
            document.getElementById('status-container').innerHTML = '';
            clearInterval(pollInterval);
            return;
        }
        const q = data.question;
        const reveal = data.reveal_state;
        const idx = data.question_index;
        if (!q) {
            document.getElementById('question-container').innerHTML = '<div class="alert alert-info">Le sondage est terminé. Merci pour votre participation !</div>';
            document.getElementById('status-container').innerHTML = '';
            updateScore(data.scoreboard);
            clearInterval(pollInterval);
            return;
        }
        // If new question, render
        if (currentQuestionId !== q.id) {
            currentQuestionId = q.id;
            renderQuestion(q);
        }
        // If reveal state changed, update explanation
        const explContainer = document.getElementById('explanation');
        if (reveal && explContainer && !explContainer.dataset.shown) {
            // Show correct answer and explanation
            let explHtml = '';
            if (q.correct_indices !== null) {
                if (Array.isArray(q.correct_indices)) {
                    explHtml += '<p><strong>Bonne réponse :</strong> ';
                    const answers = [];
                    if (q.qtype === 'rating') {
                        answers.push(q.correct_indices[0]);
                    } else {
                        q.correct_indices.forEach(idx => {
                            if (Array.isArray(q.choices) && idx < q.choices.length) {
                                answers.push(q.choices[idx]);
                            }
                        });
                    }
                    explHtml += answers.join(' | ') + '</p>';
                }
            }
            if (q.explain_text) {
                explHtml += '<p>' + q.explain_text + '</p>';
            }
            if (q.explain_media) {
                explHtml += '<p><a href="' + q.explain_media + '" target="_blank">Voir explication</a></p>';
            }
            explContainer.innerHTML = explHtml;
            explContainer.dataset.shown = '1';
        }
        updateScore(data.scoreboard);
        // Mettre à jour les statistiques des réponses
        fetchAnswerStats();
    }
    function updateScore(board) {
        let html = '<h5>Classement</h5><ul class="list-group">';
        board.forEach(item => {
            html += '<li class="list-group-item d-flex justify-content-between"><span>' + item.name + '</span><span>' + item.score + '</span></li>';
        });
        html += '</ul>';
        document.getElementById('score-container').innerHTML = html;
    }

    // Récupère et met à jour les statistiques de réponses pour la question en cours
    async function fetchAnswerStats() {
        try {
            const res = await fetch('get_question_stats.php?session_id=' + sessionId);
            if (!res.ok) return;
            const stats = await res.json();
            // Met à jour les compteurs à côté de chaque choix
            const counts = Array.isArray(stats.counts) ? stats.counts : [];
            document.querySelectorAll('.answer-count').forEach(el => {
                const idxStr = el.getAttribute('data-idx');
                const idx = parseInt(idxStr, 10);
                let cnt = 0;
                if (!Number.isNaN(idx) && idx >= 0 && idx < counts.length) {
                    cnt = counts[idx];
                }
                el.textContent = cnt;
            });
        } catch (e) {
            console.error('Erreur lors de la récupération des stats:', e);
        }
    }
    function renderQuestion(q) {
        // Clear countdown if any
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
        let html = '<div class="card"><div class="card-body">';
        html += '<h5 class="card-title">' + q.qtext + '</h5>';
        if (q.qmedia) {
            html += '<p><a href="' + q.qmedia + '" target="_blank">Voir média</a></p>';
        }
        html += '<form id="answer-form">';
        html += '<input type="hidden" name="csrf_token" value="' + csrfToken + '">';
        html += '<input type="hidden" name="session_id" value="' + sessionId + '">';
        html += '<input type="hidden" name="question_id" value="' + q.id + '">';
        if (q.qtype === 'quiz' || q.qtype === 'truefalse') {
            html += '<div class="mt-2">';
            if (Array.isArray(q.choices)) {
                q.choices.forEach((choice, idx) => {
                    const multiple = (q.qtype === 'quiz' && q.correct_indices && q.correct_indices.length > 1);
                    const inputType = multiple ? 'checkbox' : 'radio';
                    html += '<div class="form-check">';
                    html += '<input class="form-check-input" type="' + inputType + '" name="answer_indices' + (multiple ? '[]' : '') + '" value="' + idx + '" id="choice' + idx + '">';
                    html += '<label class="form-check-label" for="choice' + idx + '">' + choice + '</label>';
                    // Ajout d'un compteur pour chaque option (mise à jour en temps réel via JS)
                    html += ' <small class="text-muted ms-2 answer-count" data-idx="' + idx + '">0</small>';
                    html += '</div>';
                });
            }
            html += '</div>';
        } else if (q.qtype === 'rating') {
            const max = (q.choices && q.choices.length > 0) ? q.choices[0] : 5;
            html += '<div class="mt-2">';
            for (let i = 1; i <= max; i++) {
                html += '<div class="form-check form-check-inline">';
                html += '<input class="form-check-input" type="radio" name="rating" id="rate' + i + '" value="' + i + '">';
                html += '<label class="form-check-label" for="rate' + i + '">' + i + '</label>';
                html += '</div>';
            }
            html += '</div>';
        } else if (q.qtype === 'date') {
            html += '<div class="mt-2">';
            html += '<input type="date" name="answer_text" class="form-control">';
            html += '</div>';
        } else if (q.qtype === 'short') {
            html += '<div class="mt-2">';
            html += '<input type="text" name="answer_text" class="form-control">';
            html += '</div>';
        } else if (q.qtype === 'long') {
            html += '<div class="mt-2">';
            html += '<textarea name="answer_text" class="form-control" rows="3"></textarea>';
            html += '</div>';
        }
        if (q.seconds) {
            html += '<p class="mt-2"><span id="timer" class="badge bg-secondary"></span></p>';
        }
        html += '<button type="submit" class="btn btn-primary mt-3">Envoyer</button>';
        html += '</form>';
        html += '<div id="explanation" class="mt-3" data-shown="0"></div>';
        html += '</div></div>';
        document.getElementById('question-container').innerHTML = html;
        // Add submit handler
        const form = document.getElementById('answer-form');
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            // Build form data
            const formData = new FormData(form);
            const resp = await fetch('submit_answer.php', {
                method: 'POST',
                body: formData
            });
            const json = await resp.json();
            if (json.success) {
                document.getElementById('status-container').innerHTML = '<div class="alert alert-success">Réponse envoyée.</div>';
                // Disable inputs
                Array.from(form.elements).forEach(el => { el.disabled = true; });
            } else {
                document.getElementById('status-container').innerHTML = '<div class="alert alert-danger">' + json.error + '</div>';
            }
        });
        // Start countdown if needed
        if (q.seconds) {
            let remaining = q.seconds;
            const timerEl = document.getElementById('timer');
            timerEl.textContent = remaining + 's';
            countdownInterval = setInterval(() => {
                remaining--;
                timerEl.textContent = remaining + 's';
                if (remaining <= 0) {
                    clearInterval(countdownInterval);
                    // Auto-submit form if not already submitted
                    if (!form.dataset.submitted) {
                        form.dataset.submitted = '1';
                        // Trigger submit
                        form.dispatchEvent(new Event('submit', { cancelable: true }));
                    }
                }
            }, 1000);
        }

        // Mettre à jour immédiatement les compteurs de réponses après rendu de la question
        fetchAnswerStats();
    }
    const pollInterval = setInterval(fetchStatus, 2000);
    fetchStatus();
    </script>
</body>
</html>