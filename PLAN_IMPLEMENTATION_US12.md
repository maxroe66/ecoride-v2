# 📋 US12 : Espace Employé - Plan d'Implémentation Détaillé

## 🎯 Objectifs
1. Créer un espace d'administration pour les employés
2. Valider/refuser les avis avant publication
3. Gérer les incidents (participations avec `statut = 'probleme'`)
4. Ajouter lien header uniquement pour employés

---

## 📊 PHASE 0 : PRÉPARATION BASE DE DONNÉES

### 0.1 Migration : Ajouter rôle `employe`
**Fichier** : `database/migrations/add_employe_role.sql`

```sql
-- Ajouter rôle employe existant
ALTER TABLE utilisateur MODIFY COLUMN role ENUM('passager', 'chauffeur', 'chauffeur_passager', 'employe') DEFAULT 'passager';

-- Créer un employé test
INSERT INTO utilisateur (email, password, pseudo, role, date_creation) 
VALUES ('employe@ecoride.fr', 'hashed_password_here', 'employe_test', 'employe', NOW());
```

### 0.2 Migration : Colonnes modération avis
**Fichier** : `database/migrations/add_avis_moderation.sql`

```sql
-- Ajouter colonnes modération
ALTER TABLE avis_fallback ADD COLUMN statut_moderation ENUM('en_attente', 'approuve', 'refuse') DEFAULT 'en_attente' AFTER commentaire;
ALTER TABLE avis_fallback ADD COLUMN date_moderation DATETIME NULL AFTER statut_moderation;
ALTER TABLE avis_fallback ADD COLUMN employe_id INT NULL AFTER date_moderation;
ALTER TABLE avis_fallback ADD FOREIGN KEY (employe_id) REFERENCES utilisateur(utilisateur_id);

-- Créer index pour requêtes fréquentes
CREATE INDEX idx_avis_moderation ON avis_fallback(statut_moderation);
```

### 0.3 Schéma : Table incidents (participations problématiques)
**Utiliser** : `participation` table existante
- Filtrer par `statut = 'probleme'`
- Récupérer détails via JOIN `covoiturage` + `utilisateur`

---

## 🔧 PHASE 1 : BACKEND

### 1.1 Model : Avis avec Modération
**Fichier** : `app/Models/AvisModeration.php` (nouvelle classe ou étendre `Avis`)

```php
<?php
namespace App\Models;

class AvisModeration extends Avis {
    public function __construct(
        int $rideId,
        int $userId,
        int $rating,
        ?string $comment,
        public readonly string $statutModeration = 'en_attente',
        public readonly ?\DateTimeImmutable $dateModeration = null,
        public readonly ?int $employeId = null
    ) {
        parent::__construct($rideId, $userId, $rating, $comment);
    }
}
```

### 1.2 Middleware : Employee
**Fichier** : `app/Middleware/EmployeeMiddleware.php`

```php
<?php
namespace App\Middleware;

use App\Core\Response;
use App\Helpers\ControllerHelper;

class EmployeeMiddleware {
    public static function verify() {
        $userId = ControllerHelper::getAuthUserId();
        if (!$userId) {
            Response::json(401, ['error' => 'Non authentifié']);
            exit;
        }
        
        // Récupérer user et vérifier role = 'employe'
        // (à implémenter avec UserRepository)
    }
}
```

### 1.3 Validator : Modération Avis
**Fichier** : `app/Validators/AvisModerationValidator.php`

```php
<?php
namespace App\Validators;

use App\Exceptions\ValidationException;

class AvisModerationValidator {
    public static function validateModeration(int $avisId, string $action, ?string $reason = null): void {
        // Vérifier avisId > 0
        if ($avisId <= 0) {
            throw new ValidationException(['ID avis invalide']);
        }
        
        // Vérifier action in ['approuve', 'refuse']
        if (!in_array($action, ['approuve', 'refuse'])) {
            throw new ValidationException(['Action invalide. Doit être: approuve ou refuse']);
        }
        
        // Si refuse, raison optionnelle mais validée (max 500 chars)
        if ($action === 'refuse' && $reason !== null && strlen($reason) > 500) {
            throw new ValidationException(['Raison trop longue (max 500)']);
        }
    }
}
```

