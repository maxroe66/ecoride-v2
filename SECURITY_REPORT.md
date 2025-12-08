# Rapport d’Audit Sécurité & Cohérence

Date: 2025-11-29  
Portée: Application EcoRide (backend PHP, frontend JS, Nginx, MySQL, MongoDB, Docker) branche `filtres-covoiturages` après revert vers état précédent.

## 1. Synthèse Exécutive
L’application présente une base technique saine (requêtes SQL préparées, hachage bcrypt cost 12, séparation repositories) mais plusieurs failles critiques compromettent l’intégrité et la confidentialité des données utilisateurs:
- Usurpation possible sur création d’avis (POST `/api/avis`) via champ `utilisateur_id` fourni par le client, absent de toute vérification d’authentification.
- JWT exposé dans la réponse de login (contradiction avec la stratégie documentée de cookie HttpOnly uniquement).
- Absence de mécanisme CSRF sur endpoints mutateurs (signup, login, logout, avis POST).
- Absence de contrôle de participation avant publication d’un avis.
- Manque d’en-têtes avancés (CSP, HSTS, Permissions-Policy) augmentant surface XSS.

Priorité immédiate: durcir authentification (retirer token réponse), appliquer contrôle d’identité côté serveur pour avis et ajouter CSRF.

## 2. Méthodologie
1. Revue statique de tous fichiers applicatifs (PHP, JS, Nginx, Docker compose).  
2. Recherche de patterns sensibles (grep sur `token`, `password`, `eval`, `var_dump`, `print_r`).  
3. Classifications selon OWASP Top 10 2021 & bonnes pratiques JWT.  
4. Attribution de sévérité (Critique / Élevé / Moyen / Faible) basée sur exploitabilité et impact potentiel.  
5. Élaboration d’un plan de remédiation par phases (immédiat, court, moyen, long terme).

## 3. Portée & Exclusions
Inclus: Code présent dans le repo racine (app/, frontend/, docker/, Nginx conf), configuration Docker, logique d’authentification.  
Exclus: Tests de pénétration actifs, performance runtime, dépendances vendored (vendor/) hors usages explicites.  

## 4. Architecture Résumée
- Backend monolithique géré via `Bootstrap.php` (461 lignes) combinant routage + logique métier.  
- Authentification: JWT HS256 (`JwtService`), stockage cookie HttpOnly + session PHP (duplicatif), génération dans `AuthService::login`.  
- Données: MySQL (utilisateurs, trajets), MongoDB + fallback MySQL pour avis (résilience).  
- Frontend: Vanilla JS, localStorage pour snapshot utilisateur (sans token).  
- Serveur: Nginx reverse proxy (port 8080) avec headers basiques.

## 5. Inventaire des Endpoints Principaux
- Auth: `POST /api/auth/signup`, `POST /api/auth/login`, `POST /api/auth/logout`.
- Avis: `GET /api/avis?covoiturage_id=`, `GET /api/avis/stats?covoiturage_id=`, `POST /api/avis`.
- Trajets: `GET /api/trajets`, `GET /api/trajets/suggestions`.

## 6. Constatations Détaillées
### 6.1 Authentification & Sessions
| ID | Niveau | Constat | Evidence |
|----|--------|---------|----------|
| A1 | Critique | JWT exposé dans JSON réponse login | `AuthService.php:117` retour `['token'=>...]` |
| A2 | Élevé | Double stockage (cookie + session) non justifié | `AuthService.php:112` + `$_SESSION['user']` |
| A3 | Moyen | Claims JWT minimaux (pas `iss`,`aud`,`nbf`, pas rotation) | `JwtService.php` |
| A4 | Moyen | Secret présent en clair dans docker-compose & .env | `docker-compose.yml:26` / `.env` |
| A5 | Moyen | Pas de rate limiting login (brute force possible) | Absence globale |
| A6 | Élevé | Pas de vérification d’identité sur POST `/api/avis` | `Bootstrap.php:243-271` |

### 6.2 Autorisation & Contrôles Logiques
| ID | Niveau | Constat | Evidence |
|----|--------|---------|----------|
| B1 | Critique | `utilisateur_id` accepté depuis client pour avis (usurpation) | `Bootstrap.php:249-252` |
| B2 | Élevé | Absence de check participation avant avis | Aucun code de liage utilisateur/trajet |

