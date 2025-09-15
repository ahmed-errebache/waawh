# Guide de déploiement WAAWH sur Hostinger

## Problème résolu
L'application ne fonctionnait pas sur Hostinger à cause de plusieurs problèmes typiques des hébergements mutualisés :

1. **Permissions de fichiers incorrectes**
2. **Absence de fichier .htaccess**
3. **Gestion d'erreurs insuffisante**
4. **Configuration de base de données non optimisée**

## Corrections apportées

### 1. Fichier .htaccess ajouté
- Protection des fichiers sensibles (.sqlite, config.php)
- Configuration des uploads de fichiers
- Activation du debug (à désactiver en production)
- Protection du dossier `data/`
- Compression gzip pour de meilleures performances

### 2. Permissions de fichiers corrigées
- Dossiers `data/` et `uploads/` : permissions 755
- Vérification automatique des permissions en écriture
- Création automatique des dossiers manquants

### 3. Gestion d'erreurs améliorée
- Messages d'erreur clairs pour le debugging
- Logs d'erreurs activés
- Gestion des échecs de connexion à la base de données
- Vérification des prérequis PHP

### 4. Script de diagnostic
- Nouveau fichier `diagnostic.php` pour tester la configuration
- Vérification des extensions PHP requises
- Test de connexion à la base de données
- Contrôle des permissions de fichiers

## Instructions de déploiement

### Étapes obligatoires :

1. **Uploadez tous les fichiers** via FTP dans `public_html/` (ou le dossier web de votre domaine)

2. **Vérifiez les permissions** des dossiers suivants :
   ```
   data/     → 755 ou 775
   uploads/  → 755 ou 775
   ```

3. **Testez l'installation** en visitant :
   ```
   https://votredomaine.com/diagnostic.php
   ```

4. **Si tout est OK**, supprimez `diagnostic.php` et accédez à :
   ```
   https://votredomaine.com/index.php
   ```

### En cas de problème :

1. **Erreur de base de données** : Vérifiez que le dossier `data/` est accessible en écriture
2. **Erreur 500** : Consultez les logs d'erreurs dans votre panel Hostinger
3. **Uploads non fonctionnels** : Vérifiez les permissions du dossier `uploads/`
4. **Pages non trouvées** : Assurez-vous que le fichier `.htaccess` est bien uploadé

### Comptes par défaut

- **Administrateur** : ahmed.errebache@gmail.com / P@ssw0rd123!
- **Animateur de démonstration** : Nadia / Nadia123!

### Sécurité

⚠️ **Important** : Après déploiement, modifiez immédiatement :
1. Les mots de passe par défaut dans `config.php`
2. Désactivez l'affichage des erreurs en production en supprimant ces lignes de `.htaccess` :
   ```
   php_flag display_errors on
   php_flag display_startup_errors on
   php_value error_reporting "E_ALL"
   ```

## Support

Si vous rencontrez encore des problèmes :
1. Consultez le fichier `diagnostic.php`
2. Vérifiez les logs d'erreurs de votre hébergement
3. Contactez le support Hostinger si nécessaire

L'application devrait maintenant fonctionner correctement sur Hostinger ! 🎉