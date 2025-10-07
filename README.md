<div align="center">

# EcoRide V2 – Environnement de Développement Complet

Plateforme PHP (no framework) + MySQL + MongoDB, servie par Nginx & PHP-FPM, avec stratégie de résilience (fallback MySQL) pour les avis.

</div>

---

## 🧭 Sommaire
1. 🧱 Installation WSL2
2. 🐧 Installation Ubuntu (Microsoft Store)
3. 🔄 Mise à jour système
4. 🧑‍💻 Git (config globale)
5. 🔐 Clé SSH GitHub
6. 📁 Création dépôt GitHub
7. 📂 Initialisation locale du projet
8. 🌿 Workflow Git basique
9. 🐳 Installation Docker Desktop (Windows + WSL2)
10. ✅ Vérification Docker sous Ubuntu
11. 🧬 Architecture Docker actuelle
12. 🗂 Structure du projet
13. ⚙️ Démarrage rapide
14. 📦 Dépendances & Composer
15. 🗄 Persistance (volumes & données)
16. 🧪 Endpoint Health & Résilience Avis
17. 🛡 Troubleshooting & Debug
18. 💾 Backups manuels
19. 🛣 Roadmap / Prochaines étapes

---

## 🧱 1. Installation de WSL2
Ouvrir PowerShell (administrateur) puis :
```
wsl --install
```
Ubuntu sera installé par défaut (si première installation).

## 🐧 2. Installation d’Ubuntu (Microsoft Store)
1. Ouvrir Microsoft Store.
2. Rechercher « Ubuntu 22.04.5 LTS ».
3. Installer et lancer.
4. Créer l’utilisateur (ex: `max`) + mot de passe.

## 🔄 3. Mise à jour du système
```
sudo apt update && sudo apt upgrade -y
```

## 🧑‍💻 4. Installation et configuration de Git
```
sudo apt install git -y
git config --global user.name "TonNomGitHub"
git config --global user.email "tonemail@exemple.com"
git config --list
```

## 🔐 5. Création d’une clé SSH GitHub
```
ssh-keygen -t ed25519 -C "tonemail@exemple.com"
cat ~/.ssh/id_ed25519.pub
```
Copier la clé dans GitHub → Settings → SSH and GPG keys.

## 📁 6. Création du dépôt GitHub
Créer le dépôt vide `ecoride-v2` (sans README) sur GitHub.

## 📂 7. Initialisation locale
```
mkdir ~/ecoride-v2
cd ~/ecoride-v2
git init
git branch -m main
git remote add origin git@github.com:maxroe66/ecoride-v2.git
echo "# EcoRide V2" > README.md
git add .
git commit -m "Initial commit"
git push -u origin main
```

## 🌿 8. Workflow Git minimal
```
git checkout -b develop
git push -u origin develop
git checkout -b feature/nom-tache
git push -u origin feature/nom-tache
```

## 🐳 9. Installation Docker Desktop
1. Télécharger Docker Desktop (Windows AMD64).
2. Installer.
3. Activer WSL integration : Settings → Resources → WSL Integration → cocher Ubuntu.

## ✅ 10. Vérification Docker (Ubuntu)
```
docker --version
docker run hello-world
```

## 🧬 11. Environnement Docker – Architecture actuelle

### 11.1 Objectifs initiaux
- Démarrer un socle backend PHP sans framework pour maîtriser chaque couche (routing minimal maison, autoload PSR-4, séparation responsabilités).
- Mettre en place un environnement reproductible (Docker) pour éviter les dérives locales (différences versions PHP/DB).
- Valider rapidement un « vertical slice » métier simple : gestion d'avis (lecture/écriture + agrégation) pour tester la chaîne complète (HTTP → domaine → persistance → résilience → réponse JSON).

