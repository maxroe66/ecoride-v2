# Système d'authentification EcoRide (version simplifiée)

Ce document résume le fonctionnement de l'authentification dans EcoRide, dans une version **claire, moderne et adaptée au site web uniquement** (pas d'appli mobile ni d'API publique).

---

## 1. Vue d’ensemble

- **But** : sécuriser la connexion des utilisateurs EcoRide avec un système clair et moderne.
- **Choix** : utilisation de **JWT** stockés dans un **cookie HttpOnly sécurisé**, pas de sessions PHP classiques exposées au front.
- **Clients visés** : uniquement le **site web** (desktop + mobile via navigateur), pas d’appli mobile ni d’API publique → cela permet de **simplifier beaucoup le code**.

---

## 2. Flux complet d’authentification

### 2.1 Inscription / connexion

1. L’utilisateur envoie son **email + mot de passe** sur l’endpoint :
   - `POST /api/auth/login`
2. `AuthService` :
   - vérifie le mot de passe avec **bcrypt**,
   - génère un **JWT** via `JwtService::generate()` contenant au minimum :
     - `user_id`,
     - `pseudo`,
     - éventuellement d’autres infos utiles.

### 2.2 Création du cookie sécurisé

1. Le JWT est placé dans un cookie `ecoride_token` via `CookieManager::setToken()`.
2. Le cookie est configuré pour la sécurité :
   - **HttpOnly** → **inaccessible en JavaScript** (protège contre vol de token via XSS),
   - **Secure** (en production) → envoyé uniquement en HTTPS,
   - **SameSite=Lax** → limite les risques CSRF simples.

### 2.3 Front : `SessionManager`

1. Après un login réussi, le frontend appelle :
   - `SessionManager.setUser(userData)`
2. `SessionManager.setUser()` :
   - enregistre **uniquement les données affichables** dans `localStorage` :
     - `pseudo`, `email`, `utilisateur_id`, etc.
   - le **token “officiel”** reste dans le **cookie sécurisé** ;
   - la copie dans `localStorage` sert **uniquement à afficher le header** (par exemple le pseudo de l’utilisateur).

### 2.4 Navigation / pages protégées

1. Sur chaque page, `header.js` :
   - appelle `SessionManager.isAuthenticated()` et `SessionManager.getUser()` ;
   - affiche soit :
     - le menu **“Connexion / Inscription”** si l’utilisateur n’est pas connecté,
     - soit **"👤 Pseudo"** avec un menu utilisateur si l’utilisateur est connecté.
2. Pour une API protégée (par exemple `GET /api/avis`) :
   - le navigateur envoie automatiquement le **cookie** `ecoride_token` avec la requête ;
   - aucun header `Authorization` n’est nécessaire dans cette version simplifiée.

### 2.5 Vérification côté serveur (`AuthMiddleware`)

1. `AuthMiddleware::authenticate()` :
   - récupère le token **uniquement depuis le cookie** via `CookieManager` ;
   - **ne lit plus** ni header `Authorization`, ni query `?token=`.
2. Il appelle `JwtService::validate()` qui :
   - vérifie la **signature HMAC-SHA256** du JWT,
   - vérifie l’**expiration** du token.
3. Comportement :
   - si tout est valide → retourne les données utilisateur au contrôleur ;
   - sinon → renvoie une **erreur 401 “UNAUTHORIZED”**.

### 2.6 Déconnexion

1. Le frontend appelle `POST /api/auth/logout`.
2. `AuthService::logout()` :
   - supprime le cookie via `CookieManager::deleteToken()` ;
   - détruit la session PHP côté serveur (sécurité supplémentaire).
3. `SessionManager.logout()` côté frontend :
   - efface également les données dans `localStorage` ;
   - met à jour l’affichage du header (on repasse en mode “non connecté”).

---

## 3. Sécurité en deux phrases

1. Le navigateur ne voit jamais directement le token “officiel” : il est stocké dans un **cookie HttpOnly sécurisé** et validé à chaque requête par un **middleware**.
2. Le JavaScript n’utilise que des données minimales (pseudo, id, email…) pour l’affichage, ce qui limite fortement les risques en cas de XSS.

---
## 4. Protections de sécurité en détail

### 4.1 Cookie HttpOnly + Secure + SameSite

- **HttpOnly** :
   - Le cookie `ecoride_token` n’est **jamais accessible en JavaScript** (`document.cookie` ne peut pas le lire).
   - Même si un script malveillant s’exécute sur la page (XSS), il ne peut pas voler directement le token.
- **Secure** :
   - En environnement de production, le cookie n’est envoyé que sur des connexions **HTTPS**.
   - Cela évite qu’un attaquant sur le réseau intercepte le cookie en clair.
- **SameSite=Lax** :
   - Le navigateur n’envoie pas le cookie sur la plupart des requêtes cross-site.
   - Cette option **réduit fortement les attaques CSRF simples** (formulaires ou liens externes qui tentent de déclencher une action à ton insu).

### 4.2 JWT signé avec expiration

- Le serveur génère un **JWT** signé avec une clé secrète (`JWT_SECRET`).
- Le token contient seulement des **données minimales** : `user_id`, `pseudo`, `email`.
- `JwtService::validate()` vérifie :
   - le **format** du token (3 parties),
   - la **signature HMAC-SHA256** : le serveur recalcule la signature et la compare avec `hash_equals`,
   - l’**expiration** grâce au champ `exp`.
- Si quelque chose ne va pas (token modifié, expiré, mal formé), le middleware lève une **erreur 401** et la requête est refusée.

