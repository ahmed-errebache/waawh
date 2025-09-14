<?php
session_start();
require_once __DIR__ . '/config.php';

// Only administrators may access this page.
require_login('admin');

// Fetch all surveys with their question counts and assigned host
$db = connect_db();
$surveys = $db->query('SELECT s.*, u.username AS host_username FROM surveys s LEFT JOIN users u ON s.owner_id = u.id ORDER BY s.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

// Count questions for each survey
$question_counts = [];
foreach ($surveys as $survey) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM questions WHERE survey_id = ?');
    $stmt->execute([$survey['id']]);
    $question_counts[$survey['id']] = (int) $stmt->fetchColumn();
}

// Fetch hosts
$hosts = $db->query("SELECT * FROM users WHERE role = 'host' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord – Administrateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #FFF9F2; }
        .navbar-brand img { max-height:40px; }
        .survey-card { border-radius: 12px; box-shadow: 0 0 4px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light" style="background-color:#FFBF69;">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/logo.png" alt="Logo" class="me-2">
                <span class="fw-bold">Admin WAAWH</span>
            </a>
            <div class="d-flex">
                <a href="logout.php" class="btn btn-outline-light">Déconnexion</a>
            </div>
        </div>
    </nav>
    <div class="container py-4">
        <h2 class="mb-4">Vos sondages</h2>
        <div class="mb-3">
            <a href="builder.php" class="btn btn-primary" style="background-color:#2EC4B6;border-color:#2EC4B6;">+ Nouveau sondage</a>
            <a href="host_manage.php" class="btn btn-outline-secondary ms-2">Gérer les animateurs</a>
        </div>
        <?php if (count($surveys) === 0): ?>
            <p>Aucun sondage pour le moment.</p>
        <?php else: ?>
            <div class="row gy-3">
                <?php foreach ($surveys as $survey): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card survey-card">
                            <div class="card-body">
                                <h5 class="card-title mb-1"><?= esc($survey['title']) ?></h5>
                                <p class="small mb-2">Questions: <?= $question_counts[$survey['id']] ?></p>
                                <p class="small mb-2">Assigné à: <?= $survey['host_username'] ? esc($survey['host_username']) : '—' ?></p>
                                <div class="d-flex flex-wrap">
                                    <a href="builder.php?id=<?= $survey['id'] ?>" class="btn btn-sm btn-outline-primary me-1 mb-1">Modifier</a>
                                    <form method="post" action="api/delete_survey.php" class="me-1 mb-1" onsubmit="return confirm('Supprimer ce sondage ?');">
                                        <?= csrf_token() ?>
                                        <input type="hidden" name="survey_id" value="<?= $survey['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                    </form>
                                    <a href="preview.php?id=<?= $survey['id'] ?>" class="btn btn-sm btn-outline-secondary me-1 mb-1">Aperçu</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>