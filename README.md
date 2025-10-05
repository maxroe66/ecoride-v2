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

## 🐳 11. Mise en place de l'environnement Docker pour EcoRide V2

**Services inclus dans le fichier `docker-compose.yml` (fichiers Docker désormais à la racine) :**
- Nginx (serveur web) — configuration: `nginx.conf` à la racine
- PHP-FPM (exécution du code PHP) — Dockerfile à la racine
- MySQL (base de données relationnelle)
- MongoDB (base de données non relationnelle)
- phpMyAdmin (interface web pour MySQL)
- mongo-express (interface web pour MongoDB)

**Ports utilisés :**
- Nginx : http://localhost:8080
- phpMyAdmin : http://localhost:8081
- mongo-express : http://localhost:8082

**Volumes :**
- Le dossier du projet est monté dans les conteneurs web (modifications HTML/CSS/PHP prises en compte en temps réel).
- Les données MySQL et MongoDB sont persistées via des volumes Docker.

**Lancement des services en mode détaché (depuis la racine du projet) :**
```bash
docker compose up -d
```

**Bonnes pratiques :**
- Les fichiers web sont synchronisés automatiquement.
- Les scripts SQL doivent être importés manuellement (via phpMyAdmin ou commande Docker).
- Pour le développement, tout est regroupé dans un seul fichier pour simplifier la gestion.
- En production, il est recommandé de séparer les services pour plus de sécurité et de scalabilité.

**Accès aux interfaces :**
- Application web : http://localhost:8080
- phpMyAdmin : http://localhost:8081
- mongo-express : http://localhost:8082

**Arrêt des services :**
```bash
docker compose down
```

---
## 🔒 12. Sécurisation des variables d'environnement

**Utilisation du fichier `.env` :**
- Les identifiants et mots de passe sensibles sont placés dans le fichier `.env` à la racine du projet.
- Le fichier `.env` n'est pas versionné (voir `.gitignore`).
- Exemple de contenu :
	```env
	MYSQL_ROOT_PASSWORD=your_root_password
	MYSQL_DATABASE=ecoride
	MYSQL_USER=ecoride
	MYSQL_PASSWORD=your_user_password
	...
	```

**Fichier `.env.example` :**
- Fournit un modèle des variables à renseigner, sans valeurs sensibles.
- À copier en `.env` et à compléter pour chaque environnement.

**Fichier `.gitignore` :**
- Empêche le commit du fichier `.env` et des données sensibles ou locales.
- Exemple :
	```gitignore
	.env
	.env.local
	.env.*.local
	db_data/
	mongo_data/
	...
	```

**Bonnes pratiques :**
- Ne jamais exposer de vrais mots de passe ou secrets dans le code versionné.
- Toujours fournir un `.env.example` pour faciliter la configuration par les collaborateurs.
