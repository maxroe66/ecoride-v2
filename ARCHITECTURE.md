# EcoRide v2 – Architecture Modulaire

**Dernière mise à jour**: 2025-12-08

## Objectifs
- Séparation stricte des responsabilités (Single Responsibility Principle)
- Architecture Request-centric: objet `Request` unique source de vérité
- Validation en 2 phases: DTO (structure) → Validator (règles métier)
- Services contiennent la logique métier, Repositories l'accès aux données
- Auth sécurisée: JWT dans cookie HttpOnly + protection CSRF
- Code maintenable: zéro redondance, logique au bon endroit

## Vue d'Ensemble des Couches

```
Client (Frontend) -> HTTP -> Nginx -> public/index.php -> Bootstrap
  -> Router (création Request unique)
    -> Middleware (reçoit & modifie Request)
      -> Controller (orchestre, reçoit Request)
        -> DTO (validation structurelle)
          -> Validator (règles métier)
            -> Service (logique métier)
              -> Repository (accès données)
                -> MySQL / MongoDB
```

---

## Architecture Détaillée

### Core (Architecture Request-Centric)

**Request.php** - Objet HTTP unifié, source unique de vérité
- **Propriétés publiques**:
  - `string $method`: GET, POST, PUT, PATCH, DELETE
  - `string $uri`: chemin de la requête
  - `array $authUser`: données utilisateur depuis JWT (injecté par AuthMiddleware)
  - `array $pathParams`: paramètres dynamiques de route (ex: {id}, {trajetId})
- **Méthodes**:
  - `getJsonBody()`: parse JSON body (lazy loading, uniquement POST/PUT/PATCH/DELETE)
  - `getQueryParams()`: parse query string GET
  - `getAuthUserId()`: retourne l'ID utilisateur authentifié
  - `getPathParam(int)`: accède aux paramètres dynamiques de route
  - `setAuthUser(array)`: injection des données auth par middleware
  - `setPathParams(array)`: injection des params par Router
- **Élimine**: 14 occurrences de `file_get_contents('php://input')`, 9 de `parse_str()`, usage de `$_REQUEST` dans controllers

**Router.php** - Matching et dispatch
- Crée l'objet Request **une seule fois** au début du traitement
- Routes exactes + routes dynamiques avec `{id}`, `{trajetId}`, etc.
- Passe Request aux middlewares: `$middleware($request)`
- Passe Request aux controllers: `($route['action'])($request)`
- Extrait pathParams via regex et les injecte: `$request->setPathParams([...])`
- Gère les middlewares en chaîne (auth → csrf → controller)

**Bootstrap.php** - Point d'entrée application
- Initialise autoload Composer, chargement `.env`
- Enregistre toutes les routes API avec leurs middlewares
- Dispatch via Router pour routes `/api/*`
- Fallback: sert les pages frontend statiques (HTML/CSS/JS)
- Endpoint `/api/health` pour health checks

**Response.php** - Helpers JSON standardisés
- `Response::json($data, $code)`: réponse succès
- `Response::error($message, $code, $errorCode)`: réponse erreur
- Format uniforme:
  ```json
  {"success": true, "data": {...}}
  {"success": false, "error": {"code": "...", "message": "..."}}
  ```

---

### Controllers (Orchestration uniquement)

**Rôle**: recevoir Request, valider via DTO/Validator, appeler Service, retourner Response

**Interdictions**:
- ❌ Logique métier (calculs, règles business)
- ❌ Accès direct DB
- ❌ Validation métier (rôle des Validators)
- ❌ `file_get_contents()`, `parse_str()`, `$_REQUEST`

**Pattern standard**:
```php
public static function create(Request $req): void {
    // 1. Extraire données
    $data = $req->getJsonBody();
    $userId = $req->getAuthUserId();
    
    // 2. Valider structure (DTO)
    $dto = CreateTripRequest::fromArray($data);
    
    // 3. Valider métier (Validator)
    TripCreationValidator::validate($data);
    TripValidator::validateVehicleOwnership($userId, $data['vehicule_id']);
    
    // 4. Appeler service
    $result = TripService::createTrip($data, $userId);
    
    // 5. Retourner
    Response::json($result, 201);
}
```

