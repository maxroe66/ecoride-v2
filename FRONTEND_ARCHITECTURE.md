# Architecture Frontend - EcoRide v2

## Structure générale

L'architecture frontend suit un modèle **hybride PHP-HTML-CSS-JS** sans framework, organisé comme suit:

```
frontend/
├── css/
│   ├── global.css              # Variables, reset, typographie, composants globaux
│   ├── index.css               # Styles spécifiques à la page d'accueil
│   └── components/
│       ├── header.css          # Styles du header/navbar
│       └── footer.css          # Styles du footer
├── js/
│   ├── header.js               # Logique du header (menu toggle, dropdown)
│   ├── index.js                # Logique spécifique à la page d'accueil
│   └── utils/                  # Utilitaires partagés (à créer)
├── pages/
│   ├── accueil.php             # Page d'accueil (point d'entrée)
│   ├── rides.php               # Page des trajets (à créer)
│   ├── profile.php             # Page profil utilisateur (à créer)
│   └── ...
└── templates/
    └── layouts/
        ├── header.php          # Template du header avec logique session
        └── footer.php          # Template du footer avec liens
```

## Points d'entrée

### Page d'accueil (`public/index.php`)

Servie par le `Bootstrap.php` qui route les requêtes:
- URL `/` → `/frontend/pages/accueil.php`
- URL `/rides` → `/frontend/pages/rides.php`
- URL `/api/*` → Endpoints API (gérés par `handleApi()`)

Le routing est simple: l'URI est transformée directement en chemin de fichier PHP.

## Système CSS

### Variables globales (1rem = 1px)

Définis dans `global.css`:
```css
:root {
  --color-beige-light: #F0F4C3;
  --color-green-light: #A5D6A7;
  --color-green-dark: #2E7D32;
  --color-green-verydark: #1B3B1B;
  --color-brown-light: #A18B7F;
  --color-bluegrey: #747B85;
}
```

### Responsive breakpoints

- **Mobile** (default): 0px+
- **Tablette**: 640px+
- **Desktop**: 1024px+

Les CSS utilisent `@media (min-width: ...)` pour progresser du mobile au desktop.

## Gestion de la session utilisateur

Le header détecte l'état utilisateur via `$_SESSION['user']`:

```php
<?php
$isConnected = isset($_SESSION['user']);
if ($isConnected) {
    // Afficher menu utilisateur avec nom
    echo $userName = $_SESSION['user']['name'];
} else {
    // Afficher boutons Connexion/Inscription
}
?>
```

Structure attendue de la session:
```php
$_SESSION['user'] = [
    'id' => 123,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'avatar' => '/path/to/avatar.jpg'
];
```

## Ajouter une nouvelle page

### 1. Créer le fichier PHP

Exemple: `/frontend/pages/rides.php`

```php
<?php
/**
 * Page des trajets - Affiche les trajets disponibles
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoRide - Trajets disponibles</title>
  
  <link rel="stylesheet" href="/frontend/css/global.css">
  <link rel="stylesheet" href="/frontend/css/components/header.css">
  <link rel="stylesheet" href="/frontend/css/components/footer.css">
  <link rel="stylesheet" href="/frontend/css/rides.css">
</head>
<body>
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main>
    <!-- Contenu spécifique à la page rides -->
    <h1>Trajets disponibles</h1>
    <!-- ... -->
  </main>

  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <script src="/frontend/js/header.js"></script>
  <script src="/frontend/js/rides.js"></script>
</body>
</html>
```

### 2. Créer le CSS spécifique

Fichier: `/frontend/css/rides.css`

Importer les variables depuis `global.css` via:
```css
@import url('/frontend/css/global.css');
```

### 3. (Optionnel) Créer le JS spécifique

Fichier: `/frontend/js/rides.js` pour la logique de page.

## Gestion des assets statiques

### Images

Chemin: `/images-icons/`

Exemple:
```html
<img src="/images-icons/image_accueil.png" alt="Description">
```

Fichiers disponibles:
- `image_accueil.png` - Image héros page d'accueil
- `ecorideicon-removebg-preview.png` - Logo EcoRide
- `voiture 2.jpeg` - Image voiture

### CSS et JS

Tous accessibles via `/frontend/`:
- `/frontend/css/global.css`
- `/frontend/js/header.js`
- etc.

Configuré dans nginx avec `location ^~ /frontend/` pour accès direct.

## Sessions et authentification

La session PHP est démarrée automatiquement dans `Bootstrap::__construct()`:

```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

Les données utilisateur doivent être définies après connexion:
```php
$_SESSION['user'] = [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
];
```

Pour déconnexion:
```php
session_destroy();
```

## Bonnes pratiques

1. **HTML sémantique**: Utiliser `<main>`, `<header>`, `<footer>`, etc.
2. **Sécurité**: Toujours utiliser `htmlspecialchars()` pour les données utilisateur
3. **Performance**: CSS/JS par page, ne charger que nécessaire
4. **Responsive**: Tester sur mobile, tablette, desktop
5. **Accessibilité**: Ajouter `aria-label`, `alt` sur images, etc.

## Routes API existantes

- `GET /api/health` - Health check
- `GET /api/avis?covoiturage_id=X` - Lister avis pour un trajet
- `GET /api/avis/stats?covoiturage_id=X` - Stats avis
- `POST /api/avis` - Créer avis

## Configuration nginx

Point d'accès:
- Root: `/var/www/public`
- Frontend: `/var/www/frontend` (via alias)
- Images: `/var/www/images-icons` (via alias)

Tous les fichiers `.php` sont routed vers le pool PHP-FPM sur le port 9000.