### 11.2 Chronologie de mise en place (résumé)
1. Esquisse initiale : conteneurs `php` + `nginx` + `mysql` + outillage (phpMyAdmin) — structure `src/`.
2. Simplification structure : passage à `app/` + `public/` (front controller unique), retrait de l'ancien `src/public` en doublon.
3. Autoload corrigé : ajustement PSR-4 (`App\\` → `app/`) et suppression d'inclusions manuelles.
4. Ajout MongoDB pour stocker les avis de façon flexible (schéma libre initial possible) et tester un double backend de données.
5. Conception résilience : introduction du `ResilientAvisRepository` (pattern wrapper) + cooldown pour éviter boucle d'échecs.
6. Intégration schéma SQL complet (tables métier) et renommage anglais → français (`Review` → `Avis`) pour cohérence linguistique.
7. Problème dépendance `mongodb/mongodb` (installation Composer bloquée par volumes en lecture / lock mismatch) ⇒ pivot vers driver natif `ext-mongodb` (réduction surface de dépendance + simplicité build).
8. Implémentation endpoints `/api/avis` (GET liste + moyenne, POST création) + endpoint stats séparé.
9. Tests manuels de panne : arrêt de Mongo, insertion avis → fallback MySQL; redémarrage + expiration cooldown → retour primaire.
10. Documentation & consolider README (présent). 

### 11.3 Raisons des choix techniques
| Choix | Raison principale | Bénéfice secondaire |
|-------|-------------------|---------------------|
| Séparation Nginx / PHP-FPM | Aligné avec déploiements prod classiques | Reload Nginx sans toucher PHP |
| Pas de framework (MVP) | Contrôle, pédagogie, éviter sur-optimisation précoce | Démarrage léger, surface debug réduite |
| MySQL + Mongo | Relationnel pour cœur métier structuré, Mongo pour avis (souplesse, agrégations) | Démonstration polyglotte + cas d'usage résilience |
| Résilience dès le début | Capturer erreurs d'architecture tôt (timeouts, bascule) | Tests de comportement en conditions dégradées |
| Cooldown simple (30s) | Empêcher thrash sur service instable | Facile à raisonner et ajuster |
| Driver natif Mongo (`ext-mongodb`) | Éviter problème de dépendance Composer & overhead lib | Moins de code tiers, build plus rapide |
| Français dans le domaine | Cohérence fonctionnelle (équipe francophone) | Réduction friction vocable produit ↔ code |
| Vertical slice Avis | Petit périmètre mais traverse toutes les couches | Sert de gabarit pour futures entités |

### 11.4 Pourquoi intégrer le système d'avis pendant la phase environnement ?
1. Validation early de la persistance multi-backend (différences latence / erreurs gérées).
2. Mesurer la forme JSON et définir conventions (clé `success`, enveloppe `data`).
3. Tester stratégie de fallback réelle (arrêt conteneur) avant d'empiler d'autres fonctionnalités.
4. Permettre d'éprouver le cycle complet de contribution (modifier code → tester via curl → observer logs → affiner README).
5. Serve de patron pour orchestrer d'autres use-cases (ex: participations, incidents) sans dépendre encore d'une authentification complète.

### 11.5 Compromis et dettes techniques assumées (pour l'instant)
- Pas de tests automatisés : confiance basée sur tests manuels → à corriger rapidement.
- Pas de logger structuré : fallback silencieux (difficile à auditer en production simulée).
- Pas de backfill fallback → Mongo : risque de divergence si Mongo indisponible longtemps.
- Cooldown codé en dur : non configurable via env.
- Agrégation stats double requête (liste + moyenne) → optimisable.

### 11.6 Prochaines améliorations ciblées (architectural hardening)
1. Variable d'env `AVIS_COOLDOWN_SECONDS` pour ajuster la fenêtre.
2. Header `X-Avis-Storage` indiquant la source primaire/fallback.
3. Script de backfill (CRON / commande CLI) : réplication des avis manquants.
4. Tests PHPUnit (unitaires + mini HTTP via PHP built-in server ou curl dockerisé).
5. Intégration Monolog + canal `storage/logs/avis.log`.
6. Health enrichi : statut Mongo / statut MySQL / uptime / version git commit.

### 11.7 Etat final actuel

Services (fichier `docker-compose.yml`) :
- `nginx` : reverse proxy + statiques.
- `php` : PHP 8.3 FPM avec extensions (pdo_mysql, mongodb, intl, mbstring, zip, gd, xdebug en dev).
- `db` : MySQL 8.4 + init script (table fallback avis).
- `phpmyadmin` : interface MySQL.
- `mongo` : MongoDB 7 (stockage principal avis).
- `mongo-express` : interface Mongo.

