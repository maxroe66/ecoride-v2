# Guide de démarrage - EcoRide v2

## Prérequis

- Docker & Docker Compose installés
- Git installé
- Port 8080 disponible (nginx)
- Port 8081 disponible (phpmyadmin)
- Port 8082 disponible (mongo-express)

## Démarrage de l'application

### 1. Cloner et configurer

```bash
git clone <repo> ecoride-v2
cd ecoride-v2
```

### 2. Démarrer les containers

```bash
docker-compose up -d
```

Les services qui se lancent:
- **nginx** (port 8080): Serveur web principal
- **php** (9000): Moteur PHP
- **mysql** (3306): Base de données MySQL (interne)
- **mongo** (27017): Base MongoDB (interne)
- **phpmyadmin** (8081): Interface MySQL
- **mongo-express** (8082): Interface MongoDB

### 3. Accès à l'application

- Page d'accueil: http://localhost:8080/
- API santé: http://localhost:8080/api/health
- phpMyAdmin: http://localhost:8081 (user: `ecoride`, pwd: `ecoride-v2`)
- Mongo Express: http://localhost:8082

## Arrêter l'application

```bash
docker-compose down
```

## Structure du projet

```
ecoride-v2/
├── app/                      # Backend (logique applicative)
│   ├── Core/
│   │   ├── Bootstrap.php     # Point d'entrée, routing
│   │   └── Env.php           # Gestion des variables d'env
│   ├── Factories/
│   ├── Models/
│   ├── Repositories/         # Pattern Repository pour les données
│   ├── Services/
│   └── Validators/
├── frontend/                 # Frontend (HTML, CSS, JS)
│   ├── css/
│   │   ├── global.css        # Variables, reset, typography
│   │   ├── components/       # CSS réutilisables
│   │   └── index.css         # Page spécifique
│   ├── js/
│   │   ├── header.js         # Logique header
│   │   └── index.js          # Logique page
│   ├── pages/                # Pages PHP
│   │   ├── accueil.php       # Page d'accueil
│   │   └── _template.php     # Template pour nouvelles pages
│   └── templates/            # Templates réutilisables
│       └── layouts/
│           ├── header.php
│           └── footer.php
├── public/
│   └── index.php             # Point d'entrée (appelle Bootstrap)
├── images-icons/             # Ressources (images, icônes)
├── docker/                   # Configuration Docker
├── vendor/                   # Composer dependencies
└── storage/                  # Cache, logs, etc.
```

## Architecture

### Backend
- **Pattern**: Pas de framework, architecture custom
- **Routing**: Simple par URI → fichier PHP
- **DB**: MySQL (avis) + MongoDB (à intégrer)
- **API**: REST endpoints sous `/api/*`

### Frontend
- **Architecture**: Hybride PHP-HTML-CSS-JS
- **CSS**: Variables (1rem=1px), responsive (mobile-first)
- **Responsive**: 3 breakpoints (mobile, 640px, 1024px)
- **Sessions**: Gestion user via `$_SESSION`

## Commandes utiles

### Voir les logs

```bash
# Tous les services
docker-compose logs -f

# PHP seulement
docker-compose logs -f php

# Nginx seulement
docker-compose logs -f nginx
```

### Accéder au container PHP

```bash
docker-compose exec php bash
```

### Exécuter composer

```bash
docker-compose exec php composer install
```

### Exécuter tests

```bash
docker-compose exec php ./vendor/bin/phpunit
```

## Développement

### Ajouter une nouvelle page

1. Copier `/frontend/pages/_template.php` en `/frontend/pages/monpage.php`
2. Modifier le titre et le contenu
3. Créer `/frontend/css/monpage.css` si nécessaire
4. Créer `/frontend/js/monpage.js` si nécessaire
5. Accéder à http://localhost:8080/monpage

### Ajouter une route API

Modifier `app/Core/Bootstrap.php`, ajouter un cas dans `handleApi()`:

```php
if (str_starts_with($uri, '/api/maroute')) {
    // Votre logique
    echo json_encode(['success' => true, 'data' => $data]);
}
```

### Utiliser la session

Dans n'importe quel fichier PHP:

```php
// Récupérer les données utilisateur (si connecté)
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    echo "Bienvenue " . htmlspecialchars($user['name']);
}

// Définir après connexion
$_SESSION['user'] = [
    'id' => 123,
    'name' => 'John Doe',
    'email' => 'john@example.com'
];

// Déconnecter
session_destroy();
```

## Dépannage

### "Connection refused" sur 8080
Vérifier que nginx est lancé:
```bash
docker-compose ps
```

### CSS/images ne se chargent pas
Vérifier la configuration nginx et les logs:
```bash
docker-compose logs nginx
```

### Erreur 500 PHP
Vérifier les logs PHP:
```bash
docker-compose logs php
```

## Documentation supplémentaire

- **Frontend**: Voir `FRONTEND_ARCHITECTURE.md`
- **API**: Routes définies dans `app/Core/Bootstrap.php`
- **Base de données**: Schéma dans `docker/mysql/init/001_schema.sql`

## Notes de développement

- **Sans framework**: Code custom, plus léger mais moins de conventions
- **Responsive**: Toujours tester sur mobile/tablette/desktop
- **Sécurité**: Toujours échapper les données utilisateur avec `htmlspecialchars()`
- **Performance**: Charger CSS/JS que nécessaire par page

## Branches git

- `main` - Production
- `develop` - Branche de développement
- `feature/...` - Branches de feature
- `US-...` - Branches user story

Exemple workflow:
```bash
# Créer une branche pour une feature
git checkout develop
git pull origin develop
git checkout -b feature/ma-feature

# Faire les changements, committer, pusher
git add .
git commit -m "feat: description"
git push origin feature/ma-feature

# Créer une PR, reviewer, merger
```
