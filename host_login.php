<?php
require_once 'config.php';

// Si déjà connecté, rediriger vers le dashboard
if (isHostLoggedIn()) {
    header('Location: host_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === HOST_USERNAME && $password === HOST_PASSWORD) {
        $_SESSION['host_logged_in'] = true;
        $_SESSION['host_username'] = $username;
        header('Location: host_dashboard.php');
        exit;
    } else {
        $error = 'Identifiants incorrects';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Connexion Animatrice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .btn-custom {
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="row w-100 justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-lock-fill text-warning" style="font-size: 3rem;"></i>
                            <h2 class="mt-3">Connexion Animatrice</h2>
                            <p class="text-muted">Accès sécurisé à l'espace de gestion</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person-fill"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer votre nom d'utilisateur
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer votre mot de passe
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-warning btn-lg btn-custom" aria-label="Se connecter">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    Se connecter
                                </button>
                                
                                <a href="index.php" class="btn btn-outline-secondary btn-custom" aria-label="Retour à l'accueil">
                                    <i class="bi bi-arrow-left me-2"></i>
                                    Retour à l'accueil
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Informations de démonstration -->
                <div class="card mt-3 bg-light">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">
                            <strong>Démonstration :</strong><br>
                            Utilisateur : Nadia<br>
                            Mot de passe : P@ssw0rd123!
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation du formulaire
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Focus automatique sur le champ username
        document.getElementById('username').focus();
    </script>
</body>
</html>