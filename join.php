<?php
require_once 'config.php';
require_once 'db.php';

$db = Database::getInstance()->getPDO();

// Récupérer les paramètres
$userName = $_GET['name'] ?? '';
$sessionPin = $_GET['pin'] ?? '';

// Vérifier si la session existe
$session = null;
if ($sessionPin) {
    $stmt = $db->prepare("SELECT * FROM sessions WHERE pin = ? AND status = 'open'");
    $stmt->execute([$sessionPin]);
    $session = $stmt->fetch();
}

if (!$session && $sessionPin) {
    $error = "Session introuvable ou fermée";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Participant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: var(--secondary-color) !important;
            background-image: url('pattern.png');
            background-size: cover;
            background-attachment: fixed;
            min-height: 100vh;
        }
        :root {
            --primary-color: #981a2c;
            --secondary-color: #f4e9dd;
            --accent-color: #d48e9a;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .btn-custom {
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            min-height: 50px;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .choice-btn {
            margin: 8px 0;
            padding: 15px;
            text-align: left;
            border: 2px solid transparent;
        }
        .choice-btn:hover {
            border-color: var(--primary-color);
        }
        .choice-btn.selected {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        /* Theme overrides */
        .navbar {
            background-color: var(--primary-color) !important;
        }
        .bg-primary, .btn-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: #fff !important;
        }
        .bg-warning, .badge.bg-warning {
            background-color: var(--accent-color) !important;
            color: #fff !important;
        }
        .text-primary {
            color: var(--primary-color) !important;
        }
        .text-success {
            color: var(--accent-color) !important;
        }
        .card {
            background-color: rgba(255, 255, 255, 0.9);
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .waiting-spinner {
            width: 3rem;
            height: 3rem;
        }
        .feedback-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="logo.png" alt="Logo" style="width:40px;height:40px;" class="me-2">
                <?= APP_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($userName) ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                
                <?php if (isset($error)): ?>
                    <!-- Erreur -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
                            <h3 class="mt-3 text-danger">Erreur</h3>
                            <p class="lead"><?= htmlspecialchars($error) ?></p>
                            <a href="index.php" class="btn btn-primary btn-custom">
                                <i class="bi bi-arrow-left me-2"></i>
                                Retour à l'accueil
                            </a>
                        </div>
                    </div>
                
                <?php else: ?>
                    
                    <!-- Écran d'attente -->
                    <div id="waitingScreen" class="card">
                        <div class="card-body text-center py-5">
                            <div class="waiting-spinner spinner-border text-primary pulse mb-4" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <h3 class="mb-3">En attente du démarrage...</h3>
                            <p class="text-muted mb-4">
                                L'animatrice va bientôt commencer le quiz.<br>
                                Restez connecté !
                            </p>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <strong>PIN</strong><br>
                                        <span class="h4 text-primary"><?= htmlspecialchars($sessionPin) ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <strong>Participants</strong><br>
                                        <span class="h4 text-success" id="participantCount">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Écran de question -->
                    <div id="questionScreen" class="card d-none">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col">
                                    <span class="badge bg-light text-dark" id="questionNumber">Question 1/20</span>
                                    <span class="badge bg-warning text-dark ms-2" id="questionType">Quiz</span>
                                </div>
                                <div class="col-auto">
                                    <span class="badge bg-danger" id="questionTimer">30s</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h4 id="questionText" class="mb-4">Question apparaîtra ici</h4>
                            <div id="questionChoices" class="d-grid gap-2">
                                <!-- Les choix seront générés dynamiquement -->
                            </div>
                            <div class="mt-3 text-center">
                                <small class="text-muted" id="questionStatus">Choisissez votre réponse</small>
                            </div>
                        </div>
                    </div>

                    <!-- Écran de feedback -->
                    <div id="feedbackScreen" class="card d-none">
                        <div class="card-body text-center py-5">
                            <div id="feedbackIcon" class="feedback-icon">
                                <!-- Icône sera ajoutée dynamiquement -->
                            </div>
                            <h3 id="feedbackTitle" class="mb-3">Résultat</h3>
                            <div id="feedbackContent" class="mb-4">
                                <!-- Contenu sera ajouté dynamiquement -->
                            </div>
                            <button id="continueBtn" class="btn btn-primary btn-custom" aria-label="Continuer">
                                <i class="bi bi-arrow-right me-2"></i>
                                Continuer
                            </button>
                        </div>
                    </div>

                    <!-- Écran de fin -->
                    <div id="endScreen" class="card d-none">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-trophy-fill text-warning" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Quiz terminé !</h3>
                            <p class="lead mb-4">Merci d'avoir participé à Mission Cycle</p>
                            <a href="index.php" class="btn btn-primary btn-custom">
                                <i class="bi bi-house-fill me-2"></i>
                                Retour à l'accueil
                            </a>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables globales
        const sessionId = <?= $session ? $session['id'] : 'null' ?>;
        const userName = <?= json_encode($userName) ?>;
        let currentQuestion = null;
        let pollInterval = null;
        let selectedAnswer = null;

        // Éléments DOM
        const screens = {
            waiting: document.getElementById('waitingScreen'),
            question: document.getElementById('questionScreen'),
            feedback: document.getElementById('feedbackScreen'),
            end: document.getElementById('endScreen')
        };

        const elements = {
            participantCount: document.getElementById('participantCount'),
            questionNumber: document.getElementById('questionNumber'),
            questionType: document.getElementById('questionType'),
            questionTimer: document.getElementById('questionTimer'),
            questionText: document.getElementById('questionText'),
            questionChoices: document.getElementById('questionChoices'),
            questionStatus: document.getElementById('questionStatus'),
            feedbackIcon: document.getElementById('feedbackIcon'),
            feedbackTitle: document.getElementById('feedbackTitle'),
            feedbackContent: document.getElementById('feedbackContent'),
            continueBtn: document.getElementById('continueBtn')
        };

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            if (sessionId) {
                startPolling();
                setupEventListeners();
            }
        });

        // Configuration des événements
        function setupEventListeners() {
            if (elements.continueBtn) {
                elements.continueBtn.addEventListener('click', function() {
                    showScreen('waiting');
                    selectedAnswer = null;
                });
            }
        }

        // Démarrer le polling pour les mises à jour
        function startPolling() {
            if (pollInterval) clearInterval(pollInterval);
            
            pollInterval = setInterval(async () => {
                await updateSessionData();
            }, 2000);
        }

        // Mettre à jour les données de session
        async function updateSessionData() {
            try {
                const response = await fetch('api/get_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: sessionId
                    })
                });

                const data = await response.json();
                
                if (data.ok) {
                    const session = data.session;
                    const question = data.question;
                    
                    // Mettre à jour le nombre de participants
                    if (elements.participantCount) {
                        elements.participantCount.textContent = session.participant_count || 0;
                    }
                    
                    // Gérer l'état de la session
                    if (session.status === 'ended') {
                        showScreen('end');
                        clearInterval(pollInterval);
                    } else if (question && session.current_question_index >= 0) {
                        if (!currentQuestion || currentQuestion.id !== question.id) {
                            currentQuestion = question;
                            showQuestion(question, session.current_question_index);
                        }
                    } else {
                        showScreen('waiting');
                    }
                }
            } catch (error) {
                console.error('Erreur lors de la mise à jour:', error);
            }
        }

        // Afficher une question
        function showQuestion(question, questionIndex) {
            showScreen('question');
            
            // Mettre à jour les informations de question
            elements.questionNumber.textContent = `Question ${questionIndex + 1}/20`;
            elements.questionType.textContent = question.qtype === 'truefalse' ? 'Vrai/Faux' : 'Quiz';
            elements.questionText.textContent = question.qtext;
            elements.questionTimer.textContent = question.seconds + 's';
            
            // Afficher les choix
            const choices = JSON.parse(question.choices);
            elements.questionChoices.innerHTML = '';
            
            choices.forEach((choice, index) => {
                const button = document.createElement('button');
                button.className = 'btn btn-outline-primary btn-custom choice-btn w-100';
                button.textContent = choice;
                button.addEventListener('click', () => selectAnswer(index, button));
                elements.questionChoices.appendChild(button);
            });
            
            // Réinitialiser le statut
            elements.questionStatus.textContent = 'Choisissez votre réponse';
            selectedAnswer = null;
        }

        // Sélectionner une réponse
        async function selectAnswer(answerIndex, buttonElement) {
            if (selectedAnswer !== null) return; // Déjà répondu
            
            selectedAnswer = answerIndex;
            
            // Marquer visuellement la sélection
            document.querySelectorAll('.choice-btn').forEach(btn => {
                btn.classList.remove('selected');
                btn.disabled = true;
            });
            buttonElement.classList.add('selected');
            
            // Envoyer la réponse
            try {
                const response = await fetch('api/submit_answer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        question_id: currentQuestion.id,
                        user_name: userName,
                        answer_indices: [answerIndex]
                    })
                });

                const data = await response.json();
                
                if (data.ok) {
                    elements.questionStatus.textContent = 'Réponse enregistrée !';
                    
                    // Afficher le feedback après un court délai
                    setTimeout(() => {
                        showFeedback(data.correct, data.confirm_text, data.explain_text);
                    }, 1500);
                } else {
                    elements.questionStatus.textContent = 'Erreur lors de l\'envoi';
                }
            } catch (error) {
                console.error('Erreur:', error);
                elements.questionStatus.textContent = 'Erreur de connexion';
            }
        }

        // Afficher le feedback
        function showFeedback(isCorrect, confirmText, explainText) {
            showScreen('feedback');
            
            if (isCorrect) {
                elements.feedbackIcon.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
                elements.feedbackTitle.textContent = '✅ Bonne réponse !';
                elements.feedbackTitle.className = 'mb-3 text-success';
                elements.feedbackContent.innerHTML = `<p class="lead">${confirmText}</p>`;
            } else {
                elements.feedbackIcon.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
                elements.feedbackTitle.textContent = 'ℹ️ Réponse incorrecte';
                elements.feedbackTitle.className = 'mb-3 text-danger';
                elements.feedbackContent.innerHTML = `
                    <p class="lead mb-3"><strong>Réponse :</strong> ${confirmText}</p>
                    <div class="alert alert-info text-start">
                        <strong>Explication :</strong><br>
                        ${explainText}
                    </div>
                `;
            }
            /* Afficher une illustration si la question concerne la durée moyenne du cycle (id 8) */
            if (currentQuestion && currentQuestion.id === 8) {
                // Ajouter l’illustration du cycle menstruel (passez par le même fichier que l’interface animatrice)
                elements.feedbackContent.innerHTML += `
                    <img src="cycle_infographic.png" alt="Cycle menstruel" class="img-fluid mt-3 rounded shadow">
                `;
            }
        }

        // Afficher un écran spécifique
        function showScreen(screenName) {
            Object.values(screens).forEach(screen => {
                screen.classList.add('d-none');
            });
            
            if (screens[screenName]) {
                screens[screenName].classList.remove('d-none');
            }
        }

        // Gestion de la fermeture de page
        window.addEventListener('beforeunload', function() {
            if (pollInterval) {
                clearInterval(pollInterval);
            }
        });
    </script>
</body>
</html>