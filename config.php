<?php
/**
 * Configuration de l'application Mission Cycle
 */

// Configuration de la base de données
define('DB_PATH', __DIR__ . '/database.sqlite');

// Identifiants de l'animatrice
define('HOST_USERNAME', 'Nadia');
define('HOST_PASSWORD', 'P@ssw0rd123!');

// Configuration de l'application
define('APP_NAME', 'Mission Cycle');
define('SESSION_TIMEOUT', 3600); // 1 heure

// Configuration des sessions
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Fonction utilitaire pour vérifier si l'utilisateur est connecté comme animatrice
function isHostLoggedIn() {
    return isset($_SESSION['host_logged_in']) && $_SESSION['host_logged_in'] === true;
}

// Fonction pour rediriger vers la page de connexion si non connecté
function requireHostLogin() {
    if (!isHostLoggedIn()) {
        header('Location: host_login.php');
        exit;
    }
}

// Headers de sécurité basiques
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
?>