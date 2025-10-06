# 🧰 Récapitulatif de la mise en place de l'environnement de travail
Ce document décrit toutes les étapes réalisées pour configurer un environnement de développement complet sous Windows 11 avec WSL2, Ubuntu, GitHub et Docker.
---
## 🧱 1. Installation de WSL2
**Action :**
- Ouverture de PowerShell en mode administrateur.
- Installation de WSL2 avec Ubuntu par défaut.
**Commande :**
`wsl --install`
---
## 🐧 2. Installation d’Ubuntu via le Microsoft Store
**Action :**
- Ouverture du Microsoft Store.
- Choix de la version `Ubuntu 22.04.5 LTS`.
- Installation et lancement d’Ubuntu.
**Configuration :**
- Création d’un utilisateur Linux (`max`).
- Création d’un mot de passe.
---
## 🔄 3. Mise à jour du système Ubuntu
**Commande :**
`sudo apt update && sudo apt upgrade -y`
---
## 🧑‍💻 4. Installation et configuration de Git
**Commandes :**
`sudo apt install git -y`
`git config --global user.name "TonNomGitHub"`
`git config --global user.email "tonemail@exemple.com"`
`git config --list`
---
## 🔐 5. Création d’une clé SSH pour GitHub
**Commandes :**
`ssh-keygen -t ed25519 -C "tonemail@exemple.com"`
`cat ~/.ssh/id_ed25519.pub`
> Clé ajoutée dans GitHub → Paramètres → SSH and GPG keys
---
## 📁 6. Création d’un nouveau projet GitHub
**Action :**
- Création du dépôt `ecoride-v2` sur GitHub (sans README).
---
## 📂 7. Initialisation du projet dans Ubuntu
**Commandes :**
`mkdir ~/ecoride-v2`
`cd ~/ecoride-v2`
`git init`
`git branch -m main`
`git remote add origin git@github.com:maxroe66/ecoride-v2.git`
`echo "# EcoRide V2" > README.md`
`git add .`
`git commit -m "Initial commit"`
`git push -u origin main`
---
## 🌿 8. Mise en place du workflow Git
**Commandes :**
`git checkout -b develop`
`git push -u origin develop`
`git checkout -b feature/header`
`git push -u origin feature/header`
---
## 🐳 9. Installation de Docker Desktop
**Action :**
- Téléchargement de Docker Desktop for Windows – AMD64.
- Installation sur Windows.
- Activation manuelle de l’intégration WSL2 dans Docker Desktop → Settings → Resources → WSL Integration → Ubuntu.
---
## ✅ 10. Vérification de Docker dans Ubuntu
**Commandes :**
`docker --version`
`docker run hello-world`

## 🐳 11. Environnement Docker (Architecture actuelle EcoRide V2)

### 11.1 Objectifs
Fournir un environnement de développement performant, modulaire et prêt pour une future industrialisation (CI/CD, images reproductibles, séparation front/back).

### 11.2 Services (`docker-compose.yml`)
- `nginx` : reverse proxy + serveur web (racine : `src/backend/public`).
- `php-fpm` : exécution PHP 8.2 (image multi-stage avec vendor pré-installé).
- `composer` : utilitaire (profil `tools`) pour ajouter des dépendances sans polluer le conteneur principal.
- `db` : MySQL 8.0.
- `phpmyadmin` : interface MySQL.
- `mongodb` : base NoSQL.
- `mongo-express` : interface MongoDB.

### 11.3 Ports
- Application : http://localhost:8080
- phpMyAdmin : http://localhost:8081
- mongo-express : http://localhost:8082

### 11.4 Structure backend attendue
```
src/
  backend/
    app/               # Code applicatif (PSR-4: App\\)
    public/            # Fichiers accessibles (index.php, assets publics)
    composer.json
    composer.lock
    vendor/            # (Généré par build ou composer require) – pas commit
```
Squelette minimal EN PLACE :
- `app/Example.php` (classe statique de test)
- `public/index.php` (autoload + affichage message + version PHP)
Vous pouvez déjà accéder à http://localhost:8080 et voir le message de santé.

### 11.5 Dockerfile (résumé)
Multi-stage :
1. `composer_base` (image `composer:2.8`)
2. `vendor_builder` (installation des dépendances à partir de `composer.json`/`composer.lock`)
3. `runtime` (image finale `php:8.2-fpm` + vendor copié)

