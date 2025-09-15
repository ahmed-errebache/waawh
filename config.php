<?php
// Global configuration for the WAAWH survey application.
//
// This file contains database connection settings, default
// credentials for the development environment, CSRF helpers and
// common utility functions. When deploying to production make sure to
// update the database constants and replace the development password
// with a securely hashed value.

// -----------------------------------------------------------------------------
// Database configuration
//
// By default the application uses an embedded SQLite database stored in
// data/database.sqlite. To switch to MySQL simply set USE_SQLITE to false
// and adjust the DSN, username and password constants below. All SQL queries
// are written to be compatible with both SQLite and MySQL.

define('USE_SQLITE', true);
// When USE_SQLITE is true the DSN will be ignored and the application will
// instead connect to a file in the data folder. When set to false the DSN
// should point at your MySQL server (e.g. 'mysql:host=localhost;dbname=waawh;charset=utf8mb4').
define('MYSQL_DSN', 'mysql:host=localhost;dbname=waawh;charset=utf8mb4');
define('MYSQL_USER', 'root');
define('MYSQL_PASS', '');

// Location of the SQLite database file relative to this file.
define('SQLITE_FILE', __DIR__ . '/data/database.sqlite');

// Ensure the data and uploads directories exist with write permissions.
$directories = [__DIR__ . '/data', __DIR__ . '/uploads'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    // Ensure directory is writable
    if (!is_writable($dir)) {
        chmod($dir, 0755);
    }
}

// -----------------------------------------------------------------------------
// Development credentials
//
// In development the application uses a default administrator account. The
// credentials are defined below. When moving to production replace the
// plaintext password with a hashed version. See the note in the README
// for details on generating a password hash.

define('DEV_ADMIN_USERNAME', 'ahmed.errebache@gmail.com');
define('DEV_ADMIN_PASSWORD', 'P@ssw0rd123!');

// -----------------------------------------------------------------------------
// Utility functions and helpers
//
// connect_db() – create a PDO connection either to SQLite or MySQL.
// csrf_token() – generate a CSRF token and return HTML hidden input.
// check_csrf() – validate CSRF token on POST requests.
// current_user() – return the logged in user record or null.
// require_login($role) – enforce login and optionally role (admin|host).
// esc($value) – shortcut for htmlspecialchars() with UTF-8 encoding.

