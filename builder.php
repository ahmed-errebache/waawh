<?php
session_start();
require_once __DIR__ . '/config.php';
require_login('admin');

$db = connect_db();

// Fetch list of hosts to assign surveys
$hosts = $db->query("SELECT id, username, company_name FROM users WHERE role = 'host' AND (is_active IS NULL OR is_active = 1) ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

// Determine if editing existing survey or creating new
$survey = null;
// Selected host IDs for assignment
$assigned_hosts = [];
if (isset($_GET['id'])) {
    $stmt = $db->prepare('SELECT * FROM surveys WHERE id = ?');
    $stmt->execute([$_GET['id']]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$survey) {
        echo 'Sondage introuvable.';
        exit;
    }
    // Fetch assigned hosts for this survey
    $stmtAH = $db->prepare('SELECT host_id FROM survey_hosts WHERE survey_id = ?');
    $stmtAH->execute([$survey['id']]);
    $assigned_hosts = $stmtAH->fetchAll(PDO::FETCH_COLUMN);
}

$error = '';
// Handle POST for survey info
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $title = trim($_POST['title'] ?? '');
    // Support assignment to multiple hosts. owner_ids is an array of selected host IDs.
    $ownerIds = [];
    if (isset($_POST['owner_ids']) && is_array($_POST['owner_ids'])) {
        foreach ($_POST['owner_ids'] as $oid) {
            if ($oid !== '') {
                $ownerIds[] = (int)$oid;
            }
        }
    }
    // Keep backward compatibility: use the first selected host as owner_id or null if none
    $owner_id = $ownerIds[0] ?? null;
    $primary = $_POST['primary_color'] ?? '#FFBF69';
    $accent = $_POST['accent_color'] ?? '#2EC4B6';
    $background = $_POST['background_color'] ?? '#FFF9F2';
    // Handle optional background image upload
    $background_image = null;
    if (!empty($_FILES['background_image']['tmp_name'])) {
        $file = $_FILES['background_image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/png','image/jpeg','image/gif','image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (in_array($mime, $allowed) && $file['size'] <= 50 * 1024 * 1024) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = bin2hex(random_bytes(8)) . '.' . $ext;
                $destination = __DIR__ . '/uploads/' . $newName;
                move_uploaded_file($file['tmp_name'], $destination);
                $background_image = 'uploads/' . $newName;
            } else {
                $error = 'Image de fond invalide ou trop volumineuse.';
            }
        }
    }
    if ($title === '') {
        $error = 'Le titre est requis.';
    }
    if (!$error) {
        // Persist both color and optional image in theme
        $themeArr = ['primary' => $primary, 'accent' => $accent, 'background' => $background];
        if ($background_image) {
            $themeArr['background_image'] = $background_image;
        }
        $theme = json_encode($themeArr);
        if ($survey) {
            // Update existing survey
            $db->prepare('UPDATE surveys SET title = ?, owner_id = ?, theme_json = ? WHERE id = ?')
               ->execute([$title, $owner_id, $theme, $survey['id']]);
            $surveyId = $survey['id'];
        } else {
            // Create new survey
            $db->prepare('INSERT INTO surveys (title, owner_id, theme_json) VALUES (?,?,?)')
               ->execute([$title, $owner_id, $theme]);
            $surveyId = $db->lastInsertId();
            // Redirect to edit page after creation
            header('Location: builder.php?id=' . $surveyId);
            exit;
        }
        // Update survey-host assignments. Remove existing rows and insert selected hosts.
        // Delete previous assignments
        $db->prepare('DELETE FROM survey_hosts WHERE survey_id = ?')->execute([$surveyId]);
        foreach ($ownerIds as $hid) {
            $db->prepare('INSERT INTO survey_hosts (survey_id, host_id) VALUES (?,?)')->execute([$surveyId, $hid]);
        }
        // Reload page after update
        header('Location: builder.php?id=' . $surveyId);
        exit;
    }
}

