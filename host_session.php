<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
if (function_exists('require_login')) {
    require_login('host');
}

$db = getDatabase();
$session_id = (int)($_GET['session_id'] ?? 0);
if ($session_id <= 0) {
    // Si vous avez une page de liste des sessions, redirigez-y :
    // header('Location: host_dashboard.php'); exit;
    http_response_code(400);
    echo "session_id manquant";
    exit;
}

// Récup session
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

// Nombre total de questions
$total = (int)$db->prepare("SELECT COUNT(*) FROM questions WHERE survey_id = ?")
    ->execute([(int)$session['survey_id']]) ?: 0;
$total = (int)$db->query("SELECT COUNT(*) FROM questions WHERE survey_id=" . (int)$session['survey_id'])->fetchColumn();

$is_active = ((int)$session['is_active'] === 1);
$current_index = (int)$session['current_question_index'];
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Session animateur – <?= htmlspecialchars($session['survey_title'] ?? ('Session ' . $session_id)) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
        }

        .stat-bar {
            background: #f1f3f5;
            border-radius: 6px;
            overflow: hidden;
            height: 10px;
        }

        .stat-fill {
            height: 10px;
            background: #0d6efd;
        }

        .option-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .option-label {
            min-width: 48px;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="mb-4">
            <h3 class="mb-1"><?= htmlspecialchars($session['survey_title'] ?? 'Sondage') ?></h3>
            <div class="text-muted">
                Session #<?= (int)$session_id ?> —
                <span class="badge <?= $is_active ? 'bg-success' : 'bg-secondary' ?>">
                    <?= $is_active ? 'En cours' : 'Clôturée' ?>
                </span>
                <?php if ($total > 0): ?>
                    <span class="ms-2">Question: <span id="qpos"><?= (int)$current_index + 1 ?></span>/<?= (int)$total ?></span>
                <?php endif; ?>
            </div>
        </header>

        <div class="card mb-3">
            <div class="card-body">
                <div id="question-display" class="mb-3">
                    <!-- Texte de la question injecté par fetchStats() -->
                </div>
                <div id="stats-container">
                    <!-- Stats injectées par fetchStats() -->
                </div>
            </div>
        </div>

        <?php if ($is_active): ?>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <!-- Réveiller/afficher la réponse -->
                <button id="btnReveal" class="btn btn-outline-primary">Afficher la réponse</button>
                <!-- Question suivante -->
                <button id="btnNext" class="btn btn-outline-dark">Question suivante</button>
                <!-- Terminer -->
                <form method="post" action="api/end_session.php">
                    <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                    <button type="submit" class="btn btn-danger">Terminer</button>
                </form>

            </div>
        <?php else: ?>
            <hr class="my-4">
            <h4>Export des résultats</h4>
            <p class="text-muted mb-2">Téléchargez les données de la session clôturée :</p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <!-- Réponses détaillées (CSV/XLS) -->
                <a class="btn btn-outline-primary"
                    href="api/export_results.php?session_id=<?= (int)$session_id ?>&format=csv">
                    Exporter CSV (réponses)
                </a>
                <a class="btn btn-outline-secondary"
                    href="api/export_results.php?session_id=<?= (int)$session_id ?>&format=xls">
                    Exporter Excel (réponses)
                </a>
                <!-- Dossier complet PDF+CSV -->
                <a class="btn btn-outline-success"
                    href="api/export_full.php?session_id=<?= (int)$session_id ?>">
                    Exporter dossier complet (PDF + CSV)
                </a>
            </div>

            <?php
            // Historique des exports (si vous laissez la copie persistante dans /exports)
            $exportsDir = __DIR__ . '/exports';
            if (is_dir($exportsDir)) {
                $files = array_values(array_filter(scandir($exportsDir), function ($f) use ($exportsDir, $session_id) {
                    return is_file($exportsDir . '/' . $f)
                        && strpos($f, 'export_session_' . (int)$session_id . '_') === 0
                        && substr($f, -4) === '.zip';
                }));
                rsort($files);
                if (!empty($files)): ?>
                    <div class="mt-3">
                        <h6>Exports précédents</h6>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($files as $f): ?>
                                <li><a href="exports/<?= htmlspecialchars($f) ?>" target="_blank"><?= htmlspecialchars($f) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
            <?php endif;
            } ?>
        <?php endif; ?>

    </div>

    <script>
        const sessionId = <?= (int)$session_id ?>;
        const total = <?= (int)$total ?>;

        async function fetchStats() {
            try {
                const res = await fetch(`api/get_question_stats.php?session_id=${sessionId}`);
                if (!res.ok) return;
                const data = await res.json();

                // Mettre à jour question
                const qDisplay = document.getElementById('question-display');
                qDisplay.innerHTML = data.qtext ? `<h5 class="mb-3">${data.qtext}</h5>` : '';

                // Mettre à jour position si possible (on ne la connaît pas via l’API -> on lit côté serveur au chargement)
                // On pourrait aussi interroger un endpoint status si vous en avez un.

                // Rendu stats
                const box = document.getElementById('stats-container');
                box.innerHTML = '';

                if (Array.isArray(data.options) && data.options.length > 0) {
                    // Choix (quiz/truefalse/opinion)
                    const counts = Array.isArray(data.counts) ? data.counts : [];
                    const totalResp = counts.reduce((a, b) => a + (b || 0), 0) || 0;

                    data.options.forEach((opt, i) => {
                        const count = counts[i] || 0;
                        const pct = totalResp ? Math.round((count * 100) / totalResp) : 0;

                        const row = document.createElement('div');
                        row.className = 'option-row';
                        row.innerHTML = `
              <span class="option-label">${String.fromCharCode(65+i)}.</span>
              <div class="flex-grow-1">
                <div>${opt}</div>
                <div class="stat-bar mt-1"><div class="stat-fill" style="width:${pct}%"></div></div>
              </div>
              <div class="text-nowrap ms-2">${count} (${pct}%)</div>
            `;
                        box.appendChild(row);
                    });
                } else {
                    // Types ouverts : seulement le total
                    const p = document.createElement('p');
                    p.className = 'mb-0';
                    p.textContent = `Réponses : ${data.total ?? 0}`;
                    box.appendChild(p);
                }
            } catch (e) {
                // silencieux
            }
        }

        // Boutons (session active uniquement)
        <?php if ($is_active): ?>
            document.getElementById('btnReveal')?.addEventListener('click', async () => {
                await fetch('api/reveal_question.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        session_id: String(sessionId)
                    })
                });
                fetchStats();
            });

            document.getElementById('btnNext')?.addEventListener('click', async () => {
                const res = await fetch(`api/next_question.php?session_id=${sessionId}`);
                // pas d’auto export, la page reste
                fetchStats();
                // mettre à jour position affichée si besoin :
                try {
                    const j = await res.json();
                    if (j && typeof j.index !== 'undefined' && total > 0) {
                        const pos = document.getElementById('qpos');
                        if (pos) pos.textContent = (j.index + 1);
                    }
                } catch (e) {}
            });
        <?php endif; ?>

        // Polling stats
        fetchStats();
        setInterval(fetchStats, 2000);
    </script>
</body>

</html>