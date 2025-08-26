// État global de l'application
const gameState = {
    currentScreen: 'home',
    currentQuestion: 0,
    participants: new Map(),
    gameStarted: false,
    questionStartTime: null,
    currentAnswers: new Map(),
    leaderboard: [],
    hostId: null,
    isHost: false,
    playerName: '',
    playerScore: 0,
    gameId: Math.random().toString(36).substring(2, 15)
};

// Éléments DOM
const screens = {
    home: document.getElementById('homeScreen'),
    host: document.getElementById('hostScreen'),
    participant: document.getElementById('participantScreen'),
    final: document.getElementById('finalScreen')
};

// Initialisation de l'application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    showScreen('home');
});

function initializeApp() {
    console.log('Mission Cycle Quiz - Initialisation');
}

function setupEventListeners() {
    // Boutons de la page d'accueil
    document.getElementById('hostBtn').addEventListener('click', startAsHost);
    document.getElementById('joinBtn').addEventListener('click', joinAsParticipant);
    
    // Boutons de l'animateur
    document.getElementById('startQuizBtn').addEventListener('click', startQuiz);
    document.getElementById('nextBtn').addEventListener('click', nextQuestion);
    document.getElementById('skipBtn').addEventListener('click', skipToNext);
    document.getElementById('continueBtn').addEventListener('click', continueAfterExplanation);
    document.getElementById('backToHomeBtn').addEventListener('click', backToHome);
    
    // Gestion du temps d'explication
    document.getElementById('explanationTime').addEventListener('change', function(e) {
        const time = parseInt(e.target.value);
        if (time === 5) {
            // Auto-continue après 5 secondes
            setTimeout(() => {
                continueAfterExplanation();
            }, 5000);
        }
    });
    
    // Bouton de redémarrage
    document.getElementById('restartBtn').addEventListener('click', restartQuiz);
    
    // Gestion de la touche Entrée pour rejoindre
    document.getElementById('participantName').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            joinAsParticipant();
        }
    });
}

function showScreen(screenName) {
    // Masquer tous les écrans
    Object.values(screens).forEach(screen => {
        screen.classList.remove('active');
    });
    
    // Afficher l'écran demandé
    if (screens[screenName]) {
        screens[screenName].classList.add('active');
        gameState.currentScreen = screenName;
    }
}

function startAsHost() {
    gameState.isHost = true;
    gameState.hostId = 'host-' + Date.now();
    showScreen('host');
    initializeHostInterface();
}

function joinAsParticipant() {
    const name = document.getElementById('participantName').value.trim();
    if (!name) {
        alert('Veuillez entrer votre nom');
        return;
    }
    
    gameState.playerName = name;
    gameState.isHost = false;
    const participantId = 'participant-' + Date.now();
    
    // Ajouter le participant
    gameState.participants.set(participantId, {
        id: participantId,
        name: name,
        score: 0,
        answers: []
    });
    
    showScreen('participant');
    document.getElementById('playerName').textContent = name;
    document.getElementById('playerScore').textContent = '0';
    
    // Afficher la salle d'attente
    document.getElementById('waitingRoom').style.display = 'flex';
    document.getElementById('participantQuestion').classList.add('d-none');
    document.getElementById('participantResult').classList.add('d-none');
}

function initializeHostInterface() {
    updateParticipantCount();
    document.getElementById('activeQuestion').classList.add('d-none');
    document.getElementById('resultScreen').classList.add('d-none');
}

function updateParticipantCount() {
    document.getElementById('participantCount').textContent = gameState.participants.size;
}

function startQuiz() {
    if (gameState.participants.size === 0 && !gameState.isHost) {
        alert('Aucun participant connecté');
        return;
    }
    
    gameState.gameStarted = true;
    gameState.currentQuestion = 0;
    
    // Réinitialiser les scores
    gameState.participants.forEach(participant => {
        participant.score = 0;
        participant.answers = [];
    });
    
    // Masquer le contrôle de démarrage
    document.getElementById('hostControl').style.display = 'none';
    
    // Afficher la première question
    displayQuestion(gameState.currentQuestion);
    
    // Notifier les participants si c'était réellement connecté
    notifyParticipants('questionStart', quizData.questions[gameState.currentQuestion]);
}