### 1.4 Service : Modération Avis
**Fichier** : `app/Services/AvisModerationService.php`

```php
<?php
namespace App\Services;

use App\Repositories\AvisRepository;
use App\Validators\AvisModerationValidator;

class AvisModerationService {
    public function __construct(private AvisRepository $avisRepo = null) {
        $this->avisRepo = $avisRepo ?: new AvisRepository();
    }
    
    /**
     * Approuver un avis
     */
    public function approveReview(int $avisId, int $employeId): bool {
        AvisModerationValidator::validateModeration($avisId, 'approuve');
        
        // UPDATE avis SET statut_moderation='approuve', date_moderation=NOW(), employe_id=:id
        return $this->avisRepo->updateModeration($avisId, 'approuve', $employeId);
    }
    
    /**
     * Refuser un avis
     */
    public function rejectReview(int $avisId, int $employeId, ?string $reason = null): bool {
        AvisModerationValidator::validateModeration($avisId, 'refuse', $reason);
        
        // UPDATE avis SET statut_moderation='refuse', date_moderation=NOW(), employe_id=:id
        // (Optionnel : créer table avis_refusal pour tracer raisons)
        return $this->avisRepo->updateModeration($avisId, 'refuse', $employeId);
    }
    
    /**
     * Récupérer avis en attente de modération
     */
    public function getPendingReviews(): array {
        return $this->avisRepo->findByModeration('en_attente');
    }
}
```

### 1.5 Repository : Avis avec Modération
**Fichier** : Étendre [`app/Repositories/AvisRepository.php`](app/Repositories/MysqlAvisRepository.php)

```php
<?php
// Ajouter méthodes à MysqlAvisRepository

public function findByModeration(string $statut): array {
    $stmt = $this->db->prepare('
        SELECT 
            a.*,
            u_auteur.pseudo as auteur_pseudo,
            u_auteur.email as auteur_email,
            c.date_depart, c.heure_depart, c.lieu_depart, c.lieu_arrivee,
            u_conducteur.pseudo as conducteur_pseudo
        FROM avis_fallback a
        JOIN utilisateur u_auteur ON a.utilisateur_id = u_auteur.utilisateur_id
        JOIN covoiturage c ON a.covoiturage_id = c.covoiturage_id
        JOIN utilisateur u_conducteur ON c.conducteur_id = u_conducteur.utilisateur_id
        WHERE a.statut_moderation = :statut
        ORDER BY a.date_creation ASC
    ');
    $stmt->execute([':statut' => $statut]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function updateModeration(int $avisId, string $statut, int $employeId): bool {
    $stmt = $this->db->prepare('
        UPDATE avis_fallback 
        SET statut_moderation = :statut, 
            date_moderation = NOW(), 
            employe_id = :employe_id
        WHERE id_avis = :id
    ');
    return $stmt->execute([
        ':id' => $avisId,
        ':statut' => $statut,
        ':employe_id' => $employeId
    ]);
}
```

### 1.6 Repository : Incidents
**Fichier** : `app/Repositories/IncidentRepository.php`

```php
<?php
namespace App\Repositories;

use PDO;

class IncidentRepository {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Récupérer participations avec statut 'probleme'
     */
    public function findByStatus(string $status = 'probleme'): array {
        $stmt = $this->db->prepare('
            SELECT 
                p.participation_id,
                p.covoiturage_id,
                p.utilisateur_id as passager_id,
                u_passager.pseudo as passager_pseudo,
                u_passager.email as passager_email,
                c.conducteur_id,
                u_conducteur.pseudo as conducteur_pseudo,
                u_conducteur.email as conducteur_email,
                c.date_depart,
                c.heure_depart,
                c.lieu_depart,
                c.lieu_arrivee,
                p.raison_probleme,
                p.date_probleme,
                p.statut
            FROM participation p
            JOIN utilisateur u_passager ON p.utilisateur_id = u_passager.utilisateur_id
            JOIN covoiturage c ON p.covoiturage_id = c.covoiturage_id
            JOIN utilisateur u_conducteur ON c.conducteur_id = u_conducteur.utilisateur_id
            WHERE p.statut = :status
            ORDER BY p.date_probleme DESC
        ');
        $stmt->execute([':status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupérer détails incident par ID
     */
    public function findById(int $participationId): ?array {
        $stmt = $this->db->prepare('
            SELECT 
                p.*,
                u_passager.pseudo as passager_pseudo,
                u_passager.email as passager_email,
                c.*,
                u_conducteur.pseudo as conducteur_pseudo,
                u_conducteur.email as conducteur_email
            FROM participation p
            JOIN utilisateur u_passager ON p.utilisateur_id = u_passager.utilisateur_id
            JOIN covoiturage c ON p.covoiturage_id = c.covoiturage_id
            JOIN utilisateur u_conducteur ON c.conducteur_id = u_conducteur.utilisateur_id
            WHERE p.participation_id = :id
        ');
        $stmt->execute([':id' => $participationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
```

