<?php
require_once 'config.php';

/**
 * Connexion à la base de données
 */
function getDatabase() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            if (USE_SQLITE) {
                // Crée le dossier data si nécessaire
                $dataDir = dirname(SQLITE_FILE);
                if (!is_dir($dataDir)) {
                    mkdir($dataDir, 0755, true);
                }
                
                $dsn = 'sqlite:' . SQLITE_FILE;
                $pdo = new PDO($dsn);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                // MySQL
                $dsn = MYSQL_DSN;
                $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            
            // Initialise la base si nécessaire
            initializeDatabase($pdo);
            
        } catch (PDOException $e) {
            die('Erreur de base de données: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Initialise la structure de la base
 */
function initializeDatabase($pdo) {
    // Table surveys
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS surveys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            theme_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Table questions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            survey_id INTEGER NOT NULL,
            qtext TEXT NOT NULL,
            qtype VARCHAR(20) NOT NULL,
            choices TEXT,
            correct_indices TEXT,
            confirm_text TEXT,
            explain_text TEXT,
            explain_media TEXT,
            seconds INTEGER DEFAULT 30,
            points INTEGER DEFAULT 10,
            media TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
        )
    ");
    
    // Table sessions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            survey_id INTEGER NOT NULL,
            pin VARCHAR(6) UNIQUE NOT NULL,
            is_active INTEGER DEFAULT 1,
            current_question_index INTEGER DEFAULT 0,
            reveal_state INTEGER DEFAULT 0,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ended_at DATETIME,
            FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
        )
    ");
    
    // Table participants
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS participants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id INTEGER NOT NULL,
            name VARCHAR(100) NOT NULL,
            score INTEGER DEFAULT 0,
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
            UNIQUE(session_id, name)
        )
    ");
    
    // Table responses
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id INTEGER NOT NULL,
            question_id INTEGER NOT NULL,
            user_name VARCHAR(100) NOT NULL,
            answer_indices TEXT,
            is_correct INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
        )
    ");
    
    // Données de démonstration
    seedDemoData($pdo);
}

/**
 * Crée des données de démonstration
 */
function seedDemoData($pdo) {
    // Vérifie s'il y a déjà des données
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM surveys");
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        return; // Données déjà présentes
    }
    
    // Crée un sondage de démonstration
    $stmt = $pdo->prepare("INSERT INTO surveys (title, theme_json) VALUES (?, ?)");
    $theme = json_encode([
        'primary' => '#FFBF69',
        'accent' => '#2EC4B6',
        'background' => '#FFF9F2'
    ]);
    $stmt->execute(['Sondage de Démonstration WAAWH 🎉', $theme]);
    $surveyId = $pdo->lastInsertId();
    
    // Questions de démonstration
    $questions = [
        [
            'qtext' => 'Quelle est la capitale de la France ? 🇫🇷',
            'qtype' => 'quiz',
            'choices' => json_encode(['Paris', 'Lyon', 'Marseille', 'Toulouse']),
            'correct_indices' => json_encode([0]),
            'confirm_text' => 'Bravo ! Paris est bien la capitale ! 🎊',
            'explain_text' => 'Paris est la capitale et plus grande ville de France.',
            'seconds' => 20,
            'points' => 10
        ],
        [
            'qtext' => 'La Terre est-elle ronde ? 🌍',
            'qtype' => 'truefalse',
            'choices' => json_encode(['Vrai', 'Faux']),
            'correct_indices' => json_encode([0]),
            'confirm_text' => 'Exact ! La Terre est (approximativement) ronde ! 🌎',
            'explain_text' => 'La Terre est un sphéroïde aplati aux pôles.',
            'seconds' => 15,
            'points' => 5
        ],
        [
            'qtext' => 'Combien font 7 x 8 ? 🧮',
            'qtype' => 'short',
            'choices' => json_encode(['56']),
            'correct_indices' => json_encode([0]),
            'confirm_text' => 'Parfait ! 7 x 8 = 56 ! 🎯',
            'explain_text' => 'Table de multiplication : 7 × 8 = 56',
            'seconds' => 25,
            'points' => 15
        ],
        [
            'qtext' => 'Comment évaluez-vous cette application ? ⭐',
            'qtype' => 'rating',
            'choices' => json_encode(['max:5']),
            'correct_indices' => json_encode([]),
            'confirm_text' => 'Merci pour votre évaluation ! 😊',
            'explain_text' => 'Votre avis compte beaucoup pour nous !',
            'seconds' => 30,
            'points' => 5
        ],
        [
            'qtext' => 'Partagez votre impression sur cette démonstration 💭',
            'qtype' => 'long',
            'choices' => json_encode([]),
            'correct_indices' => json_encode([]),
            'confirm_text' => 'Merci pour votre commentaire ! 🙏',
            'explain_text' => 'Vos retours nous aident à améliorer l\'expérience.',
            'seconds' => 60,
            'points' => 0
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO questions (survey_id, qtext, qtype, choices, correct_indices, 
                             confirm_text, explain_text, seconds, points) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($questions as $q) {
        $stmt->execute([
            $surveyId, $q['qtext'], $q['qtype'], $q['choices'], 
            $q['correct_indices'], $q['confirm_text'], $q['explain_text'],
            $q['seconds'], $q['points']
        ]);
    }
}