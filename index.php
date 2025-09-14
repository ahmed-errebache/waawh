<?php
session_start();
require_once __DIR__ . '/config.php';

// Determine theme colors for the homepage. We use the default WAAWH palette.
$primary = '#FFBF69';
$accent  = '#2EC4B6';
$coral   = '#FF6B6B';
$rose    = '#F06595';
$background = '#FFF9F2';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WAAWH – Accueil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: <?= esc($background) ?>;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .hero {
            padding: 4rem 1rem;
            text-align: center;
        }
        .hero img {
            max-width: 200px;
        }
        .hero h1 {
            font-size: 2.5rem;
            margin-top: 1rem;
            color: <?= esc($primary) ?>;
            font-weight: 700;
        }
        .hero p {
            color: #444;
            margin-bottom: 2rem;
        }
        .btn-waawh {
            font-size: 1.2rem;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            margin: 0.5rem;
        }
        .badge-feature {
            font-size: 1rem;
            border-radius: 20px;
            padding: 0.6rem 1.2rem;
            margin: 0.3rem;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container hero">
        <img src="assets/logo.png" alt="WAAWH logo" class="mb-3">
        <h1>Plateforme de sondages interactive</h1>
        <p>Créez, gérez et animez des sondages en direct. Invitez vos participants par code PIN et suivez les résultats en temps réel.</p>
        <div class="d-flex justify-content-center flex-wrap mb-4">
            <a href="join.php" class="btn btn-waawh" style="background-color: <?= esc($primary) ?>; color:#fff;">Rejoindre une session</a>
            <a href="host_login.php" class="btn btn-waawh" style="background-color: <?= esc($accent) ?>; color:#fff;">Connexion</a>
        </div>
        <div class="d-flex justify-content-center flex-wrap">
            <span class="badge-feature" style="background-color: <?= esc($primary) ?>; color: #fff;">PIN d'accès</span>
            <span class="badge-feature" style="background-color: <?= esc($accent) ?>; color: #fff;">Questions multimédias</span>
            <span class="badge-feature" style="background-color: <?= esc($coral) ?>; color: #fff;">Classement 🥇🥈🥉</span>
        </div>
    </div>
</body>
</html>