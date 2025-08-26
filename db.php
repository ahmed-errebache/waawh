<?php
/**
 * Gestion de la base de données SQLite
 */

require_once 'config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Créer les tables si elles n'existent pas
            $this->createTables();
            
            // Insérer les questions si la table est vide
            $this->seedQuestions();
            
        } catch (PDOException $e) {
            die('Erreur de connexion à la base de données : ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPDO() {
        return $this->pdo;
    }

    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pin TEXT UNIQUE NOT NULL,
            status TEXT DEFAULT 'open',
            current_question_index INTEGER DEFAULT -1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            qtext TEXT NOT NULL,
            qtype TEXT NOT NULL,
            choices TEXT NOT NULL,
            correct_indices TEXT NOT NULL,
            confirm_text TEXT NOT NULL,
            explain_text TEXT NOT NULL,
            explain_media TEXT NOT NULL,
            seconds INTEGER NOT NULL,
            points INTEGER NOT NULL
        );

        CREATE TABLE IF NOT EXISTS responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id INTEGER NOT NULL,
            question_id INTEGER NOT NULL,
            user_name TEXT NOT NULL,
            answer_indices TEXT NOT NULL,
            is_correct INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES sessions(id),
            FOREIGN KEY (question_id) REFERENCES questions(id)
        );
        ";

        $this->pdo->exec($sql);
    }

    private function seedQuestions() {
        // Vérifier si des questions existent déjà
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM questions");
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return; // Les questions sont déjà présentes
        }

        $questions = [
            // Brise-glace (non notées)
            [
                'qtext' => 'Bienvenue dans « Mission Cycle » : ici on apprend sans jugement 💛.',
                'qtype' => 'truefalse',
                'choices' => ['Vrai', 'Faux'],
                'correct_indices' => [0],
                'confirm_text' => 'Merci d\'être là !',
                'explain_text' => 'Objectif : casser les tabous, partager des infos fiables et des astuces de confort.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 10,
                'points' => 0
            ],
            [
                'qtext' => 'On reste bienveillant·e·s et on pose des questions si un mot n\'est pas clair.',
                'qtype' => 'truefalse',
                'choices' => ['Vrai', 'Faux'],
                'correct_indices' => [0],
                'confirm_text' => 'Bienveillance & clarté avant tout.',
                'explain_text' => 'Aucune question n\'est « bête ». On avance ensemble.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 10,
                'points' => 0
            ],
            // Faits clés
            [
                'qtext' => 'Combien de jours une femme est-elle menstruée au cours de sa vie (en moyenne) ?',
                'qtype' => 'quiz',
                'choices' => ['1200', '2000', '2400', '3000'],
                'correct_indices' => [2],
                'confirm_text' => 'Réponse : 2400 jours.',
                'explain_text' => '≈ 5 jours/mois × 12 mois × ~40 ans (début ~12 ans, fin ~51 ans) ≈ 2 400 jours. Le vécu varie.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 30,
                'points' => 1
            ],
            [
                'qtext' => 'À combien revient l\'achat de protections jetables au cours d\'une vie ?',
                'qtype' => 'quiz',
                'choices' => ['1 800 €', '2 800 €', '3 800 €', '5 800 €'],
                'correct_indices' => [2],
                'confirm_text' => 'Environ 3 800 € pour les jetables.',
                'explain_text' => 'Avec extras (culottes, détachants, bouillotte, consultations…), le total peut approcher ~5 800 €. Les réutilisables réduisent le coût et les déchets.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 30,
                'points' => 1
            ],
            [
                'qtext' => 'La précarité menstruelle, c\'est…',
                'qtype' => 'quiz',
                'choices' => ['Difficulté à acheter des protections à cause de faibles revenus', 'Difficulté à se procurer des vêtements', 'Difficulté à aller à l\'école', 'Manque de temps'],
                'correct_indices' => [0],
                'confirm_text' => 'Exact.',
                'explain_text' => 'En France, ~1 personne menstruée/3 est concernée : impact scolarité, travail, santé. Pistes : distributions, boîtes à dons, réutilisables.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 25,
                'points' => 1
            ],
            [
                'qtext' => 'On perd en moyenne l\'équivalent d\'1 à 3 cuillères à soupe de sang par cycle (~30–40 ml).',
                'qtype' => 'truefalse',
                'choices' => ['Vrai', 'Faux'],
                'correct_indices' => [0],
                'confirm_text' => 'Oui, c\'est une moyenne.',
                'explain_text' => 'Le flux varie d\'une personne à l\'autre et d\'un cycle à l\'autre. L\'important est de connaître son normal.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 20,
                'points' => 1
            ],
            // Règles abondantes
            [
                'qtext' => 'Des règles sont dites abondantes si elles durent plus de 7 jours (souvent).',
                'qtype' => 'truefalse',
                'choices' => ['Vrai', 'Faux'],
                'correct_indices' => [0],
                'confirm_text' => '>7 jours = un signe.',
                'explain_text' => 'Durée longue régulière → avis médical (risque d\'anémie).',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 15,
                'points' => 1
            ],
            [
                'qtext' => '> 80 ml par cycle est un signe de règles abondantes.',
                'qtype' => 'truefalse',
                'choices' => ['Vrai', 'Faux'],
                'correct_indices' => [0],
                'confirm_text' => 'Oui.',
                'explain_text' => 'Repères : ≥5 tampons super plus/jour ou cup qui se remplit très vite.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 15,
                'points' => 1
            ],
            [
                'qtext' => 'Protection qui se remplit en moins de 2 h régulièrement = signe d\'abondance.',
                'qtype' => 'truefalse',
                'choices' => ['Vrai', 'Faux'],
                'correct_indices' => [0],
                'confirm_text' => 'Exact.',
                'explain_text' => 'Changer très souvent malgré une taille super est un indicateur. Parles-en pour éviter l\'anémie.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 15,
                'points' => 1
            ],
            // Cycle
            [
                'qtext' => 'Combien de jours dure en moyenne un cycle menstruel ?',
                'qtype' => 'quiz',
                'choices' => ['21 jours', '28 jours', '35 jours', '40 jours'],
                'correct_indices' => [1],
                'confirm_text' => '28 jours en moyenne.',
                'explain_text' => 'Plage « normale » ~21–35 jours. La régularité compte plus que le chiffre exact.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 20,
                'points' => 1
            ],
            [
                'qtext' => 'Les règles commencent…',
                'qtype' => 'quiz',
                'choices' => ['Au milieu du cycle', 'Au début du cycle', 'À la fin du cycle', 'Après l\'ovulation'],
                'correct_indices' => [1],
                'confirm_text' => 'Jour 1 = premier jour des règles.',
                'explain_text' => 'Phases : Menstruelle → Folliculaire → Ovulation → Lutéale.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 20,
                'points' => 1
            ],
            [
                'qtext' => 'Combien y a-t-il de phases dans un cycle ?',
                'qtype' => 'quiz',
                'choices' => ['2', '3', '4', '5'],
                'correct_indices' => [2],
                'confirm_text' => '4 phases.',
                'explain_text' => 'Menstruelle, Folliculaire, Ovulation, Lutéale.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 20,
                'points' => 1
            ],
            [
                'qtext' => 'L\'ovulation survient en général…',
                'qtype' => 'quiz',
                'choices' => ['~14 jours après les règles', '~14 jours avant les prochaines règles', 'Le jour 1 du cycle', 'Juste avant les règles suivantes'],
                'correct_indices' => [1],
                'confirm_text' => '≈ 14 jours avant les prochaines règles.',
                'explain_text' => 'Le timing varie selon la longueur du cycle.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 25,
                'points' => 1
            ],
            // Santé & hygiène
            [
                'qtext' => '« La douleur des règles, il faut faire avec. »',
                'qtype' => 'truefalse',
                'choices' => ['Vrai', 'Faux'],
                'correct_indices' => [1],
                'confirm_text' => 'Faux.',
                'explain_text' => 'Des crampes légères sont fréquentes, mais des douleurs intenses qui empêchent de vivre normalement ne sont pas « normales » → consulter.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 20,
                'points' => 1
            ],
            [
                'qtext' => 'Le SPM (syndrome prémenstruel), c\'est…',
                'qtype' => 'quiz',
                'choices' => ['Une envie irrésistible de sucreries', 'Un ensemble de symptômes physiques et émotionnels', 'Une période où les règles sont plus abondantes', 'Une infection'],
                'correct_indices' => [1],
                'confirm_text' => 'Un ensemble de symptômes (avant les règles).',
                'explain_text' => 'Fatigue, irritabilité, ballonnements, seins sensibles… Si c\'est très handicapant : avis médical.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 25,
                'points' => 1
            ],
            [
                'qtext' => 'À quelle fréquence changer tampons ou serviettes ?',
                'qtype' => 'quiz',
                'choices' => ['Toutes les 2–8 heures', 'Toutes les 4–8 heures', 'Une fois par jour', 'Quand c\'est plein seulement'],
                'correct_indices' => [1],
                'confirm_text' => 'Toutes les 4–8 heures (jamais >8 h pour un tampon).',
                'explain_text' => 'Adapter selon le flux : hygiène + confort + prévention des odeurs/infections.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 20,
                'points' => 1
            ],
            [
                'qtext' => 'Pour la toilette intime pendant les règles, on privilégie…',
                'qtype' => 'quiz',
                'choices' => ['Eau + savon doux / nettoyant intime sans parfum', 'Eau uniquement', 'Savon parfumé/gel douche classique', 'Désinfectant'],
                'correct_indices' => [0],
                'confirm_text' => 'Doux & sans parfum.',
                'explain_text' => 'Respecter le pH et éviter les irritations.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 25,
                'points' => 1
            ],
            [
                'qtext' => 'Les douches vaginales sont recommandées pendant les règles.',
                'qtype' => 'truefalse',
                'choices' => ['Vrai', 'Faux'],
                'correct_indices' => [1],
                'confirm_text' => 'À éviter.',
                'explain_text' => 'Elles perturbent la flore vaginale et favorisent les infections.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 20,
                'points' => 1
            ],
            [
                'qtext' => 'Ces signaux doivent faire consulter : règles très abondantes/prolongées, douleurs intenses, cycles absents/irréguliers, fatigue/pâleur/essoufflement.',
                'qtype' => 'truefalse',
                'choices' => ['Vrai', 'Faux'],
                'correct_indices' => [0],
                'confirm_text' => 'Oui, consultez dans ces cas.',
                'explain_text' => 'Pour écarter endométriose, fibromes, troubles hormonaux, anémie.',
                'explain_media' => ['image' => '', 'video' => ''],
                'seconds' => 35,
                'points' => 2
            ]
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO questions (qtext, qtype, choices, correct_indices, confirm_text, explain_text, explain_media, seconds, points)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($questions as $question) {
            $stmt->execute([
                $question['qtext'],
                $question['qtype'],
                json_encode($question['choices']),
                json_encode($question['correct_indices']),
                $question['confirm_text'],
                $question['explain_text'],
                json_encode($question['explain_media']),
                $question['seconds'],
                $question['points']
            ]);
        }
    }
}
?>