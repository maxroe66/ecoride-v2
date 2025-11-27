# Guide SessionManager - Gestion de Session Frontend

## 🎯 Objectif

`SessionManager.js` est une classe utilitaire qui gère **entièrement la session utilisateur côté client** :
- Stockage du token JWT et des données utilisateur
- Persistance entre les rechargements de page
- Suppression sécurisée lors de la déconnexion
- Vérification de l'authentification
- Détection d'expiration du token

## 📂 Architecture

### Fichiers impliqués

```
frontend/
├── js/
│   ├── SessionManager.js      # ⭐ Gestionnaire de session
│   ├── header.js              # Menu dynamique (utilise SessionManager)
│   └── auth.js                # Login/Signup (utilise SessionManager)
├── pages/
│   ├── login.php              # Page de connexion
│   ├── signup.php             # Page d'inscription
│   ├── profile.php            # ⭐ Page de profil (exemple utilisation)
│   └── accueil.php            # Page d'accueil (menu dynamique)
└── templates/layouts/
    └── header.php             # Header (dynamique avec SessionManager)
```

## 🔐 Comment ça marche

### 1️⃣ **Login** → Sauvegarde de la session

Quand l'utilisateur se connecte sur `/login` :

```javascript
// Dans auth.js - submit du formulaire
const response = await fetch('/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email, password })
});

const data = await response.json();

// ✅ Enregistrer la session
SessionManager.setUser(data.data);  // Token + données utilisateur

// Redirection vers accueil
window.location.href = '/';
```

**Ce que SessionManager.setUser() fait :**
- Stocke les données utilisateur dans `localStorage` (clé: `ecoride_user`)
- Stocke le token JWT dans `localStorage` (clé: `ecoride_token`)
- Dispatche un événement `userLoggedIn` pour que les autres composants réagissent

```javascript
localStorage.getItem('ecoride_user')  // { utilisateur_id, pseudo, email, credit }
localStorage.getItem('ecoride_token') // eyJhbGc...
```

### 2️⃣ **Affichage du menu** → Header dynamique

Sur chaque page, le `header.js` s'exécute et affiche le bon menu :

```javascript
// Dans header.js - initializeAuthMenu()
function initializeAuthMenu() {
  const isAuthenticated = SessionManager.isAuthenticated();
  const user = SessionManager.getUser();

  if (isAuthenticated && user) {
    // Affiche: Avatar + Pseudo + Dropdown (Profil | Déconnexion)
    authContainer.innerHTML = `
      <li class="user-menu">
        <button>👤 ${user.pseudo}</button>
        <ul class="dropdown">
          <li><a href="/profile">Mon profil</a></li>
          <li><a onclick="logout()">Déconnexion</a></li>
        </ul>
      </li>
    `;
  } else {
    // Affiche: Boutons "Connexion" + "Inscription"
    authContainer.innerHTML = `
      <li><a href="/login">Connexion</a></li>
      <li><a href="/signup">Inscription</a></li>
    `;
  }
}
```

### 3️⃣ **Logout** → Suppression de la session

Quand l'utilisateur clique sur "Déconnexion" :

```javascript
// Dans auth.js
async function logout() {
  if (!confirm('Êtes-vous sûr ?')) return;
  
  try {
    const response = await fetch('/api/auth/logout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    });

    if (response.ok) {
      // ✅ Effacer la session
      SessionManager.clearSession();  // localStorage vide + événement
    }
  } catch (error) {
    // Même en cas d'erreur, on efface la session locale
    SessionManager.clearSession();
  }

  // Redirection vers accueil
  window.location.href = '/';
}
```

**Ce que SessionManager.clearSession() fait :**
- Supprime `localStorage['ecoride_user']`
- Supprime `localStorage['ecoride_token']`
- Dispatche un événement `userLoggedOut` pour rafraîchir le menu
- Le header se réaffiche avec les boutons "Connexion" + "Inscription"

### 4️⃣ **Page de profil** → Vérification de l'authentification

Sur `/profile.php` :