**Controllers existants**:
- `AuthController`: signup (201), login, logout, refreshCsrfToken
- `TrajetController`: create, list, myTrips, suggestions, getTripById
- `ParticipationController`: request, approve, decline, list
- `AvisController`: create, list, stats
- `UserController`: profile, addVehicle, updateProfile, getCreditOperations
- `HistoryController`: getUserHistory (chauffeur + passager)

---

### DTOs (Validation Structurelle)

**Rôle STRICT**: vérifier que les champs requis sont présents et ont le bon type

**Interdictions**:
- ❌ Règles métier (formats, limites, unicité, calculs)
- ❌ Accès DB
- ❌ Logique conditionnelle complexe

**Pattern**:
```php
class CreateTripRequest {
    public static function fromArray(array $data): void {
        if (!isset($data['lieu_depart'])) {
            throw new ValidationException(['lieu_depart' => 'Le lieu de départ est requis']);
        }
        // Répéter pour tous les champs requis
    }
}
```

**DTOs existants**:
- `SignupRequest`: vérifie présence email, pseudo, password
- `LoginRequest`: vérifie présence email, password
- `CreateTripRequest`: vérifie présence lieu_depart, date_depart, heure_depart, etc.
- `CreateAvisRequest`: vérifie présence note, covoiturage_id
- `RequestParticipationRequest`: vérifie trajet_id, nb_places_demandees
- `ParticipationActionRequest`: vérifie participation_id, action
- `AddVehicleRequest`: vérifie présence marque_id, modele, etc.
- `UpdateProfileRequest`: vérifie au moins un champ à modifier

**Principe**: Pas de vérification de format email, longueur, plage de valeurs → rôle du Validator

---

### Validators (Règles Métier)

**Rôle**: appliquer les contraintes business (formats, limites, unicité, cohérence)

**Méthodes statiques**: `validate(array): void` lance `ValidationException` si erreur

**Exemples de règles**:

**SignupValidator**:
- Email valide (format)
- Pseudo 3-20 caractères
- Password 8+ caractères
- Unicité email/pseudo (via Repository)

**TripCreationValidator**:
- Date format ISO (Y-m-d)
- Date future (pas dans le passé)
- Heure format HH:MM
- Nombre de places 1-8
- Prix 2-1000 crédits

**LoginValidator**:
- Email valide
- Password non vide

**AvisValidator**:
- Note 1-5
- Commentaire optionnel max 500 caractères
- Covoiturage existe

**TripValidator**:
- `validateTripId()`: trajet existe
- `validateDriverRole()`: utilisateur est chauffeur
- `validateVehicleOwnership()`: véhicule appartient au chauffeur

**ParticipationValidator**:
- Places disponibles suffisantes
- Utilisateur pas déjà participant
- Trajet pas annulé

---

### Services (Logique Métier)

**Rôle**: orchestrer les opérations métier, appliquer règles complexes, coordonner repositories

**Interdictions**:
- ❌ Validation (déjà faite en amont)
- ❌ Accès HTTP direct
- ❌ Echo/die/exit

**Injection**: repositories via interfaces (testabilité)

**Services existants**:

**AuthService** (`app/Services/AuthService.php`)
- `verifyCredentials(email, password)`: vérifie bcrypt
- `generateToken(user)`: crée JWT via JwtService
- `setAuthCookie(token)`: pose cookie HttpOnly via CookieManager

**TripService** (`app/Services/TripService.php`)
- `createTrip(array, userId)`: création trajet
- `filterUpcoming(array)`: filtre trajets passés/annulés
- `searchTrips(filters)`: recherche avec critères
- `getTripById(id)`: récupération détails

**ParticipationService** (`app/Services/ParticipationService.php`)
- `requestParticipation(trajetId, userId, nbPlaces)`: demande participation
- `approve(participationId)`: approuver participation
- `decline(participationId)`: refuser participation
- Gère débit/crédit utilisateurs

**CancellationService** (`app/Services/CancellationService.php`)
- `cancelTrip(trajetId, userId)`: annulation trajet (chauffeur)
- `cancelParticipation(trajetId, userId)`: annulation participation (passager)
- Remboursement automatique crédits
- Notifications email via EmailService

**CreditOperationService** (`app/Services/CreditOperationService.php`)
- `calculateTotals(operations)`: calcule total crédit/débit
- Évite duplication de logique calcul

