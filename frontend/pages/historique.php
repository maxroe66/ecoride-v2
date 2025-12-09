<?php
/**
 * Page d'historique des covoiturages (US10)
 * Affiche tous les trajets de l'utilisateur (en tant que chauffeur et passager)
 * Permet d'annuler les trajets/participations
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoRide - Historique des covoiturages</title>
  
  <link rel="stylesheet" href="/frontend/css/global.css">
  <link rel="stylesheet" href="/frontend/css/components/header.css">
  <link rel="stylesheet" href="/frontend/css/components/footer.css">
  <link rel="stylesheet" href="/frontend/css/historique.css">
  <link rel="stylesheet" href="/frontend/css/us11-trip-actions.css">
</head>
<body>
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main>
    <div class="historique-container">
      <!-- En-tête -->
      <div class="historique-header">
        <h1>Historique de mes covoiturages</h1>
        <p>Retrouvez tous vos trajets en tant que chauffeur ou passager</p>
        <div class="credit-summary" id="creditSummary" style="display:none;">
          <div class="credit-item"><span>Total gagnés:</span> <strong id="totalCredit">0</strong></div>
          <div class="credit-item"><span>Total utilisés:</span> <strong id="totalDebit">0</strong></div>
          <div class="credit-item"><span>Solde:</span> <strong id="currentBalance">0</strong></div>
        </div>
      </div>

      <!-- Messages d'erreur/succès -->
      <div id="messageContainer"></div>

      <!-- Filtres -->
      <div class="filters-section">
        <label for="statusFilter">Filtrer par statut :</label>
        <select id="statusFilter">
          <option value="">-- Tous les trajets --</option>
          <option value="planifie">Planifiés</option>
          <option value="en_cours">En cours</option>
          <option value="termine">Terminés</option>
          <option value="annule">Annulés</option>
        </select>
      </div>

      <!-- Liste des trajets -->
      <div id="tripsContainer" class="trips-list">
        <p class="loading">Chargement de vos trajets...</p>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <!-- Modal d'annulation -->
  <div id="cancelModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Annuler ce covoiturage</h2>
        <button class="close-btn">&times;</button>
      </div>
      <div class="modal-body">
        <form id="cancelForm">
          <div class="form-group">
            <label for="cancelReason">Raison d'annulation (optionnel) :</label>
            <textarea id="cancelReason" placeholder="Décrivez la raison de votre annulation..." maxlength="500"></textarea>
            <small id="charCount">0/500</small>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-cancel" id="closeModal">Annuler</button>
            <button type="submit" class="btn-danger">Confirmer l'annulation</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- US11 Modal: Démarrer Trajet (Chauffeur) -->
  <div id="startTripModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>🚀 Démarrer le trajet</h2>
        <button class="close-btn">&times;</button>
      </div>
      <div class="modal-body">
        <p class="modal-text">Êtes-vous sûr de vouloir démarrer ce trajet maintenant ?</p>
        <div class="modal-info">
          ℹ️ Une fois démarré, vous devrez indiquer l'arrivée à destination pour terminer le trajet. Les participants seront notifiés.
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" id="closeStartModal">Annuler</button>
          <button type="button" class="btn-primary" id="confirmStartTrip">Démarrer maintenant</button>
        </div>
      </div>
    </div>
  </div>

  <!-- US11 Modal: Arrivée à Destination (Chauffeur) -->
  <div id="endTripModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>⏸️ Arrivée à destination</h2>
        <button class="close-btn">&times;</button>
      </div>
      <div class="modal-body">
        <p class="modal-text">Confirmez-vous l'arrivée à destination ?</p>
        <div class="modal-warning">
          ⚠️ Les participants recevront une notification leur demandant de valider leur participation. C'est à ce moment que vous recevrez vos crédits.
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" id="closeEndModal">Annuler</button>
          <button type="button" class="btn-primary" id="confirmEndTrip">Confirmer l'arrivée</button>
        </div>
      </div>
    </div>
  </div>

  <!-- US11 Modal: Valider Participation (Passager) -->
  <div id="validateParticipationModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>✅ Valider votre participation</h2>
        <button class="close-btn">&times;</button>
      </div>
      <div class="modal-body">
        <p class="modal-text">Confirmez-vous que le trajet s'est bien passé ?</p>
        <div class="modal-info">
          ✓ Le chauffeur recevra ses crédits une fois cette validation confirmée. Vous pourrez ensuite soumettre un avis sur ce trajet.
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" id="closeValidateModal">Annuler</button>
          <button type="button" class="btn-primary" id="confirmValidateParticipation">Valider la participation</button>
        </div>
      </div>
    </div>
  </div>

  <!-- US11 Modal: Signaler un Problème (Passager) -->
  <div id="reportProblemModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>⚠️ Signaler un problème</h2>
        <button class="close-btn">&times;</button>
      </div>
      <div class="modal-body">
        <p class="modal-text">Le trajet ne s'est pas déroulé comme prévu ?</p>
        <div class="modal-danger">
          Un employé EcoRide vous contactera dans les plus brefs délais pour résoudre la situation avant la mise à jour des crédits du chauffeur.
        </div>
        <form id="reportProblemForm">
          <div class="form-group">
            <label for="problemReason">Décrivez brièvement le problème rencontré :</label>
            <textarea id="problemReason" 
                      placeholder="Ex: Le chauffeur a pris un mauvais chemin, retard important de 30 minutes, détour inutile..."
                      maxlength="500"
                      required></textarea>
            <span class="char-count" id="problemCharCount">0/500</span>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-cancel" id="closeReportModal">Annuler</button>
            <button type="submit" class="btn-danger">Signaler le problème</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="/frontend/js/historique.js"></script>
  <script src="/frontend/js/us11-trip-actions.js"></script>
</body>
</html>
