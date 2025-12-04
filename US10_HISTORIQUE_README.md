# US10 - Historique des Covoiturages

## 📋 Résumé de l'US

**Utilisateurs concernés**: Chauffeur + Passager

**Objectif**: Permettre aux utilisateurs de consulter l'historique de leurs covoiturages (en tant que chauffeur ou passager) et d'annuler un covoiturage avec gestion automatique des crédits et notifications.

**Fonctionnalités principales**:
1. Consulter l'historique des trajets passés et en cours
2. Annuler un covoiturage (chauffeur annule le trajet complet; passager annule sa participation)
3. Remboursement automatique des crédits
4. Libération des places disponibles
5. Notifications email aux participants en cas d'annulation par le chauffeur

---

## 🏗️ Architecture

```
Frontend (/historique.html)
    ↓
GET /api/historique/trajets (récupère l'historique)
    ↓
POST /api/trajets/{id}/annuler (annule le trajet/participation)
    ↓
Backend Services
    ├─ HistoryService (récupération historique)
    ├─ CancellationService (logique annulation)
    └─ EmailService (notifications)
    ↓
Repositories
    ├─ TrajetRepository
    ├─ ParticipationRepository
    ├─ UserRepository
    └─ CreditOperationRepository
    ↓
MySQL Database
    ├─ covoiturage (statut)
    ├─ participation (statut)
    ├─ utilisateur (credit)
    └─ credit_operation (logs)
```

---

## 📝 Ordre d'Implémentation (Étapes Séquentielles)

### **PHASE 1: Préparation Backend - Repositories**

#### Étape 1: Vérifier/Modifier `ParticipationRepository`
**Fichier**: `app/Repositories/ParticipationRepository.php`

**Tâches**:
- [ ] Ajouter méthode `findByTrip(int $tripId): array`
  - Retourne toutes les participations d'un trajet
  - Utile pour chercher tous les participants à notifier
  
- [ ] Ajouter méthode `updateStatusByTrip(int $tripId, string $newStatus): int`
  - Met à jour le statut de TOUTES les participations d'un trajet
  - Utile pour annulation chauffeur

- [ ] Ajouter méthode `findByUserAndStatus(int $userId, string $status): array`
  - Retourne participations d'un utilisateur avec un certain statut
  - Utile pour l'historique

**Dépend de**: Aucune

---

#### Étape 2: Vérifier/Modifier `TrajetRepository`
**Fichier**: `app/Repositories/TrajetRepository.php`

**Tâches**:
- [ ] Vérifier existence méthode `findByDriver(int $driverId): array`
  - Retourne tous les trajets créés par un chauffeur
  - Si absent: ajouter

- [ ] Ajouter méthode `updateStatus(int $trajetId, string $newStatus): bool`
  - Change le statut d'un trajet
  - Ex: 'planifie' → 'annule'

- [ ] Ajouter méthode `updatePlaces(int $trajetId, int $nbPlaces): bool`
  - Augmente/diminue les places disponibles
  - Ex: libération de places lors annulation passager

**Dépend de**: Aucune

---

#### Étape 3: Créer `CreditOperationRepository` (si absent)
**Fichier**: `app/Repositories/CreditOperationRepository.php`

**Tâches**:
- [ ] Créer classe implémentant interface `CreditOperationRepositoryInterface`
- [ ] Méthode `create(int $userId, string $type, float $amount): int`
  - Enregistre une opération de crédit/débit
  - Params: type = 'credit'|'debit', amount = montant
  
- [ ] Méthode `findByUser(int $userId, ?int $limit): array`
  - Retourne historique opérations d'un utilisateur

**Dépend de**: Aucune

---

#### Étape 4: Modifier `UserRepository`
**Fichier**: `app/Repositories/UserRepository.php`

**Tâches**:
- [ ] Ajouter méthode `updateCredit(int $userId, float $amount): bool`
  - Ajoute/soustrait des crédits (increment/decrement)
  - Valider que credit ne devient pas négatif
  