**HistoryService** (`app/Services/HistoryService.php`)
- `getUserTripHistoryByStatus(userId, status)`: historique par statut
- Combine trajets chauffeur + participations passager

**ReviewService** (`app/Services/ReviewService.php`)
- `createReview(data, userId)`: création avis
- `getReviews(covoiturageId)`: liste avis
- `getStats(covoiturageId)`: moyenne + count

**EmailService** (`app/Services/EmailService.php`)
- `sendCancellationNotification(users, trip)`: email annulation
- Templates email transactionnels

**JwtService** (`app/Services/JwtService.php`)
- `generate(payload)`: crée JWT signé HMAC SHA256
- `decode(token)`: valide signature + expiration

**CookieManager** (`app/Services/CookieManager.php`)
- `setToken(token)`: cookie HttpOnly, Secure (prod), SameSite=Lax
- `getToken()`: lecture cookie
- `clearToken()`: suppression cookie

**CsrfService** (`app/Services/CsrfService.php`)
- `generateToken()`: token stocké en session
- `validate(request)`: vérifie header X-CSRF-Token

---

### Repositories (Accès Données)

**Rôle**: exclusivement accès DB (SQL, Mongo), aucune logique métier

**Interfaces**: tous implémentent des interfaces pour injection de dépendances

**Repositories MySQL**:

**UserRepository** (`app/Repositories/UserRepository.php`)
- Implémente `UserRepositoryInterface`
- `create(User): int`
- `findByEmail(string): ?User`
- `findByPseudo(string): ?User`
- `emailExists(string): bool`
- `pseudoExists(string): bool`
- `updateCredits(userId, amount): void`

**TrajetRepository** (`app/Repositories/TrajetRepository.php`)
- Implémente `TrajetRepositoryInterface`
- `create(array, userId): int`
- `searchTrajets(filters): array`
- `getNextAvailableDates(depart, arrivee): array`
- `findById(id): ?array`
- `updateStatus(id, status): void`

**ParticipationRepository** (`app/Repositories/ParticipationRepository.php`)
- Implémente `ParticipationRepositoryInterface`
- `create(array): int`
- `findById(id): ?array`
- `updateStatus(id, status): void`
- `findByTrajetAndUser(trajetId, userId): ?array`
- `findByTrajetId(trajetId): array`

**CreditOperationRepository** (`app/Repositories/CreditOperationRepository.php`)
- Implémente `CreditOperationRepositoryInterface`
- `create(userId, type, amount, description): int`
- `findByUserId(userId): array`

**VehicleRepository** (`app/Repositories/VehicleRepository.php`)
- `create(array): int`
- `findByUserId(userId): array`
- `findById(id): ?array`

**MarqueRepository** (`app/Repositories/MarqueRepository.php`)
- `findAll(): array`
- `findById(id): ?array`

**Repositories Avis** (stratégie résilience):

**MongoAvisRepository** (`app/Repositories/MongoAvisRepository.php`)
- Stockage primaire MongoDB
- Collection `avis`
- Agrégation pipeline pour stats

**MysqlAvisRepository** (`app/Repositories/MysqlAvisRepository.php`)
- Fallback MySQL
- Table `avis_fallback`

**ResilientAvisRepository** (`app/Repositories/ResilientAvisRepository.php`)
- Wrapper avec pattern fallback
- Cooldown 30s si Mongo down
- Bascule automatique MySQL si échec
- Implémente `AvisRepositoryInterface`

---

### Middleware (Filtres Transversaux)

**MiddlewareFactory.php** - Factory de middlewares réutilisables

**auth()**: retourne `function(Request $req)` qui:
- Vérifie cookie `ecoride_token`
- Décode JWT via `JwtService::decode()`
- Injecte userData dans Request: `$req->setAuthUser($userData)`
- Maintient `$_REQUEST['auth_user']` pour compatibilité legacy
- Lance 401 si token invalide/expiré

**csrf()**: retourne `function(Request $req)` qui:
- Valide header `X-CSRF-Token` via `CsrfService::validate()`
- Uniquement pour POST/PUT/PATCH/DELETE
- Lance 403 si token manquant/invalide

**AuthMiddleware.php** - Middleware d'authentification legacy
- Vérifie cookie `ecoride_token`
- Décode JWT et injecte dans `$_REQUEST['auth_user']`
- Utilisé sur routes protégées (POST avis, créer trajet, etc.)

