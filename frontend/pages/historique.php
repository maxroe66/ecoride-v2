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
          <option value="validee">Validés</option>
          <option value="probleme">Problèmes signalés</option>
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

  <!-- US11 Modal: Finaliser / Signaler / Avis (Passager) -->
  <div id="finalizeParticipationModal" class="modal">
    <div class="modal-content finalize-modal">
      <div class="modal-header">
        <h2>✨ Finaliser ce trajet</h2>
        <button class="close-btn finalize-close" type="button">&times;</button>
      </div>
      <div class="modal-body">
        <p class="modal-text">
          Centralisez toutes vos actions post-trajet : valider le covoiturage, signaler un incident ou partager votre retour d'expérience.
        </p>
        <div class="finalize-status">
          <div class="finalize-status-header">
            <span class="finalize-status-chip" id="finalizeStatusBadge">Statut en attente</span>
            <span class="finalize-status-label" id="finalizeStatusLabel"></span>
          </div>
          <p class="finalize-status-hint" id="finalizeStatusHint"></p>
        </div>

        <div class="finalize-grid">
          <section class="finalize-card" id="finalizeValidateSection">
            <h3>✅ Valider le trajet</h3>
            <p>Confirmez que tout s'est bien passé pour débloquer les crédits du chauffeur.</p>
            <div class="modal-info">
              ✓ Une fois validé, vous pourrez immédiatement partager votre avis.
            </div>
            <button type="button" class="btn-primary finalize-action-button" id="confirmValidateParticipation">
              Valider la participation
            </button>
            <small class="finalize-hint" id="finalizeValidateHint"></small>
          </section>

          <section class="finalize-card" id="finalizeProblemSection">
            <h3>⚠️ Signaler un problème</h3>
            <p>Décrivez toute situation nécessitant l'intervention d'un employé EcoRide.</p>
            <div class="modal-danger">
              Aucun crédit ne sera versé avant la résolution de l'incident par notre équipe.
            </div>
            <form id="reportProblemForm">
              <div class="form-group">
                <label for="problemReason">Décrivez brièvement le problème :</label>
                <textarea id="problemReason"
                          placeholder="Retard important, comportement inadapté, annulation tardive..."
                          maxlength="500"
                          required></textarea>
                <span class="char-count" id="problemCharCount">0/500</span>
              </div>
              <button type="submit" class="btn-danger finalize-action-button">
                Signaler ce trajet
              </button>
              <small class="finalize-hint" id="finalizeProblemHint"></small>
            </form>
          </section>

          <section class="finalize-card review-card" id="finalizeReviewSection">
            <h3>⭐ Laisser un avis</h3>
            <p>Partagez votre ressenti une fois le trajet validé ou si un incident a été signalé.</p>
            <button type="button" class="btn-secondary finalize-action-button" id="finalizeReviewCTA">
              Ouvrir le formulaire d'avis
            </button>
            <small class="finalize-hint" id="finalizeReviewHint"></small>
          </section>
        </div>

        <div class="form-actions finalize-footer">
          <button type="button" class="btn-cancel" id="closeFinalizeModal">Fermer</button>
        </div>
      </div>
    </div>
  </div>

  <!-- US11 Modal: Laisser un Avis (Passager) -->
  <div id="leaveReviewModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>⭐ Laisser un avis</h2>
        <button class="close-btn">&times;</button>
      </div>
      <div class="modal-body">
        <p class="modal-text">Partagez votre expérience de ce trajet :</p>
        <form id="leaveReviewForm">
          <div class="form-group">
            <label for="reviewRating">Note (1-5 étoiles) :</label>
            <div class="rating-stars" id="reviewRating">
              <span class="star" data-value="1">★</span>
              <span class="star" data-value="2">★</span>
              <span class="star" data-value="3">★</span>
              <span class="star" data-value="4">★</span>
              <span class="star" data-value="5">★</span>
            </div>
            <input type="hidden" id="selectedRating" value="0" required>
          </div>
          <div class="form-group">
            <label for="reviewComment">Commentaire (optionnel) :</label>
            <textarea id="reviewComment" 
                      placeholder="Partagez vos impressions sur ce trajet..."
                      maxlength="500"></textarea>
            <span class="char-count" id="reviewCharCount">0/500</span>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-cancel" id="closeReviewModal">Annuler</button>
            <button type="submit" class="btn-primary">Soumettre l'avis</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="/frontend/js/historique.js"></script>
  <script src="/frontend/js/us11-trip-actions.js"></script>
  <script src="/frontend/js/leave-review.js"></script>
</body>
</html>
