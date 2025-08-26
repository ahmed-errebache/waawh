<?php
require_once 'config.php';
require_once 'db.php';

// Initialiser la base de données
Database::getInstance();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Accueil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Palette de couleurs personnalisées inspirées du logo Nadia Ness */
        :root {
            --primary-color: #981a2c;    /* rouge profond du logo */
            --secondary-color: #f4e9dd;  /* beige clair pour le fond */
            --accent-color: #d48e9a;     /* rose doux pour les accents */
        }

        body {
            background-color: var(--secondary-color);
            background-image: url('pattern.png');
            background-size: cover;
            background-attachment: fixed;
            min-height: 100vh;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            background-color: rgba(255, 255, 255, 0.95);
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

        /* Style pour le logo image au lieu de l’icône cœur */
        .logo-img {
            width: 100px;
            height: auto;
        }

        /* Ajustement des couleurs des icônes et boutons */
        .text-primary {
            color: var(--primary-color) !important;
        }
        .text-warning {
            color: var(--accent-color) !important;
        }
        .btn-success {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: #fff !important;
        }
        .btn-success:hover {
            background-color: var(--primary-color) !important;
        }
        .btn-warning {
            background-color: var(--accent-color) !important;
            border-color: var(--accent-color) !important;
            color: #fff !important;
        }
        .btn-warning:hover {
            background-color: var(--accent-color) !important;
        }
        .bg-light {
            background-color: rgba(255,255,255,0.95) !important;
        }
        /* Simple darken function fallback for browsers without SASS */
        @supports not (color: color-mod(var(--primary-color) darker(5%))) {
            /* fallback: do nothing, base color remains */
        }
    </style>
</head>
<body>
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="row w-100 justify-content-center">
            <div class="col-md-10 col-lg-8">
                <!-- En-tête -->
                <div class="text-center mb-5">
                    <!-- Remplacer l’icône cœur par le logo image et l’agrandir -->
                    <img src="logo.png" alt="Logo Nadia Ness" class="logo-img mb-3">
                    <h1 class="display-4 fw-bold" style="color: var(--primary-color);">
                        <?= APP_NAME ?>
                    </h1>
                    <p class="lead" style="color: var(--primary-color);">
                        Quiz éducatif interactif sur le cycle menstruel
                    </p>
                </div>

                <!-- Cartes principales -->
                <div class="row g-4">
                    <!-- Carte Participant -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body p-4 text-center">
                                <i class="bi bi-people-fill text-primary mb-3" style="font-size: 3rem;"></i>
                                <h3 class="card-title mb-3">Rejoindre une session</h3>
                                <p class="text-muted mb-4">Participez au quiz avec votre prénom et le PIN de session</p>
                                
                                <form id="joinForm" class="needs-validation" novalidate>
                                    <div class="mb-3">
                                        <label for="participantName" class="form-label">Votre prénom</label>
                                        <input type="text" class="form-control form-control-lg" id="participantName" 
                                               placeholder="Entrez votre prénom" maxlength="20" required>
                                        <div class="invalid-feedback">
                                            Veuillez entrer votre prénom
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label for="sessionPin" class="form-label">PIN de session</label>
                                        <input type="text" class="form-control form-control-lg text-center" id="sessionPin" 
                                               placeholder="12345" maxlength="5" pattern="[0-9]{5}" required>
                                        <div class="invalid-feedback">
                                            Le PIN doit contenir 5 chiffres
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-lg btn-custom w-100" aria-label="Rejoindre la session">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>
                                        Rejoindre
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Carte Animatrice -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body p-4 text-center">
                                <i class="bi bi-person-badge-fill text-warning mb-3" style="font-size: 3rem;"></i>
                                <h3 class="card-title mb-3">Espace animatrice</h3>
                                <p class="text-muted mb-4">Créez et gérez vos sessions de quiz éducatif</p>
                                
                                <div class="d-grid gap-3">
                                    <a href="host_login.php" class="btn btn-warning btn-lg btn-custom" aria-label="Connexion animatrice">
                                        <i class="bi bi-key-fill me-2"></i>
                                        Connexion animatrice
                                    </a>
                                    <small class="text-muted">
                                        Accès réservé aux animatrices certifiées
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations supplémentaires -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body text-center py-3">
                                <h5 class="mb-2">À propos de Mission Cycle</h5>
                                <p class="mb-0 text-muted">
                                    Un quiz éducatif pour démystifier le cycle menstruel et briser les tabous. 
                                    Apprenons ensemble dans la bienveillance ! 💛
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation du formulaire de participation
        document.getElementById('joinForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }

            const name = document.getElementById('participantName').value.trim();
            const pin = document.getElementById('sessionPin').value.trim();

            if (name && pin && pin.length === 5) {
                // Rediriger vers la page de participation avec les paramètres
                window.location.href = `join.php?name=${encodeURIComponent(name)}&pin=${encodeURIComponent(pin)}`;
            }
        });

        // Formatage automatique du PIN (chiffres uniquement)
        document.getElementById('sessionPin').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 5);
        });

        // Animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>