**CsrfMiddleware.php** - Protection CSRF
- Valide header `X-CSRF-Token` pour requêtes mutatives
- Token stocké en session PHP
- Endpoint `GET /api/csrf-token` pour récupération après login

---

### Models (Entités Métier)

Objets simples représentant les données métier (pas de logique)

**User** (`app/Models/User.php`)
- Properties: id, email, pseudo, password_hash, credit, role, date_creation, etc.

**Avis** (`app/Models/Avis.php`)
- Properties: id, covoiturage_id, utilisateur_id, note, commentaire, date_creation

**Trajet** (`app/Models/Trajet.php`)
- Properties: id, utilisateur_id, lieu_depart, lieu_arrivee, date, heure, prix, nbPlaces, statut, etc.

**Vehicules** (`app/Models/Vehicules.php`)
- Properties: id, utilisateur_id, marque_id, modele, immatriculation, couleur, etc.

---

### Factories & Helpers

**UserFactory** (`app/Factories/UserFactory.php`)
- `fromSignup(array)`: crée User depuis données inscription (hash password une seule fois)
- `fromRow(array)`: hydrate User depuis résultat SQL
- Évite duplication logique création

**DatabaseFactory** (`app/Factories/DatabaseFactory.php`)
- `createMysqlConnection()`: PDO MySQL
- `createMongoConnection()`: MongoDB\Client
- Credentials depuis `.env`

**ServiceLocator** (`app/Factories/ServiceLocator.php`)
- Registry de services (singleton pattern)
- `get('ServiceName')`: retourne instance
- Utilisé par Controllers pour obtenir services

**ControllerHelper** (`app/Helpers/ControllerHelper.php`)
- `getAuthUserId()`: extrait user_id depuis `$_REQUEST['auth_user']` (legacy)
- **Déprécié**: préférer `$req->getAuthUserId()` (architecture moderne)

---

## Flux Complet d'une Requête

### Exemple 1: POST /api/trajets (Créer un trajet)

```
1. Nginx reçoit requête → public/index.php
2. Bootstrap initialise Router
3. Router match route POST /api/trajets
4. Router crée Request($method, $uri)
5. Router exécute middlewares dans l'ordre:
   a. AuthMiddleware($request) → vérifie JWT → $request->setAuthUser([...])
   b. CsrfMiddleware($request) → valide X-CSRF-Token
6. Router appelle TrajetController::create($request)
7. Controller:
   $data = $request->getJsonBody()              // Parse JSON une fois
   $userId = $request->getAuthUserId()          // Depuis authUser
   CreateTripRequest::fromArray($data)          // Validation structure
   TripCreationValidator::validate($data)       // Validation métier
   TripValidator::validateVehicleOwnership(...)
   $result = TripService::createTrip($data, $userId)
   Response::json($result, 201)
8. Response envoyée client
```

### Exemple 2: GET /api/trajets/{id} (Récupérer un trajet)

```
1-4. Même flux initial
5. Router extrait {id} via regex → $request->setPathParams([42])
6. Router appelle TrajetController::getTripById($request)
7. Controller:
   $id = $request->getPathParam(0)              // 42
   TripValidator::validateTripId($id)
   $trip = TripService::getTripById($id)
   Response::json($trip)
8. Response envoyée
```

### Exemple 3: POST /api/participations/request (Demander participation)

```
1-5. Flux standard (auth + csrf middlewares)
6. Controller ParticipationController::request($request)
7. Controller:
   $data = $request->getJsonBody()
   $userId = $request->getAuthUserId()
   RequestParticipationRequest::fromArray($data)
   ParticipationValidator::validate($data, $userId)
   $result = ParticipationService::requestParticipation(...)
   Response::json($result, 201)
```

---

## Conventions de Réponses JSON

### Succès
```json
{
  "success": true,
  "data": { 
    "user": {...},
    "token": "..." 
  }
}
```

### Erreur
```json
{
  "success": false,
  "error": {
    "code": "INVALID_INPUT",
    "message": "Validation échouée",
    "details": {
      "email": "Email invalide",
      "password": "Minimum 8 caractères"
    }
  }
}
```

