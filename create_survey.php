<?php
require_once 'config.php';
require_once 'db.php';

// Vérifier l'authentification de l'animateur
requireHostLogin();

// Chemin vers le fichier de stockage des sondages
$surveysFile = __DIR__ . '/surveys.json';

// Créer le fichier s'il n'existe pas
if (!file_exists($surveysFile)) {
    file_put_contents($surveysFile, json_encode([]));
}

// Gestion de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $primaryColor = $_POST['primary_color'] ?? '#009fe3';
    $accentColor = $_POST['accent_color'] ?? '#e15f99';
    $highlightColor = $_POST['highlight_color'] ?? '#ffd400';

    // Télécharger l'image de fond si fournie
    $backgroundPath = '';
    if (!empty($_FILES['background']['name']) && $_FILES['background']['error'] === UPLOAD_ERR_OK) {
        $uploadsDir = __DIR__ . '/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        $ext = pathinfo($_FILES['background']['name'], PATHINFO_EXTENSION);
        $bgName = uniqid('bg_') . '.' . $ext;
        $target = $uploadsDir . '/' . $bgName;
        if (move_uploaded_file($_FILES['background']['tmp_name'], $target)) {
            $backgroundPath = 'uploads/' . $bgName;
        }
    }

    // Récupérer les questions du formulaire (JSON string)
    $questionsJson = $_POST['questions_json'] ?? '[]';
    $questions = json_decode($questionsJson, true);
    if (!is_array($questions)) {
        $questions = [];
    }

    // Charger les sondages existants
    $surveys = json_decode(file_get_contents($surveysFile), true);
    if (!is_array($surveys)) {
        $surveys = [];
    }

    // Créer un nouvel identifiant
    $newId = 1;
    if (!empty($surveys)) {
        $ids = array_column($surveys, 'id');
        $newId = max($ids) + 1;
    }

    // Construire l'objet sondage
    $survey = [
        'id' => $newId,
        'title' => $title,
        'theme' => [
            'primary' => $primaryColor,
            'accent' => $accentColor,
            'highlight' => $highlightColor,
            'background' => $backgroundPath
        ],
        'questions' => $questions
    ];

    $surveys[] = $survey;
    file_put_contents($surveysFile, json_encode($surveys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    header('Location: host_dashboard.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Créer un sondage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #009fe3;
            --secondary-color: #fff9e6;
            --accent-color: #e15f99;
            --highlight-color: #ffd400;
        }
        body {
            background-color: var(--secondary-color);
            min-height: 100vh;
        }
        .navbar {
            background-color: var(--primary-color) !important;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background-color: rgba(255,255,255,0.95);
        }
        .form-control:focus {
            box-shadow: none;
            border-color: var(--primary-color);
        }
        .question-card {
            border: 1px solid var(--primary-color);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="host_dashboard.php">
                <svg width="50" height="30" viewBox="0 0 200 120" xmlns="http://www.w3.org/2000/svg" class="me-2">
                    <path d="M20 40 Q100 -10 180 40" fill="var(--highlight-color)" />
                    <path d="M20 80 Q100 130 180 80" fill="var(--highlight-color)" />
                    <text x="40" y="78" font-size="60" font-family="Arial, sans-serif" font-weight="700">
                        <tspan fill="var(--primary-color)">W</tspan>
                        <tspan fill="var(--accent-color)">AA</tspan>
                        <tspan fill="var(--primary-color)">W</tspan>
                        <tspan fill="var(--highlight-color)">H</tspan>
                    </text>
                </svg>
                Créer un sondage
            </a>
        </div>
    </nav>

    <div class="container my-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card p-4">
                    <h2 class="mb-4">Nouveau sondage</h2>
                    <form method="post" enctype="multipart/form-data" id="surveyForm">
                        <div class="mb-3">
                            <label class="form-label">Titre du sondage</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Couleur principale</label>
                                <input type="color" name="primary_color" class="form-control form-control-color" value="#009fe3">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Couleur secondaire</label>
                                <input type="color" name="accent_color" class="form-control form-control-color" value="#e15f99">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Couleur de surlignage</label>
                                <input type="color" name="highlight_color" class="form-control form-control-color" value="#ffd400">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image de fond (optionnel)</label>
                            <input type="file" name="background" accept="image/*" class="form-control">
                        </div>
                        <hr>
                        <h4>Questions</h4>
                        <div id="questionsContainer"></div>
                        <button type="button" class="btn btn-outline-primary" id="addQuestionBtn">
                            <i class="bi bi-plus-circle me-1"></i> Ajouter une question
                        </button>
                        <input type="hidden" name="questions_json" id="questionsJson">
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-success">Enregistrer le sondage</button>
                            <a href="host_dashboard.php" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let questions = [];
        document.getElementById('addQuestionBtn').addEventListener('click', () => {
            addQuestion();
        });
        function addQuestion() {
            const index = questions.length;
            const container = document.getElementById('questionsContainer');
            const card = document.createElement('div');
            card.className = 'question-card';
            card.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Intitulé de la question</label>
                    <input type="text" class="form-control question-text" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Type de question</label>
                    <select class="form-select question-type">
                        <option value="single">Choix unique</option>
                        <option value="multiple">Choix multiple</option>
                        <option value="truefalse">Vrai / Faux</option>
                        <option value="text">Réponse libre</option>
                    </select>
                </div>
                <div class="choices-container"></div>
                <div class="mb-3 explanation-container">
                    <label class="form-label">Explication / Justification</label>
                    <textarea class="form-control explanation-text" rows="2"></textarea>
                </div>
                <button type="button" class="btn btn-sm btn-danger remove-question-btn">Supprimer</button>
                <hr>
            `;
            container.appendChild(card);
            questions.push({});
            initQuestionCard(card, index);
        }
        function initQuestionCard(card, index) {
            const typeSelect = card.querySelector('.question-type');
            const choicesContainer = card.querySelector('.choices-container');
            const removeBtn = card.querySelector('.remove-question-btn');
            typeSelect.addEventListener('change', () => {
                renderChoices();
            });
            removeBtn.addEventListener('click', () => {
                card.remove();
                questions[index] = null;
            });
            function renderChoices() {
                choicesContainer.innerHTML = '';
                const type = typeSelect.value;
                if (type === 'single' || type === 'multiple') {
                    const list = document.createElement('div');
                    list.className = 'mb-3';
                    list.innerHTML = `
                        <label class="form-label">Options</label>
                        <div class="option-items"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm add-option-btn">
                            <i class="bi bi-plus me-1"></i> Ajouter une option
                        </button>
                    `;
                    choicesContainer.appendChild(list);
                    const optionItems = list.querySelector('.option-items');
                    const addOptionBtn = list.querySelector('.add-option-btn');
                    addOptionBtn.addEventListener('click', () => {
                        addOption(optionItems, type);
                    });
                    // Ajouter une option initiale
                    addOption(optionItems, type);
                    addOption(optionItems, type);
                } else if (type === 'truefalse') {
                    // Ajouter options vrai / faux
                    const trueOption = createOptionInput('Vrai', type);
                    const falseOption = createOptionInput('Faux', type);
                    choicesContainer.appendChild(trueOption);
                    choicesContainer.appendChild(falseOption);
                }
            }
            function addOption(container, type) {
                const optionDiv = document.createElement('div');
                optionDiv.className = 'input-group mb-2';
                optionDiv.innerHTML = `
                    <span class="input-group-text">
                        <input type="${type === 'multiple' ? 'checkbox' : 'radio'}" name="correct_${index}" class="correct-option">
                    </span>
                    <input type="text" class="form-control option-text" placeholder="Option">
                    <button type="button" class="btn btn-outline-danger remove-option-btn"><i class="bi bi-x"></i></button>
                `;
                container.appendChild(optionDiv);
                optionDiv.querySelector('.remove-option-btn').addEventListener('click', () => {
                    optionDiv.remove();
                });
            }
            function createOptionInput(label, type) {
                const div = document.createElement('div');
                div.className = 'form-check form-check-inline mb-3';
                div.innerHTML = `
                    <input class="form-check-input correct-option" type="${type === 'multiple' ? 'checkbox' : 'radio'}" name="correct_${index}" id="opt_${index}_${label}" value="${label}">
                    <label class="form-check-label" for="opt_${index}_${label}">${label}</label>
                `;
                return div;
            }
            renderChoices();
        }
        // Avant de soumettre, rassembler les questions en JSON
        document.getElementById('surveyForm').addEventListener('submit', function(e) {
            const formQuestions = [];
            const cards = document.querySelectorAll('.question-card');
            cards.forEach((card, idx) => {
                const qText = card.querySelector('.question-text').value.trim();
                const qType = card.querySelector('.question-type').value;
                const explanation = card.querySelector('.explanation-text').value.trim();
                const options = [];
                const correctIndices = [];
                if (qType === 'single' || qType === 'multiple') {
                    const optionDivs = card.querySelectorAll('.option-text');
                    optionDivs.forEach((input, i) => {
                        options.push(input.value);
                    });
                    const correctInputs = card.querySelectorAll('.correct-option');
                    correctInputs.forEach((input, i) => {
                        if (input.checked) {
                            correctIndices.push(i);
                        }
                    });
                } else if (qType === 'truefalse') {
                    options.push('Vrai');
                    options.push('Faux');
                    const correctInputs = card.querySelectorAll('.correct-option');
                    correctInputs.forEach((input, i) => {
                        if (input.checked) correctIndices.push(i);
                    });
                }
                formQuestions.push({
                    type: qType,
                    question: qText,
                    options: options,
                    correct: correctIndices,
                    explanation: explanation
                });
            });
            document.getElementById('questionsJson').value = JSON.stringify(formQuestions);
        });
    </script>
</body>
</html>