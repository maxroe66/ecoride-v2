# Guide de sécurité pour nouvelles pages & features

Ce guide décrit les bonnes pratiques à appliquer à chaque nouvelle page ou feature pour respecter notre modèle de sécurité: JWT en cookie HttpOnly, session + CSRF, et contrôles côté serveur.

## Principes de base
- JWT: stocké uniquement dans un cookie `HttpOnly`, jamais dans `localStorage` ni exposé au JS.
- Auth côté serveur: ne jamais faire confiance aux champs d’identité envoyés par le client; dériver l’utilisateur depuis le JWT côté serveur.
- CSRF: chaque requête mutatrice (POST/PUT/PATCH/DELETE) exige l’en-tête `X-CSRF-Token` issu de la session.
- Moindre privilège: vérifier les rôles/droits côté serveur pour chaque action sensible.
- Erreurs sûres: toujours renvoyer des erreurs JSON explicites sans divulgation d’internals.

## Checklist côté Backend (à faire pour chaque nouvelle route)
- Validation d’authentification:
  - Lire l’identité via `AuthMiddleware` / `JwtService` (cookie HttpOnly ou header test uniquement).
  - Ne pas accepter `user_id`/`utilisateur_id` fournis par le client.
- Protection CSRF:
  - Appliquer `CsrfMiddleware->validate($request, $response)` sur toutes les routes mutatrices.
  - Offrir `GET /api/csrf-token` pour récupérer le token après login (déjà présent).
- Validation des données:
  - Valider et normaliser les entrées via un Validator approprié.
  - Refuser champs inattendus ou dangereux.
- Autorisation:
  - Vérifier rôle/droits (ex: seul chauffeur peut créer un trajet).
- Réponses:
  - Utiliser `Response` pour JSON cohérents (codes 2xx/4xx/5xx).

## Checklist côté Frontend (à faire pour chaque nouvelle page)
- Session & CSRF:
  - Après login: appeler `GET /api/csrf-token` et stocker le token via `SessionManager.refreshCsrfToken()`.
  - Pour toute requête mutatrice: inclure `X-CSRF-Token` via `SessionManager.csrfHeaders()`.
- Cookies:
  - Ne jamais tenter de lire le JWT en JS; le cookie HttpOnly est géré par le navigateur.
- Appels API:
  - GET/lecture: header CSRF non requis.
  - POST/PUT/PATCH/DELETE: ajouter l’en-tête `X-CSRF-Token` et `Content-Type: application/json`.
  - Toujours utiliser `credentials: 'include'` si vous faites des `fetch` côté navigateur.
- Données sensibles:
  - Ne pas conserver des infos d’identité côté client; n’utiliser que les champs d’affichage nécessaires.

## Exemples rapides
- Récupérer et utiliser CSRF après login (JS):
```
await SessionManager.refreshCsrfToken();
await fetch('/api/avis', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    ...SessionManager.csrfHeaders(),
  },
  body: JSON.stringify({ covoiturage_id: 1, note: 5, commentaire: 'OK' }),
});
```

- Backend (contrôleur) pour une route mutatrice:
```
$auth = new AuthMiddleware($this->jwtService);
if (!$auth->validate($request, $response)) return;
$csrf = new CsrfMiddleware($this->csrfService);
if (!$csrf->validate($request, $response)) return;
// continuer avec la logique métier, user dérivé du token
```

## Flux standard pour nouvelles features
1. Ajouter la route backend et appliquer `AuthMiddleware` + `CsrfMiddleware` sur les méthodes mutatrices.
2. Valider les entrées avec un Validator dédié; ignorer tout champ d’identité fourni par le client.
3. Côté front, après login, appeler `/api/csrf-token`; stocker le token dans `SessionManager`.
4. Pour chaque action mutatrice, inclure `X-CSRF-Token` via `SessionManager.csrfHeaders()`.
5. Tester avec curl: 403 sans CSRF, succès avec CSRF.

## Tests (curl)
```
# Après signup/login
CSRF=$(curl -s -c cookies.txt -b cookies.txt http://localhost:8080/api/csrf-token | sed -n 's/.*"csrfToken":"\([^" ]*\)".*/\1/p')

# Échec attendu sans CSRF
curl -c cookies.txt -b cookies.txt -H 'Content-Type: application/json' \
  -d '{"covoiturage_id":1,"note":5,"commentaire":"Test"}' \
  http://localhost:8080/api/avis

# Succès avec CSRF
curl -c cookies.txt -b cookies.txt -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: $CSRF" \
  -d '{"covoiturage_id":1,"note":5,"commentaire":"Test"}' \
  http://localhost:8080/api/avis
```

## Rappels importants
- Ne pas exposer le JWT au JS; uniquement cookie HttpOnly.
- Toujours forcer l’identité côté serveur à partir du token.
- Toutes les requêtes mutatrices doivent exiger `X-CSRF-Token`.
- Documenter brièvement les exigences CSRF/JWT pour chaque nouvelle feature dans le README du module/page.
