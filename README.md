<div align="center">

# 🚗 EcoRide V2

**Plateforme de covoiturage PHP sans framework**

Architecture moderne • Request-centric • JWT HttpOnly • Tests automatisés

[Documentation Architecture](ARCHITECTURE.md) • [Guide Contribution](CONTRIBUTING.md)

</div>

---

## 🎯 Vue d'ensemble

EcoRide v2 est une plateforme de covoiturage développée en **PHP pur** (sans framework), conçue pour maîtriser chaque couche de l'application et démontrer une architecture backend professionnelle.

**Stack technique**:
- **Backend**: PHP 8.3 (architecture MVC custom)
- **Bases de données**: MySQL 8.4 + MongoDB 7 (stratégie fallback)
- **Web server**: Nginx + PHP-FPM
- **Authentification**: JWT dans cookies HttpOnly + protection CSRF
- **Environnement**: Docker Compose (reproductible)

**Architecture**:
```
Request → DTO (structure) → Validator (métier) → Service (logique) → Repository (données)
```

---

## ⚡ Démarrage rapide

### Prérequis
- Docker Desktop avec WSL2 (Windows) ou Docker Engine (Linux/Mac)
- Git

### Installation

1. **Cloner le projet**:
```bash
git clone git@github.com:maxroe66/ecoride-v2.git
cd ecoride-v2
```

2. **Configuration environnement**:
```bash
cp .env.example .env
```

3. **Démarrer les services**:
```bash
docker compose up -d
```

4. **Installer dépendances** (si `vendor/` vide):
```bash
docker compose exec php composer install
```

5. **Vérifier**:
```bash
curl http://localhost:8080/api/health
```

Réponse attendue: `{"success":true,"data":"ok"}`

---

## 🌐 Accès services

| Service | URL | Description |
|---------|-----|-------------|
| **Application** | http://localhost:8080 | Frontend + API REST |
| **phpMyAdmin** | http://localhost:8081 | Interface MySQL |
| **Mongo Express** | http://localhost:8082 | Interface MongoDB |

**Credentials DB** (voir `.env`):
- MySQL: `ecoride_user` / `ecoride_password`
- MongoDB: `ecoride_user` / `ecoride_password`

---

## 📁 Structure du projet

```
ecoride-v2/
├── app/                      # Code applicatif
│   ├── Controllers/          # Orchestration HTTP (6 controllers)
│   ├── Services/             # Logique métier (13 services)
│   ├── Repositories/         # Accès données (interfaces + implémentations)
│   ├── Validators/           # Règles métier (7 validators)
│   ├── DTO/                  # Validation structurelle (10 DTOs)
│   ├── Middleware/           # Auth + CSRF
│   ├── Core/                 # Request, Router, Bootstrap, Response
│   ├── Models/               # Entités (User, Trajet, Avis, etc.)
│   ├── Factories/            # Factories + ServiceLocator
│   └── Helpers/              # Utilitaires
├── public/                   # Front controller
│   └── index.php             # Point d'entrée unique
├── frontend/                 # HTML/CSS/JS
│   ├── pages/                # Pages SPA
│   ├── js/                   # Scripts (SessionManager, API calls)
│   └── css/                  # Styles
├── tests/                    # PHPUnit
│   ├── Controllers/
│   └── Services/
├── docker/                   # Configuration Docker
│   ├── nginx/
│   ├── php/
│   ├── mysql/
│   └── mongo/
├── storage/                  # Logs + cache
├── vendor/                   # Dépendances Composer
├── .env                      # Configuration (ne pas committer)
├── composer.json             # Dépendances PHP
├── docker-compose.yml        # Stack services
└── ARCHITECTURE.md           # Documentation architecture détaillée
```

---

## 🔑 Fonctionnalités principales

### Authentification
- ✅ Inscription / Connexion sécurisée (bcrypt)
- ✅ JWT dans cookie HttpOnly (anti-XSS)
- ✅ Protection CSRF (token session)
- ✅ Expiration automatique (24h)
- ✅ Refresh token CSRF après login

### Trajets
- ✅ Création trajet (chauffeur)
- ✅ Recherche multicritères (départ, arrivée, date, filtres)
- ✅ Suggestions de dates disponibles
- ✅ Historique trajets (chauffeur + passager)
- ✅ Annulation avec remboursement auto

### Participations
- ✅ Demande participation passager
- ✅ Approbation/refus chauffeur
- ✅ Gestion crédits automatique
- ✅ Notifications email annulation
- ✅ Historique participations

### Avis
- ✅ Création avis post-trajet
- ✅ Note 1-5 étoiles + commentaire
- ✅ Statistiques (moyenne, count)
- ✅ Résilience Mongo → MySQL (fallback automatique)

### Profil utilisateur
- ✅ Consultation solde crédits
- ✅ Historique opérations crédit
- ✅ Gestion véhicules
- ✅ Modification profil

---

## 🏗️ Architecture

### Pattern Request-Centric

