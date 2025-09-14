<?php
require_once __DIR__ . '/config.php';

// If already logged in, redirect to dashboard
if ($user = current_user()) {
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
    $role     = $_POST['role'] ?? '';
    if ($username && $password && $role) {
        $db = connect_db();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($account && $account['role'] === $role) {
            $stored = $account['password'];
            $ok = false;
            if (password_verify($password, $stored)) {
                $ok = true;
            } elseif ($password === $stored) {
                $ok = true; // Support plaintext (legacy)
            }
            if ($ok) {
                // Check active status for host
                if ($role === 'host' && !(int)$account['is_active']) {
                    $error = "Ce compte est désactivé. Contactez l'administrateur.";
                } else {
                    $_SESSION['user_id'] = $account['id'];
                    session_regenerate_id(true);
                    header('Location: ' . ($role === 'admin' ? 'admin.php' : 'host_dashboard.php'));
                    exit;
                }
            }
        }
        if (!$error) {
            $error = "Identifiants incorrects.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
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
            background-color: #FFF9F2;
        }
        .login-card {
            max-width: 420px;
            margin: 4rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="login-card">
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
                <label class="form-label" for="username">Nom d'utilisateur</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="role">Rôle</label>
                <select name="role" id="role" class="form-select" required>
                    <option value="host">Animateur</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary" style="background-color:#FFBF69;border-color:#FFBF69;">Se connecter</button>
            </div>
        </form>
    </div>
</body>
</html>