### 6.3 Intégrité des Données
| ID | Niveau | Constat | Evidence |
|----|--------|---------|----------|
| C1 | Moyen | Commentaires avis sans limite longueur | `Bootstrap.php:255-259` |
| C2 | Moyen | Valeurs placeholder nom/prenom à l’inscription | `AuthService.php:44-50` |

### 6.4 Injection (SQL / NoSQL / Commande)
| ID | Niveau | Constat | Evidence |
|----|--------|---------|----------|
| D1 | Élevé | Interpolation nom de table (fallback avis) sans validation stricte | `MysqlAvisRepository.php:16` |
| D2 | Faible | Requête dynamique filtres trajets; paramètres bindés (OK) | `TrajetRepository.php` |
| D3 | Faible | Pas de patterns dangereux (eval, system) trouvés | Grep aucun match |

### 6.5 XSS / Sortie
| ID | Niveau | Constat | Evidence |
|----|--------|---------|----------|
| E1 | Élevé | Commentaires avis renvoyés bruts (future vue potentielle) | `MysqlAvisRepository.php:25-37` + payload retour |
| E2 | Moyen | Pas de CSP définie dans Nginx | `docker/nginx/default.conf` |
| E3 | Faible | Pseudos échappés côté front (positif) | `header.js:38-44` usage `escapeHtml` |

### 6.6 CSRF
| ID | Niveau | Constat | Evidence |
|----|--------|---------|----------|
| F1 | Critique | Aucune protection CSRF sur POST auth & avis | Revue endpoints |
| F2 | Moyen | Reliance seule sur SameSite=Lax | `CookieManager.php:32` options cookie |

### 6.7 Configuration & Infrastructure
| ID | Niveau | Constat | Evidence |
|----|--------|---------|----------|
| G1 | Élevé | Manque HSTS, CSP, Permissions-Policy, COOP/ CORP | `default.conf` |
| G2 | Moyen | `Secure` cookie dépend de HTTPS (risque oubli déploiement) | `CookieManager.php:29-37` |
| G3 | Faible | Absence journaux sécurité (login échecs, avis créations) | Global |

### 6.8 Cryptographie & Stockage
| ID | Niveau | Constat | Evidence |
|----|--------|---------|----------|
| H1 | Moyen | Pas de rotation / versionnement token | `JwtService.php` |
| H2 | Faible | Bcrypt cost 12 (OK mais surveiller performance) | `User.php:43-45` |

### 6.9 Observabilité & Résilience
| ID | Niveau | Constat | Evidence |
|----|--------|---------|----------|
| R1 | Moyen | Résilience Mongo→MySQL sans journalisation bascule | `ResilientAvisRepository.php` |
| R2 | Faible | Cooldown fixe 30s non adaptatif | `ResilientAvisRepository.php:11-18` |

## 7. Classement par Priorité (Top 10)
1. (A1/B1/F1) Retrait token réponse + auth stricte POST `/api/avis` + CSRF.  
2. (B2) Vérification participation avant avis (liens trajets-utilisateurs).  
3. (E1/E2/G1) Mise en place CSP + validation/échappement commentaires + en-têtes supplémentaires.  
4. (D1) Validation stricte du nom de table fallback avis.  
5. (A5) Rate limiting & lockout progressif login.  
6. (G2) Forcer `Secure` cookie en production + HSTS.  
7. (A3/H1) Enrichir claims JWT + rotation/refresh.  
8. (C1) Limiter longueur commentaire + filtrage Unicode dangereux.  
9. (G3/R1) Journalisation sécurité & bascules résilience.  
10. (C2) Collecte données profil réelles (éviter placeholders persistants).

## 8. Plan de Remédiation Par Phases
### Phase 0 (Immédiat – 0 à 2 jours)
- Modifier `AuthService::login` pour ne plus retourner le token.  
- Ajouter appel `requireAuth()` sur POST `/api/avis`.  
- Ignorer champ `utilisateur_id` fourni; dériver depuis JWT.  
- Implémenter token CSRF (génération + validation).  

