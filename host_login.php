<?php
session_start();
require_once __DIR__ . '/config.php';

// Determine if the user is already logged in
if (current_user()) {
    $user = current_user();
    if ($user['role'] === 'admin') {
        header('Location: admin.php');
    } else {
        header('Location: host_dashboard.php');
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $selectedRole = $_POST['role'] ?? '';
    if ($username && $password) {
        $db = connect_db();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $stored = $user['password'];
            $isValid = false;
            // Support both hashed and plaintext passwords
            if (password_verify($password, $stored)) {
                $isValid = true;
            } elseif ($password === $stored) {
                $isValid = true;
            }
            if ($isValid) {
                // Ensure that the selected role matches the account role
                if ($selectedRole && $user['role'] !== $selectedRole) {
                    $error = "Le rôle sélectionné ne correspond pas au compte.";
                } elseif ($user['role'] !== 'admin' && isset($user['is_active']) && !$user['is_active']) {
                    // If a host account is inactive, prevent login
                    $error = "Ce compte est désactivé. Veuillez contacter un administrateur.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    // Regenerate session ID to prevent fixation
                    session_regenerate_id(true);
                    if ($user['role'] === 'admin') {
                        header('Location: admin.php');
                    } else {
                        header('Location: host_dashboard.php');
                    }
                    exit;
                }
            }
        }
        $error = "Identifiants invalides.";
    } else {
        $error = "Veuillez entrer un nom d'utilisateur et un mot de passe.";
    }
}

// Choose theme colors for login page (neutral palette)
$primary = '#FFBF69';
$background = '#FFF9F2';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion – WAAWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: <?= esc($background) ?>;
        }
        .login-container {
            max-width: 400px;
            margin: 5rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background-color: <?= esc($primary) ?>;
            border-color: <?= esc($primary) ?>;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-3">
            <img src="assets/logo.png" alt="Logo WAAWH" style="max-width:80px;">
            <h3 class="mt-2">Connexion</h3>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?= esc($error) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <?= csrf_token() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Nom d'utilisateur</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Rôle</label>
                <select name="role" class="form-select" required>
                    <option value="host" <?= (($_POST['role'] ?? '') === 'host') ? 'selected' : '' ?>>Animateur</option>
                    <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrateur</option>
                </select>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Se connecter</button>
            </div>
        </form>
    </div>
</body>
</html>