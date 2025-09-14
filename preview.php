<?php
session_start();
require_once __DIR__ . '/config.php';

$survey_id = $_GET['id'] ?? null;
if (!$survey_id) {
    echo 'Sondage non spécifié.';
    exit;
}
$db = connect_db();
$stmt = $db->prepare('SELECT * FROM surveys WHERE id = ?');
$stmt->execute([$survey_id]);
$survey = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$survey) {
    echo 'Sondage introuvable.';
    exit;
}
// Theme
$theme = ['primary' => '#FFBF69','accent' => '#2EC4B6','background' => '#FFF9F2'];
if ($survey['theme_json']) {
    $dec = json_decode($survey['theme_json'], true);
    if (is_array($dec)) { $theme = array_merge($theme, $dec); }
}
// Fetch questions
$stmt = $db->prepare('SELECT * FROM questions WHERE survey_id = ? ORDER BY id');
$stmt->execute([$survey_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aperçu du sondage – WAAWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: <?= esc($theme['background']) ?>; }
        .navbar { background-color: <?= esc($theme['primary']) ?>; }
        img.img-fluid {
    max-width: 50% !important;
    height: auto;
    display: block;
    margin: 0 auto; /* centre l'image */
}

    </style>
</head>
<body>
    <nav class="navbar navbar-light">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="<?= isset($_SESSION['user_id']) ? ($survey['owner_id'] && current_user()['role']=='host' ? 'host_dashboard.php' : 'admin.php') : 'index.php' ?>">
                <img src="assets/logo.png" alt="Logo" style="max-height:40px;" class="me-2">
                Aperçu
            </a>
        </div>
    </nav>
    <div class="container py-4">
        <h2><?= esc($survey['title']) ?></h2>
        <?php foreach ($questions as $idx => $q): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Question <?= $idx + 1 ?> – <?= esc($q['qtype']) ?></h5>
                    <?php if ($q['qtext']): ?>
                        <p><?= nl2br(esc($q['qtext'])) ?></p>
                    <?php endif; ?>
                    <?php if ($q['qmedia']): ?>
                        <?php $path = esc($q['qmedia']); $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); ?>
                        <?php if (in_array($ext, ['png','jpg','jpeg','gif','webp'])): ?>
                            <img src="<?= $path ?>" alt="media" class="img-fluid mb-2">
                        <?php elseif ($ext === 'mp4'): ?>
                            <video controls class="mb-2" style="max-width:100%"><source src="<?= $path ?>" type="video/mp4"></video>
                        <?php elseif ($ext === 'mp3'): ?>
                            <audio controls class="mb-2"><source src="<?= $path ?>" type="audio/mpeg"></audio>
                        <?php elseif ($ext === 'pdf'): ?>
                            <embed src="<?= $path ?>" type="application/pdf" style="width:100%; height:300px;" class="mb-2"></embed>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php
                    $choices = $q['choices'] ? json_decode($q['choices'], true) : [];
                    $correct = $q['correct_indices'] ? json_decode($q['correct_indices'], true) : [];
                    ?>
                    <?php if ($q['qtype'] === 'quiz'): ?>
                        <ul class="list-group mb-2">
                            <?php foreach ($choices as $i => $opt): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <?= esc($opt) ?>
                                    <?php if (in_array($i, $correct)): ?><span class="badge bg-success">Correct</span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif ($q['qtype'] === 'truefalse'): ?>
                        <ul class="list-group mb-2">
                            <li class="list-group-item d-flex justify-content-between">Vrai <?php if ($correct[0] === 0) echo '<span class="badge bg-success">Correct</span>'; ?></li>
                            <li class="list-group-item d-flex justify-content-between">Faux <?php if ($correct[0] === 1) echo '<span class="badge bg-success">Correct</span>'; ?></li>
                        </ul>
                    <?php elseif ($q['qtype'] === 'short'): ?>
                        <p class="mb-2"><em>Réponses acceptées:</em></p>
                        <ul>
                        <?php foreach ($choices as $c): ?>
                            <li><?= esc($c) ?></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php elseif ($q['qtype'] === 'rating'): ?>
                        <p class="mb-2">Notation sur <?= esc($choices[0] ?? 5) ?> étoiles.</p>
                    <?php elseif ($q['qtype'] === 'date'): ?>
                        <p class="mb-2"><em>Dates acceptées:</em></p>
                        <ul>
                        <?php foreach ($choices as $c): ?>
                            <li><?= esc($c) ?></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($q['seconds']): ?><p>Temps: <?= esc($q['seconds']) ?> s</p><?php endif; ?>
                    <?php if ($q['points']): ?><p>Points: <?= esc($q['points']) ?></p><?php endif; ?>
                    <?php if ($q['confirm_text']): ?><p class="text-success"><strong>Si correct:</strong> <?= esc($q['confirm_text']) ?></p><?php endif; ?>
                    <?php if ($q['wrong_text']): ?><p class="text-danger"><strong>Si faux:</strong> <?= esc($q['wrong_text']) ?></p><?php endif; ?>
                    <?php if ($q['explain_text']): ?><p class="mb-2"><em>Explication:</em> <?= nl2br(esc($q['explain_text'])) ?></p><?php endif; ?>
                    <?php if ($q['explain_media']): ?>
                        <?php $path = esc($q['explain_media']); $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); ?>
                        <?php if (in_array($ext, ['png','jpg','jpeg','gif','webp'])): ?>
                            <img src="<?= $path ?>" alt="explication" class="img-fluid mb-2">
                        <?php elseif ($ext === 'mp4'): ?>
                            <video controls class="mb-2" style="max-width:100%"><source src="<?= $path ?>" type="video/mp4"></video>
                        <?php elseif ($ext === 'mp3'): ?>
                            <audio controls class="mb-2"><source src="<?= $path ?>" type="audio/mpeg"></audio>
                        <?php elseif ($ext === 'pdf'): ?>
                            <embed src="<?= $path ?>" type="application/pdf" style="width:100%; height:300px;" class="mb-2"></embed>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>