### 1.7 DTO : Modération Avis
**Fichier** : `app/DTO/AvisModerationRequest.php`

```php
<?php
namespace App\DTO;

use Exception;

class AvisModerationRequest {
    public function __construct(
        public readonly int $avisId,
        public readonly string $action, // 'approuve' | 'refuse'
        public readonly ?string $reason = null
    ) {}
    
    public static function fromArray(array $data): self {
        if (empty($data['avis_id']) || !is_int((int)$data['avis_id'])) {
            throw new Exception('ID avis manquant ou invalide');
        }
        if (empty($data['action']) || !is_string($data['action'])) {
            throw new Exception('Action manquante');
        }
        
        return new self(
            avisId: (int)$data['avis_id'],
            action: trim($data['action']),
            reason: isset($data['reason']) ? trim($data['reason']) : null
        );
    }
}
```

### 1.8 Controller : Employé
**Fichier** : `app/Controllers/EmployeeController.php`

```php
<?php
namespace App\Controllers;

use App\Factories\ServiceLocator as SL;
use App\Validators\AvisModerationValidator;
use App\DTO\AvisModerationRequest;
use App\Helpers\ControllerHelper;
use App\Core\Request;
use App\Core\Response;
use Exception;

class EmployeeController {
    /**
     * GET /api/employee/reviews/pending
     * Récupérer avis en attente
     */
    public static function getPendingReviews(Request $req): void {
        try {
            $employeId = ControllerHelper::getAuthUserId();
            $service = SL::getAvisModerationService();
            $pending = $service->getPendingReviews();
            
            Response::json(200, [
                'success' => true,
                'data' => [
                    'count' => count($pending),
                    'items' => $pending
                ]
            ]);
        } catch (Exception $e) {
            Response::json(500, [
                'success' => false,
                'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }
    
    /**
     * POST /api/employee/reviews/{id}/moderation
     * Approver/Refuser un avis
     */
    public static function moderateReview(Request $req): void {
        try {
            $employeId = ControllerHelper::getAuthUserId();
            $avisId = (int)$req->getPathParam(0);
            
            $dto = AvisModerationRequest::fromArray($req->getJsonBody());
            AvisModerationValidator::validateModeration($dto->avisId, $dto->action, $dto->reason);
            
            $service = SL::getAvisModerationService();
            
            if ($dto->action === 'approuve') {
                $ok = $service->approveReview($avisId, $employeId);
            } else {
                $ok = $service->rejectReview($avisId, $employeId, $dto->reason);
            }
            
            if ($ok) {
                Response::json(200, [
                    'success' => true,
                    'data' => ['message' => 'Avis modéré avec succès']
                ]);
            } else {
                Response::json(400, ['success' => false, 'error' => ['message' => 'Erreur modération']]);
            }
        } catch (Exception $e) {
            Response::json(400, [
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]
            ]);
        }
    }
    
    /**
     * GET /api/employee/incidents
     * Récupérer participations avec problèmes
     */
    public static function getIncidents(Request $req): void {
        try {
            $incidentRepo = new \App\Repositories\IncidentRepository(\App\Factories\DatabaseFactory::getConnection());
            $incidents = $incidentRepo->findByStatus('probleme');
            
            Response::json(200, [
                'success' => true,
                'data' => [
                    'count' => count($incidents),
                    'items' => $incidents
                ]
            ]);
        } catch (Exception $e) {
            Response::json(500, [
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ]);
        }
    }
    
    /**
     * GET /api/employee/incidents/{id}
     * Détails incident
     */
    public static function getIncidentDetail(Request $req): void {
        try {
            $participationId = (int)$req->getPathParam(0);
            $incidentRepo = new \App\Repositories\IncidentRepository(\App\Factories\DatabaseFactory::getConnection());
            $incident = $incidentRepo->findById($participationId);
            
            if (!$incident) {
                Response::json(404, ['success' => false, 'error' => ['message' => 'Incident non trouvé']]);
                return;
            }
            
            Response::json(200, [
                'success' => true,
                'data' => $incident
            ]);
        } catch (Exception $e) {
            Response::json(500, ['success' => false, 'error' => ['message' => $e->getMessage()]]);
        }
    }
}
```