function connect_db(): PDO
{
    static $pdo;
    if ($pdo) {
        return $pdo;
    }
    
    try {
        if (USE_SQLITE) {
            $dsn = 'sqlite:' . SQLITE_FILE;
            
            // Ensure SQLite file directory exists and is writable
            $dir = dirname(SQLITE_FILE);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (!is_writable($dir)) {
                chmod($dir, 0755);
            }
            
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Enable foreign keys for SQLite
            $pdo->exec('PRAGMA foreign_keys = ON');
            // Optimize SQLite for shared hosting
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('PRAGMA cache_size = 10000');
            $pdo->exec('PRAGMA temp_store = MEMORY');
        } else {
            $dsn = MYSQL_DSN;
            $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
    } catch (PDOException $e) {
        // Better error handling for deployment debugging
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection failed. Please check your configuration. Error: " . $e->getMessage());
    }
    
    return $pdo;
}

// Create tables if they don't exist. This is run on every request to ensure
// the database schema exists. In a production environment you might prefer
// running migrations manually instead of automatically.
function ensure_schema()
{
    try {
        $db = connect_db();
        
        // Users: administrators and hosts/animators.
        // Initial table definition. Additional columns such as email and is_active
        // are added conditionally below to avoid breaking existing installations.
        $db->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN (\'admin\', \'host\')),
            company_name TEXT,
            logo TEXT,
            primary_color TEXT,
            accent_color TEXT,
            background_color TEXT,
            background_image TEXT,
            email TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        // SQLite does not support ALTER TABLE IF NOT EXISTS, so we check for missing
        // columns manually and add them as needed. This allows older databases to
        // upgrade gracefully when new fields are introduced.
        if (USE_SQLITE) {
            $cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_column($cols, 'name');
            // Ensure the background_image column exists
            if (!in_array('background_image', $colNames)) {
                $db->exec('ALTER TABLE users ADD COLUMN background_image TEXT');
            }
            // Add email column if it does not exist
            if (!in_array('email', $colNames)) {
                $db->exec('ALTER TABLE users ADD COLUMN email TEXT');
            }
            // Add is_active column if it does not exist. Use INTEGER type with default 1
            if (!in_array('is_active', $colNames)) {
                $db->exec('ALTER TABLE users ADD COLUMN is_active INTEGER DEFAULT 1');
            }
        }
    // Surveys table with owner_id referring to a host user (NULL if created by admin only).
    $db->exec('CREATE TABLE IF NOT EXISTS surveys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        owner_id INTEGER,
        title TEXT NOT NULL,
        theme_json TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
    )');

    // Assignment table linking surveys to one or more hosts. A survey may
    // optionally be assigned to multiple hosts; this table enforces a
    // unique pairing of survey_id and host_id.
    $db->exec('CREATE TABLE IF NOT EXISTS survey_hosts (
        survey_id INTEGER NOT NULL,
        host_id INTEGER NOT NULL,
        PRIMARY KEY (survey_id, host_id),
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
        FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
    )');
    // Questions linked to surveys.
    $db->exec('CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_id INTEGER NOT NULL,
        qtype TEXT NOT NULL,
        qtext TEXT,
        qmedia TEXT,
        choices TEXT,
        correct_indices TEXT,
        confirm_text TEXT,
        wrong_text TEXT,
        explain_text TEXT,
        explain_media TEXT,
        seconds INTEGER,
        points INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
    )');
    // Sessions table for running a survey.
    $db->exec('CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_id INTEGER NOT NULL,
        host_id INTEGER NOT NULL,
        pin INTEGER UNIQUE NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        current_question_index INTEGER DEFAULT 0,
        reveal_state INTEGER DEFAULT 0,
        started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
        FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
    )');
    // Participants join sessions.
    $db->exec('CREATE TABLE IF NOT EXISTS participants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        score INTEGER DEFAULT 0,
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (session_id, name),
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
    )');
    // Responses per question per user.
    $db->exec('CREATE TABLE IF NOT EXISTS responses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        user_name TEXT NOT NULL,
        answer_indices TEXT,
        is_correct INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    )');
    // Seed default admin user if not exists.
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $stmt->execute([DEV_ADMIN_USERNAME]);
    if ($stmt->fetchColumn() == 0) {
        // In development we store the password in plaintext for convenience.
        // In production call password_hash() and update DEV_ADMIN_PASSWORD constant accordingly.
        $db->prepare('INSERT INTO users (username, password, role) VALUES (?,?,?)')
            ->execute([DEV_ADMIN_USERNAME, DEV_ADMIN_PASSWORD, 'admin']);
    }
    // Seed demonstration host (Nadia Zmirli) with company WAAWH if not exists
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $stmt->execute(['Nadia']);
    if ($stmt->fetchColumn() == 0) {
        // Hash the password 'Nadia123!' for demonstration
        $passwordHash = password_hash('Nadia123!', PASSWORD_DEFAULT);
        $db->prepare('INSERT INTO users (username, password, role, company_name, primary_color, accent_color, background_color, background_image) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([
                'Nadia',
                $passwordHash,
                'host',
                'WAAWH',
                '#FFBF69',
                '#2EC4B6',
                '#FFF9F2',
                null
            ]);
    }

    // Seed two sample surveys with questions if none exist yet
    $stmt = $db->query('SELECT COUNT(*) FROM surveys');
    if ($stmt->fetchColumn() == 0) {
        // Retrieve host id
        $stmt2 = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt2->execute(['Nadia']);
        $hostId = $stmt2->fetchColumn();
        // Define common theme
        $defaultTheme = json_encode(['primary' => '#FFBF69', 'accent' => '#2EC4B6', 'background' => '#FFF9F2']);
        // ------------------ Survey 1: Sensibilisation aux règles ------------------
        $db->prepare('INSERT INTO surveys (owner_id, title, theme_json) VALUES (?,?,?)')
            ->execute([$hostId, 'Sensibilisation aux règles', $defaultTheme]);
        $survey1Id = $db->lastInsertId();
        $survey1Questions = [
            // Sujet tabou
            [
                'qtype' => 'long',
                'qtext' => 'Considérez-vous les règles comme un sujet tabou ?',
                // Use specific illustrative image for this question
                'qmedia' => 'uploads/q1_taboo.png',
                'choices' => [],
                'correct' => [],
                'confirm' => 'Merci de votre participation !',
                'wrong' => '',
                'explain' => 'Une personne sur deux considère encore les règles comme un sujet tabou (Baromètre Règles Élémentaires 2022).',
                'explain_media' => 'uploads/q1_taboo.png',
                'seconds' => null,
                'points' => 0,
            ],
            // Stress pendant les règles
            [
                'qtype' => 'long',
                'qtext' => 'Au travail, à l’école, en stage ou en sport, qu’est-ce qui vous stresse le plus pendant vos règles ?',
                'qmedia' => 'uploads/q2_stress.png',
                'choices' => [],
                'correct' => [],
                'confirm' => 'Merci de votre participation !',
                'wrong' => '',
                'explain' => 'Prévoir des protections de rechange et en parler librement peut réduire ce stress.',
                'explain_media' => 'uploads/q2_stress.png',
                'seconds' => null,
                'points' => 0,
            ],
            // Absences liées aux règles
            [
                'qtype' => 'long',
                'qtext' => 'Avez-vous déjà manqué l’école ou le travail à cause de vos règles ?',
                'qmedia' => 'uploads/q3_absence.png',
                'choices' => [],
                'correct' => [],
                'confirm' => 'Merci pour votre réponse !',
                'wrong' => '',
                'explain' => '44 % des femmes ont manqué le travail et 36 % des filles ont manqué l’école pour cette raison.',
                'explain_media' => 'uploads/q3_absence.png',
                'seconds' => null,
                'points' => 0,
            ],
            // Durée des menstruations dans la vie
            [
                'qtype' => 'quiz',
                'qtext' => 'Combien de jours une femme est-elle menstruée en moyenne au cours de sa vie ?',
                'qmedia' => 'uploads/q4_days.png',
                'choices' => ['1200','2000','2400'],
                'correct' => [2],
                'confirm' => 'Correct !',
                'wrong' => 'La bonne réponse est 2 400 jours.',
                'explain' => 'En moyenne, une femme a ses règles entre 12 et 51 ans, pendant environ 5 jours par mois, soit 2 400 jours au total.',
                'explain_media' => 'uploads/q4_days.png',
                'seconds' => 30,
                'points' => 10,
            ],
            // Coût des protections périodiques
            [
                'qtype' => 'quiz',
                'qtext' => 'À combien revient l’achat de protections périodiques jetables pour une femme au cours de sa vie ?',
                'qmedia' => 'uploads/q5_cost.png',
                'choices' => ['1800 €','2800 €','3800 €'],
                'correct' => [2],
                'confirm' => 'Exact !',
                'wrong' => 'La bonne réponse est 3 800 €.',
                'explain' => 'En incluant culottes de rechange, bouillottes, détachants et consultations gynécologiques, le coût peut atteindre environ 5 800 € sur 38 ans.',
                'explain_media' => 'uploads/q5_cost.png',
                'seconds' => 30,
                'points' => 10,
            ],
            // Précarité menstruelle
            [
                'qtype' => 'quiz',
                'qtext' => 'Qu’est-ce que la précarité menstruelle ?',
                'qmedia' => 'uploads/q6_precarite.png',
                'choices' => ['Difficulté à se procurer des protections hygiéniques à cause des faibles revenus','Difficulté à se procurer des vêtements','Difficulté à aller à l’école'],
                'correct' => [0],
                'confirm' => 'Exact !',
                'wrong' => 'La précarité menstruelle concerne l’accès aux protections hygiéniques.',
                'explain' => 'Une femme sur trois en France est concernée, soit 4 millions de femmes et personnes menstruées.',
                'explain_media' => 'uploads/q6_precarite.png',
                'seconds' => 20,
                'points' => 10,
            ],
            // Quantité de sang menstruel
            [
                'qtype' => 'truefalse',
                'qtext' => 'Une femme perd en moyenne l’équivalent d’1 à 3 cuillères à soupe de sang pendant ses règles',
                'qmedia' => 'uploads/q7_blood.png',
                'choices' => ['Vrai','Faux'],
                'correct' => [0],
                'confirm' => 'Correct !',
                'wrong' => 'La bonne réponse est "Vrai".',
                'explain' => 'Le flux menstruel varie, mais en moyenne c’est 30 à 40 ml par cycle.',
                'explain_media' => 'uploads/q7_blood.png',
                'seconds' => 15,
                'points' => 5,
            ],
            // Règles abondantes
            [
                'qtype' => 'quiz',
                'qtext' => 'Comment savoir si ses règles sont abondantes ?',
                'qmedia' => 'uploads/q8_abundant.png',
                'choices' => ['Durée > 7 jours','Quantité totale > 80 ml par cycle','Protection se remplit en <2 heures régulièrement'],
                'correct' => [0,1,2],
                'confirm' => 'Bien vu !',
                'wrong' => 'Les critères des règles abondantes sont une durée >7 jours, plus de 80 ml par cycle ou un changement de protection en moins de 2 heures.',
                'explain' => 'Les règles abondantes peuvent nécessiter un suivi médical pour éviter l’anémie.',
                'explain_media' => 'uploads/q8_abundant.png',
                'seconds' => 25,
                'points' => 10,
            ],
            // Durée moyenne du cycle
            [
                'qtype' => 'quiz',
                'qtext' => 'Combien de jours dure en moyenne un cycle menstruel ?',
                'qmedia' => 'uploads/q9_cycle_length.png',
                'choices' => ['21 jours','28 jours','35 jours'],
                'correct' => [1],
                'confirm' => 'Exact !',
                'wrong' => 'La durée moyenne est de 28 jours.',
                'explain' => 'La durée moyenne est 28 jours, mais elle peut varier de 21 à 35 jours.',
                'explain_media' => 'uploads/q9_cycle_length.png',
                'seconds' => 20,
                'points' => 10,
            ],
            // Début des règles
            [
                'qtype' => 'quiz',
                'qtext' => 'Les règles commencent :',
                'qmedia' => 'uploads/q10_start.png',
                'choices' => ['Au milieu du cycle','Au début du cycle','À la fin du cycle'],
                'correct' => [1],
                'confirm' => 'Exact !',
                'wrong' => 'Les règles marquent le jour 1 du cycle menstruel.',
                'explain' => 'Les règles marquent le jour 1 du cycle menstruel.',
                'explain_media' => 'uploads/q10_start.png',
                'seconds' => 20,
                'points' => 10,
            ],
            // Phases du cycle
            [
                'qtype' => 'quiz',
                'qtext' => 'Combien y a‑t‑il de phases dans un cycle ?',
                'qmedia' => 'uploads/menstrual_icon.png',
                'choices' => ['2','3','4','5'],
                'correct' => [2],
                'confirm' => 'Exact !',
                'wrong' => 'Il y a 4 phases dans un cycle.',
                'explain' => 'Chaque phase correspond à des variations hormonales qui influencent le corps et l’humeur.',
                'explain_media' => 'uploads/menstrual_icon.png',
                'seconds' => 20,
                'points' => 10,
            ],
            // Symptômes courants
            [
                'qtype' => 'quiz',
                'qtext' => 'Quels symptômes peuvent accompagner les règles ?',
                'qmedia' => 'uploads/menstrual_icon.png',
                'choices' => ['Crampes abdominales','Fatigue','Ballonnements','Sautes d’humeur','Maux de tête','Aucun'],
                'correct' => [0,1,2,3,4],
                'confirm' => 'Bien vu !',
                'wrong' => 'Les symptômes courants incluent crampes, fatigue, ballonnements, sautes d’humeur et maux de tête.',
                'explain' => 'Connaître ses symptômes permet de mieux anticiper et gérer le cycle.',
                'explain_media' => 'uploads/menstrual_icon.png',
                'seconds' => 25,
                'points' => 10,
            ],
            // Douleur menstruelle
            [
                'qtype' => 'quiz',
                'qtext' => 'La douleur pendant les règles est-elle normale ?',
                'qmedia' => 'uploads/menstrual_icon.png',
                'choices' => ['Oui, un peu de douleur légère à modérée est normale','Non, toute douleur est anormale'],
                'correct' => [0],
                'confirm' => 'Exact !',
                'wrong' => 'Un peu de douleur est normale, mais pas les douleurs très intenses.',
                'explain' => 'Des crampes légères sont fréquentes, mais des douleurs très intenses qui empêchent les activités quotidiennes ne sont pas normales.',
                'explain_media' => 'uploads/menstrual_icon.png',
                'seconds' => 15,
                'points' => 5,
            ],
            // Syndrome prémenstruel (SPM)
            [
                'qtype' => 'quiz',
                'qtext' => 'Le SPM correspond à :',
                'qmedia' => 'uploads/menstrual_icon.png',
                'choices' => ['Une envie irrésistible de sucreries','Un ensemble de symptômes physiques et émotionnels','Un moment où les règles sont plus abondantes'],
                'correct' => [1],
                'confirm' => 'Exact !',
                'wrong' => 'Le SPM est un ensemble de symptômes physiques et émotionnels.',
                'explain' => 'Le SPM survient avant les règles et peut inclure fatigue, irritabilité, ballonnements, seins sensibles…',
                'explain_media' => 'uploads/menstrual_icon.png',
                'seconds' => 20,
                'points' => 5,
            ],
            // Quand consulter un professionnel de santé
            [
                'qtype' => 'quiz',
                'qtext' => 'Quand est-il conseillé de consulter un médecin ou un gynécologue ?',
                'qmedia' => 'uploads/menstrual_icon.png',
                'choices' => ['Règles très abondantes ou prolongées','Douleurs intenses qui perturbent la vie quotidienne','Cycles irréguliers ou absents','Symptômes de fatigue, pâleur ou essoufflement','SPM très handicapant'],
                'correct' => [0,1,2,3,4],
                'confirm' => 'Exact !',
                'wrong' => 'Tous ces signes peuvent nécessiter un avis médical.',
                'explain' => 'Certaines douleurs ou anomalies peuvent signaler des troubles hormonaux, fibromes, endométriose ou anémie. Un suivi médical est important pour préserver la santé.',
                'explain_media' => 'uploads/menstrual_icon.png',
                'seconds' => 25,
                'points' => 10,
            ],
        ];
        // Insert questions for survey 1
        foreach ($survey1Questions as $q) {
            $db->prepare('INSERT INTO questions (survey_id, qtype, qtext, qmedia, choices, correct_indices, confirm_text, wrong_text, explain_text, explain_media, seconds, points) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $survey1Id,
                    $q['qtype'],
                    $q['qtext'],
                    $q['qmedia'],
                    json_encode($q['choices']),
                    json_encode($q['correct']),
                    $q['confirm'],
                    $q['wrong'],
                    $q['explain'],
                    $q['explain_media'],
                    $q['seconds'],
                    $q['points'],
                ]);
        }
        // ------------------ Survey 2: Hygiène et protections ------------------
        $db->prepare('INSERT INTO surveys (owner_id, title, theme_json) VALUES (?,?,?)')
            ->execute([$hostId, 'Hygiène et protections', $defaultTheme]);
        $survey2Id = $db->lastInsertId();
        $survey2Questions = [
            // Protections utilisées
            [
                'qtype' => 'long',
                'qtext' => 'Quelles protections menstruelles connaissez-vous ou utilisez-vous ?',
                'qmedia' => 'uploads/pad_icon.png',
                'choices' => [],
                'correct' => [],
                'confirm' => 'Merci pour votre réponse !',
                'wrong' => '',
                'explain' => 'Les protections réutilisables sont économiques, écologiques et sûres si utilisées correctement.',
                'explain_media' => 'uploads/pad_icon.png',
                'seconds' => null,
                'points' => 0,
            ],
            // Confort pendant les règles
            [
                'qtype' => 'long',
                'qtext' => 'Que faites-vous pour rester à l’aise pendant vos règles ?',
                'qmedia' => 'uploads/pad_icon.png',
                'choices' => [],
                'correct' => [],
                'confirm' => 'Merci pour votre réponse !',
                'wrong' => '',
                'explain' => 'Bien connaître son cycle et ses besoins permet de réduire le stress et améliorer le confort.',
                'explain_media' => 'uploads/pad_icon.png',
                'seconds' => null,
                'points' => 0,
            ],
            // Fréquence du changement
            [
                'qtype' => 'quiz',
                'qtext' => 'À quelle fréquence doit-on changer tampons ou serviettes ?',
                'qmedia' => 'uploads/pad_icon.png',
                'choices' => ['Toutes les 2 à 8 heures','Une fois par jour','Quand elles sont pleines seulement'],
                'correct' => [0],
                'confirm' => 'Exact !',
                'wrong' => 'Il est recommandé de changer toutes les 4 à 8 heures.',
                'explain' => 'Changer régulièrement les protections évite les infections et les odeurs. Les tampons et serviettes doivent être changés toutes les 4 à 8 heures environ selon le flux.',
                'explain_media' => 'uploads/pad_icon.png',
                'seconds' => 15,
                'points' => 5,
            ],
            // Lavage et produits intimes
            [
                'qtype' => 'quiz',
                'qtext' => 'Que faut-il utiliser pour se laver pendant les règles ?',
                'qmedia' => 'uploads/pad_icon.png',
                'choices' => ['Eau et savon doux ou nettoyant intime sans parfum','Eau uniquement','Savon parfumé fort ou gel douche classique'],
                'correct' => [0],
                'confirm' => 'Exact !',
                'wrong' => 'Un savon doux ou un nettoyant intime sans parfum est recommandé.',
                'explain' => 'Il est conseillé d’utiliser un savon doux ou un nettoyant intime sans parfum pour respecter le pH de la zone intime et éviter les irritations.',
                'explain_media' => 'uploads/pad_icon.png',
                'seconds' => 15,
                'points' => 5,
            ],
            // Douche vaginale
            [
                'qtype' => 'quiz',
                'qtext' => 'Les douches vaginales sont :',
                'qmedia' => 'uploads/pad_icon.png',
                'choices' => ['Nécessaires pour être propre','À éviter','Recommandées pendant les règles'],
                'correct' => [1],
                'confirm' => 'Exact !',
                'wrong' => 'Les douches vaginales sont à éviter.',
                'explain' => 'Les douches vaginales ne sont pas recommandées, car elles peuvent perturber la flore vaginale et favoriser les infections.',
                'explain_media' => 'uploads/pad_icon.png',
                'seconds' => 15,
                'points' => 5,
            ],
            // Hygiène des protections réutilisables
            [
                'qtype' => 'quiz',
                'qtext' => 'Pour les protections lavables ou culottes menstruelles, que faut-il faire ?',
                'qmedia' => 'uploads/pad_icon.png',
                'choices' => ['Les rincer et laver selon les instructions du fabricant','Les réutiliser sans lavage','Les laver seulement une fois par mois'],
                'correct' => [0],
                'confirm' => 'Exact !',
                'wrong' => 'Il faut les laver correctement après chaque utilisation.',
                'explain' => 'Les protections réutilisables doivent être lavées correctement pour rester hygiéniques et éviter les infections.',
                'explain_media' => 'uploads/pad_icon.png',
                'seconds' => 15,
                'points' => 5,
            ],
            // Signes d’alerte d’infection
            [
                'qtype' => 'quiz',
                'qtext' => 'Quels signes peuvent indiquer une infection ? (cochez tout ce qui s’applique)',
                'qmedia' => 'uploads/pad_icon.png',
                'choices' => ['Démangeaisons ou brûlures','Odeur désagréable','Sécrétions inhabituelles','Douleur intense','Aucun'],
                'correct' => [0,1,2,3],
                'confirm' => 'Exact !',
                'wrong' => 'Les signes d’infection incluent démangeaisons, odeurs, sécrétions inhabituelles et douleurs intenses.',
                'explain' => 'En présence de ces signes, il est important de consulter un professionnel de santé rapidement.',
                'explain_media' => 'uploads/pad_icon.png',
                'seconds' => 20,
                'points' => 10,
            ],
        ];
        foreach ($survey2Questions as $q) {
            $db->prepare('INSERT INTO questions (survey_id, qtype, qtext, qmedia, choices, correct_indices, confirm_text, wrong_text, explain_text, explain_media, seconds, points) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $survey2Id,
                    $q['qtype'],
                    $q['qtext'],
                    $q['qmedia'],
                    json_encode($q['choices']),
                    json_encode($q['correct']),
                    $q['confirm'],
                    $q['wrong'],
                    $q['explain'],
                    $q['explain_media'],
                    $q['seconds'],
                    $q['points'],
                ]);
        }
    }
    } catch (PDOException $e) {
        error_log("Database schema error: " . $e->getMessage());
        die("Database schema initialization failed. Please check file permissions and database configuration. Error: " . $e->getMessage());
    } catch (Exception $e) {
        error_log("Schema initialization error: " . $e->getMessage());
        die("Application initialization failed. Error: " . $e->getMessage());
    }
}

// Initialize the database schema on every request. This ensures the app works
// out of the box even on a fresh installation.
try {
    ensure_schema();
} catch (Exception $e) {
    error_log("Failed to initialize application: " . $e->getMessage());
    die("Application initialization failed. Please check your hosting configuration and file permissions.");
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

function check_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            http_response_code(400);
            echo 'Invalid CSRF token.';
            exit;
        }
    }
}

/**
 * Return the current logged in user record from the database or null if not
 * logged in. The user information is stored in the session (user_id).
 */
function current_user(): ?array
{
    if (!empty($_SESSION['user_id'])) {
        $db = connect_db();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }
    return null;
}

/**
 * Enforce login and optionally restrict to a specific role ('admin' or 'host').
 * Redirects to the login page if the user is not authenticated or lacks the
 * necessary role. When $role is null any authenticated user may proceed.
 */
function require_login(string $role = null): void
{
    $user = current_user();
    if (!$user) {
        header('Location: host_login.php');
        exit;
    }
    if ($role && $user['role'] !== $role) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

/**
 * Shortcut for htmlspecialchars(). Use this on any output originating from
 * user input to prevent XSS.
 */
function esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>