### Codes HTTP
- `200`: Succès général (GET, PUT)
- `201`: Création réussie (POST signup, trajet, participation)
- `400`: Validation échouée
- `401`: Non authentifié
- `403`: Non autorisé / CSRF invalide
- `404`: Ressource non trouvée
- `500`: Erreur serveur

---

## Authentification & Sécurité

### JWT (JSON Web Token)
- **Algorithme**: HMAC SHA256
- **Secret**: variable `JWT_SECRET` dans `.env`
- **Claims**:
  - `iat`: timestamp création
  - `exp`: timestamp expiration (24h par défaut)
  - `user_id`: ID utilisateur
  - `pseudo`: pseudo utilisateur
  - `email`: email utilisateur

### Cookie Sécurisé
- **Nom**: `ecoride_token`
- **Flags**:
  - `HttpOnly`: true (inaccessible JavaScript → anti-XSS)
  - `Secure`: true en production (HTTPS uniquement)
  - `SameSite`: Lax (protection CSRF basique)
  - `Path`: /
  - `Expires`: 24h

### Protection CSRF
- Token généré côté serveur (session PHP)
- Header requis: `X-CSRF-Token` pour POST/PUT/PATCH/DELETE
- Endpoint: `GET /api/csrf-token` après login
- Frontend stocke token et l'inclut dans toutes requêtes mutatives

### Frontend SessionManager
- `localStorage`: stocke uniquement données affichage (pseudo, email)
- Token JWT reste dans cookie HttpOnly (invisible JS)
- `SessionManager.csrfHeaders()`: retourne headers avec token CSRF
- `SessionManager.refreshCsrfToken()`: récupère nouveau token après login

---

## Extension Points

### Ajouter une nouvelle route

1. **Créer action dans Controller**:
   ```php
   public static function myNewAction(Request $req): void {
       $data = $req->getJsonBody();
       // ...
       Response::json($result);
   }
   ```

2. **Enregistrer dans Bootstrap**:
   ```php
   $router->add('POST', '/api/ma-route', [MyController::class, 'myNewAction'], [
       MiddlewareFactory::auth(),
       MiddlewareFactory::csrf()
   ]);
   ```

### Ajouter un DTO

1. **Créer classe dans `app/DTO/`**:
   ```php
   class MyRequestDTO {
       public static function fromArray(array $data): void {
           if (!isset($data['field'])) {
               throw new ValidationException(['field' => 'Requis']);
           }
       }
   }
   ```

### Ajouter un Validator

1. **Créer classe dans `app/Validators/`**:
   ```php
   class MyValidator {
       public static function validate(array $data): void {
           if ($data['field'] < 5) {
               throw new ValidationException(['field' => 'Minimum 5']);
           }
       }
   }
   ```

### Ajouter un Service

1. **Créer classe dans `app/Services/`**:
   ```php
   class MyService {
       private MyRepositoryInterface $repo;
       
       public function __construct(MyRepositoryInterface $repo) {
           $this->repo = $repo;
       }
       
       public function doSomething(array $data): array {
           // Logique métier
           return $this->repo->save($data);
       }
   }
   ```

### Ajouter un Repository

1. **Créer interface dans `app/Repositories/`**:
   ```php
   interface MyRepositoryInterface {
       public function save(array $data): int;
       public function findById(int $id): ?array;
   }
   ```

2. **Implémenter interface**:
   ```php
   class MyRepository implements MyRepositoryInterface {
       private PDO $db;
       
       public function __construct(PDO $db) {
           $this->db = $db;
       }
       
       public function save(array $data): int {
           // SQL
       }
   }
   ```

---

## Tests

### PHPUnit
```bash
docker compose exec php vendor/bin/phpunit --testdox
```

### Tests existants
- `tests/Services/` : tests unitaires services
- Coverage: Services critiques (Auth, Trip, Participation)
- Mocks: repositories mockés via interfaces

### Exemple test
```php
public function testCreateTripSuccess(): void {
    $repoMock = $this->createMock(TrajetRepositoryInterface::class);
    $repoMock->expects($this->once())
        ->method('create')
        ->willReturn(1);
    
    $service = new TripService($repoMock);
    $result = $service->createTrip([...], 1);
    
    $this->assertEquals(1, $result['id']);
}
```

---

## Performances & Optimisations

### Request Object (Lazy Loading)
- `getJsonBody()`: parse JSON uniquement si appelé
- GET requests: pas de parsing JSON (économie mémoire/CPU)
- Query params: parse uniquement si `getQueryParams()` appelé