**Objet `Request` unique** créé par le Router, passé à tous les middlewares et controllers:

```php
// Router crée Request une seule fois
$request = new Request($method, $uri);
$request->setPathParams([42]); // Extraction params dynamiques

// Middlewares enrichissent Request
AuthMiddleware($request);  // → $request->authUser = [...]
CsrfMiddleware($request);  // → valide token

// Controller utilise Request
public static function getTripById(Request $req): void {
    $id = $req->getPathParam(0);           // Param dynamique
    $userId = $req->getAuthUserId();       // Depuis JWT
    $data = $req->getJsonBody();           // Parse JSON (lazy)
    // ...
}
```

**Avantages**:
- ✅ Élimine 14 occurrences `file_get_contents('php://input')`
- ✅ Élimine 9 occurrences `parse_str($_SERVER['QUERY_STRING'])`
- ✅ Élimine usage `$_REQUEST` dans controllers
- ✅ Parse JSON une seule fois (lazy loading)
- ✅ Source unique de vérité

### Validation 2 phases

1. **DTO (structure)**: champs présents + types corrects
2. **Validator (métier)**: formats, limites, unicité, cohérence

```php
// Phase 1: Validation structure (DTO)
CreateTripRequest::fromArray($data);
// → Lance exception si champ manquant

// Phase 2: Validation métier (Validator)
TripCreationValidator::validate($data);
// → Vérifie date future, prix 2-1000, etc.
```

**Séparation stricte**:
- ❌ DTO ne valide PAS formats, limites, unicité
- ❌ Validator ne vérifie PAS présence champs (rôle DTO)

### Services & Repositories

**Services** (logique métier):
- Orchestration opérations complexes
- Coordination entre repositories
- Pas de validation (déjà faite en amont)

**Repositories** (accès données):
- Implémentent interfaces (injection dépendances)
- Uniquement SQL/Mongo, zéro logique métier
- Tests: repositories mockés

```php
// Service injecte repository interface
class TripService {
    public function __construct(
        private TrajetRepositoryInterface $repo
    ) {}
    
    public function createTrip(array $data, int $userId): array {
        return $this->repo->create($data, $userId);
    }
}

// Test: repository mocké
$repoMock = $this->createMock(TrajetRepositoryInterface::class);
$service = new TripService($repoMock);
```

---

## 🔒 Sécurité

### JWT (JSON Web Token)
- Algorithme: HMAC SHA256
- Secret: `JWT_SECRET` dans `.env` (256 bits minimum)
- Claims: `user_id`, `pseudo`, `email`, `iat`, `exp`
- Expiration: 24h (configurable)

### Cookie sécurisé
```php
Cookie: ecoride_token
Flags:
  - HttpOnly: true    // Invisible JavaScript
  - Secure: true      // HTTPS uniquement (production)
  - SameSite: Lax     // Protection CSRF basique
  - Path: /
  - MaxAge: 86400     // 24h
```

### Protection CSRF
- Token session PHP généré côté serveur
- Header requis: `X-CSRF-Token` pour POST/PUT/PATCH/DELETE
- Endpoint: `GET /api/csrf-token` après login
- Frontend: `SessionManager.csrfHeaders()` ajoute header automatiquement

### Validation données
- Tous les inputs validés (DTO + Validator)
- Requêtes préparées PDO (anti-SQL injection)
- Pas de `eval()`, `exec()`, ou fonctions dangereuses
- Pas de données sensibles dans logs

---

## 🧪 Tests

### Exécuter tests
```bash
# Tous les tests
docker compose exec php vendor/bin/phpunit

# Mode verbose
docker compose exec php vendor/bin/phpunit --testdox

# Test spécifique
docker compose exec php vendor/bin/phpunit tests/Services/AuthServiceTest.php
```

### Coverage actuelle
- ✅ Services: AuthService, TripService, ParticipationService
- ✅ Total: 6 tests, 16 assertions
- ⏳ À venir: DTOs, Validators, Controllers

---

## 📡 API Endpoints

### Authentification
```
POST   /api/auth/signup          Inscription (201)
POST   /api/auth/login           Connexion + cookie JWT
POST   /api/auth/logout          Déconnexion (clear cookie)
GET    /api/csrf-token           Récupère token CSRF
```

### Trajets
```
GET    /api/trajets              Liste + recherche multicritères
POST   /api/trajets              Créer trajet (auth + csrf)
GET    /api/trajets/{id}         Détails trajet
GET    /api/trajets/mes-trajets  Mes trajets chauffeur
GET    /api/trajets/suggestions  Suggestions dates
POST   /api/trajets/{id}/annuler Annuler trajet
```

### Participations
```
POST   /api/participations/request         Demander participation
POST   /api/participations/action          Approuver/refuser
GET    /api/participations/mes-demandes    Mes demandes passager
```

### Avis
```
GET    /api/avis                 Liste avis covoiturage
POST   /api/avis                 Créer avis (auth + csrf)
GET    /api/avis/stats           Statistiques (moyenne, count)
```