### 4.3 Middleware AuthMiddleware

- Toutes les routes protégées passent par `AuthMiddleware::authenticate()`.
- Il :
   - lit le token **uniquement** dans le cookie sécurisé via `CookieManager`,
   - appelle `JwtService::validate()` pour vérifier signature + expiration,
   - renvoie les **données utilisateur du token** au contrôleur en cas de succès.
- Cela centralise la logique de sécurité au même endroit, ce qui évite les oublis.

### 4.4 Validation stricte côté serveur

- `AuthService::signup()` vérifie :
   - le **pseudo** (longueur, caractères autorisés),
   - l’**email** (format valide),
   - le **mot de passe** (longueur, au moins une majuscule, au moins un chiffre).
- `AuthService::login()` :
   - vérifie le mot de passe avec **bcrypt** (`User::verifyPassword`),
   - bloque les comptes **suspendus**.
- Ces validations côté serveur ne peuvent pas être contournées par un simple changement dans le JavaScript.

### 4.5 Protection XSS côté front

- Le pseudo affiché dans le header est passé par `escapeHtml()` dans `header.js`.
- Cette fonction remplace les caractères spéciaux (`<`, `>`, `"`, `'`, `&`) par leurs équivalents HTML (`&lt;`, `&gt;`, etc.).
- Résultat : même si un utilisateur choisit un pseudo "malicieux" (par exemple `<script>alert('xss')</script>`), il sera affiché comme du **texte**, pas exécuté comme du code.
- De plus :
   - le JWT n’est **jamais** stocké dans `localStorage`,
   - `localStorage` ne contient que des données de profil (pseudo, email, crédit, type_utilisateur).

### 4.6 Session côté client limitée à l’affichage

- `SessionManager` ne stocke que `ecoride_user` dans `localStorage`.
- Ces données sont utilisées uniquement pour :
   - savoir si l’utilisateur est connecté (`isAuthenticated()`),
   - afficher son pseudo/email/crédits dans le header ou la page profil.
- Toute la **vraie autorisation** (accès aux routes protégées) se fait côté serveur, via le cookie HttpOnly et le middleware.

---

## 5. Améliorations de sécurité possibles

Même si le niveau actuel est déjà solide pour un TP, voici des pistes d’amélioration que l’on pourrait ajouter plus tard :

### 5.1 Token CSRF dédié

- **Idée** : ajouter un token CSRF généré côté serveur et stocké en session, puis l’injecter dans les formulaires ou dans un header personnalisé.
- À chaque requête POST critique, le serveur vérifierait :
   - que le cookie (JWT) est présent,
   - **et** que le token CSRF envoyé correspond à celui en session.
- Effet : même si un site externe arrive à faire envoyer une requête par ton navigateur (CSRF), il ne connaît pas le token CSRF et la requête sera refusée.

### 5.2 Limitation des tentatives de connexion (rate limiting)

- Ajouter un **compteur de tentatives de login** par IP ou par adresse email :
   - ex : pas plus de 5 tentatives ratées en 5 minutes.
- Si la limite est dépassée, temporairement bloquer les nouvelles tentatives ou demander une action supplémentaire (captcha, délai d’attente).
- Effet : rend les attaques par force brute beaucoup plus difficiles.

### 5.3 Durée de vie plus courte des tokens

- Aujourd’hui, le JWT est valide **7 jours**.
- On pourrait réduire cette durée (ex : 1 à 3 jours) pour diminuer la fenêtre d’exploitation en cas de vol de cookie.
- Contrepartie : l’utilisateur devra se reconnecter un peu plus souvent.

### 5.4 Journaux de sécurité (logging)

- Enregistrer dans les logs certains événements sensibles :
   - tentatives de login échouées,
   - erreurs de validation de token (signature invalide, token expiré),
   - connexions de comptes suspendus.
- Utile pour analyser des comportements suspects et expliquer lors de la soutenance que "le système surveille les incidents de sécurité".

### 5.5 CSP plus stricte (Content-Security-Policy)

- Ajouter un header `Content-Security-Policy` pour limiter les sources de scripts, styles, images, etc.
- Exemple minimal :
   - `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:`
- Effet : même si une injection HTML se produit, il sera plus difficile d’exécuter du JavaScript externe.

---

## 6. Version courte prête pour la soutenance (à l’oral)

> "Pour l’authentification d’EcoRide, j’ai choisi d’utiliser des JWT stockés dans un cookie HttpOnly sécurisé plutôt que des sessions PHP classiques. Quand un utilisateur se connecte, le serveur génère un token signé avec une date d’expiration et le place dans un cookie que le navigateur renvoie automatiquement sur chaque requête. Côté frontend, je ne garde que les informations nécessaires à l’affichage, comme le pseudo ou l’email, dans le localStorage, et j’utilise un `SessionManager` pour afficher dynamiquement le bon menu et la page profil. Toutes les routes protégées passent par un `AuthMiddleware` qui lit uniquement le cookie, vérifie la signature et l’expiration du JWT avant de laisser passer. La déconnexion supprime le cookie et nettoie aussi la session côté front. Grâce à ce schéma, le token n’est jamais accessible en JavaScript et le cookie est configuré en HttpOnly, Secure et SameSite=Lax, ce qui renforce la protection contre les attaques XSS et CSRF."
“Le token JWT n’est jamais accessible en JavaScript, il n’est stocké que dans un cookie HttpOnly sécurisé, et le front ne conserve que des informations minimales d’affichage.”

