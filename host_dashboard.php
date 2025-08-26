<?php
require_once 'config.php';
require_once 'db.php';

// Vérifier l'authentification
requireHostLogin();

$db = Database::getInstance()->getPDO();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Dashboard Animatrice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: 700;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .btn-custom {
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .question-card {
            min-height: 400px;
        }
        .choice-item {
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 8px;
            background-color: #f8f9fa;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .choice-item.correct {
            background-color: #d4edda;
            border-color: #28a745;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-waiting { background-color: #ffc107; }
        .status-active { background-color: #28a745; }
        .status-ended { background-color: #dc3545; }
    </style>
    <!-- Custom theme styles injected by assistant -->
    <style>
        :root {
            --primary-color: #981a2c;
            --secondary-color: #f4e9dd;
            --accent-color: #d48e9a;
        }
        body {
            background-color: var(--secondary-color) !important;
            background-image: url('pattern.png');
            background-size: cover;
            background-attachment: fixed;
        }
        .navbar {
            background-color: var(--primary-color) !important;
        }
        .bg-primary, .btn-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: #fff !important;
        }
        .bg-info, .btn-info {
            background-color: var(--accent-color) !important;
            border-color: var(--accent-color) !important;
            color: #fff !important;
        }
        .bg-warning, .btn-warning {
            background-color: var(--accent-color) !important;
            border-color: var(--accent-color) !important;
            color: #fff !important;
        }
        .card {
            /* Increase opacity of cards so the motif doesn’t show through too strongly */
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
        }
        /* Limit the height of the real‑time response chart so it doesn’t expand the card indefinitely */
        #responseChart {
            max-height: 250px;
        }
        /* Ensure the chart container has a subtle white background to improve readability over the motif */
        .card .card-body {
            background-color: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="logo.png" alt="Logo" style="width:40px;height:40px;" class="me-2">
                <?= APP_NAME ?> - Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i>
                    Bonjour, <?= htmlspecialchars($_SESSION['host_username']) ?>
                </span>
                <a href="?logout=1" class="btn btn-outline-light btn-sm" aria-label="Se déconnecter">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Panneau de contrôle -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-gear-fill me-2"></i>
                            Contrôle de session
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Statut de session -->
                        <div id="sessionStatus" class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <span class="status-indicator status-waiting"></span>
                                <span>Aucune session active</span>
                            </div>
                            <div id="sessionInfo" class="d-none">
                                <small class="text-muted">PIN: <strong id="sessionPin">-</strong></small><br>
                                <small class="text-muted">Participants: <strong id="participantCount">0</strong></small>
                            </div>
                        </div>

                        <!-- Boutons de contrôle -->
                        <div class="d-grid gap-2">
                            <button id="createSessionBtn" class="btn btn-success btn-custom" aria-label="Créer une session">
                                <i class="bi bi-plus-circle-fill me-2"></i>
                                Créer une session
                            </button>
                            
                            <button id="startQuizBtn" class="btn btn-primary btn-custom d-none" aria-label="Démarrer le quiz">
                                <i class="bi bi-play-fill me-2"></i>
                                Démarrer le quiz
                            </button>
                            
                            <button id="nextQuestionBtn" class="btn btn-info btn-custom d-none" aria-label="Question suivante">
                                <i class="bi bi-arrow-right-circle-fill me-2"></i>
                                Question suivante
                            </button>
                            
                            <button id="endSessionBtn" class="btn btn-danger btn-custom d-none" aria-label="Terminer la session">
                                <i class="bi bi-stop-fill me-2"></i>
                                Terminer la session
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistiques en temps réel -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-bar-chart-fill me-2"></i>
                            Réponses en temps réel
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="responseChart" width="400" height="300"></canvas>
                        <div id="noDataMessage" class="text-center text-muted py-4">
                            <i class="bi bi-graph-up" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">Les statistiques apparaîtront ici</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Question en cours -->
            <div class="col-lg-8">
                <div class="card question-card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="bi bi-question-circle-fill me-2"></i>
                            Question en cours
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="noQuestionMessage" class="text-center text-muted py-5">
                            <i class="bi bi-chat-quote" style="font-size: 4rem;"></i>
                            <h4 class="mt-3">Aucune question active</h4>
                            <p>Créez une session et démarrez le quiz pour voir les questions</p>
                        </div>

                        <div id="questionContent" class="d-none">
                            <div class="row mb-3">
                                <div class="col">
                                    <span class="badge bg-secondary" id="questionNumber">Question 1/20</span>
                                    <span class="badge bg-primary ms-2" id="questionType">Quiz</span>
                                </div>
                                <div class="col-auto">
                                    <span class="badge bg-warning text-dark" id="questionTimer">30s</span>
                                </div>
                            </div>

                            <h4 id="questionText" class="mb-4">Question apparaîtra ici</h4>

                            <div id="questionChoices" class="mb-4">
                                <!-- Les choix seront générés dynamiquement -->
                            </div>

                            <div class="row">
                                <div class="col">
                                    <small class="text-muted">
                                        <i class="bi bi-people-fill me-1"></i>
                                        <span id="responseCount">0</span> réponse(s) reçue(s)
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <small class="text-muted">
                                        Points: <strong id="questionPoints">1</strong>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script>
        // Variables globales
        let currentSession = null;
        let currentQuestion = null;
        let responseChart = null;
        let pollInterval = null;

        // Éléments DOM
        const elements = {
            sessionStatus: document.getElementById('sessionStatus'),
            sessionInfo: document.getElementById('sessionInfo'),
            sessionPin: document.getElementById('sessionPin'),
            participantCount: document.getElementById('participantCount'),
            createSessionBtn: document.getElementById('createSessionBtn'),
            startQuizBtn: document.getElementById('startQuizBtn'),
            nextQuestionBtn: document.getElementById('nextQuestionBtn'),
            endSessionBtn: document.getElementById('endSessionBtn'),
            noQuestionMessage: document.getElementById('noQuestionMessage'),
            questionContent: document.getElementById('questionContent'),
            questionNumber: document.getElementById('questionNumber'),
            questionType: document.getElementById('questionType'),
            questionTimer: document.getElementById('questionTimer'),
            questionText: document.getElementById('questionText'),
            questionChoices: document.getElementById('questionChoices'),
            responseCount: document.getElementById('responseCount'),
            questionPoints: document.getElementById('questionPoints'),
            noDataMessage: document.getElementById('noDataMessage')
        };

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            initializeChart();
            setupEventListeners();
            checkExistingSession();
        });

        // Configuration des événements
        function setupEventListeners() {
            elements.createSessionBtn.addEventListener('click', createSession);
            elements.startQuizBtn.addEventListener('click', startQuiz);
            elements.nextQuestionBtn.addEventListener('click', nextQuestion);
            elements.endSessionBtn.addEventListener('click', endSession);
        }

        // Initialiser le graphique Chart.js
        function initializeChart() {
            const ctx = document.getElementById('responseChart').getContext('2d');
            responseChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Réponses',
                        data: [],
                        backgroundColor: [
                            '#981a2c',
                            '#d48e9a',
                            '#f4e9dd',
                            '#c56b77'
                        ],
                        borderColor: [
                            '#981a2c',
                            '#d48e9a',
                            '#f4e9dd',
                            '#c56b77'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // Créer une nouvelle session
        async function createSession() {
            try {
                const response = await fetch('api/create_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();
                
                if (data.ok) {
                    currentSession = data.session;
                    updateSessionUI();
                    startPolling();
                    showAlert('Session créée avec succès ! PIN: ' + data.session.pin, 'success');
                } else {
                    showAlert('Erreur lors de la création de la session', 'danger');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion', 'danger');
            }
        }

        // Démarrer le quiz
        async function startQuiz() {
            if (!currentSession) return;

            try {
                const response = await fetch('api/start_question.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        session_id: currentSession.id
                    })
                });

                const data = await response.json();
                
                if (data.ok) {
                    showAlert('Quiz démarré !', 'success');
                } else {
                    showAlert('Erreur lors du démarrage', 'danger');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion', 'danger');
            }
        }

        // Question suivante
        async function nextQuestion() {
            if (!currentSession) return;

            try {
                const response = await fetch('api/next_question.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        session_id: currentSession.id
                    })
                });

                const data = await response.json();
                
                if (data.ok) {
                    if (data.session.status === 'ended') {
                        showAlert('Quiz terminé !', 'info');
                        currentSession = null;
                        updateSessionUI();
                        stopPolling();
                    }
                } else {
                    showAlert('Erreur lors du passage à la question suivante', 'danger');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion', 'danger');
            }
        }

        // Terminer la session
        async function endSession() {
            if (!currentSession) return;

            if (confirm('Êtes-vous sûre de vouloir terminer cette session ?')) {
                try {
                    const response = await fetch('api/end_session.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            session_id: currentSession.id
                        })
                    });

                    const data = await response.json();
                    
                    if (data.ok) {
                        showAlert('Session terminée', 'info');
                        currentSession = null;
                        updateSessionUI();
                        stopPolling();
                    } else {
                        showAlert('Erreur lors de la fermeture', 'danger');
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    showAlert('Erreur de connexion', 'danger');
                }
            }
        }

        // Vérifier s'il existe une session active
        async function checkExistingSession() {
            // Cette fonction pourrait être implémentée pour récupérer une session existante
            // Pour la démo, on commence sans session
        }

        // Démarrer le polling pour les mises à jour en temps réel
        function startPolling() {
            if (pollInterval) clearInterval(pollInterval);
            
            pollInterval = setInterval(async () => {
                if (currentSession) {
                    await updateSessionData();
                }
            }, 2000);
        }

        // Arrêter le polling
        function stopPolling() {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
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
                        id: currentSession.id
                    })
                });

                const data = await response.json();
                
                if (data.ok) {
                    currentSession = data.session;
                    currentQuestion = data.question;
                    updateSessionUI();
                    updateQuestionUI();
                    updateChart(data.results);
                }
            } catch (error) {
                console.error('Erreur lors de la mise à jour:', error);
            }
        }

        // Mettre à jour l'interface de session
        function updateSessionUI() {
            if (currentSession) {
                // Mettre à jour le statut
                const statusIndicator = elements.sessionStatus.querySelector('.status-indicator');
                const statusText = elements.sessionStatus.querySelector('span:last-child');
                
                if (currentSession.status === 'open') {
                    if (currentSession.current_question_index >= 0) {
                        statusIndicator.className = 'status-indicator status-active';
                        statusText.textContent = 'Quiz en cours';
                    } else {
                        statusIndicator.className = 'status-indicator status-waiting';
                        statusText.textContent = 'En attente de démarrage';
                    }
                } else {
                    statusIndicator.className = 'status-indicator status-ended';
                    statusText.textContent = 'Session terminée';
                }

                // Afficher les informations de session
                elements.sessionInfo.classList.remove('d-none');
                elements.sessionPin.textContent = currentSession.pin;
                elements.participantCount.textContent = currentSession.participant_count || 0;

                // Gérer la visibilité des boutons
                elements.createSessionBtn.classList.add('d-none');
                
                if (currentSession.current_question_index < 0) {
                    elements.startQuizBtn.classList.remove('d-none');
                    elements.nextQuestionBtn.classList.add('d-none');
                } else if (currentSession.status === 'open') {
                    elements.startQuizBtn.classList.add('d-none');
                    elements.nextQuestionBtn.classList.remove('d-none');
                } else {
                    elements.startQuizBtn.classList.add('d-none');
                    elements.nextQuestionBtn.classList.add('d-none');
                }
                
                elements.endSessionBtn.classList.remove('d-none');
            } else {
                // Réinitialiser l'interface
                const statusIndicator = elements.sessionStatus.querySelector('.status-indicator');
                const statusText = elements.sessionStatus.querySelector('span:last-child');
                
                statusIndicator.className = 'status-indicator status-waiting';
                statusText.textContent = 'Aucune session active';
                
                elements.sessionInfo.classList.add('d-none');
                elements.createSessionBtn.classList.remove('d-none');
                elements.startQuizBtn.classList.add('d-none');
                elements.nextQuestionBtn.classList.add('d-none');
                elements.endSessionBtn.classList.add('d-none');
                
                // Masquer la question
                elements.noQuestionMessage.classList.remove('d-none');
                elements.questionContent.classList.add('d-none');
                
                // Réinitialiser le graphique
                responseChart.data.labels = [];
                responseChart.data.datasets[0].data = [];
                responseChart.update();
                elements.noDataMessage.classList.remove('d-none');
            }
        }

        // Mettre à jour l'interface de question
        function updateQuestionUI() {
            if (currentQuestion) {
                elements.noQuestionMessage.classList.add('d-none');
                elements.questionContent.classList.remove('d-none');
                
                // Mettre à jour les informations de question
                elements.questionNumber.textContent = `Question ${currentSession.current_question_index + 1}/20`;
                elements.questionType.textContent = currentQuestion.qtype === 'truefalse' ? 'Vrai/Faux' : 'Quiz';
                elements.questionText.textContent = currentQuestion.qtext;
                elements.questionPoints.textContent = currentQuestion.points;
                
                // Afficher les choix
                const choices = JSON.parse(currentQuestion.choices);
                const correctIndices = JSON.parse(currentQuestion.correct_indices);
                
                elements.questionChoices.innerHTML = '';
                choices.forEach((choice, index) => {
                    const choiceDiv = document.createElement('div');
                    choiceDiv.className = 'choice-item';
                    if (correctIndices.includes(index)) {
                        choiceDiv.classList.add('correct');
                        choiceDiv.innerHTML = `<i class="bi bi-check-circle-fill text-success me-2"></i>${choice}`;
                    } else {
                        choiceDiv.innerHTML = choice;
                    }
                    elements.questionChoices.appendChild(choiceDiv);
                });
            } else {
                elements.noQuestionMessage.classList.remove('d-none');
                elements.questionContent.classList.add('d-none');
            }
        }

        // Mettre à jour le graphique
        function updateChart(results) {
            if (results && results.counts && results.counts.length > 0) {
                const choices = JSON.parse(currentQuestion.choices);
                responseChart.data.labels = choices;
                responseChart.data.datasets[0].data = results.counts;
                responseChart.update();
                
                elements.noDataMessage.classList.add('d-none');
                elements.responseCount.textContent = results.counts.reduce((a, b) => a + b, 0);
            } else {
                responseChart.data.labels = [];
                responseChart.data.datasets[0].data = [];
                responseChart.update();
                
                elements.noDataMessage.classList.remove('d-none');
                elements.responseCount.textContent = '0';
            }
        }

        // Afficher une alerte
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }

        // Gestion de la déconnexion
        if (window.location.search.includes('logout=1')) {
            window.location.href = 'index.php';
        }
    </script>
</body>
</html>

<?php
// Gestion de la déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>