```javascript
// Au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
  const user = SessionManager.getUser();

  if (!user) {
    // Pas connecté → redirection vers login
    window.location.href = '/login';
    return;
  }

  // Afficher les infos personnelles
  document.getElementById('userName').textContent = user.pseudo;
  document.getElementById('infoEmail').textContent = user.email;
  document.getElementById('infoCredit').textContent = user.credit + ' crédits';
});
```

## 📖 API SessionManager

### Méthodes principales

#### `isAuthenticated()`
Retourne `true` si l'utilisateur est connecté
```javascript
if (SessionManager.isAuthenticated()) {
  // Afficher contenu personnel
}
```

#### `getUser()`
Retourne les données utilisateur ou `null`
```javascript
const user = SessionManager.getUser();
// { utilisateur_id, pseudo, email, credit, type_utilisateur }
```

#### `getToken()`
Retourne le token JWT ou `null`
```javascript
const token = SessionManager.getToken();
```

#### `setUser(userData)`
Enregistre l'utilisateur et le token (appelé après login)
```javascript
SessionManager.setUser({
  utilisateur_id: 5,
  pseudo: "alice",
  email: "alice@example.com",
  credit: 20,
  token: "eyJhbGc..."  // JWT
});
```

#### `clearSession()`
Efface complètement la session (appelé au logout)
```javascript
SessionManager.clearSession();
```

#### `logout()`
Appelle l'API de logout ET efface la session locale
```javascript
await SessionManager.logout();  // Retourne true/false
```

#### `fetchAuthenticated(url, options)`
Effectue une requête fetch avec le token JWT automatiquement
```javascript
const response = await SessionManager.fetchAuthenticated('/api/rides', {
  method: 'GET'
});
// Le header Authorization: Bearer <token> est ajouté automatiquement
```

#### `decodeToken()`
Décode le JWT pour lire les données (⚠️ sans vérifier la signature)
```javascript
const decoded = SessionManager.decodeToken();
// { user_id, pseudo, email, iat, exp }

// Vérifier l'expiration
if (SessionManager.isTokenExpired()) {
  console.warn('Token expiré');
  SessionManager.clearSession();
}
```

#### `initialize()`
Appelé automatiquement au chargement → nettoie les tokens expirés
```javascript
// Appelé dans le DOM ready event
SessionManager.initialize();
```

## 📡 Flux complet d'une session

### Flux Login

```
1. Utilisateur tape email/password sur /login
   ↓
2. Click "Se connecter" → fetch('/api/auth/login')
   ↓
3. Serveur retourne { token, utilisateur_id, pseudo, ... }
   ↓
4. SessionManager.setUser() sauvegarde tout dans localStorage
   ↓
5. Événement 'userLoggedIn' dispatché
   ↓
6. header.js reçoit l'événement → recreateHeader()
   ↓
7. Header affiche "👤 alice" au lieu de "Connexion"
   ↓
8. Redirection vers / (accueil)
```

### Flux Logout

```
1. Utilisateur clique "Déconnexion" dans le header
   ↓
2. logout() appelée → fetch('/api/auth/logout')
   ↓
3. SessionManager.clearSession() → localStorage vide
   ↓
4. Événement 'userLoggedOut' dispatché
   ↓
5. header.js reçoit l'événement → recreateHeader()
   ↓
6. Header affiche "Connexion" au lieu de "👤 alice"
   ↓
7. Redirection vers / (accueil)
```

### Flux Navigation

```
1. Utilisateur sur /accueil (ou n'importe quelle page)
   ↓
2. Page charge → SessionManager.js charge
   ↓
3. header.js charge → DOMContentLoaded event
   ↓
4. initializeAuthMenu() appelle SessionManager.isAuthenticated()
   ↓
5. Si localStorage['ecoride_user'] existe:
   - Affiche menu connecté
   Sinon:
   - Affiche boutons connexion/inscription
   ↓
6. Utilisateur voit le bon menu dès le chargement
```

## 🔐 Sécurité

### ✅ Points de sécurité respectés