Avantages :
- Couches mises en cache → builds plus rapides.
- `vendor` figé par `composer.lock` → reproductible.
- Possibilité de build prod (sans dev deps) via `APP_ENV=prod`.

### 11.6 Montages & volumes
- Code backend monté : `./src/backend:/var/www/html/src/backend` (mode dev).
- `vendor` utilisé : celui de l'image (non monté) → meilleures performances I/O.
- Cache Composer : volume nommé `composer_cache` (`/tmp/composer`).
- Données MySQL : `db_data`.
- Données MongoDB : `mongo_data`.

### 11.7 Variables importantes
- `APP_ENV=dev|prod` (contrôle installation dépendances).
- Identifiants MySQL / MongoDB / phpMyAdmin / mongo-express (définis dans `.env`).

### 11.8 Démarrer l'environnement
```bash
docker compose up -d
```

### 11.9 Ajouter une dépendance Composer
```bash
docker compose run --rm composer require psr/log
```
Explications :
- Le service `composer` monte le code local.
- Met à jour `composer.json` + `composer.lock` dans `src/backend/`.
- Pour "figer" la dépendance dans une image (CI ou déploiement) :
```bash
docker compose build php-fpm
docker compose up -d
```

### 11.10 Vérifier Composer / PHP
```bash
docker compose run --rm composer --version
docker compose exec php-fpm php -v
```

### 11.11 Mode production (exclure dépendances de dev)
```bash
APP_ENV=prod docker compose build php-fpm
APP_ENV=prod docker compose up -d
```
Dans ce mode : `composer install --no-dev --optimize-autoloader`.

### 11.12 Logs & debug
```bash
docker compose logs -f nginx
docker compose logs -f php-fpm
```

### 11.13 Arrêt & nettoyage
```bash
docker compose down
docker compose down -v   # (ATTENTION: supprime les données MySQL/Mongo)
```

### 11.14 Prochaines améliorations possibles
- Ajout d’un Makefile (aliases : `make up`, `make deps`).
- Intégration d’un framework (Laravel / Slim / Symfony).
- Ajout de tests automatisés (PHPUnit) + service dédié.

### 11.15 Test rapide du bootstrap actuel
Commande (depuis l’hôte) :
```bash
curl -s http://localhost:8080
```
Sortie attendue (exemple) :
```
EcoRide backend opérationnel ✅
PHP version: 8.2.29
APP_ENV=dev (si ajouté plus tard via dotenv)
```

Si vous ne voyez pas ce message :
1. Vérifiez que le conteneur `php-fpm` est "Up" : `docker compose ps`
2. Vérifiez les logs Nginx : `docker compose logs -f nginx`
3. Vérifiez que `public/index.php` existe bien.

---
## 🔒 12. Sécurisation & configuration des variables d'environnement

### 12.1 Fichier `.env`
Placé à la racine (non versionné). Exemple :
```env
APP_ENV=dev
MYSQL_ROOT_PASSWORD=your_root_password
MYSQL_DATABASE=ecoride
MYSQL_USER=ecoride
MYSQL_PASSWORD=your_user_password
PMA_HOST=db
PMA_USER=ecoride
PMA_PASSWORD=your_user_password
MONGO_INITDB_ROOT_USERNAME=ecoride_admin
MONGO_INITDB_ROOT_PASSWORD=your_mongo_pwd
ME_CONFIG_MONGODB_ADMINUSERNAME=ecoride_admin
ME_CONFIG_MONGODB_ADMINPASSWORD=your_mongo_pwd
ME_CONFIG_MONGODB_SERVER=mongodb
```

### 12.2 Fichier `.env.example`
Fournir le même schéma sans valeurs sensibles → à copier / adapter.

### 12.3 `.gitignore` (extrait recommandé)
```gitignore
.env
.env.local
.env.*.local
vendor/
db_data/
mongo_data/
composer_cache/
```

### 12.4 Bonnes pratiques
- Ne jamais commit de secrets.
- Toujours committer `composer.lock` (garantit reproductibilité).
- Utiliser `APP_ENV=prod` pour les builds destinés à un déploiement.

### 12.5 Vérification de cohérence
```bash
docker compose config    # Vérifie la résolution des variables
```

---
Fin de la section mise à jour.