Ports exposés :
- App : http://localhost:8080
- phpMyAdmin : http://localhost:8081
- Mongo Express : http://localhost:8082

Volumes nommés :
- `mysql_data` → données MySQL
- `mongo_data` → données MongoDB

Montages (dev) :
- `./app` → /var/www/app
- `./public` → /var/www/public
- `./frontend` → /var/www/frontend
- `./storage` → /var/www/storage
- `./vendor` → /var/www/vendor (partagé, attention à la cohérence)

Autoload PSR-4 : `App\\` → `app/`

Stratégie build image PHP : installation des dépendances Composer dans l’image puis montage local (si présent). En cas de dossier `vendor` vide côté hôte, exécuter `composer install` dans le conteneur.

## 🗂 12. Structure du projet
```
app/                # Code applicatif (Core, Models, Repositories, etc.)
public/             # Front controller (index.php) + futurs assets publics
frontend/           # (Placeholders / JS/CSS futurs)
storage/            # Données applicatives (logs, export, cache futur)
docker/             # Config docker (nginx, php, mysql init, mongo seed)
vendor/             # Dépendances Composer (généré)
.env.example        # Exemple de configuration
docker-compose.yml  # Stack services
composer.json       # Dépendances & autoload
```

## ⚙️ 13. Démarrage rapide
1. Copier le fichier d’exemple :
```
cp .env.example .env
```
2. Construire & lancer :
```
docker compose build
docker compose up -d
```
3. Tester :
```
curl -f http://localhost:8080/api/health
```
Réponse attendue : `{"success":true,"data":"ok"}`

## 📦 14. Dépendances & Composer
Installation (si `vendor/` vide) :
```
docker compose exec php composer install
```
Ajouter une dépendance :
```
docker compose exec php composer require monolog/monolog
```
Optimiser autoload :
```
docker compose exec php composer dump-autoload -o
```

## 🗄 15. Persistance & Données
- MySQL : scripts init dans `docker/mysql/init/` (schéma complet `001_schema.sql` + table fallback `avis_fallback`).
	- Tables principales : `utilisateur`, `marque`, `voiture`, `covoiturage`, `participation`, `incident`, `credit_operation`, `escrow_transaction`, `avis_fallback`.
	- Table fallback avis : `avis_fallback` (colonnes : `avis_id`, `utilisateur_id`, `covoiturage_id`, `note`, `commentaire`, `date_creation`).
	- Mapping côté code (Model `Avis`) :
		- `rideId` → `covoiturage_id`
		- `userId` → `utilisateur_id`
		- `rating` → `note`
		- `comment` → `commentaire`
		- `createdAt` → `date_creation`
	- Variable env pour personnaliser le nom de la table fallback : `REVIEWS_FALLBACK_TABLE` (défaut: `avis_fallback`).
	- Application manuelle du schéma (si volume déjà existant) :
		```
		docker compose exec -T db mysql -uecoride -pecoride-v2 ecoride < docker/mysql/init/001_schema.sql
		```
	- Réinitialisation complète (PERTE DE DONNÉES) :
		```
		docker compose down
		docker volume rm ecoride-v2_mysql_data
		docker compose up -d db
		docker compose up -d
		```
- Mongo : seed placeholder `docker/mongo/init/` (à compléter). Prévu pour stocker la source principale des avis.
- Synchronisation future : script à écrire pour pousser les avis de `avis_fallback` vers Mongo lorsque celui-ci redevient disponible.
- Suppression totale (ATTENTION données MySQL & Mongo) :
```
docker compose down -v
```

## 🧪 16. Health & Résilience Avis
- Endpoint actuel : `GET /api/health`
- Architecture avis :
	- Stockage primaire : MongoDB (collection `avis`).
	- Fallback : MySQL (`avis_fallback`).
	- Stratégie : `ResilientAvisRepository` détecte une erreur Mongo, active un cooldown (30s) puis retente.
- Endpoints avis disponibles (MVP implémenté) :
	- `GET /api/avis?covoiturage_id=ID` → renvoie `{ items[], count, average }` (average via agrégation mongo ou fallback SQL)
	- `GET /api/avis/stats?covoiturage_id=ID` → renvoie `{ covoiturage_id, average, count }`
	- `POST /api/avis` (body JSON minimal) :
		```
		{
		  "covoiturage_id": 1,
		  "utilisateur_id": 1,
		  "note": 5,
		  "commentaire": "Trajet top"
		}
		```