### Phase 1 (Court Terme – 3 à 10 jours)
- Ajouter CSP stricte + Permissions-Policy + HSTS (en prod).  
- Échapper/filtrer commentaires avis avant stockage ou à l’affichage.  
- Limiter longueur commentaire (ex: 1000 chars).  
- Validation regex table fallback avis.  
- Introduire rate limiting (Nginx `limit_req` + compteur serveur).  

### Phase 2 (Moyen Terme – 2 à 4 semaines)
- Refactoriser `Bootstrap.php` en Router + Contrôleurs + Middleware pour audits futurs.  
- Ajouter journaux sécurité structurés (JSON) + corrélation ID requête.  
- Enrichir JWT (claims `iss`,`aud`,`nbf`) + mettre en place refresh token court + rotation.  
- Vérifier participation trajet avant autoriser avis (schéma relationnel ou vérification réservation).  
- Mécanisme de suspension → invalidation (token versioning).  

### Phase 3 (Long Terme – 1 à 2 mois)
- Intégration WAF léger / détection anomalies.  
- Password blacklist (top 10k) + zxcvbn pour mesure robustesse.  
- Outil de revue automatique (CI) pour secrets & patterns dangereux.  
- Observabilité bascule Mongo (metrics Prometheus / logs).  

## 9. Recommandations Techniques (Détaillées)
- Auth strict: centraliser extraction utilisateur dans middleware, fournir objet user à contrôleurs.  
- CSRF: Stocker Token en session serveur + inclure dans formulaire hidden / header `X-CSRF-Token`.  
- Commentaires: conserver version brute + version échappée ou effectuer escaping au rendu (préférable).  
- Table fallback: `preg_match('/^[a-zA-Z0-9_]{1,32}$/', $table)` avant usage.  
- Rate limiting: clé = IP + identifiant (glisser fenêtre 15min), lockout après X échecs.  
- JWT rotation: Access token 15min + refresh 7j, endpoint de renouvellement; inclure `jti` pour liste de révocation.  
- Logging: format `{timestamp, user_id, action, status, ip, user_agent}` sans données sensibles.  
- En-têtes Nginx additionnels:  
  - `Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self';`  
  - `Permissions-Policy: geolocation=(), microphone=(), camera=()`  
  - `Strict-Transport-Security: max-age=63072000; includeSubDomains; preload` (production HTTPS)  
  - `Cross-Origin-Opener-Policy: same-origin`  
  - `Cross-Origin-Resource-Policy: same-origin`  

## 10. Éléments de Conformité vs README_AUTHENTIFICATION
| Aspect | README | Implémentation Réelle | Écart |
|--------|--------|-----------------------|-------|
| Accès JWT front | Jamais exposé | Exposé via JSON login | Critique |
| Middleware usage | Toutes routes protégées | Non appliqué à POST avis | Critique |
| CSRF mention | Proposé en améliorations | Absent | Critique |
| Cookie sécurité | HttpOnly + Secure | Secure conditionnel (HTTP dev) | Moyen |
| Support Authorization header | Cookie uniquement | Cookie prioritaire + Authorization: Bearer (fallback) | Aucun (doc mise à jour) |

## 11. Résultats Pattern Search
- Pas de `eval`, `system`, `shell_exec`, `var_dump`, `print_r` exposés.  
- Usage mot `password` limité à hachage / validation (pas de fuite).  
- Secret JWT présent dans `.env` & `docker-compose.yml` (exposition en runtime build).  

## 12. Risques Résiduels Après Phase 0
- XSS potentielle commentaires si rendu sans échappement.  
- Absence rotation tokens jusqu’à Phase 2.  
- Pas de rate limiting login avant Phase 1.  

## 13. Indicateurs de Succès (Post-Remédiation)
- Taux échecs login < seuil défini; verrouillage après N tentatives.  
- Aucun token présent dans réponses réseau (inspect DevTools).  
- CSP appliquée → blocage scripts externes non autorisés.  
- Avis crée toujours avec user authentifié (correspond à cookie).  
- Logs sécurité agrégés & consultables (recherches par user_id).  

## 14. Backlog Sécurité Structuré
| Sprint | Items |
|--------|-------|
| 1 | A1, B1, F1, B2 |
| 2 | E1, E2, D1, A5, G2 |
| 3 | Refactor Bootstrap, JWT rotation, Logging, Suspension invalidation |
| 4 | Password blacklist, WAF léger, Monitoring résilience |