### 1.9 Routes : Bootstrap
**Fichier** : Ajouter à [`app/Core/Bootstrap.php`](app/Core/Bootstrap.php)

```php
// Ajouter imports
use App\Controllers\EmployeeController;
use App\Middleware\EmployeeMiddleware;

// Ajouter routes (après authentification)
$mwEmployeeAuth = [MW::auth(), MW::csrf(), 'employee']; // Custom middleware check

// Avis en attente
$router->add('GET', '/api/employee/reviews/pending', [EmployeeController::class, 'getPendingReviews'], $mwEmployeeAuth);

// Modérer un avis
$router->add('POST', '/api/employee/reviews/{id}/moderation', [EmployeeController::class, 'moderateReview'], $mwEmployeeAuth);

// Incidents
$router->add('GET', '/api/employee/incidents', [EmployeeController::class, 'getIncidents'], $mwEmployeeAuth);
$router->add('GET', '/api/employee/incidents/{id}', [EmployeeController::class, 'getIncidentDetail'], $mwEmployeeAuth);
```

### 1.10 ServiceLocator : Ajouter Services
**Fichier** : Modifier [`app/Factories/ServiceLocator.php`](app/Factories/ServiceLocator.php)

```php
public static function getAvisModerationService(): AvisModerationService {
    return new AvisModerationService(
        new MysqlAvisRepository(DatabaseFactory::getConnection())
    );
}

public static function getIncidentRepository(): IncidentRepository {
    return new IncidentRepository(DatabaseFactory::getConnection());
}
```

---

## 🎨 PHASE 2 : FRONTEND

### 2.1 Mise à jour Header
**Fichier** : [`frontend/templates/layouts/header.php`](frontend/templates/layouts/header.php)

```php
<!-- Ajouter après lien "Profil" -->
<?php
  $userRole = isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : null;
?>

<!-- Lien Espace Employé (visible uniquement si role = 'employe') -->
<?php if ($userRole === 'employe'): ?>
  <li class="nav-item">
    <a href="/employee" class="nav-link">🛠️ Espace Employé</a>
  </li>
<?php endif; ?>
```

### 2.2 JavaScript : Session Manager
**Fichier** : Mettre à jour [`frontend/js/SessionManager.js`](frontend/js/SessionManager.js)

```javascript
// Ajouter propriété role au User
class SessionManager {
  static getUser() {
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    return {
      id: user.id,
      pseudo: user.pseudo,
      email: user.email,
      role: user.role || 'passager',  // ← AJOUTER
      credit: user.credit
    };
  }
  
  // Après login :
  static setUser(userData) {
    localStorage.setItem('user', JSON.stringify({
      id: userData.id,
      pseudo: userData.pseudo,
      email: userData.email,
      role: userData.role,  // ← CAPTURER
      credit: userData.credit
    }));
  }
}
```

### 2.3 Page Employé
**Fichier** : `frontend/pages/employee-space.php`

