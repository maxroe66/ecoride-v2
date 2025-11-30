# EcoRide v2 – Architecture Modulaire

Date: 2025-11-30

## Objectifs
- Séparer responsabilités (Single Responsibility / séparation couches)
- Préserver invariance des réponses API (structure JSON existante)
- Faciliter l’extension (ajout endpoints, services, validations) sans modifier le noyau
- Garantir auth sécurisée (JWT + cookie HttpOnly SameSite=Lax)

## Vue d’Ensemble des Couches
```
Client (Frontend) -> HTTP -> Nginx -> public/index.php -> Bootstrap
  -> Router -> Middleware -> Controller -> Service -> Repository -> (MySQL / MongoDB)
                                        -> Validator / DTO -> Response JSON
```

### Core
- `Bootstrap.php`: initialise autoload, environnement, enregistre routes API, déclenche dispatch puis fallback frontend.
- `Router.php`: registre des routes (méthode, chemin, action, middlewares). Fournit `dispatch()` pour exécuter l’action correspondante.
- `Request.php`: encapsule méthode, chemin, query params, corps JSON.
- `Response.php`: helper pour réponses JSON standard.

### Controllers
- Minces. Reçoivent Request/Middlewares, délèguent logique métier aux Services.
- Normalisent données (via méthodes statiques ou DTO) en conservant structure JSON.

### Services
- Regroupent logique métier transversale (authentification, trajets, avis, utilisateurs).
- Orchestrent appels aux Repositories et appliquent règles (choix fallback, calcul agrégats, formatage résultat).

### Repositories
- Responsables de l’accès aux données.
- `UserRepository` (MySQL), `TrajetRepository` (MySQL), `MongoAvisRepository` (MongoDB), `MysqlAvisRepository` (MySQL), `ResilientAvisRepository` (combinaison/fallback).
- But: isoler SQL/Mongo des couches supérieures.

### Models
- Objets métier simples (`User`, `Avis`). Représentation en mémoire.

### Middleware
- Exemple: `AuthMiddleware.php` vérifie présence/validité du JWT avant autoriser routes protégées (ex: POST avis).

### Validators
- `QueryValidator.php` centralise validations des entrées (trajets, suggestions, avis création, signup/login).
- Évite duplication de logique dans Controllers/Services.

### DTOs
- `LoginRequest` (normalisation input login), `UserResponse` (format standard sortie utilisateur + éventuel token).
- Permettent évolution interne sans changer contrat externe.

## Flux d’une Requête Type (POST /api/auth/login)
1. Nginx redirige vers `public/index.php`.
2. `Bootstrap.php` crée Router et enregistre routes.
3. Router match `/api/auth/login` + méthode POST.
4. Exécution contrôleur `AuthController::login()`.
5. Création `LoginRequest` depuis corps JSON, validation simple.
6. Service `AuthService` : vérifie credentials via `UserRepository`, génère JWT (`JwtService`), pose cookie (`CookieManager`).
7. Réponse normalisée JSON (success, data.user, data.token).

## Conventions de Réponses JSON
Structure générale succès:
```
{
  "success": true,
  "data": { ... }
}
```
Structure échec:
```
{
  "success": false,
  "error": {
    "code": "INVALID_INPUT|UNAUTHORIZED|NOT_FOUND|SERVER_ERROR|...",
    "message": "Message lisible",
    "details": [optional warnings]
  }
}
```
- Ne pas modifier clés existantes pour compatibilité frontend.
- Codes HTTP: 200 pour succès général, 201 création (signup attendu 201 – vérifier si route actuelle renvoie 200 et réaligner), 400 validation, 401 auth, 404 ressource, 500 interne.

## Authentification
- JWT signé HMAC SHA256 (secret environnement `JWT_SECRET`).
- Claims: iat, exp (durée), sub (user id/pseudo/email selon implémentation).
- Token stocké cookie HttpOnly SameSite=Lax (anti XSS + CSRF basique). Pas de localStorage.
- Middleware peut valider: décodage + signature + expiration.

## Extension Points
| Objet | Ajouter | Étapes |
|-------|---------|--------|
| Route | Nouveau endpoint | 1) Créer action dans Controller 2) Enregistrer via `Bootstrap::routeApiWithRouter()` 3) (Option) Middleware |
| Controller | Nouvelle ressource métier | 1) Fichier dans `Controllers/` 2) Injection services nécessaires 3) Normaliser output |
| Service | Nouvelle logique | 1) Fichier dans `Services/` 2) Dépendances repositories/validators 3) Méthodes publiques cohérentes |
| Repository | Nouvelle source données | 1) Implémenter accès (SQL/Mongo) 2) Exposer méthodes utilisées par Service 3) Ajouter si fallback nécessaire |
| Middleware | Filtre transversal | 1) Classe dans `Middleware/` 2) Ajouter dans registration route `add(..., [AuthMiddleware::class])` |
| Validator | Règle input | 1) Méthode dans `QueryValidator` ou nouveau fichier 2) Appel dans Controller avant Service |
| DTO | Contrat interne stable | 1) Créer classe dans `DTO/` 2) Méthodes statiques `fromArray()/toArray()` |