### Cooldown Avis (Résilience)
- Fallback MySQL si Mongo down
- Cooldown 30s empêche thrashing
- Variable future: `AVIS_COOLDOWN_SECONDS`

### Indexes DB
- Table `covoiturage`: index sur `lieu_depart`, `lieu_arrivee`, `date_depart`
- Table `participation`: index sur `trajet_id`, `utilisateur_id`, `statut`
- Table `utilisateur`: unique sur `email`, `pseudo`

### Pagination
- Limite actuelle: 100 résultats (trajets)
- Future: pagination via query params `?page=1&limit=20`

---

## Roadmap Future

### Court terme
1. ✅ Refactor controllers (DTO pattern)
2. ✅ Request-centric architecture
3. ✅ Éliminer code redondant (773 lignes)
4. ⏳ Migrer `ControllerHelper` vers `Request` methods
5. ⏳ Tests unitaires DTOs/Validators
6. ⏳ Tests intégration (curl automatisé)

### Moyen terme
1. Logger structuré (Monolog)
2. Header `X-Storage-Source` (Mongo/MySQL)
3. Backfill avis Mongo ← MySQL (CRON)
4. Rate limiting (login attempts)
5. Refresh tokens séparés
6. Pagination API

### Long terme
1. Conteneur DI (Pimple/PHP-DI)
2. Event dispatcher (annulation → email async)
3. Cache (Redis) pour suggestions trajets
4. API versioning (`/api/v1/`, `/api/v2/`)
5. GraphQL endpoint (alternative REST)

---

## Checklist Ajout Feature

- [ ] Définir besoin métier (données, validations, erreurs)
- [ ] Créer DTO (validation structure)
- [ ] Créer Validator (règles métier)
- [ ] Créer méthodes Service + tests unitaires
- [ ] Créer Repository (si nouvelle entité)
- [ ] Ajouter action Controller
- [ ] Enregistrer route dans Bootstrap
- [ ] Tester via curl (statuts + structure JSON)
- [ ] Vérifier 0 erreurs PHP (`get_errors`)
- [ ] Commit Git avec message descriptif

---

## Glossaire

- **DTO**: Data Transfer Object - validation structure
- **Validator**: validation règles métier
- **Service**: logique métier orchestration
- **Repository**: accès données (DB)
- **Middleware**: filtre transversal (auth, csrf)
- **Request**: objet HTTP unifié
- **Response**: helpers JSON
- **JWT**: JSON Web Token
- **CSRF**: Cross-Site Request Forgery
- **PSR-4**: Standard autoload PHP
- **Fallback**: stratégie de secours (Mongo → MySQL)
- **Lazy Loading**: chargement différé (parse JSON si nécessaire)
- **Cooldown**: période d'attente avant retry

---

## Diagramme Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         FRONTEND                             │
│  (HTML/CSS/JS + SessionManager + CSRF Token)                │
└────────────────────┬────────────────────────────────────────┘
                     │ HTTP Request
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                          NGINX                               │
│              (Reverse Proxy + Static Files)                  │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                   public/index.php                           │
│                      Bootstrap                               │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│                        Router                                │
│  • Crée Request object (authUser, pathParams)               │
│  • Match route + extrait params dynamiques                  │
│  • Exécute middlewares chaîne                               │
└────────┬───────────────────────────┬────────────────────────┘
         │                           │
    Middlewares                  Controller
         │                           │
    ┌────▼────┐              ┌───────▼───────┐
    │  Auth   │              │   DTO Check   │
    │  CSRF   │              │  (structure)  │
    └────┬────┘              └───────┬───────┘
         │                           │
         └──────────┬────────────────┘
                    ▼
         ┌──────────────────────┐
         │     Validator        │
         │  (règles métier)     │
         └──────────┬───────────┘
                    ▼
         ┌──────────────────────┐
         │      Service         │
         │  (logique métier)    │
         └──────────┬───────────┘
                    ▼
         ┌──────────────────────┐
         │     Repository       │
         │  (accès données)     │
         └──────────┬───────────┘
                    │
         ┌──────────▼───────────┐
         │   MySQL / MongoDB    │
         └──────────────────────┘
```

---

**Document maintenu à jour avec l'évolution du projet.**