```php
<?php
/**
 * Page d'administration employé
 * Gestion avis + incidents
 */
$isConnected = isset($_SESSION['user']);
$userRole = isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : null;

// Vérifier accès
if (!$isConnected || $userRole !== 'employe') {
    header('Location: /login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoRide - Espace Employé</title>
  <link rel="stylesheet" href="/frontend/css/global.css">
  <link rel="stylesheet" href="/frontend/css/components/header.css">
  <link rel="stylesheet" href="/frontend/css/components/footer.css">
  <link rel="stylesheet" href="/frontend/css/employee-space.css">
</head>
<body>
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>
  
  <main class="employee-space">
    <div class="container">
      <h1>🛠️ Espace Employé</h1>
      
      <!-- Tabs Navigation -->
      <div class="tabs">
        <button class="tab-btn active" data-tab="reviews">📋 Avis en Attente</button>
        <button class="tab-btn" data-tab="incidents">⚠️ Incidents</button>
      </div>
      
      <!-- Tab 1 : Avis en Attente -->
      <div id="reviews-tab" class="tab-content active">
        <h2>Avis en attente de modération</h2>
        <div id="reviewsContainer" class="reviews-list">
          <p>Chargement...</p>
        </div>
      </div>
      
      <!-- Tab 2 : Incidents -->
      <div id="incidents-tab" class="tab-content">
        <h2>Incidents signalés</h2>
        <div id="incidentsContainer" class="incidents-list">
          <p>Chargement...</p>
        </div>
      </div>
    </div>
  </main>
  
  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>
  
  <script src="/frontend/js/employee-space.js"></script>
</body>
</html>
```

### 2.4 JavaScript : Employee Space
**Fichier** : `frontend/js/employee-space.js`

```javascript
/**
 * Gestion espace employé
 */

document.addEventListener('DOMContentLoaded', () => {
  setupTabs();
  loadPendingReviews();
  loadIncidents();
});

// === TABS ===
function setupTabs() {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const tabName = e.target.dataset.tab;
      switchTab(tabName);
    });
  });
}

function switchTab(tabName) {
  // Cacher tous les tabs
  document.querySelectorAll('.tab-content').forEach(t => {
    t.classList.remove('active');
  });
  
  // Afficher tab sélectionné
  document.getElementById(`${tabName}-tab`).classList.add('active');
  
  // Marquer bouton actif
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  event.target.classList.add('active');
}

// === AVIS EN ATTENTE ===
async function loadPendingReviews() {
  try {
    const response = await fetch('/api/employee/reviews/pending');
    const result = await response.json();
    
    if (!result.success) {
      document.getElementById('reviewsContainer').innerHTML = 
        '<p style="color:red;">Erreur lors du chargement</p>';
      return;
    }
    
    const reviews = result.data.items || [];
    if (reviews.length === 0) {
      document.getElementById('reviewsContainer').innerHTML = 
        '<p style="color:#999;">Aucun avis en attente ✅</p>';
      return;
    }
    
    const html = reviews.map(review => `
      <div class="review-card">
        <div class="review-header">
          <strong>${review.auteur_pseudo}</strong>
          <span class="review-date">${new Date(review.date_creation).toLocaleDateString('fr-FR')}</span>
        </div>
        <div class="review-content">
          <p><strong>Trajet :</strong> ${review.lieu_depart} → ${review.lieu_arrivee}</p>
          <p><strong>Conducteur :</strong> ${review.conducteur_pseudo}</p>
          <p><strong>Note :</strong> ${'⭐'.repeat(review.note)}</p>
          <p><strong>Commentaire :</strong> "${review.commentaire || '(aucun)"}"</p>
        </div>
        <div class="review-actions">
          <button class="btn-approve" onclick="moderateReview(${review.id_avis}, 'approuve')">✅ Approuver</button>
          <button class="btn-reject" onclick="openRejectModal(${review.id_avis})">❌ Refuser</button>
        </div>
      </div>
    `).join('');
    
    document.getElementById('reviewsContainer').innerHTML = html;
  } catch (error) {
    console.error('Erreur:', error);
  }
}

async function moderateReview(avisId, action) {
  try {
    const response = await fetch(`/api/employee/reviews/${avisId}/moderation`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ avis_id: avisId, action })
    });
    
    const result = await response.json();
    if (result.success) {
      alert(`✅ Avis ${action === 'approuve' ? 'approuvé' : 'refusé'}`);
      loadPendingReviews();
    }
  } catch (error) {
    alert('❌ Erreur : ' + error.message);
  }
}

// === INCIDENTS ===
async function loadIncidents() {
  try {
    const response = await fetch('/api/employee/incidents');
    const result = await response.json();
    
    if (!result.success) {
      document.getElementById('incidentsContainer').innerHTML = 
        '<p style="color:red;">Erreur chargement</p>';
      return;
    }
    
    const incidents = result.data.items || [];
    if (incidents.length === 0) {
      document.getElementById('incidentsContainer').innerHTML = 
        '<p style="color:#999;">Aucun incident signalé</p>';
      return;
    }
    
    const html = incidents.map(incident => `
      <div class="incident-card">
        <div class="incident-header">
          <strong>#${incident.participation_id}</strong>
          <span class="incident-date">${new Date(incident.date_probleme).toLocaleDateString('fr-FR')}</span>
        </div>
        <div class="incident-content">
          <p><strong>Passager :</strong> ${incident.passager_pseudo} (${incident.passager_email})</p>
          <p><strong>Conducteur :</strong> ${incident.conducteur_pseudo} (${incident.conducteur_email})</p>
          <p><strong>Trajet :</strong> ${incident.lieu_depart} → ${incident.lieu_arrivee}</p>
          <p><strong>Date :</strong> ${incident.date_depart} ${incident.heure_depart}</p>
          <p><strong>Problème :</strong> ${incident.raison_probleme || '(non décrit)'}</p>
        </div>
        <div class="incident-actions">
          <button class="btn-detail" onclick="viewIncidentDetail(${incident.participation_id})">📋 Détails</button>
        </div>
      </div>
    `).join('');
    
    document.getElementById('incidentsContainer').innerHTML = html;
  } catch (error) {
    console.error('Erreur:', error);
  }
}

async function viewIncidentDetail(participationId) {
  try {
    const response = await fetch(`/api/employee/incidents/${participationId}`);
    const result = await response.json();
    
    if (result.success) {
      const incident = result.data;
      alert(`
