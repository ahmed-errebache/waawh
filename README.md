# Application WAAWH – Sondages en direct

Cette application est une implémentation complète d'un système de sondages/quiz en direct écrite en PHP 8 natif avec HTML, CSS (Bootstrap 5) et JavaScript minimal. Elle est prête à être déployée sur un hébergement mutualisé comme Hostinger : il suffit de transférer les fichiers via FTP et de s'assurer que PHP 8 et les droits d'écriture sur les dossiers `uploads` et `data` sont disponibles.

## Fonctionnalités

- **Gestion multi‐utilisateurs** : un administrateur peut créer des comptes d'animateurs (clients) et leur attribuer des sondages. Les animateurs disposent d'un profil avec logo et palette de couleurs.
- **Création et édition de sondages** : l'administrateur crée et modifie des sondages, ajoute des questions de différents types (choix multiples, vrai/faux, réponses courtes, réponses longues, notation, date) avec supports texte et médias (images, vidéos, audio, PDF). Les questions peuvent être chronométrées et notées.
- **Sessions en direct** : un animateur démarre une session sur un sondage qui lui est assigné. Les participants rejoignent via un code PIN à six chiffres et répondent en temps réel. L'animateur contrôle le déroulement (démarrage, révélation des réponses, passage à la question suivante, fin) et voit un classement actualisé.
- **Classement** : calcul du score des participants en fonction des réponses et affichage du top 3 ainsi que de la liste complète des joueurs.
- **Thèmes personnalisables** : chaque animateur peut définir des couleurs et un logo qui personnaliseront son espace et les pages participants.
- **Support SQLite/MySQL** : par défaut la base de données est un fichier SQLite. Il est possible de passer facilement à MySQL en modifiant les constantes dans `config.php`.
- **Sécurité simple** : gestion de sessions HTTP‐only, jetons CSRF, validation des entrées, contrôle des types MIME et des tailles de fichiers (50 Mo max) lors des uploads.

## Installation

1. **Prérequis** : un hébergement supportant PHP 8.0 ou plus avec l'extension PDO (SQLite et/ou MySQL).
2. **Déploiement** : transférez l'ensemble du dossier `waawh_app` sur votre serveur (par exemple dans `public_html`). Assurez‐vous que les dossiers `waawh_app/data` et `waawh_app/uploads` disposent des droits d'écriture (chmod 755 ou 775 selon l'hébergeur).
3. **Configuration** : ouvrez `config.php` pour ajuster les éléments suivants :
   - `USE_SQLITE` : laissez `true` pour utiliser SQLite (par défaut) ou mettez `false` pour MySQL.
   - `MYSQL_DSN`, `MYSQL_USER`, `MYSQL_PASS` : renseignez vos paramètres MySQL si nécessaire.
   - `DEV_ADMIN_USERNAME` et `DEV_ADMIN_PASSWORD` : modifiez le compte administrateur par défaut en production et pensez à le hasher avec `password_hash()`.
4. **Accès** :
   - Page d'accueil : `/index.php`.
   - Connexion administrateur ou animateur : `/host_login.php`.
5. **Comptes de démonstration** :
   - Administrateur : `admin` / `admin`.
   - Un animateur de démonstration a été créé (`nadia` / `nadia`) avec l'entreprise *WAAWH*.

## Notes de sécurité

- Les mots de passe en clair dans la base de données sont destinés au développement. Passez en production en hachant les mots de passe avec `password_hash()` et en mettant à jour les entrées correspondantes.
- Les uploads sont contrôlés par type MIME et taille maximale mais il est recommandé d'utiliser un répertoire non accessible au public ou d'ajouter des protections supplémentaires selon votre hébergement.

## Structure du projet

- `index.php` : page d’accueil publique.
- `host_login.php` : connexion (admin et animateurs).
- `admin.php` : tableau de bord administrateur (gestion des sondages et animateurs).
- `host_manage.php`, `host_edit.php` : gestion des comptes animateurs.
- `builder.php` : création/édition de sondages.
- `edit_question.php` : création/édition de questions avec prise en charge des médias.
- `host_dashboard.php` : tableau de bord animateur (lancement des sessions et suivi).
- `host_session.php` : contrôle d’une session en direct avec rafraîchissement du classement.
- `join.php` : interface participant (connexion via PIN, réponses aux questions, affichage des corrections et explications).
- `preview.php` : aperçu en lecture seule d’un sondage.
- Dossier `api/` : scripts d’API pour gérer les sessions et actions AJAX.
- Dossier `uploads/` : stockage des fichiers envoyés (images, vidéos, audio, PDF).

## Personnalisation

- Les couleurs par défaut sont inspirées du logo WAAWH (#FFBF69 pour la couleur primaire, #2EC4B6 pour l’accent, #FFF9F2 pour l’arrière‐plan). Chaque animateur peut définir sa propre palette via son profil.
- Pour changer le logo global, remplacez `assets/logo.png` par votre propre fichier PNG.

## Remerciements

Ce projet a été généré automatiquement par un assistant IA selon un cahier des charges détaillé. Il vise à fournir une base solide mais peut nécessiter des ajustements pour un usage en production (performances, sécurité renforcée, ergonomie). N’hésitez pas à l’améliorer !