function displayQuestion(questionIndex) {
    const question = quizData.questions[questionIndex];
    if (!question) return;
    
    // Réinitialiser les réponses
    gameState.currentAnswers.clear();
    gameState.questionStartTime = Date.now();
    
    // Afficher la question côté animateur
    document.getElementById('activeQuestion').classList.remove('d-none');
    document.getElementById('resultScreen').classList.add('d-none');
    
    // Mettre à jour le type de question avec couleur
    const typeBadge = document.querySelector('.question-type-badge');
    typeBadge.textContent = question.type === 'true-false' ? 'Vrai/Faux' : 
                            question.type === 'quiz' ? 'Quiz' : 'Puzzle';
    typeBadge.className = `badge question-type-badge badge-${question.type.replace('-', '-')}`;
    
    // Appliquer le thème de couleur
    const card = document.querySelector('.question-card');
    card.className = `card question-card shadow-lg ${question.type.replace('-', '-')}-theme`;
    
    document.getElementById('currentQuestionNum').textContent = questionIndex + 1;
    document.getElementById('questionText').textContent = question.question;
    
    // Afficher les options
    const optionsContainer = document.getElementById('questionOptions');
    optionsContainer.innerHTML = '';
    
    question.options.forEach((option, index) => {
        const col = document.createElement('div');
        col.className = question.options.length <= 2 ? 'col-6' : 'col-md-6';
        
        const optionBtn = document.createElement('div');
        optionBtn.className = 'option-btn';
        optionBtn.textContent = option;
        optionBtn.style.backgroundColor = optionColors[index] + '20';
        optionBtn.style.borderColor = optionColors[index];
        optionBtn.style.color = optionColors[index];
        
        col.appendChild(optionBtn);
        optionsContainer.appendChild(col);
    });

    // Retirer toute image de média de la question précédente
    const existingMedia = document.getElementById('questionMedia');
    if (existingMedia) {
        existingMedia.remove();
    }
    // Si une illustration est définie dans les données de l’explication, l’afficher sous la question
    if (question.explanation && question.explanation.media) {
        const cardBody = document.querySelector('#activeQuestion .question-card .card-body');
        const img = document.createElement('img');
        img.id = 'questionMedia';
        img.src = question.explanation.media;
        img.alt = 'Illustration';
        img.className = 'img-fluid my-3';
        cardBody.insertBefore(img, optionsContainer);
    }
    
    // Démarrer le timer
    startTimer(question.time);
    
    // Réinitialiser les statistiques
    updateLiveStats();
    
    // Status
    document.getElementById('questionStatus').innerHTML = 
        '<i class="fas fa-clock text-warning me-2"></i>En attente des réponses...';
    document.getElementById('nextBtn').disabled = true;
    
    // Afficher la question aux participants
    if (gameState.isHost) {
        displayQuestionForParticipants(question, questionIndex);
    }
}

function displayQuestionForParticipants(question, questionIndex) {
    // Si on est aussi participant (mode test), afficher la question
    if (gameState.playerName) {
        document.getElementById('waitingRoom').style.display = 'none';
        document.getElementById('participantQuestion').classList.remove('d-none');
        document.getElementById('participantResult').classList.add('d-none');
        
        // Mettre à jour les éléments
        document.querySelector('.question-type-badge-participant').textContent = 
            question.type === 'true-false' ? 'Vrai/Faux' : 
            question.type === 'quiz' ? 'Quiz' : 'Puzzle';
        
        document.getElementById('participantQuestionNum').textContent = questionIndex + 1;
        document.getElementById('participantQuestionText').textContent = question.question;
        
        // Afficher les options pour participants
        const participantOptions = document.getElementById('participantOptions');
        participantOptions.innerHTML = '';
        
        question.options.forEach((option, index) => {
            const col = document.createElement('div');
            col.className = question.options.length <= 2 ? 'col-6' : 'col-md-6 col-6';
            
            const optionBtn = document.createElement('button');
            optionBtn.className = 'btn option-btn w-100';
            optionBtn.textContent = option;
            optionBtn.style.backgroundColor = optionColors[index] + '20';
            optionBtn.style.borderColor = optionColors[index];
            optionBtn.style.color = optionColors[index];
            
            optionBtn.addEventListener('click', () => selectAnswer(index, optionBtn));
            
            col.appendChild(optionBtn);
            participantOptions.appendChild(col);
        });
        
        // Démarrer le timer participant
        startParticipantTimer(question.time);
        
        // Réinitialiser le statut
        document.getElementById('participantStatus').innerHTML = 
            '<i class="fas fa-mouse-pointer text-primary me-2"></i>Choisissez votre réponse';
    }
}