## 15. Annexes
### 15.1 Fichiers Audités
- Core: `Bootstrap.php`, `Env.php`  
- Services: `AuthService.php`, `JwtService.php`, `CookieManager.php`  
- Middleware: `AuthMiddleware.php`  
- Repositories: `UserRepository.php`, `TrajetRepository.php`, `MysqlAvisRepository.php`, `MongoAvisRepository.php`, `ResilientAvisRepository.php`  
- Factories: `DatabaseFactory.php`, `AvisRepositoryFactory.php`  
- Models: `User.php`, `Avis.php`  
- Frontend JS: `auth.js`, `header.js`, `SessionManager.js`, `vue-des-covoiturages.js`  
- Config: `docker-compose.yml`, `docker/nginx/default.conf`, `.env`, `.env.example`  

### 15.3 Note sur Authorization: Bearer
- Nginx transmet explicitement `Authorization` à PHP (`fastcgi_param HTTP_AUTHORIZATION $http_authorization;`).
- `AuthMiddleware` privilégie le **cookie** et accepte `Authorization: Bearer` en fallback pour les tests outillés (curl/Postman).
- Impact sécurité: nul côté CSRF (header non envoyé automatiquement par le navigateur). Conserver le cookie HttpOnly pour le site web.

### 15.2 Références OWASP
- A01 Broken Access Control → B1, B2  
- A02 Cryptographic Failures → A3, H1  
- A05 Security Misconfiguration → G1, G2  
- A07 Identification & Auth Failures → A1, A5  
- A08 Software/Data Integrity → D1  
- A10 Server-Side Request Forgery (non applicable)  

## 16. Conclusion
L’application peut atteindre un bon niveau de sécurité avec des corrections ciblées réalisées en 3 phases. Les failles critiques sont aisément corrigeables sans refonte globale. Une modularisation ultérieure améliorera la maintenabilité et réduira le risque futur.

---
Rapport préparé automatiquement – Adapter selon politique interne sécurité.
















📋 Analyse complète pour US6 : Participer à un covoiturage
Architecture existante
✅ Base de données : Schéma MySQL complet avec :

Table utilisateur : avec colonne credit (DECIMAL(10,2))
Table covoiturage : avec colonne nb_places (INT)
Table participation : avec statuts ['demandee', 'confirmee', 'refusee', 'annulee', 'en_attente_validation', 'validee', 'probleme']
Table credit_operation : pour tracer les opérations de crédit
✅ Backend :

UserRepository pour gestion utilisateurs
TrajetRepository pour gestion trajets
TripService pour logique métier
TrajetController pour routes API
AuthMiddleware pour authentification JWT
System de validation avec QueryValidator
✅ Frontend :

Pages PHP dynamiques avec session
JavaScript (Vue.js-like) pour interactions
API REST JSON pour communication
Implémentation à faire
Phase 1 : Backend

ParticipationRepository - CRUD pour participations
ParticipationService - Logique double confirmation + mises à jour
ParticipationValidator - Validation des requêtes
UserRepository::updateCredit() - Débit des crédits
TrajetRepository::updateNbPlaces() - Mise à jour places
Routes API dans Bootstrap.php + méthodes dans TrajetController
Phase 2 : Frontend

Bouton "Participer" dans vue-covoiturage-detail.php
Modal double confirmation avec montant
JavaScript pour appels API + gestion session
Redirection login si visiteur
Workflow participations :


Utilisateur clique "Participer"  ↓[Vérifier : places > 0 ET credit >= prix]  ↓[Si visiteur → Redirection login]  ↓Modal 1ère confirmation (affiche montant, bouton "Confirmer")  ↓POST /api/participations/request → status: 'demandee'  ↓Modal 2ème confirmation (dernière chance, bouton "Valider")  ↓POST /api/participations/validate → status: 'validee'  ↓UPDATE utilisateur.credit -= prix_personne  ↓UPDATE covoiturage.nb_places -= 1  ↓✅ Confirmation
Informations clés :

Crédit initial : 20.00 € (défini dans User::__construct)
Trajet détail récupéré via /api/trajets/detail?id=
Authentification via JWT/Session cookie
Double validation utilise statuts : demandee → en_attente_validation → validee