<?php
session_start();
require_once __DIR__ . '/config.php';
require_login('admin');

// Fetch list of hosts
$db = connect_db();
$hosts = $db->query("SELECT * FROM users WHERE role = 'host' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des animateurs – WAAWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #FFF9F2; }
        .navbar-brand img { max-height:40px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light" style="background-color:#FFBF69;">
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
        <h2 class="mb-4">Gestion des animateurs</h2>
        <?php if (!empty($_SESSION['host_delete_error'])): ?>
            <div class="alert alert-danger">
                <?= esc($_SESSION['host_delete_error']) ?>
            </div>
            <?php unset($_SESSION['host_delete_error']); ?>
        <?php endif; ?>
        <a href="host_edit.php" class="btn btn-primary mb-3" style="background-color:#2EC4B6;border-color:#2EC4B6;">+ Nouvel animateur</a>
        <?php if (!$hosts): ?>
            <p>Aucun animateur.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom d'utilisateur</th>
                            <th>E‑mail</th>
                            <th>Entreprise</th>
                            <th>Actif</th>
                            <th>Couleur principale</th>
                            <th>Couleur accent</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hosts as $host): ?>
                            <tr>
                                <td><?= $host['id'] ?></td>
                                <td><?= esc($host['username']) ?></td>
                                <td><?= esc($host['email'] ?? '') ?></td>
                                <td><?= esc($host['company_name']) ?></td>
                                <td>
                                    <?php if (!isset($host['is_active']) || $host['is_active']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge" style="background-color: <?= esc($host['primary_color'] ?: '#ccc') ?>;">&nbsp;</span> <?= esc($host['primary_color']) ?></td>
                                <td><span class="badge" style="background-color: <?= esc($host['accent_color'] ?: '#ccc') ?>;">&nbsp;</span> <?= esc($host['accent_color']) ?></td>
                                <td>
                                    <a href="host_edit.php?id=<?= $host['id'] ?>" class="btn btn-sm btn-outline-primary mb-1">Modifier</a>
                                    <!-- Toggle active/inactive -->
                                    <form method="post" action="api/toggle_host.php" style="display:inline-block;" class="mb-1">
                                        <?= csrf_token() ?>
                                        <input type="hidden" name="host_id" value="<?= $host['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                            <?= isset($host['is_active']) && !$host['is_active'] ? 'Activer' : 'Désactiver' ?>
                                        </button>
                                    </form>
                                    <!-- Delete host form (only if there are no surveys assigned) -->
                                    <form method="post" action="api/delete_host.php" style="display:inline-block;" onsubmit="return confirm('Supprimer cet animateur ?');">
                                        <?= csrf_token() ?>
                                        <input type="hidden" name="host_id" value="<?= $host['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>