### 🔥 Test manuel de la résilience (exemple réalisé)
1. Arrêter Mongo :
   ```
   docker compose stop mongo
   ```
2. (Si besoin) Créer les entités minimales pour respecter les FK MySQL : `utilisateur`, `marque`, `voiture`, `covoiturage`.
3. Faire un `POST /api/avis` → persiste dans `avis_fallback` (si FK OK). En cas d’erreur 1452, créer les données manquantes.
4. Vérifier avec :
   ```
   curl -s "http://localhost:8080/api/avis?covoiturage_id=1"
   ```
5. Redémarrer Mongo :
   ```
   docker compose start mongo
   ```
6. Attendre > 30s (cooldown) puis refaire un POST : les nouveaux avis repartent sur Mongo.

Notes :
- Aucune synchronisation (backfill) des avis du fallback vers Mongo n’est encore implémentée.
- Une future amélioration pourra ajouter : marquage de la source (`primary|fallback`) via un header `X-Avis-Storage` ou un champ meta.
- Le cooldown est actuellement codé en dur (30s) dans `ResilientAvisRepository`.

### État actuel
- Résilience testée (bascule + retour confirmés).
- Pas encore de replay ni de métriques.
- Pas de logger dédié (à ajouter : Monolog ou simple fichier dans `storage/logs/`).

## 🛡 17. Troubleshooting & Debug
| Problème | Cause possible | Solution |
|----------|----------------|----------|
| 502 Bad Gateway | PHP-FPM crash, vendor absent | Vérifier `docker compose logs php`, lancer `composer install` |
| Class not found | `vendor/` monté vide | `docker compose exec php composer install` |
| Xdebug warning connexion | IDE pas à l’écoute | Ignorer ou configurer `XDEBUG_CLIENT_HOST` + IDE |
| Port déjà utilisé | Conflit local | Modifier mapping ports dans `docker-compose.yml` |
| Données MySQL perdues | `down -v` exécuté | Restaurer depuis `backups/` |

Inspection rapide :
```
docker compose ps
docker compose logs -f php
docker compose exec php php -v
```

## 💾 18. Backups manuels
MySQL :
```
docker compose exec db mysqldump -uecoride -pecoride-v2 ecoride > backups/mysql_dump.sql
```
Mongo :
```
docker compose exec mongo mongodump --archive > backups/mongo_dump.archive
```

## 🛣 19. Roadmap / Prochaines étapes
- Implémenter endpoints CRUD avis + agrégation moyenne (pipeline Mongo).
- Brancher réellement les repositories dans le Bootstrap (injection propre).
- Script de synchronisation Mongo ⇄ MySQL (rattrapage offline).
- Ajout d’un logger (Monolog) + traces fallback.
- Tests PHPUnit (unitaire + intégration API).
- Health enrichi (statuts Mongo/MySQL + version PHP + uptime).
- Ajout d’un Makefile (qualité de vie).
- CI (GitHub Actions) : build image + tests automatiques.

Mise à jour réalisée : endpoints avis (GET/POST + stats) et résilience opérationnels → ajuster la roadmap :
- [FAIT] Bascule résilience Mongo → MySQL
- [FAIT] Endpoints basiques avis
- [À FAIRE] Backfill avis fallback → Mongo
- [À FAIRE] Logging + métriques
- [À FAIRE] Tests automatisés
- [À FAIRE] Exposition source stockage (header)

---

## 🔒 Sécurité & Bonnes pratiques
- Ne jamais committer `.env`.
- Toujours committer `composer.lock`.
- `APP_ENV=prod` pour builds de déploiement (désactiver Xdebug + opcache config future).
- Prévoir plus tard des headers CSP/Nginx renforcés.

## ✅ Vérification rapide (script one-liner)
```
curl -fsSL http://localhost:8080/api/health || echo "Health endpoint KO"
```

## 🤝 Contribution
Branche de fonctionnalité : `feature/xxx` → PR vers `develop` → fusion vers `main` quand stable.

## © Licence
À définir.

---

> Dernière mise à jour : 2025-10-07
