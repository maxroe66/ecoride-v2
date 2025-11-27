# API Authentification - EcoRide v2

## Endpoints

### Inscription (Signup)

**POST** `/api/auth/signup`

Crée un nouveau compte utilisateur avec 20 crédits initiaux.

#### Requête

```json
{
  "pseudo": "votrepseudo",
  "email": "votre@email.com",
  "password": "VotreMotDePasse123"
}
```

#### Validation côté serveur

- **Pseudo**: 3-20 caractères, lettres/chiffres/tirets/underscores uniquement
- **Email**: Format email valide
- **Password**: 8+ caractères, 1 majuscule, 1 chiffre minimum
- **Unicité**: Pseudo et email doivent être uniques en base

#### Réponses

**201 Created - Succès**
```json
{
  "success": true,
  "data": {
    "utilisateur_id": 123,
    "pseudo": "votrepseudo",
    "email": "votre@email.com",
    "credit": 20.00,
    "message": "Inscription réussie. Vous avez reçu 20 crédits de bienvenue !"
  }
}
```

**400 Bad Request - Données manquantes**
```json
{
  "success": false,
  "error": {
    "code": "MISSING_FIELDS",
    "message": "Pseudo, email et mot de passe requis"
  }
}
```

**422 Unprocessable Entity - Validation échouée**
```json
{
  "success": false,
  "error": {
    "code": "SIGNUP_FAILED",
    "message": "Ce pseudo est déjà utilisé"  // ou autre erreur de validation
  }
}
```

---

### Connexion (Login)

**POST** `/api/auth/login`

Authentifie un utilisateur avec email/pseudo et mot de passe.

#### Requête

```json
{
  "email": "votre@email.com",  // ou pseudo
  "password": "VotreMotDePasse123",
  "remember": false  // optionnel, pour session persistante
}
```

#### Réponses

**200 OK - Succès**
```json
{
  "success": true,
  "data": {
    "utilisateur_id": 123,
    "pseudo": "votrepseudo",
    "email": "votre@email.com",
    "nom": "Anonymous",
    "prenom": "User",
    "credit": 20.00,
    "type_utilisateur": "standard"
  },
  "redirect": "/"
}
```

**400 Bad Request - Données manquantes**
```json
{
  "success": false,
  "error": {
    "code": "MISSING_FIELDS",
    "message": "Email/Pseudo et mot de passe requis"
  }
}
```

**401 Unauthorized - Identifiants invalides**
```json
{
  "success": false,
  "error": {
    "code": "LOGIN_FAILED",
    "message": "Identifiant ou mot de passe incorrect"
  }
}
```

---

### Déconnexion (Logout)

**POST** `/api/auth/logout`

Détruit la session utilisateur.

#### Requête
Pas de corps nécessaire.

#### Réponses

**200 OK - Succès**
```json
{
  "success": true,
  "data": {
    "message": "Déconnexion réussie"
  }
}
```

**500 Internal Server Error**
```json
{
  "success": false,
  "error": {
    "code": "LOGOUT_FAILED",
    "message": "Erreur détail"
  }
}
```

---

## Frontend Integration

### Validation côté client (auth.js)

Le formulaire valide les données avant envoi:

#### Pseudo
- Min 3 caractères, max 20
- Caractères autorisés: `a-z`, `A-Z`, `0-9`, `-`, `_`

#### Email
- Format email valide (`user@domain.com`)

#### Mot de passe
- Min 8 caractères
- Au moins 1 majuscule (A-Z)
- Au moins 1 chiffre (0-9)
- Indicateur de force en temps réel

### Gestion Session

Après une connexion réussie, la session PHP est créée:

```php
$_SESSION['user'] = [
    'utilisateur_id' => 123,
    'pseudo' => 'votrepseudo',
    'email' => 'votre@email.com',
    'nom' => 'Anonymous',
    'prenom' => 'User',
    'credit' => 20.00,
    'type_utilisateur' => 'standard'
];
```

Le header affiche automatiquement le menu utilisateur ou les boutons connexion/inscription en fonction de `$_SESSION['user']`.

---

## Exemple d'utilisation

### Avec curl

```bash
# Inscription
curl -X POST http://localhost:8080/api/auth/signup \
  -H "Content-Type: application/json" \
  -d '{
    "pseudo": "newuser",
    "email": "new@example.com",
    "password": "SecurePass123"
  }'

# Connexion
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "new@example.com",
    "password": "SecurePass123"
  }'

# Déconnexion
curl -X POST http://localhost:8080/api/auth/logout \
  -H "Content-Type: application/json"
```

### Avec JavaScript

```javascript
// Inscription
async function signup(pseudo, email, password) {
  const response = await fetch('/api/auth/signup', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ pseudo, email, password })
  });
  return await response.json();
}

// Connexion
async function login(email, password) {
  const response = await fetch('/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  const data = await response.json();
  if (data.success) {
    window.location.href = data.redirect || '/';
  }
  return data;
}

// Déconnexion
async function logout() {
  const response = await fetch('/api/auth/logout', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' }
  });
  if (response.ok) {
    window.location.href = '/';
  }
}
```

---

## Sécurité

- ✅ Mots de passe hashés avec **bcrypt** (cost 12)
- ✅ Sessions PHP sécurisées
- ✅ Validation input côté client ET serveur
- ✅ Protection SQL injection via prepared statements
- ✅ Vérification unicité pseudo/email
- ✅ Gestion compte suspendu

## Notes

- Les utilisateurs reçoivent **20 crédits** à la création
- Le type d'utilisateur par défaut est **"standard"**
- Les sessions persistent tant que le cookie n'est pas expiré
- Le mot de passe n'est jamais renvoyé à la réponse API
