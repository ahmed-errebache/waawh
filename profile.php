<?php
require_once __DIR__ . '/config.php';
requireAnimator();
$db = get_db();
$user = current_user();
$error = '';
$success = '';
// Allowed image types for logo
$allowedLogoTypes = ['image/png','image/jpeg','image/gif','image/webp'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf($_POST['csrf'] ?? '');
    // Handle theme colours
    $background = $_POST['background'] ?? ($user['theme_json'] ? json_decode($user['theme_json'], true)['background'] : DEFAULT_THEME['background']);
    $primary = $_POST['primary'] ?? ($user['theme_json'] ? json_decode($user['theme_json'], true)['primary'] : DEFAULT_THEME['primary']);
    $accent = $_POST['accent'] ?? ($user['theme_json'] ? json_decode($user['theme_json'], true)['accent'] : DEFAULT_THEME['accent']);
    $coral = $_POST['coral'] ?? ($user['theme_json'] ? json_decode($user['theme_json'], true)['coral'] : DEFAULT_THEME['coral']);
    $rose = $_POST['rose'] ?? ($user['theme_json'] ? json_decode($user['theme_json'], true)['rose'] : DEFAULT_THEME['rose']);
    $themeJson = json_encode([
        'background' => $background,
        'primary' => $primary,
        'accent' => $accent,
        'coral' => $coral,
        'rose' => $rose,
    ]);
    // Handle logo upload
    $logoPath = $user['logo'];
    if (!empty($_FILES['logo']['name'])) {
        $file = $_FILES['logo'];
        if ($file['error'] === UPLOAD_ERR_OK && $file['size'] <= 5*1024*1024) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (in_array($mime, $allowedLogoTypes, true)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = bin2hex(random_bytes(8)) . '.' . $ext;
                $dest = __DIR__ . '/uploads/' . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $logoPath = 'uploads/' . $newName;
                }
            }
        }
    }
    // Update user
    $db->prepare("UPDATE users SET theme_json = ?, logo = ? WHERE id = ?")
        ->execute([$themeJson, $logoPath, $user['id']]);
    $success = "Profil mis à jour.";
    // reload user
    $user = current_user();
}
// Determine current theme
$theme = $user['theme_json'] ? json_decode($user['theme_json'], true) : DEFAULT_THEME;
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil Animateur - WAAWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style> body { background-color: <?= e($theme['background']) ?>; } .pill-btn{border-radius:50px;} </style>
</head>
<body>
<nav class="navbar navbar-light" style="background-color: <?= e($theme['primary']) ?>;">
    <div class="container-fluid">
        <a class="navbar-brand text-white" href="admin.php">← Retour</a>
        <span class="navbar-text text-white">Profil</span>
    </div>
</nav>
<div class="container my-4">
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <h2>Mettre à jour votre profil</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="mb-3">
            <label class="form-label">Logo actuel</label><br>
            <?php if ($user['logo']): ?>
                <img src="<?= e($user['logo']) ?>" alt="logo" style="max-width:150px;" class="mb-2">
            <?php else: ?>
                <p>Aucun logo défini.</p>
            <?php endif; ?>
            <input type="file" name="logo" class="form-control mt-2">
        </div>
        <h4>Thème</h4>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Fond</label>
                <input type="color" class="form-control form-control-color" name="background" value="<?= e($theme['background']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Primaire</label>
                <input type="color" class="form-control form-control-color" name="primary" value="<?= e($theme['primary']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Accent</label>
                <input type="color" class="form-control form-control-color" name="accent" value="<?= e($theme['accent']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Coral</label>
                <input type="color" class="form-control form-control-color" name="coral" value="<?= e($theme['coral']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Rose</label>
                <input type="color" class="form-control form-control-color" name="rose" value="<?= e($theme['rose']) ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-success mt-3 pill-btn">Enregistrer</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>