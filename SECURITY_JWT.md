# Sécurité Authentification - JWT + Cookies

## 🔐 Améliorations de sécurité implémentées

### 1. **Tokens JWT (JSON Web Tokens)**

Les tokens JWT remplacent les sessions basiques par un système stateless et sécurisé.

#### Structure du JWT

```
Header.Payload.Signature

Header:     {"alg": "HS256", "typ": "JWT"}
Payload:    {"user_id": 5, "pseudo": "bob2024", "email": "bob@example.com", "iat": 1234567890, "exp": 1234654290}
Signature:  HMAC-SHA256(Header.Payload, secret_key)
```

#### Génération (Login)

```php
$jwtService = new JwtService();
$token = $jwtService->generate([
    'user_id' => 5,
    'pseudo' => 'bob2024',
    'email' => 'bob@example.com'
]);
// Retourne: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo1LCJwc2V1ZG8iOiJib2IyMDI0IiwiZW1haWwiOiJib2JAZXhhbXBsZS5jb20iLCJpYXQiOjE3NjQyNDk5ODgsImV4cCI6MTc2NDg1NDc4OH0.vH4pdWkS3dvjIPiKOuXnwv4A-pmOSQZZXlR8jV-tAVw
```

#### Validation

```php
try {
    $payload = $jwtService->validate($token);
    // Retourne: ["user_id" => 5, "pseudo" => "bob2024", "email" => "bob@example.com", ...]
} catch (Exception $e) {
    // Token invalide ou expiré
}
```

#### Avantages

- ✅ **Stateless**: Pas besoin de stocker les sessions serveur
- ✅ **Portable**: Peut être transmis en header ou cookie
- ✅ **Sécurisé**: Signé avec HMAC-SHA256
- ✅ **Expirant**: Expire automatiquement après 7 jours
- ✅ **Décentralisé**: Valide sans accès à la base de données

---

### 2. **Cookies Sécurisés (HttpOnly + Secure + SameSite)**

Les cookies stockent le JWT de manière sécurisée, protégée contre XSS et CSRF.

#### Flags de sécurité

| Flag | Effet | Protection |
|------|-------|-----------|
| **HttpOnly** | Cookie pas accessible via JavaScript | XSS (vol de cookie) |
| **Secure** | Cookie envoyé seulement en HTTPS | Man-in-the-middle |
| **SameSite=Lax** | Cookie pas envoyé vers sites externes | CSRF (requêtes cross-site) |

#### Création du cookie (Login)

```php
$cookieManager = new CookieManager();
$cookieManager->setToken($token);

// Crée un cookie:
// Set-Cookie: ecoride_token=<JWT>; Path=/; HttpOnly; Secure; SameSite=Lax; Expires=...
```

#### Récupération du cookie

```php
$token = $cookieManager->getToken();
// Récupère le JWT depuis $_COOKIE['ecoride_token']
```

#### Suppression du cookie (Logout)

```php
$cookieManager->deleteToken();
// Expédie un cookie avec expiration passée
```

---

### 3. **Middleware AuthMiddleware**

Valide l'authentification sur les routes protégées.

#### Utilisation dans Bootstrap

```php
// Pour protéger une route
private function handleProtectedRoute(): void
{
    $user = $this->requireAuth();  // Lève 401 si pas authentifié
    
    // $user contient: ["user_id" => 5, "pseudo" => "bob2024", ...]
    echo json_encode(['success' => true, 'data' => $user]);
}
```

#### Sources du token (ordre de priorité)

1. **Cookie** `ecoride_token` (priorité haute)
2. **Header** `Authorization: Bearer <token>` (priorité moyenne)
3. **Query** `?token=<token>` (priorité basse)

#### Exemple avec curl

```bash
# 1. Avec cookie (automatique après login)
curl -b "ecoride_token=<jwt>" http://localhost:8080/api/protected-route

# 2. Avec header Authorization
curl -H "Authorization: Bearer <jwt>" http://localhost:8080/api/protected-route

# 3. Avec query param (moins sûr)
curl http://localhost:8080/api/protected-route?token=<jwt>
```

---

## 🔄 Flux d'authentification complet

### **Inscription**

```
1. Client POST /api/auth/signup
   ├─ pseudo: "bob2024"
   ├─ email: "bob@example.com"
   └─ password: "BobPassword456"

2. Serveur
   ├─ Valide données (format, unicité)
   ├─ Hash password avec bcrypt
   ├─ Crée utilisateur en base
   └─ Attribue 20 crédits

3. Réponse 201
   {
     "success": true,
     "data": {
       "utilisateur_id": 5,
       "pseudo": "bob2024",
       "email": "bob@example.com",
       "credit": 20.00
     }
   }
```

### **Connexion**

```
1. Client POST /api/auth/login
   ├─ email: "bob@example.com"
   └─ password: "BobPassword456"

2. Serveur
   ├─ Cherche utilisateur (email ou pseudo)
   ├─ Vérifie password avec bcrypt
   ├─ Génère JWT token
   ├─ Crée cookie HttpOnly
   └─ Crée session PHP (compatibilité)

3. Réponse 200
   Headers:
   Set-Cookie: ecoride_token=<JWT>; HttpOnly; Secure; SameSite=Lax

   Body:
   {
     "success": true,
     "data": {
       "utilisateur_id": 5,
       "pseudo": "bob2024",
       "email": "bob@example.com",
       "credit": 20.00,
       "token": "<JWT>"
     }
   }
```