// If editing, decode theme json
$theme = ['primary' => '#FFBF69', 'accent' => '#2EC4B6', 'background' => '#FFF9F2', 'background_image' => null];
if ($survey && $survey['theme_json']) {
    $dec = json_decode($survey['theme_json'], true);
    if (is_array($dec)) {
        $theme = array_merge($theme, $dec);
    }
}

// Fetch questions for this survey
$questions = [];
if ($survey) {
    $stmt = $db->prepare('SELECT * FROM questions WHERE survey_id = ? ORDER BY id');
    $stmt->execute([$survey['id']]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $survey ? 'Modifier le sondage' : 'Créer un sondage' ?> – WAAWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #FFF9F2; }
        .navbar { background-color: #FFBF69; }
        .navbar-brand img { max-height: 40px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="admin.php">
                <img src="assets/logo.png" alt="Logo" class="me-2">
                <span class="fw-bold">Admin WAAWH</span>
            </a>
            <div class="d-flex">
                <a href="logout.php" class="btn btn-outline-light">Déconnexion</a>
            </div>
        </div>
    </nav>
    <div class="container py-4">
        <h2 class="mb-3">Informations générales</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= esc($error) ?></div>
        <?php endif; ?>
        <!-- When uploading files (background images), the form must use multipart/form-data -->
        <form method="post" enctype="multipart/form-data" class="mb-4">
            <?= csrf_token() ?>
            <div class="mb-3">
                <label class="form-label">Titre du sondage</label>
                <input type="text" name="title" class="form-control" required value="<?= esc($survey['title'] ?? '') ?>">
            </div>
        <div class="mb-3">
            <label class="form-label">Assigné aux animateurs</label>
            <select name="owner_ids[]" class="form-select" multiple>
                <?php foreach ($hosts as $h): ?>
                    <?php $selected = in_array($h['id'], $assigned_hosts) || (!$survey && isset($_POST['owner_ids']) && in_array($h['id'], (array)$_POST['owner_ids'])) ? 'selected' : ''; ?>
                    <option value="<?= $h['id'] ?>" <?= $selected ?>><?= esc($h['username']) ?> (<?= esc($h['company_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">Maintenez Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs animateurs.</small>
        </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Couleur primaire</label>
                    <input type="color" name="primary_color" class="form-control form-control-color" value="<?= esc($theme['primary']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Couleur accent</label>
                    <input type="color" name="accent_color" class="form-control form-control-color" value="<?= esc($theme['accent']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Couleur de fond</label>
                    <input type="color" name="background_color" class="form-control form-control-color" value="<?= esc($theme['background']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Image de fond (facultatif)</label>
                    <input type="file" name="background_image" class="form-control">
                    <?php if (isset($theme['background_image']) && $theme['background_image']): ?>
                        <div class="mt-1"><small>Image actuelle: <?= esc($theme['background_image']) ?></small></div>
                    <?php endif; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-success">Enregistrer</button>
        </form>
        <?php if ($survey): ?>
            <h3 class="mb-3">Questions</h3>
            <div class="mb-2">
                <a href="edit_question.php?survey_id=<?= $survey['id'] ?>" class="btn btn-primary" style="background-color:#2EC4B6;border-color:#2EC4B6;">+ Ajouter une question</a>
            </div>
            <?php if (!$questions): ?>
                <p>Aucune question pour le moment.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($questions as $q): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">[<?= esc($q['qtype']) ?>] <?= esc(substr($q['qtext'], 0, 50)) ?></h6>
                                <?php if ($q['seconds']): ?>
                                    <small>Temps: <?= esc($q['seconds']) ?> s, Points: <?= esc($q['points']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center">
                                <a href="edit_question.php?id=<?= $q['id'] ?>&survey_id=<?= $survey['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Modifier</a>
                                <form method="post" action="api/delete_question.php" onsubmit="return confirm('Supprimer cette question ?');">
                                    <?= csrf_token() ?>
                                    <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>