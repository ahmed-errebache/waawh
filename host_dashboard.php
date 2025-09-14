<?php
session_start();
require_once __DIR__ . '/config.php';
require_login('host');
$user = current_user();

$db = connect_db();
// Get surveys assigned to this host either directly (owner_id) or via survey_hosts table
$stmt = $db->prepare('SELECT DISTINCT s.* FROM surveys s
    LEFT JOIN survey_hosts sh ON sh.survey_id = s.id
    WHERE (s.owner_id = ? OR sh.host_id = ?) ORDER BY s.created_at DESC');
$stmt->execute([$user['id'], $user['id']]);
$surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for active session
$stmt = $db->prepare('SELECT s.*, su.title FROM sessions s JOIN surveys su ON s.survey_id = su.id WHERE s.host_id = ? AND s.is_active = 1');
$stmt->execute([$user['id']]);
$active_session = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord – Animateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        <?php if (!empty($user['background_image'])): ?>body {
            background-image: url(<?= "'" . esc($user['background_image']) . "'" ?>);
            background-size: cover;
            background-position: center;
        }

        <?php else: ?>body {
            background-color: <?= esc($user['background_color'] ?: '#FFF9F2') ?>;
        }

        <?php endif; ?>.navbar {
            background-color: <?= esc($user['primary_color'] ?: '#FFBF69') ?>;
        }

        .navbar-brand img {
            max-height: 40px;
        }
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
                <span class="fw-bold">Espace animateur</span>
            </a>
            <div class="d-flex">
                <a href="logout.php" class="btn btn-outline-light">Déconnexion</a>
            </div>
        </div>
    </nav>
    <div class="container py-4">
        <h2 class="mb-4">Vos sondages</h2>
        <?php if (!$surveys): ?>
            <p>Aucun sondage assigné pour le moment.</p>
        <?php else: ?>
            <div class="row gy-3">
                <?php foreach ($surveys as $survey): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title mb-2"><?= esc($survey['title']) ?></h5>
                                <p class="small mb-3">Créé le <?= esc(date('d/m/Y', strtotime($survey['created_at']))) ?></p>
                                <div class="mt-auto">
                                    <a href="preview.php?id=<?= $survey['id'] ?>" class="btn btn-sm btn-outline-secondary me-1">Aperçu</a>
                                    <?php if (!$active_session): ?>
                                        <form method="post" action="api/create_session.php" class="d-inline">
                                            <?= csrf_token() ?>
                                            <input type="hidden" name="survey_id" value="<?= $survey['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-primary" style="background-color:<?= esc($user['accent_color'] ?: '#2EC4B6') ?>;border-color:<?= esc($user['accent_color'] ?: '#2EC4B6') ?>;">Démarrer une session</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>Session en cours</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($active_session): ?>
            <hr class="my-4">
            <h3>Session active</h3>
            <p>Sondage: <strong><?= esc($active_session['title']) ?></strong></p>
            <p>PIN: <strong><?= esc($active_session['pin']) ?></strong></p>
            <div class="d-flex flex-wrap">
                <a href="host_session.php?session_id=<?= $active_session['id'] ?>" class="btn btn-success me-2 mb-2">Contrôler la session</a>
                <form method="post" action="api/end_session.php">
                    <input type="hidden" name="session_id" value="<?= (int)$active_session['id'] ?>">
                    <?php if (function_exists('csrf_token')) echo csrf_token(); ?>
                    <button type="submit" class="btn btn-danger">Terminer</button>
                </form>


            </div>
        <?php endif; ?>
    </div>
    <script src="./script.js" defer></script>
</body>

</html>