Participation #${incident.participation_id}
Passager: ${incident.passager_pseudo}
Conducteur: ${incident.conducteur_pseudo}
Problème: ${incident.raison_probleme}
      `);
    }
  } catch (error) {
    alert('Erreur: ' + error.message);
  }
}
```

### 2.5 CSS : Employee Space
**Fichier** : `frontend/css/employee-space.css`

```css
/* Employee Space Styling */

.employee-space {
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
  min-height: 100vh;
  padding: 40px 20px;
}

.employee-space h1 {
  text-align: center;
  font-size: 32px;
  margin-bottom: 40px;
  color: #333;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 20px;
  margin-bottom: 40px;
  border-bottom: 2px solid #e0e0e0;
  justify-content: center;
}

.tab-btn {
  padding: 12px 24px;
  background: transparent;
  border: none;
  font-size: 16px;
  font-weight: 600;
  color: #666;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  transition: all 0.3s;
}

.tab-btn.active {
  color: #27ae60;
  border-bottom-color: #27ae60;
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
}

/* Cards */
.review-card, .incident-card {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.review-card:hover, .incident-card:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

/* Review Card */
.review-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  border-bottom: 1px solid #f0f0f0;
  padding-bottom: 10px;
}

.review-date {
  font-size: 12px;
  color: #999;
}

.review-content p {
  margin: 8px 0;
  font-size: 14px;
}

.review-actions {
  display: flex;
  gap: 10px;
  margin-top: 15px;
}

.btn-approve, .btn-reject {
  flex: 1;
  padding: 10px;
  border: none;
  border-radius: 4px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-approve {
  background: #27ae60;
  color: white;
}

.btn-approve:hover {
  background: #229954;
}

.btn-reject {
  background: #e74c3c;
  color: white;
}

.btn-reject:hover {
  background: #c0392b;
}

/* Incident Card */
.incident-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid #ffe0b2;
}

.incident-content p {
  margin: 8px 0;
  font-size: 14px;
  line-height: 1.6;
}

.incident-actions {
  margin-top: 15px;
}

.btn-detail {
  width: 100%;
  padding: 10px;
  background: #3498db;
  color: white;
  border: none;
  border-radius: 4px;
  font-weight: 600;
  cursor: pointer;
}

.btn-detail:hover {
  background: #2980b9;
}
```

---

## ✅ PHASE 3 : TESTS & VALIDATION

### 3.1 Tests Unitaires
- `AvisModerationValidatorTest` : validation inputs
- `AvisModerationServiceTest` : logique approbation/refus
- `EmployeeControllerTest` : endpoints

