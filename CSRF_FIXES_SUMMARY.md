# Résumé des Corrections CSRF (4 décembre 2025)

## 🔴 Problèmes Identifiés et Corrigés

### 1. **Méthode fantôme `CsrfMiddleware::validateToken()`**
**Statut**: ✅ CORRIGÉ

**Problème**:
- `ParticipationController.php` ligne 59 appelait `$csrf->validateToken()` qui n'existait pas
- Causait une erreur fatale lors de l'annulation de participation

**Solution**:
```php
// ❌ Avant
$csrf = new CsrfMiddleware();
if (!$csrf->validateToken()) {  // N'existe pas !
    ...
}

// ✅ Après
(new CsrfMiddleware())->validate();
```

---

### 2. **Méthode fantôme `AuthMiddleware::getAuthenticatedUser()`**
**Statut**: ✅ CORRIGÉ

**Problème**:
- `ParticipationController.php` ligne 37 appelait `AuthMiddleware::getAuthenticatedUser()` qui n'existait pas
- La vraie méthode est `AuthMiddleware::authenticate()`

**Solution**:
```php
// ❌ Avant
$user = AuthMiddleware::getAuthenticatedUser();

// ✅ Après
$authMw = new AuthMiddleware();
$userData = $authMw->authenticate();
$userId = (int)$userData['user_id'];
```

---

### 3. **Patterns CSRF inconsistants dans les contrôleurs**
**Statut**: ✅ CORRIGÉ

**Problème**:
Vous aviez 3 approches différentes :

| Approche | Fichiers | Statut |
|----------|----------|--------|
| `(new CsrfMiddleware())->validate()` | AvisController, UserController, AuthController | ✅ Bon |
| `$csrf->validateToken()` | ParticipationController ligne 59 | ❌ Fantôme |
| `if (REQUEST_METHOD === 'POST') { $csrf->validate() }` | TrajetController ligne 479 | ⚠️ Inefficace |

**Solution appliquée**:
- Tous les contrôleurs utilisent maintenant le pattern **standardisé** : `(new CsrfMiddleware())->validate()`
- La vérification de `REQUEST_METHOD` n'est pas nécessaire puisque CSRF doit être validé pour **toutes** les requêtes mutatrices (POST, PUT, DELETE)

```php
// ✅ Pattern unifié dans TrajetController, ParticipationController
// 1. AUTHENTIFICATION
try {
    $authMw = new AuthMiddleware();
    $userData = $authMw->authenticate();
    $userId = (int)$userData['user_id'];
} catch (Exception $e) {
    http_response_code(401);
    ...
}

// 2. CSRF
(new CsrfMiddleware())->validate();

// 3. Logique métier
...
```

---

### 4. **Duplication de vérification `session_status()` dans CsrfService**
**Statut**: ✅ CORRIGÉ

**Problème**:
```php
// ❌ Avant : duplication inutile
public static function getToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    // ...
}

public static function validate(?string $provided): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    // ...
}
```

**Solution**:
```php
// ✅ Après : méthode helper centralisée
private static function ensureSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

public static function getToken(): string
{
    self::ensureSession();
    // ...
}

public static function validate(?string $provided): bool
{
    self::ensureSession();
    // ...
}
```

---

### 5. **Variable intermédiaire inutile dans CsrfMiddleware**
**Statut**: ✅ CORRIGÉ

**Problème**:
```php
// ❌ Avant : variable inutile
$header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
$isValid = CsrfService::validate($header);
if (!$isValid) {
    ...
}
```

**Solution**:
```php
// ✅ Après : plus directionnel
$header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!CsrfService::validate($header)) {
    ...
}
```

---

## 📋 Fichiers Modifiés

| Fichier | Changements |
|---------|-----------|
| `app/Controllers/ParticipationController.php` | Correction auth + CSRF, remplacement `validateToken()` → `validate()` |
| `app/Controllers/TrajetController.php` | Simplification pattern CSRF, suppression vérification `REQUEST_METHOD` |
| `app/Services/CsrfService.php` | Centralisation `session_start()` dans méthode `ensureSession()` |
| `app/Middleware/CsrfMiddleware.php` | Suppression variable intermédiaire `$isValid` |

---

## ✅ Résultat Final

### Flux d'authentification et CSRF standardisé

Tous les endpoints mutateurs (POST, PUT, DELETE) protégés suivent maintenant ce flux :

```
1. Définir header Content-Type: application/json
2. AUTHENTIFICATION: AuthMiddleware::authenticate()
3. CSRF: CsrfMiddleware::validate()
4. Logique métier
```

### Routes Protégées

| Route | Méthode | Auth | CSRF | Statut |
|-------|---------|------|------|--------|
| `/api/auth/logout` | POST | ✅ | ✅ | ✅ OK |
| `/api/avis` | POST | ✅ | ✅ | ✅ OK |
| `/api/trajets` | POST | ✅ | ✅ | ✅ OK |
| `/api/trajets/{id}/annuler` | POST | ✅ | ✅ | ✅ CORRIGÉ |
| `/api/participations/request` | POST | ✅ | ✅ | ✅ OK |
| `/api/participations/validate` | POST | ✅ | ✅ | ✅ OK |
| `/api/participations/confirm` | POST | ✅ | ✅ | ✅ OK |
| `/api/participations/{id}/annuler` | POST | ✅ | ✅ | ✅ CORRIGÉ |
| `/api/user/profile` | PUT | ✅ | ✅ | ✅ OK |
| `/api/user/vehicles` | POST | ✅ | ✅ | ✅ OK |
| `/api/user/vehicles` | DELETE | ✅ | ✅ | ✅ OK |

---

## 🧪 Comment Tester

```bash
# 1. Récupérer un token CSRF
CSRF=$(curl -s -c cookies.txt -b cookies.txt -X POST -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"Password123"}' \
  http://localhost:8080/api/auth/login | jq -r '.data // empty')

# 2. Récupérer le token CSRF depuis l'API
CSRF=$(curl -s -c cookies.txt -b cookies.txt http://localhost:8080/api/csrf-token | jq -r '.data.csrfToken')

# 3. Appeler un endpoint mutatrice AVEC le token CSRF
curl -c cookies.txt -b cookies.txt \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $CSRF" \
  -d '{"covoiturage_id":1,"note":5}' \
  http://localhost:8080/api/avis

# ✅ Doit réussir avec le token CSRF
# ❌ Doit échouer 403 CSRF_FAILED sans le token
```

---

## 🎯 Bilan

- ✅ Toutes les méthodes fantômes supprimées
- ✅ Patterns CSRF standardisés partout
- ✅ Duplication de code éliminée
- ✅ Sécurité CSRF maintenue sur tous les endpoints critiques
- ✅ Code plus maintenable et cohérent