### **Requête protégée**

```
1. Client GET /api/protected-route
   (Cookie envoyé automatiquement)

2. Serveur
   ├─ Extrait token du cookie
   ├─ Valide signature JWT
   ├─ Vérifie expiration
   └─ Retourne données utilisateur

3. Réponse 200
   {
     "success": true,
     "user_id": 5,
     "pseudo": "bob2024",
     "data": {...}
   }
```

### **Déconnexion**

```
1. Client POST /api/auth/logout

2. Serveur
   ├─ Efface le cookie (expiration passée)
   └─ Détruit la session PHP

3. Réponse 200
   Headers:
   Set-Cookie: ecoride_token=; Expires=<past>

   Body:
   { "success": true, "message": "Déconnexion réussie" }
```

---

## 🛡️ Protections contre attaques courantes

| Attaque | Protection | Détails |
|---------|-----------|---------|
| **XSS** (Vol de cookie) | HttpOnly flag | Cookie pas accessible via JavaScript |
| **CSRF** | SameSite flag | Cookie pas envoyé vers domaines externes |
| **Man-in-the-middle** | Secure flag | Cookie seulement en HTTPS (production) |
| **Token replay** | Expiration JWT | Token expire après 7 jours |
| **Brute force** | Validation bcrypt | Hash coûteux (cost 12) |
| **SQL injection** | Prepared statements | Paramètres liés (PDO) |
| **Force brute password** | Hashage sécurisé | Impossible à retrouver en clair |

---

## ⚙️ Configuration

### Variables d'environnement (optionnel)

```bash
# Dans .env ou variables système
JWT_SECRET=your-super-secret-key-change-in-production
DB_HOST=db
DB_USER=ecoride
DB_PASSWORD=ecoride-v2
```

### Durée de vie (par défaut)

- **JWT**: 7 jours (604800 secondes)
- **Cookie**: 7 jours
- Modifiable dans `JwtService` et `CookieManager`

### HTTPS en production

Le flag `Secure` du cookie:
- ✅ Désactivé en **développement** (localhost)
- ✅ Activé en **production** (domaine réel)

Vérifier: `if ($_SERVER['HTTP_HOST'] !== 'localhost')`

---

## 📋 Classes et méthodes

### JwtService

```php
$jwt = new JwtService($secret, $expirationTime);

$jwt->generate(array $payload): string  // Crée un JWT
$jwt->validate(string $token): array    // Valide et décode
JwtService::getTokenFromHeader(?string): ?string  // Parse Authorization header
```

### CookieManager

```php
$cookie = new CookieManager($cookieLifetime);

$cookie->setToken(string $token): void      // Crée le cookie
$cookie->getToken(): ?string                // Récupère du cookie
$cookie->hasToken(): bool                   // Vérifie existence
$cookie->deleteToken(): void                // Efface le cookie
CookieManager::getCookieName(): string      // Retourne le nom
```

### AuthMiddleware

```php
$middleware = new AuthMiddleware($jwt, $cookie);

$middleware->authenticate(): array          // Valide, retourne payload ou 401
$middleware->isAuthenticated(): bool        // Vérifie sans exception
$middleware->getUser(): ?array              // Récupère user ou null
```

### AuthService (modifié)

```php
$auth = new AuthService($userRepository, $jwtService, $cookieManager);

$auth->signup(...): array           // Inscription (inchangé)
$auth->login(...): array            // Login + JWT + Cookie
$auth->logout(): void               // Logout + delete cookie
```

---

## 🧪 Tests

### Test d'inscription

```bash
curl -X POST http://localhost:8080/api/auth/signup \
  -H "Content-Type: application/json" \
  -d '{"pseudo":"testuser","email":"test@example.com","password":"TestPass123"}'
```

### Test de connexion

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"TestPass123"}' \
  -v  # -v pour voir les headers Set-Cookie
```

### Test d'une route protégée (à implémentation)

```bash
# Après login, utiliser le token
curl -H "Authorization: Bearer <token>" \
  http://localhost:8080/api/protected-route

# Ou le cookie est automatique (depuis le login)
curl http://localhost:8080/api/protected-route
```

---

## 📚 Références

- [JWT.io](https://jwt.io/) - Création et décodage de JWT
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [MDN: HttpOnly Cookies](https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies#security)
- [OWASP: CSRF Prevention](https://owasp.org/www-community/attacks/csrf)

---

## ✅ Checklist sécurité

- [x] Mots de passe hashés avec bcrypt
- [x] JWT tokens avec signature HMAC-SHA256
- [x] Cookies HttpOnly
- [x] Cookies Secure (HTTPS production)
- [x] Cookies SameSite=Lax
- [x] Validation input côté serveur
- [x] Prepared statements (SQL injection)
- [x] Token expiration
- [x] Middleware d'authentification
- [ ] Rate limiting (TODO)
- [ ] Refresh tokens (TODO)
- [ ] 2FA / MFA (TODO)
