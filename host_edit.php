<?php
session_start();
require_once __DIR__ . '/config.php';
require_login('admin');

// Determine if we are editing an existing host or creating a new one
$db = connect_db();
$host = null;
if (isset($_GET['id'])) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND role = \"host\"');
    $stmt->execute([$_GET['id']]);
    $host = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$host) {
        echo 'Animateur introuvable.';
        exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = $_POST['id'] ?? null;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $company = trim($_POST['company_name'] ?? '');
    $primary = $_POST['primary_color'] ?? null;
    $accent = $_POST['accent_color'] ?? null;
    $background = $_POST['background_color'] ?? null;
    // Handle background image upload
    $bgImage = $host['background_image'] ?? null;
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
                $bgImage = 'uploads/' . $newName;
            } else {
                $error = 'Image de fond invalide ou trop volumineuse.';
            }
        }
    }
    if ($username === '') {
        $error = "Le nom d'utilisateur est requis.";
    }
    // Optional: validate email format when provided
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse e‑mail n'est pas valide.";
    }
    if (!$error) {
        // Handle logo upload if provided
        $logoPath = $host['logo'] ?? null;
        if (!empty($_FILES['logo']['tmp_name'])) {
            $file = $_FILES['logo'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/png','image/jpeg','image/gif','image/webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                if (in_array($mime, $allowed) && $file['size'] <= 50 * 1024 * 1024) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $newName = bin2hex(random_bytes(8)) . '.' . $ext;
                    $destination = __DIR__ . '/uploads/' . $newName;
                    move_uploaded_file($file['tmp_name'], $destination);
                    $logoPath = 'uploads/' . $newName;
                } else {
                    $error = 'Logo invalide ou trop volumineux.';
                }
            }
        }
        if (!$error) {
            if ($id) {
                // Update existing host
                $updateFields = [
                    'username' => $username,
                    'email' => $email,
                    'is_active' => $is_active,
                    'company_name' => $company,
                    'primary_color' => $primary,
                    'accent_color' => $accent,
                    'background_color' => $background,
                    'background_image' => $bgImage,
                    'logo' => $logoPath
                ];
                $setParts = [];
                $params = [];
                foreach ($updateFields as $col => $val) {
                    $setParts[] = "$col = ?";
                    $params[] = $val;
                }
                // Only update password if provided
                if ($password !== '') {
                    // Hash password for security
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $setParts[] = "password = ?";
                    $params[] = $hashed;
                }
                $params[] = $id;
                $db->prepare('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ? AND role = \"host\"')
                    ->execute($params);
            } else {
                // Create new host
                $hashed = password_hash($password ?: 'changeme', PASSWORD_DEFAULT);
                $db->prepare('INSERT INTO users (username, password, role, company_name, email, is_active, primary_color, accent_color, background_color, background_image, logo) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$username, $hashed, 'host', $company, $email ?: null, $is_active, $primary, $accent, $background, $bgImage, $logoPath]);
            }
            header('Location: host_manage.php');
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $host ? 'Modifier un animateur' : 'Nouvel animateur' ?> – WAAWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #FFF9F2; }
    </style>
</head>
<body>
    <div class="container py-4">
        <h2><?= $host ? 'Modifier un animateur' : 'Créer un nouvel animateur' ?></h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= esc($error) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_token() ?>
            <?php if ($host): ?>
                <input type="hidden" name="id" value="<?= $host['id'] ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label">Nom d'utilisateur</label>
                <input type="text" name="username" class="form-control" required value="<?= esc($host['username'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Mot de passe <?= $host ? '(laisser vide pour ne pas changer)' : '' ?></label>
                <input type="password" name="password" class="form-control" <?= $host ? '' : 'required' ?> >
            </div>
            <div class="mb-3">
                <label class="form-label">Adresse e‑mail</label>
                <input type="email" name="email" class="form-control" value="<?= esc($host['email'] ?? '') ?>">
            </div>
            <div class="mb-3 form-check form-switch">
                <?php
                $activeFlag = 1;
                if ($host && isset($host['is_active'])) {
                    $activeFlag = (int)$host['is_active'];
                }
                ?>
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $activeFlag ? 'checked' : '' ?> >
                <label class="form-check-label" for="is_active">Compte actif</label>
            </div>
            <div class="mb-3">
                <label class="form-label">Nom de l'entreprise</label>
                <input type="text" name="company_name" class="form-control" value="<?= esc($host['company_name'] ?? '') ?>">
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Couleur principale</label>
                    <input type="color" name="primary_color" class="form-control form-control-color" value="<?= esc($host['primary_color'] ?? '#FFBF69') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Couleur accent</label>
                    <input type="color" name="accent_color" class="form-control form-control-color" value="<?= esc($host['accent_color'] ?? '#2EC4B6') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Couleur de fond</label>
                    <input type="color" name="background_color" class="form-control form-control-color" value="<?= esc($host['background_color'] ?? '#FFF9F2') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Image de fond (optionnelle)</label>
                    <input type="file" name="background_image" class="form-control">
                    <?php if ($host && $host['background_image']): ?>
                        <div class="mt-2">
                            <small>Image actuelle: <?= esc($host['background_image']) ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Logo</label>
                <input type="file" name="logo" class="form-control">
                <?php if ($host && $host['logo']): ?>
                    <div class="mt-2">
                        <img src="<?= esc($host['logo']) ?>" alt="Logo" style="max-height:60px;">
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex">
                <button type="submit" class="btn btn-success me-2">Enregistrer</button>
                <a href="host_manage.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</body>
</html>