function selectAnswer(answerIndex, buttonElement) {
    const participantId = gameState.playerName ? 'participant-self' : null;
    if (!participantId) return;
    
    // Marquer comme sélectionné visuellement
    document.querySelectorAll('#participantOptions .option-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    buttonElement.classList.add('selected');
    
    // Enregistrer la réponse
    const responseTime = Date.now() - gameState.questionStartTime;
    gameState.currentAnswers.set(participantId, {
        answer: answerIndex,
        time: responseTime,
        participantName: gameState.playerName
    });
    
    // Mettre à jour le statut
    document.getElementById('participantStatus').innerHTML = 
        '<i class="fas fa-check text-success me-2"></i>Réponse enregistrée !';
    
    // Mettre à jour les stats côté animateur si on est en mode test
    if (gameState.isHost) {
        updateLiveStats();
    }
}

function startTimer(duration) {
    const timerElement = document.getElementById('timer');
    let timeLeft = duration;
    
    const countdown = setInterval(() => {
        timerElement.textContent = timeLeft;
        
        if (timeLeft <= 10) {
            timerElement.classList.add('warning');
        }
        
        if (timeLeft <= 0) {
            clearInterval(countdown);
            timeUp();
        }
        
        timeLeft--;
    }, 1000);
}

function startParticipantTimer(duration) {
    const timerElement = document.getElementById('participantTimer');
    let timeLeft = duration;
    
    const countdown = setInterval(() => {
        timerElement.textContent = timeLeft;
        
        if (timeLeft <= 10) {
            timerElement.classList.add('warning');
        }
        
        if (timeLeft <= 0) {
            clearInterval(countdown);
            // Temps écoulé pour le participant
            if (!gameState.currentAnswers.has('participant-self')) {
                document.getElementById('participantStatus').innerHTML = 
                    '<i class="fas fa-clock text-warning me-2"></i>Temps écoulé !';
            }
        }
        
        timeLeft--;
    }, 1000);
}

function timeUp() {
    // Désactiver les interactions
    document.querySelectorAll('.option-btn').forEach(btn => {
        btn.style.pointerEvents = 'none';
    });
    
    // Activer le bouton suivant
    document.getElementById('nextBtn').disabled = false;
    document.getElementById('questionStatus').innerHTML = 
        '<i class="fas fa-hourglass-end text-info me-2"></i>Temps écoulé !';
    
    // Afficher les résultats
    showQuestionResults();
}

function updateLiveStats() {
    const statsContainer = document.getElementById('liveStats');
    const question = quizData.questions[gameState.currentQuestion];
    
    if (!question) return;
    
    const totalParticipants = Math.max(gameState.participants.size, 1);
    const responses = gameState.currentAnswers.size;
    
    // Calculer les pourcentages par réponse
    const answerCounts = new Array(question.options.length).fill(0);
    gameState.currentAnswers.forEach(answer => {
        answerCounts[answer.answer]++;
    });
    
    statsContainer.innerHTML = '';
    
    question.options.forEach((option, index) => {
        const count = answerCounts[index];
        const percentage = totalParticipants > 0 ? (count / totalParticipants * 100) : 0;
        
        const statBar = document.createElement('div');
        statBar.className = 'stat-bar mb-2';
        
        const statFill = document.createElement('div');
        statFill.className = `stat-fill option-${String.fromCharCode(97 + index)}`;
        statFill.style.width = percentage + '%';
        statFill.textContent = `${option.substring(0, 15)}${option.length > 15 ? '...' : ''} (${count})`;
        
        statBar.appendChild(statFill);
        statsContainer.appendChild(statBar);
    });
    
    // Mettre à jour le taux de réponse
    const responseRate = totalParticipants > 0 ? Math.round(responses / totalParticipants * 100) : 0;
    document.getElementById('responseRate').textContent = responseRate + '%';
}

function showQuestionResults() {
    const question = quizData.questions[gameState.currentQuestion];
    
    // Marquer les bonnes réponses côté animateur
    const optionBtns = document.querySelectorAll('#questionOptions .option-btn');
    optionBtns.forEach((btn, index) => {
        if (index === question.correct) {
            btn.classList.add('correct');
        }
    });
    
    // Calculer les scores et mettre à jour le classement
    calculateScores(question);
    updateLeaderboard();
}

function calculateScores(question) {
    gameState.currentAnswers.forEach((answer, participantId) => {
        const participant = gameState.participants.get(participantId) || {
            id: participantId,
            name: answer.participantName || 'Participant',
            score: gameState.playerScore || 0,
            answers: []
        };
        
        let points = 0;
        if (answer.answer === question.correct) {
            // Calcul des points basé sur le temps de réponse
            const maxTime = question.time * 1000; // en millisecondes
            const timeFactor = Math.max(0, (maxTime - answer.time) / maxTime);
            points = Math.round(question.points * (0.5 + 0.5 * timeFactor));
        }
        
        participant.score += points;
        participant.answers.push({
            questionId: question.id,
            answer: answer.answer,
            correct: answer.answer === question.correct,
            points: points,
            time: answer.time
        });
        
        // Mettre à jour le score du joueur si c'est nous
        if (participantId === 'participant-self') {
            gameState.playerScore = participant.score;
            document.getElementById('playerScore').textContent = participant.score;
        }
        
        gameState.participants.set(participantId, participant);
    });
}

function updateLeaderboard() {
    const leaderboard = Array.from(gameState.participants.values())
        .sort((a, b) => b.score - a.score)
        .slice(0, 5); // Top 5
    
    const leaderboardContainer = document.getElementById('leaderboard');
    leaderboardContainer.innerHTML = '';
    
    leaderboard.forEach((participant, index) => {
        const item = document.createElement('div');
        item.className = `leaderboard-item rank-${index < 3 ? index + 1 : 'other'}`;
        
        const rank = index + 1;
        const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : `${rank}.`;
        
        item.innerHTML = `
            <span class="me-2">${medal}</span>
            <span class="flex-grow-1">${participant.name}</span>
            <span class="badge bg-primary">${participant.score}</span>
        `;
        
        leaderboardContainer.appendChild(item);
    });
}

function nextQuestion() {
    const question = quizData.questions[gameState.currentQuestion];
    showResults(question);
}

function showResults(question) {
    // Masquer la question active
    document.getElementById('activeQuestion').classList.add('d-none');
    document.getElementById('resultScreen').classList.remove('d-none');
    
    // Calculer le taux de bonnes réponses
    const totalResponses = gameState.currentAnswers.size;
    const correctResponses = Array.from(gameState.currentAnswers.values())
        .filter(answer => answer.answer === question.correct).length;
    
    const correctRate = totalResponses > 0 ? (correctResponses / totalResponses) : 0;
    
    // Afficher la confirmation
    document.getElementById('resultTitle').textContent = question.confirmation.title;
    document.getElementById('resultExplanation').textContent = question.confirmation.text;
    
    // Statistiques
    const statsContainer = document.getElementById('resultStats');
    statsContainer.innerHTML = `
        <div class="col-md-4">
            <h4 class="text-success">${correctResponses}</h4>
            <small class="text-muted">Bonnes réponses</small>
        </div>
        <div class="col-md-4">
            <h4 class="text-danger">${totalResponses - correctResponses}</h4>
            <small class="text-muted">Mauvaises réponses</small>
        </div>
        <div class="col-md-4">
            <h4 class="text-info">${Math.round(correctRate * 100)}%</h4>
            <small class="text-muted">Taux de réussite</small>
        </div>
    `;
    
    // Ajuster le temps d'explication selon le taux de réussite
    const explanationTime = document.getElementById('explanationTime');
    if (correctRate >= 0.8) {
        explanationTime.value = '5';
        // Auto-continue après 5 secondes si ≥80% de bonnes réponses
        setTimeout(() => {
            if (gameState.currentScreen === 'host' && !document.getElementById('resultScreen').classList.contains('d-none')) {
                continueAfterExplanation();
            }
        }, 5000);
    } else {
        explanationTime.value = '25';
    }
    
    // Afficher le résultat au participant
    showParticipantResult(question, correctRate);
}

function showParticipantResult(question, correctRate) {
    if (!gameState.playerName) return;
    
    document.getElementById('participantQuestion').classList.add('d-none');
    document.getElementById('participantResult').classList.remove('d-none');
    
    const participantAnswer = gameState.currentAnswers.get('participant-self');
    const isCorrect = participantAnswer && participantAnswer.answer === question.correct;
    
    // Icône et message
    const resultIcon = document.getElementById('participantResultIcon');
    const resultText = document.getElementById('participantResultText');
    
    if (isCorrect) {
        resultIcon.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
        resultText.textContent = 'Bonne réponse !';
        resultText.className = 'mb-3 text-success';
    } else {
        resultIcon.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
        resultText.textContent = 'Réponse incorrecte';
        resultText.className = 'mb-3 text-danger';
    }
    
    // Détails
    const correctAnswer = question.options[question.correct];
    document.getElementById('participantResultDetails').innerHTML = `
        <p class="mb-2"><strong>Bonne réponse :</strong> ${correctAnswer}</p>
        <p class="text-muted">${question.confirmation.text}</p>
    `;
    
    // Points gagnés
    const points = participantAnswer ? 
        gameState.participants.get('participant-self')?.answers.slice(-1)[0]?.points || 0 : 0;
    document.getElementById('earnedPoints').textContent = points;
}

function continueAfterExplanation() {
    // Passer à la question suivante ou terminer
    if (gameState.currentQuestion < quizData.questions.length - 1) {
        gameState.currentQuestion++;
        displayQuestion(gameState.currentQuestion);
    } else {
        // Quiz terminé
        endQuiz();
    }
}

function skipToNext() {
    continueAfterExplanation();
}

function endQuiz() {
    // Calculer le classement final
    const finalLeaderboard = Array.from(gameState.participants.values())
        .sort((a, b) => b.score - a.score);
    
    gameState.leaderboard = finalLeaderboard;
    
    // Afficher l'écran final
    showScreen('final');
    displayFinalResults();
}

function displayFinalResults() {
    const leaderboardContainer = document.getElementById('finalLeaderboard');
    leaderboardContainer.innerHTML = '';
    
    gameState.leaderboard.forEach((participant, index) => {
        const item = document.createElement('div');
        item.className = `leaderboard-item rank-${index < 3 ? index + 1 : 'other'} mb-3`;
        
        const rank = index + 1;
        const medal = rank === 1 ? '🏆' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : `${rank}.`;
        
        item.innerHTML = `
            <div class="d-flex align-items-center">
                <span class="me-3 fs-4">${medal}</span>
                <div class="flex-grow-1">
                    <div class="fw-bold">${participant.name}</div>
                    <small class="text-muted">${participant.answers.filter(a => a.correct).length}/${quizData.questions.length} bonnes réponses</small>
                </div>
                <div class="text-end">
                    <div class="badge bg-primary fs-6">${participant.score} pts</div>
                </div>
            </div>
        `;
        
        leaderboardContainer.appendChild(item);
    });
}

function restartQuiz() {
    // Réinitialiser l'état
    gameState.currentQuestion = 0;
    gameState.gameStarted = false;
    gameState.currentAnswers.clear();
    gameState.participants.clear();
    gameState.playerScore = 0;
    gameState.playerName = '';
    
    // Retourner à l'accueil
    showScreen('home');
    
    // Réinitialiser les champs
    document.getElementById('participantName').value = '';
    document.getElementById('hostControl').style.display = 'block';
}

function backToHome() {
    restartQuiz();
}

// Fonctions utilitaires pour la simulation
function notifyParticipants(event, data) {
    // Dans une vraie application, cela enverrait des données via WebSocket
    console.log('Notification participants:', event, data);
}

// Gestion des erreurs
window.addEventListener('error', function(e) {
    console.error('Erreur de l\'application:', e.error);
});

// Gestion du redimensionnement
window.addEventListener('resize', function() {
    // Ajustements responsive si nécessaire
});

console.log('Mission Cycle Quiz - Script chargé');