1. **Token JWT dans HttpOnly Cookie**
   - Le cookie backend est impossible à voler via XSS (pas accessible en JS)
   - SessionManager utilise localStorage UNIQUEMENT pour afficher l'état
   - Les requêtes API envoient le cookie automatiquement

2. **Validation côté serveur**
   - AuthMiddleware valide TOUS les tokens reçus
   - Les données du token sont vérifiées avec la signature HMAC-SHA256

3. **Suppression sécurisée**
   - `clearSession()` efface localStorage
   - L'API logout efface aussi le cookie côté serveur

4. **Détection d'expiration**
   - `isTokenExpired()` relit le token chaque fois
   - Si expiré → `clearSession()` automatique

### ⚠️ Limitations

- **localStorage n'est PAS sécurisé contre XSS** (contrairement au HttpOnly Cookie)
- **Solution :** On ne stocke QUE les données non-sensibles dans localStorage
- Le vrai token reste dans le HttpOnly Cookie, inaccessible en JS

```javascript
// localStorage (visible en JS) = données publiques
localStorage['ecoride_user'] = { pseudo, email, credit }

// HttpOnly Cookie (invisible en JS) = token JWT
Cookie: ecoride_token=eyJhbGc...  // Envoyé auto avec chaque requête
```

## 🚀 Comment utiliser dans une nouvelle page

### Exemple : Page "Mes trajets" (`rides.php`)

```php
<!DOCTYPE html>
<html>
<head>
  <title>Mes trajets</title>
</head>
<body>
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main id="ridesContent">
    <!-- Rempli par JavaScript -->
  </main>

  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <script src="/frontend/js/SessionManager.js"></script>
  <script src="/frontend/js/header.js"></script>
  <script>
    // Vérifier l'authentification
    if (!SessionManager.isAuthenticated()) {
      window.location.href = '/login';
    }

    const user = SessionManager.getUser();
    const userId = user.utilisateur_id;

    // Charger les trajets de l'utilisateur
    SessionManager.fetchAuthenticated(`/api/rides?user_id=${userId}`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          // Afficher les trajets
          displayRides(data.data.items);
        } else {
          alert('Erreur: ' + data.error.message);
        }
      });

    function displayRides(rides) {
      const html = rides.map(ride => `
        <div class="ride">
          <h3>${ride.destination}</h3>
          <p>${ride.date}</p>
          <p>${ride.passengers} passagers</p>
        </div>
      `).join('');
      
      document.getElementById('ridesContent').innerHTML = html;
    }
  </script>
</body>
</html>
```

## 📝 Checklist pour nouvelles pages

- [ ] Importer `<script src="/frontend/js/SessionManager.js"></script>`
- [ ] Importer `<script src="/frontend/js/header.js"></script>`
- [ ] Vérifier authentification : `if (!SessionManager.isAuthenticated()) { redirect }`
- [ ] Récupérer l'utilisateur : `const user = SessionManager.getUser()`
- [ ] Pour appels API : utiliser `SessionManager.fetchAuthenticated()`
- [ ] Ajouter écouteurs pour `userLoggedIn` / `userLoggedOut` si besoin de rafraîchir le contenu

## 🐛 Debug

### Vérifier l'état de la session

Ouvre la DevTools (F12) et tape :

```javascript
// Vérifier si connecté
console.log(SessionManager.isAuthenticated());  // true/false

// Lire les données utilisateur
console.log(SessionManager.getUser());
// { utilisateur_id: 5, pseudo: "alice", email: "...", credit: 20 }

// Lire le token
console.log(SessionManager.getToken());

// Lire le contenu du localStorage
console.log(localStorage);
```

### Forcer une déconnexion

```javascript
SessionManager.clearSession();
window.location.reload();
```

### Tester le logout

```bash
# Vérifier que l'API reçoit bien la déconnexion
curl -X POST http://localhost:8080/api/auth/logout -v
```

## 📚 Références

- **JWT:** https://jwt.io
- **localStorage API:** https://developer.mozilla.org/en-US/docs/Web/API/Window/localStorage
- **Custom Events:** https://developer.mozilla.org/en-US/docs/Web/API/CustomEvent