### 3.2 Tests E2E
1. Employé se connecte → voir "Espace Employé" dans header ✅
2. Employé accède `/employee` → voir avis en attente ✅
3. Employé approuve avis → statut change, avis visible publiquement ✅
4. Employé refuse avis → avis caché, raison tracée ✅
5. Employé voit incidents → liste participations `status='probleme'` ✅

### 3.3 Données de Test
```php
// Créer employé test
INSERT INTO utilisateur VALUES 
  (NULL, 'emp@ecoride.fr', 'hashed', 'employe_test', 'employe', 20.00, NOW());

// Créer avis en attente
INSERT INTO avis_fallback VALUES 
  (NULL, 1, 2, 3, 4, 'Bon trajet', 'en_attente', NULL, NULL, NOW());

// Créer incident
UPDATE participation SET statut = 'probleme', 
  raison_probleme = 'Chauffeur en retard', 
  date_probleme = NOW() WHERE participation_id = 1;
```

---

## 📝 CHECKLIST IMPLÉMENTATION

- [ ] **0. Base de Données**
  - [ ] Ajouter rôle `employe`
  - [ ] Ajouter colonnes modération
  - [ ] Créer IncidentRepository
  
- [ ] **1. Backend**
  - [ ] AvisModerationValidator
  - [ ] AvisModerationService
  - [ ] EmployeeController
  - [ ] Étendre MysqlAvisRepository
  - [ ] IncidentRepository
  - [ ] Routes Bootstrap
  - [ ] Middleware Employee
  
- [ ] **2. Frontend**
  - [ ] Mettre à jour header.php
  - [ ] SessionManager : ajouter role
  - [ ] Créer employee-space.php
  - [ ] Créer employee-space.js
  - [ ] Créer employee-space.css
  
- [ ] **3. Tests**
  - [ ] Tests unitaires services
  - [ ] Tests E2E workflows
  - [ ] Validation données test

---

## 🚀 ROADMAP SUPPLÉMENTAIRE

**Futures améliorations** :
1. Dashboard stats (nombre avis approuvés/refusés)
2. Export incidents en PDF
3. Historique modérations (qui a approuvé quand)
4. Assignation incidents à employé spécifique
5. Notifications temps réel avis en attente


┌─────────────────────────────────────────────────────────┐
│                    UTILISATEUR EMPLOYÉ                  │
└─────────────────────────────────────────────────────────┘
                           │
                    Se connecte avec JWT
                           │
                ┌──────────▼──────────┐
                │   HEADER.PHP        │
                │  Affiche lien si    │
                │  role = 'employe'   │
                └──────────┬──────────┘
                           │
                  Clique "Espace Employé"
                           │
        ┌──────────────────▼──────────────────┐
        │      EMPLOYEE-SPACE.PHP              │
        │  (route protégée: role = 'employe') │
        └──────────────────┬──────────────────┘
                           │
            ┌──────────────┴──────────────┐
            │                             │
      AVIS EN ATTENTE             INCIDENTS
      (statut_moderation=          (statut=
       'en_attente')               'probleme')
            │                             │
      ┌─────▼─────┐               ┌──────▼──────┐
      │ Approuver  │               │ Voir détails│
      │ Refuser    │               │ de l'incident
      │            │               │             │
      └─────┬─────┘               └──────┬──────┘
            │                             │
      ┌─────▼──────────────────────────┬─┘
      │  API /api/employee/*            │
      │  (EmployeeController)           │
      │                                 │
      │  - getPendingReviews()          │
      │  - moderateReview()             │
      │  - getIncidents()               │
      │  - getIncidentDetail()          │
      │                                 │
      └─────┬──────────────────────────┘
            │
      ┌─────▼──────────────────┐
      │    SERVICES            │
      │ - AvisModerationSvc    │
      │ - IncidentSvc (futur)  │
      │                        │
      └─────┬──────────────────┘
            │
      ┌─────▼──────────────────┐
      │    REPOSITORIES        │
      │ - MysqlAvisRepository  │
      │ - IncidentRepository   │
      │                        │
      └─────┬──────────────────┘
            │
      ┌─────▼──────────────────┐
      │    BASE DE DONNÉES     │
      │ - avis_fallback (UPDATE)
      │ - participation (SELECT)
      │ - covoiturage (JOIN)   │
      │ - utilisateur (JOIN)   │
      │                        │
      └────────────────────────┘