- [ ] Ajouter méthode `getCredit(int $userId): float`
  - Retourne solde crédits actuel
  - Utile pour validation avant débit

**Dépend de**: Aucune

---

### **PHASE 2: Préparation Backend - Services**

#### Étape 5: Créer `HistoryService`
**Fichier**: `app/Services/HistoryService.php`

**Tâches**:
- [ ] Créer classe `HistoryService`
- [ ] Injecter dépendances: `TrajetRepository`, `ParticipationRepository`
- [ ] Ajouter méthode `getUserTripHistory(int $userId, ?array $filters): array`
  - Récupère trajets utilisateur (chauffeur + passager)
  - Filtre optionnel: date, statut, rôle
  - Retourne tableau avec:
    ```json
    {
      "role": "chauffeur|passager",
      "covoiturage_id": 1,
      "date_depart": "2025-12-10",
      "heure_depart": "14:00",
      "lieu_depart": "Paris",
      "lieu_arrivee": "Lyon",
      "statut": "planifie|en_cours|termine|annule",
      "prix_personne": 45.00,
      "nb_places": 2,
      "conducteur_id": 5,
      "conducteur_pseudo": "jean_covoiture",
      "nb_participants": 2
    }
    ```
  - Trier par date DESC (plus récent d'abord)

- [ ] Ajouter méthode `getDetailedTrip(int $tripId): array`
  - Retourne détails complets d'un trajet pour affichage

**Dépend de**: Étape 1-2

---

#### Étape 6: Créer `CancellationService`
**Fichier**: `app/Services/CancellationService.php`

**Tâches**:
- [ ] Créer classe `CancellationService`
- [ ] Injecter dépendances: `TrajetRepository`, `ParticipationRepository`, `UserRepository`, `CreditOperationRepository`, `EmailService`

- [ ] Ajouter méthode `cancelTripAsDriver(int $tripId, int $driverId, ?string $reason): array`
  - **Validations**:
    - Vérifier `driverId` = conducteur_id du trajet (403 sinon)
    - Vérifier trajet.statut ≠ 'en_cours' et ≠ 'termine' (403 sinon)
    - Vérifier trajet.statut ≠ 'annule' déjà (400 sinon)
  
  - **Logique**:
    - Changer `trajet.statut` → 'annule'
    - Enregistrer `date_annulation` = NOW()
    - Récupérer tous les participants avec `participation.statut` = 'confirmee'
    - Pour chaque participant:
      - Changer participation.statut → 'annulee'
      - Calculer remboursement: `montant = participation.nb_places * trajet.prix_personne`
      - Mettre à jour `utilisateur.credit` += montant
      - Enregistrer dans `credit_operation` (type='credit', montant, user_id)
    - Envoyer emails à tous les participants (via `EmailService`)
  
  - **Retour**:
    ```json
    {
      "success": true,
      "message": "Trajet annulé",
      "data": {
        "trajet_id": 1,
        "statut": "annule",
        "remboursements": [
          {"utilisateur_id": 10, "montant": 45.00},
          {"utilisateur_id": 11, "montant": 45.00}
        ],
        "emails_envoyes": 2
      }
    }
    ```

- [ ] Ajouter méthode `cancelParticipationAsPassenger(int $tripId, int $passengerId, ?string $reason): array`
  - **Validations**:
    - Vérifier passager a une participation sur ce trajet (404 sinon)
    - Vérifier participation.statut = 'confirmee' (400 sinon)
    - Vérifier trajet.statut ≠ 'en_cours' et ≠ 'termine' (403 sinon)
  
  - **Logique**:
    - Récupérer participation
    - Changer participation.statut → 'annulee'
    - Calculer remboursement: `montant = participation.nb_places * trajet.prix_personne`
    - Mettre à jour utilisateur.credit += montant
    - Enregistrer credit_operation
    - Libérer places: `trajet.nb_places` += participation.nb_places
    - Optionnel: notifier chauffeur (via email)
  
  - **Retour**: similaire à `cancelTripAsDriver`

- [ ] Ajouter méthode `validateCancellation(int $tripId, int $userId, string $role): void`
  - Classe utilitaire de validation
  - Lance exception si conditions non respectées

**Dépend de**: Étape 1-4

---

#### Étape 7: Créer/Modifier `EmailService`
**Fichier**: `app/Services/EmailService.php`

**Tâches** (si service n'existe pas):
- [ ] Créer classe `EmailService`
- [ ] Ajouter méthode `sendCancellationNotification(array $participant, array $tripDetails, string $reason): bool`
  - Params:
    - `$participant`: array avec email, prenom, pseudo
    - `$tripDetails`: array avec date, lieu, heure, montant remboursé
    - `$reason`: raison annulation (optionnel)
  
  - **Template email**:
    ```
    Sujet: ❌ Annulation du covoiturage {{ date }} {{ lieu_depart }} → {{ lieu_arrivee }}
    
    Bonjour {{ prenom }},
    
    Le chauffeur {{ chauffeur_pseudo }} a annulé le covoiturage prévu le {{ date }} à {{ heure }}.
    
    📍 Trajet: {{ lieu_depart }} → {{ lieu_arrivee }}
    💺 Places: {{ nb_places }} place(s) annulée(s)
    💰 Remboursement: {{ montant_rembourse }} crédits
    📝 Raison: {{ reason }}
    
    Votre nouveau solde: {{ nouveau_solde }} crédits.
    
    EcoRide Team
    ```
  
  - Retour: true/false (log erreurs, ne pas bloquer annulation)

- [ ] Ajouter méthode `sendParticipantCancellationToDriver(array $driver, array $tripDetails, string $passengerName): bool`
  - Notifier le chauffeur quand un passager annule sa participation

**Dépend de**: Étape 4

---

### **PHASE 3: Préparation Backend - Controllers**

#### Étape 8: Créer `HistoryController`
**Fichier**: `app/Controllers/HistoryController.php`

**Tâches**:
- [ ] Créer classe `HistoryController`
- [ ] Ajouter méthode statique `getTripHistory(): void`
  - **Route**: GET `/api/historique/trajets`
  - **Middlewares**: `AuthMiddleware` (obligatoire)
  - **Query params** (optionnels):
    - `statut`: 'planifie'|'en_cours'|'termine'|'annule'
    - `role`: 'chauffeur'|'passager'
    - `date_from`: YYYY-MM-DD
    - `date_to`: YYYY-MM-DD
  
  - **Logique**:
    - Récupérer user_id du JWT via `AuthMiddleware`
    - Valider params optionnels
    - Appeler `HistoryService->getUserTripHistory($userId, $filters)`
    - Normaliser réponse JSON
  
  - **Réponse succès** (200):
    ```json
    {
      "success": true,
      "data": {
        "items": [
          {
            "role": "chauffeur",
            "covoiturage_id": 1,
            ...
          }
        ],
        "count": 5
      }
    }
    ```
  
  - **Erreurs**:
    - 401: Non authentifié
    - 400: Paramètres invalides

**Dépend de**: Étape 5

---

#### Étape 9: Modifier `TrajetController` - Ajouter méthode annulation
**Fichier**: `app/Controllers/TrajetController.php`

**Tâches**:
- [ ] Ajouter méthode statique `cancelTrip(): void`
  - **Route**: POST `/api/trajets/{id}/annuler`
  - **Middlewares**: `AuthMiddleware` + `CsrfMiddleware` (obligatoire)
  - **Body JSON**:
    ```json
    {
      "raison": "Raison optionnelle"
    }
    ```
  
  - **Logique**:
    - Récupérer `id` depuis l'URL (paramètre)
    - Récupérer user_id du JWT
    - Récupérer trajet via `TrajetRepository->findById($id)`
    - Déterminer rôle utilisateur:
      - Si user_id = conducteur_id → `cancelTripAsDriver()`
      - Si user_id a participation → `cancelParticipationAsPassenger()`
      - Sinon → 403 Unauthorized
    - Appeler `CancellationService->cancelTripAsDriver()` ou `->cancelParticipationAsPassenger()`
    - Retourner réponse JSON
  
  - **Réponses**:
    - 200: Succès
    - 400: Données invalides (trajet déjà annulé, mauvais statut)
    - 401: Non authentifié
    - 403: Non autorisé (pas le droit d'annuler)
    - 404: Trajet non trouvé

**Dépend de**: Étape 6

---

### **PHASE 4: Préparation Frontend**

#### Étape 10: Créer page `frontend/pages/historique.html`
**Fichier**: `frontend/pages/historique.html`

**Tâches**:
- [ ] Créer structure HTML de base
  - Header avec titre "Mon Historique de Covoiturages"
  - Section filtres (date, statut, rôle)
  - Section liste trajets
  - Modal confirmation annulation
  - Pagination (si liste longue)

- [ ] Design responsive (mobile, tablette, desktop)
- [ ] Appliquer styles EcoRide (colors, thème écologique)
- [ ] Zones dynamiques à remplir via JS

**Contenu minimal**:
```html
<div class="historique-container">
  <h1>📋 Mon Historique de Covoiturages</h1>
  
  <div class="filters">
    <select id="filter-statut">
      <option value="">Tous les statuts</option>
      <option value="planifie">Planifié</option>
      <option value="en_cours">En cours</option>
      <option value="termine">Terminé</option>
      <option value="annule">Annulé</option>
    </select>
    <select id="filter-role">
      <option value="">Tous les rôles</option>
      <option value="chauffeur">Chauffeur</option>
      <option value="passager">Passager</option>
    </select>
  </div>
  
  <div id="trips-list"></div>
  <div id="loading" style="display:none;">Chargement...</div>
  <div id="error" style="display:none;"></div>
  
  <div id="cancel-modal" class="modal" style="display:none;">
    <!-- Contenu modal annulation -->
  </div>
</div>
```

**Dépend de**: Aucune (frontend)

---

#### Étape 11: Créer script `frontend/js/historique.js`
**Fichier**: `frontend/js/pages/historique.js`

**Tâches**:
- [ ] Ajouter fonction `loadHistory(filters = {}): Promise`
  - Appelle GET `/api/historique/trajets?...`
  - Utilise credentials: 'include' (cookies)
  - Gère erreurs (401 → redirect login, 400 → affiche message)

- [ ] Ajouter fonction `renderTrips(trips): void`
  - Pour chaque trajet, construire élément HTML
  - Afficher: date, lieu, statut, rôle, prix
  - Bouton "Annuler" visible si statut='planifie' et droits OK
  - Bouton "Voir détails" (optionnel, pour afficher plus d'infos)

- [ ] Ajouter fonction `initCancelModal(tripId): void`
  - Affiche modal de confirmation
  - Champs: raison (optionnel)
  - Boutons: Confirmer, Annuler

- [ ] Ajouter fonction `submitCancelation(tripId, reason = ''): Promise`
  - POST `/api/trajets/{tripId}/annuler`
  - Headers: `X-CSRF-Token` (via `SessionManager.csrfHeaders()`)
  - Body: `{raison: reason}`
  - Gère réponse: affiche succès, met à jour liste

- [ ] Ajouter listeners:
  - Click "Annuler" → `initCancelModal()`
  - Confirm annulation → `submitCancelation()`
  - Changement filtres → `loadHistory()`
  - Page load → `loadHistory()`

- [ ] Ajouter style pour affichage statut (couleurs):
  - Planifié: vert
  - En cours: bleu
  - Terminé: gris
  - Annulé: rouge

**Dépend de**: Étape 8, Étape 10

---

#### Étape 12: Modifier menu/navbar - Ajouter lien historique
**Fichier**: `frontend/templates/navbar.html` ou fichier menu concerné

**Tâches**:
- [ ] Ajouter lien vers `/pages/historique.html`
  - Visible après authentification uniquement
  - Icône: 📋 ou 🕐
  - Texte: "Mon Historique"
  - Position: avant "Déconnexion"

**Dépend de**: Aucune

---

#### Étape 13: Modifier `frontend/js/session-manager.js` (ou auth.js)
**Tâches**:
- [ ] Vérifier que `SessionManager.csrfHeaders()` existe
- [ ] Utiliser dans `historique.js` pour POST annulation
- [ ] Tester avec curl:
  ```bash
  # Sans CSRF → 403
  curl -X POST http://localhost:8080/api/trajets/1/annuler \
    -H 'Content-Type: application/json' \
    -d '{"raison":"Test"}'
  
  # Avec CSRF → 200
  curl -X POST http://localhost:8080/api/trajets/1/annuler \
    -H 'Content-Type: application/json' \
    -H "X-CSRF-Token: $TOKEN" \
    -d '{"raison":"Test"}'
  ```

**Dépend de**: Étape 9

---

### **PHASE 5: Tests**

#### Étape 14: Tester endpoints via curl
**Commandes**:

1. **Authentification**:
   ```bash
   # Login
   curl -c cookies.txt -X POST http://localhost:8080/api/auth/login \
     -H 'Content-Type: application/json' \
     -d '{"email":"test@test.com","password":"Test1234"}'
   
   # Récupérer CSRF token
   CSRF=$(curl -s -b cookies.txt http://localhost:8080/api/csrf-token | jq -r '.data.csrfToken')
   ```

2. **GET historique**:
   ```bash
   curl -b cookies.txt http://localhost:8080/api/historique/trajets
   # Attendu: tableau de trajets avec rôle (chauffeur/passager)
   ```

3. **POST annulation (chauffeur)**:
   ```bash
   curl -b cookies.txt -X POST http://localhost:8080/api/trajets/1/annuler \
     -H 'Content-Type: application/json' \
     -H "X-CSRF-Token: $CSRF" \
     -d '{"raison":"Problème personnel"}'
   # Attendu: 200 + message succès + remboursements
   ```

4. **POST annulation (passager)**:
   ```bash
   curl -b cookies.txt -X POST http://localhost:8080/api/trajets/2/annuler \
     -H 'Content-Type: application/json' \
     -H "X-CSRF-Token: $CSRF" \
     -d '{"raison":"Changement de plans"}'
   # Attendu: 200 + montant remboursé
   ```

5. **POST annulation non autorisé**:
   ```bash
   curl -b cookies.txt -X POST http://localhost:8080/api/trajets/3/annuler \
     -H 'Content-Type: application/json' \
     -H "X-CSRF-Token: $CSRF" \
     -d '{"raison":"Test"}'
   # Attendu: 403 si user n'a pas de droit
   ```

**Dépend de**: Étape 9, Étape 14

---

#### Étape 15: Tester sur page frontend
**Tests manuels**:
- [ ] Charger `/pages/historique.html`
- [ ] Vérifier liste trajets chargée (GET /api/historique/trajets)
- [ ] Tester filtres (statut, rôle)
- [ ] Cliquer "Annuler" sur trajet planifié
- [ ] Modal confirmation apparu
- [ ] Valider annulation
- [ ] Vérifier trajet statut changé à 'annulé'
- [ ] Vérifier message succès + montant remboursé affiché
- [ ] Rafraîchir page → historique à jour

**Tests de sécurité**:
- [ ] Vérifier que non-authentifié → redirected vers login
- [ ] Vérifier que passager ne peut annuler trajet du chauffeur
- [ ] Vérifier que chauffeur ne peut annuler participation du passager

**Dépend de**: Étape 11-12

---

### **PHASE 6: Optimisations & Finalisation**

#### Étape 16: Ajouter pagination (optionnel mais recommandé)
**Fichier**: `app/Services/HistoryService.php`

**Tâches**:
- [ ] Modifier `getUserTripHistory()` pour accepter `limit` et `offset`
- [ ] Retourner aussi `total_count` dans réponse
- [ ] Frontend: implémenter pagination (Prev/Next ou scroll infini)

---

#### Étape 17: Ajouter export historique (optionnel)
**Fonctionnalité**:
- [ ] Bouton "Exporter en PDF" ou "CSV"
- [ ] Endpoint GET `/api/historique/trajets/export?format=pdf|csv`
- [ ] Générer document avec PHP (TCPDF pour PDF)

---

#### Étape 18: Documentation API
**Fichier**: `API_AUTH.md`

**Tâches**:
- [ ] Documenter GET `/api/historique/trajets`
  - Params, réponses, codes erreur
- [ ] Documenter POST `/api/trajets/{id}/annuler`
  - Body, réponses, codes erreur, exemple curl

---

#### Étape 19: Checklist de sécurité
**À vérifier**:
- [ ] `AuthMiddleware` appliqué sur GET et POST
- [ ] `CsrfMiddleware` appliqué sur POST
- [ ] user_id dérivé du JWT (jamais du client)
- [ ] Validation des droits (chauffeur/passager)
- [ ] Pas d'annulation trajet 'en_cours'/'termine'
- [ ] Erreurs JSON sans infos internes
- [ ] Credit ne devient jamais négatif
- [ ] Logs d'annulation créés

---

## ✅ Checklist Finale (À Cocher Après Implémentation)

### Backend
- [ ] Étape 1: ParticipationRepository complété
- [ ] Étape 2: TrajetRepository modifié
- [ ] Étape 3: CreditOperationRepository créé
- [ ] Étape 4: UserRepository modifié
- [ ] Étape 5: HistoryService créé
- [ ] Étape 6: CancellationService créé
- [ ] Étape 7: EmailService créé/modifié
- [ ] Étape 8: HistoryController créé
- [ ] Étape 9: TrajetController.cancelTrip() ajouté
- [ ] Étape 13: Tests curl passants

### Frontend
- [ ] Étape 10: Page historique.html créée
- [ ] Étape 11: Script historique.js créé
- [ ] Étape 12: Lien menu ajouté
- [ ] Étape 15: Tests manuels passants

### Sécurité & Docs
- [ ] Étape 18: API documentée
- [ ] Étape 19: Checklist sécurité validée

---

## 🔗 Dépendances Entre Étapes

```
Étape 1 ─────────┐
Étape 2 ─────┐   │
            ├─→ Étape 5 ─→ Étape 8 ─→ Étape 10 ─→ Étape 11 ─→ Étape 15
Étape 3 ─┐  │
Étape 4 ─┼──┘
         │
         └─→ Étape 6 ─→ Étape 9 ─→ Étape 13

Étape 7 ────→ Étape 6

Étape 12 ──→ Étape 15
Étape 13 ──→ Étape 15

Étapes 16, 17, 18, 19 → indépendantes (optimisations finales)
```

---

## 🚀 Points Importants à Retenir

1. **Ordre de priorité**: Backend AVANT Frontend (API doit être prête)
2. **Sécurité**: AuthMiddleware + CsrfMiddleware sur TOUS les endpoints
3. **Atomicité**: Annulation = transaction (tout ou rien)
4. **Notifications**: Emails ne doivent PAS bloquer l'annulation
5. **Statuts**: Vérifier validité (pas d'annulation si 'en_cours')
6. **Crédits**: Toujours enregistrer dans `credit_operation` pour audit

---

## 📞 Support

Pour toute question ou blocage, se référer à:
- Architecture: `ARCHITECTURE.md`
- Auth: `API_AUTH.md`
- Sécurité: `README_DEVELOPMENT_SECURITY.md`
- Structure BD: `docker/mysql/init/001_schema.sql`