## Diagramme ASCII Couches
```
          +------------------+
          |    Frontend      |
          +---------+--------+
                    |
                HTTP Request
                    |
          +---------v--------+
          |     Nginx        |
          +---------+--------+
                    |
          +---------v--------+
          |   index.php      |
          +---------+--------+
                    |
          +---------v--------+
          |   Bootstrap      |
          +---------+--------+
                    |
          +---------v--------+
          |     Router       |
          +--+-----------+---+
             |           |
     (MW chain)       No MW
             |           |
          +--v-----------v--+
          |    Controller    |
          +--------+---------+
                   |
          +--------v---------+
          |     Service      |
          +----+--------+----+
               |        |
       +-------v--+  +--v-------+
       |Repository |  |Validator |
       +-------+---+  +----+----+
               |          |
          +----v----+     |
          |  DB /   |     |
          | MongoDB |     |
          +---------+     |
                           |
                       +---v---+
                       |  DTO  |
                       +-------+
```

## Tests
- PHPUnit: tests unitaires sur Services avec stubs pour isoler logique métier.
- Couverture visée: Services + Validators critiques + JwtService.
- Recommandation future: Tests d’intégration (requête HTTP simulée) pour vérifier pipeline complet.

## Améliorations Futures
1. Interfaces pour repositories (`UserRepositoryInterface`, `TrajetRepositoryInterface`, etc.) et rétablir type-hints stricts dans Services.
2. Conteneur d’injection de dépendances (simple factory ou Pimple/DI maison) pour éviter instanciations répétées.
3. Gestion des erreurs centralisée (exception -> mapper -> réponse JSON).
4. Logger structuré (monolog) pour événements principaux (auth, création avis, échec validation).
5. Rate limiting ou protection brute-force login.
6. Refresh token séparé si sessions longues.

## Interfaces & DI
- Interfaces introduites pour durcir les types et faciliter les tests:
  - `App\Repositories\UserRepositoryInterface`: `create(User): int`, `findByEmail(string): ?User`, `findByPseudo(string): ?User`, `emailExists(string): bool`, `pseudoExists(string): bool`.
  - `App\Repositories\TrajetRepositoryInterface`: `searchTrajets(...)`, `searchTrajetsWithFilters(...)`, `getNextAvailableDates(...)`, `getNextAvailableDatesWithFilters(...)`.
- Implémentations concrètes:
  - `UserRepository implements UserRepositoryInterface`
  - `TrajetRepository implements TrajetRepositoryInterface`
- Services typés sur interfaces:
  - `UserService(UserRepositoryInterface)`
  - `TripService(TrajetRepositoryInterface)`
- Tests: stubs implémentent les interfaces pour isoler la logique métier.
- DI (optionnel):
  - Aujourd’hui, les controllers instancient directement les repositories.
  - Évolution possible: une simple factory ou un container pour injecter les implémentations (mockables en tests d’intégration).

## Checklist Ajout Endpoint
- [ ] Définir besoin métier (données, validations, erreurs possibles)
- [ ] Créer méthodes Service + tests unitaires
- [ ] Ajouter route dans Bootstrap + Controller action
- [ ] Ajouter validations (Validator/DTO)
- [ ] Tester via curl (statuts + structure JSON)
- [ ] Ajouter scénario test PHPUnit si logique complexe

## Sécurité
- Cookie HttpOnly réduit risque vol token via JS.
- Vérifier toujours expiration token côté middleware.
- Paramètres d’entrée validés (types, formats) par `QueryValidator`.
- Prévoir sanitation supplémentaire si champs libres (ex: commentaires avis).

## Performance
- Limiter résultats trajets: pagination ou LIMIT raisonnable (actuel LIMIT 100).
- Fallback Avis: `ResilientAvisRepository` peut introduire latence — surveiller temps moyen.
- Ajouter cache sur suggestions trajets (clé composite départ+arrivée+filtres).

## Statut Actuel
- Refactor terminé couches principales.
- Tests Services opérationnels (6 tests, 16 assertions).
- À aligner: code retour signup (doit être 201 si création).
- Prochaine cible: interfaces + doc vivante.

## Glossaire
- DTO: Data Transfer Object
- MW: Middleware
- Fallback: stratégie de recours à une autre source si échec.

---
Ce document évoluera avec les prochaines itérations (interfaces & DI).
