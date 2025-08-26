# Mission Cycle - Quiz Éducatif

Application web interactive de quiz éducatif sur le thème du cycle menstruel, développée en PHP avec SQLite.

## 🎯 Fonctionnalités

- **Interface animatrice** : Création et gestion de sessions de quiz en temps réel
- **Interface participants** : Participation au quiz avec feedback immédiat
- **Visualisation en direct** : Graphiques des réponses en temps réel avec Chart.js
- **Base de données SQLite** : Stockage des sessions, questions et réponses
- **20 questions éducatives** : Contenu pédagogique sur le cycle menstruel
- **Système de feedback** : Confirmations courtes et explications détaillées

## 🛠️ Prérequis

- **PHP 8.0+** avec extensions PDO et SQLite
- **Serveur web** (Apache, Nginx, ou serveur de développement PHP)
- **Navigateur moderne** supportant JavaScript ES6+

## 📦 Installation

1. **Télécharger et décompresser** le projet dans votre dossier web
2. **Configurer les permissions** (optionnel) :
   ```bash
   chmod 755 .
   chmod 666 database.sqlite  # Si le fichier existe déjà
   ```
3. **Ouvrir dans le navigateur** : `http://localhost/mission-cycle/index.php`

La base de données SQLite sera créée automatiquement au premier lancement avec les 20 questions pré-intégrées.

## 🔐 Identifiants Animatrice

- **Nom d'utilisateur** : `Nadia`
- **Mot de passe** : `P@ssw0rd123!`

## 🚀 Utilisation

### Pour l'animatrice :

1. **Connexion** : Cliquer sur "Espace animatrice" depuis l'accueil
2. **Créer une session** : Générer un PIN à 5 chiffres
3. **Partager le PIN** : Communiquer le PIN aux participants
4. **Démarrer le quiz** : Lancer la première question
5. **Gérer les questions** : Passer aux questions suivantes ou terminer

### Pour les participants :

1. **Rejoindre** : Entrer prénom + PIN sur la page d'accueil
2. **Attendre** : Patienter jusqu'au démarrage par l'animatrice
3. **Répondre** : Sélectionner les réponses aux questions
4. **Feedback** : Recevoir confirmation ou explication détaillée

## 📊 Fonctionnalités Techniques

### API Endpoints

- `POST /api/create_session.php` - Créer une nouvelle session
- `POST /api/get_session.php` - Récupérer l'état d'une session
- `POST /api/start_question.php` - Démarrer le quiz
- `POST /api/next_question.php` - Passer à la question suivante
- `POST /api/end_session.php` - Terminer une session
- `POST /api/submit_answer.php` - Soumettre une réponse

### Base de Données

- **sessions** : Gestion des sessions de quiz
- **questions** : 20 questions pré-intégrées
- **responses** : Réponses des participants

### Technologies Utilisées

- **Backend** : PHP 8+ avec PDO SQLite
- **Frontend** : HTML5, Bootstrap 5, JavaScript vanilla
- **Graphiques** : Chart.js v4
- **Base de données** : SQLite

## 🎨 Personnalisation

### Modifier les questions

Éditer le fichier `db.php`, fonction `seedQuestions()` :

```php
$questions = [
    [
        'qtext' => 'Votre question ici',
        'qtype' => 'quiz', // ou 'truefalse'
        'choices' => ['Choix A', 'Choix B', 'Choix C', 'Choix D'],
        'correct_indices' => [0], // Index de la/des bonne(s) réponse(s)
        'confirm_text' => 'Confirmation courte',
        'explain_text' => 'Explication détaillée',
        'explain_media' => ['image' => '', 'video' => ''],
        'seconds' => 30,
        'points' => 1
    ],
    // ... autres questions
];
```

### Modifier les identifiants

Éditer le fichier `config.php` :

```php
define('HOST_USERNAME', 'VotreNom');
define('HOST_PASSWORD', 'VotreMotDePasse');
```

## 🔧 Structure des Fichiers

```
mission-cycle/
├── index.php              # Page d'accueil
├── host_login.php          # Connexion animatrice
├── host_dashboard.php      # Dashboard animatrice
├── join.php               # Interface participant
├── config.php             # Configuration
├── db.php                 # Gestion base de données
├── database.sqlite        # Base SQLite (auto-créée)
├── api/                   # Endpoints API
│   ├── create_session.php
│   ├── get_session.php
│   ├── start_question.php
│   ├── next_question.php
│   ├── end_session.php
│   └── submit_answer.php
└── README.md              # Documentation
```

## 🐛 Dépannage

### Erreurs courantes

- **"Base de données introuvable"** : Vérifier les permissions d'écriture
- **"Session introuvable"** : Le PIN a peut-être expiré ou la session est fermée
- **Graphiques non affichés** : Vérifier la connexion internet (Chart.js CDN)

### Logs

Les erreurs PHP sont affichées selon la configuration du serveur. Pour le développement :

```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## 📝 Licence

Projet éducatif - Usage libre pour fins pédagogiques.

## 👥 Support

Pour toute question ou suggestion d'amélioration, n'hésitez pas à nous contacter.

---

**Mission Cycle** - Démystifier le cycle menstruel par l'éducation interactive 💛