### Utilisateur
```
GET    /api/utilisateur/profil              Profil utilisateur
PUT    /api/utilisateur/profil              Modifier profil
POST   /api/utilisateur/vehicules           Ajouter véhicule
GET    /api/utilisateur/credits/operations  Historique crédits
```

### Historique
```
GET    /api/historique/trajets   Historique complet (chauffeur + passager)
```

### Health
```
GET    /api/health               Status serveur
```

**Format réponse standard**:
```json
// Succès
{"success": true, "data": {...}}

// Erreur
{"success": false, "error": {"code": "...", "message": "...", "details": {...}}}
```

---

## 🐳 Docker

### Services
- **nginx**: Reverse proxy + static files (port 8080)
- **php**: PHP 8.3 FPM + extensions (pdo_mysql, mongodb, xdebug)
- **db**: MySQL 8.4 (port 3306 interne)
- **mongo**: MongoDB 7 (port 27017 interne)
- **phpmyadmin**: Interface MySQL (port 8081)
- **mongo-express**: Interface MongoDB (port 8082)

### Volumes persistants
- `mysql_data`: Données MySQL
- `mongo_data`: Données MongoDB

### Commandes utiles
```bash
# Démarrer
docker compose up -d

# Arrêter
docker compose down

# Logs
docker compose logs -f php
docker compose logs -f nginx

# Shell PHP
docker compose exec php bash

# Rebuild image
docker compose build php
docker compose up -d

# Reset complet (⚠️ perd données)
docker compose down -v
docker compose up -d
```

---

## 🛠️ Développement

### Ajouter une feature

1. **Créer DTO** (`app/DTO/`)
2. **Créer Validator** (`app/Validators/`)
3. **Créer Service** (`app/Services/`) + tests
4. **Créer Repository** (si nouvelle entité)
5. **Ajouter Controller action** (`app/Controllers/`)
6. **Enregistrer route** dans `Bootstrap.php`
7. **Tester** via curl ou frontend
8. **Vérifier** 0 erreurs PHP
9. **Commit** avec message descriptif

**Exemple**:
```bash
# 1. Créer branche
git checkout -b feature/ma-feature

# 2. Développer (voir checklist ci-dessus)

# 3. Tester
docker compose exec php vendor/bin/phpunit
curl -X POST http://localhost:8080/api/ma-route \
  -H "Content-Type: application/json" \
  -d '{"field": "value"}'

# 4. Commit
git add .
git commit -m "feat: ajout ma-feature"
git push origin feature/ma-feature
```

### Conventions Git
- `feat:` nouvelle fonctionnalité
- `fix:` correction bug
- `refactor:` restructuration code
- `test:` ajout/modification tests
- `docs:` documentation

### Hot reload
- PHP: modifications visibles immédiatement (FPM reload auto)
- Nginx: `docker compose restart nginx` si conf modifiée
- Frontend: F5 navigateur (pas de build)

---

## 📚 Documentation détaillée

- **[ARCHITECTURE.md](ARCHITECTURE.md)**: Architecture complète, patterns, flux requêtes
- **[CONTRIBUTING.md](CONTRIBUTING.md)**: Guide contribution
- **[API_AUTH.md](API_AUTH.md)**: Détails authentification JWT + CSRF
- **[SECURITY_REPORT.md](SECURITY_REPORT.md)**: Rapport sécurité

---

## 🗺️ Roadmap

### ✅ Terminé
- Architecture Request-centric
- Refactor 6 controllers (DTO pattern)
- Élimination 773 lignes code redondant
- Tests unitaires services critiques
- Protection JWT + CSRF
- Résilience avis (Mongo → MySQL)

### 🚧 En cours
- Historique covoiturages (US10)
- Tests DTOs/Validators
- Documentation API complète

### 📋 Prochaines étapes
- Migration `ControllerHelper` → `Request` methods
- Logger structuré (Monolog)
- Rate limiting login
- Pagination API
- Refresh tokens
- Cache Redis (suggestions trajets)

---

## 🤝 Contribution

Les contributions sont bienvenues ! Voir [CONTRIBUTING.md](CONTRIBUTING.md).

**Processus**:
1. Fork le projet
2. Créer branche feature (`git checkout -b feature/ma-feature`)
3. Commit changements (`git commit -m 'feat: ajout ma-feature'`)
4. Push branche (`git push origin feature/ma-feature`)
5. Ouvrir Pull Request

---

## 📄 Licence

Projet éducatif - Titre professionnel Développeur Web et Web Mobile

---

## 👤 Auteur

**Maxime ROE**  
GitHub: [@maxroe66](https://github.com/maxroe66)

---

## 🙏 Remerciements

- Architecture inspirée par les bonnes pratiques PHP modernes
- Stratégie fallback inspirée par les systèmes distribués
- Pattern Request-centric pour éliminer redondances

---

<div align="center">

**Développé avec ❤️ pour maîtriser chaque couche**

</div>
