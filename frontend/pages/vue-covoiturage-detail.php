<?php
/**
 * Page de détail d'un covoiturage
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Détail du covoiturage - EcoRide</title>
  <link rel="stylesheet" href="/frontend/css/global.css">
  <link rel="stylesheet" href="/frontend/css/components/header.css">
  <link rel="stylesheet" href="/frontend/css/components/footer.css">
  <link rel="stylesheet" href="/frontend/css/vue-covoiturage-detail.css">
</head>
<body>
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main class="detail-container">
    <!-- Barre de chargement -->
    <div id="loadingSpinner" class="loading-spinner" style="display: none;">
      <p>Chargement du détail...</p>
    </div>

    <!-- Contenu détail -->
    <div id="detailContent" style="display: none;">
      <!-- Conteneur principal 2 colonnes -->
      <div class="detail-wrapper">
        <!-- COLONNE GAUCHE : Conducteur -->
        <div class="detail-column detail-column-left">
          <!-- Détail conducteur avec préférences et avis -->
          <section class="detail-section conductor-section">
            <h2>Profil du conducteur</h2>
            
            <!-- Carte profil -->
            <div class="conducteur-card">
              <div class="conducteur-info">
                <div class="conducteur-avatar">
                  <img id="driver-photo" src="/images-icons/icons8-avatar-50.png" alt="Photo du conducteur">
                </div>
                <div class="conducteur-details">
                  <h3 id="driver-name">---</h3>
                  <div class="driver-rating">
                    <span id="driver-rating-value">---</span>
                    <span id="driver-rating-count" class="rating-count">---</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Préférences du conducteur -->
            <div class="conductor-subsection">
              <h3>Préférences</h3>
              <ul id="driver-preferences"></ul>
            </div>

            <!-- Avis du conducteur -->
            <div class="conductor-subsection">
              <h3>Avis</h3>
              <div id="driver-reviews" class="reviews-container"></div>
              <button id="toggle-reviews-btn" class="btn-toggle-reviews" style="display: none;">
                Voir tous les avis
              </button>
            </div>
          </section>
        </div>

        <!-- COLONNE DROITE : Trajet et Véhicule -->
        <div class="detail-column detail-column-right">
          <!-- Détail trajet -->
          <section class="detail-section">
            <h2>Informations du trajet</h2>
            <div class="detail-grid">
              <div class="detail-item">
                <strong>Départ</strong>
                <p id="detail-departure">---</p>
              </div>
              <div class="detail-item">
                <strong>Arrivée</strong>
                <p id="detail-arrival">---</p>
              </div>
              <div class="detail-item">
                <strong>Date</strong>
                <p id="detail-date">---</p>
              </div>
              <div class="detail-item">
                <strong>Heure</strong>
                <p id="detail-time">---</p>
              </div>
              <div class="detail-item">
                <strong>Durée estimée</strong>
                <p id="detail-duration">---</p>
              </div>
              <div class="detail-item">
                <strong>Places restantes</strong>
                <p id="detail-seats">---</p>
              </div>
              <div class="detail-item">
                <strong>Prix par personne</strong>
                <p id="detail-price">---</p>
              </div>
              <div class="detail-item" id="ecoLabel" style="display: none;">
                <strong>Type de véhicule</strong>
                <p><span class="eco-badge">♻️ Écologique</span></p>
              </div>
            </div>
          </section>

          <!-- Détail véhicule -->
          <section class="detail-section">
            <h2>Informations du véhicule</h2>
            <div class="detail-grid">
              <div class="detail-item">
                <strong>Marque</strong>
                <p id="vehicle-brand">---</p>
              </div>
              <div class="detail-item">
                <strong>Modèle</strong>
                <p id="vehicle-model">---</p>
              </div>
              <div class="detail-item">
                <strong>Énergie</strong>
                <p id="vehicle-energy">---</p>
              </div>
            </div>
          </section>
        </div>
      </div>

      <!-- CTA - Pleine largeur en bas -->
      <section class="detail-section cta-section">
        <button class="btn btn-primary btn-large" id="btn-participate">Participer</button>
      </section>
    </div>

    <!-- MODAL 1 : Première confirmation du montant -->
    <div id="modal1-participation" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Confirmer votre participation</h2>
          <button class="modal-close" aria-label="Fermer">×</button>
        </div>
        <div class="modal-body">
          <p>Vous êtes sur le point de participer à ce covoiturage.</p>
          <div class="modal-info">
            <p><strong>Montant à débiter :</strong> <span id="modal1-amount">---</span> €</p>
            <p><strong>Nombre de places :</strong> <span id="modal1-seats">---</span></p>
          </div>
          <p class="modal-note">
            Vous pourrez confirmer votre participation à l'étape suivante.
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-action="close-modal1">Annuler</button>
          <button class="btn btn-primary" data-action="proceed-modal2">Continuer</button>
        </div>
      </div>
    </div>

    <!-- MODAL 2 : Deuxième confirmation finale -->
    <div id="modal2-confirmation" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Confirmation finale</h2>
          <button class="modal-close" aria-label="Fermer">×</button>
        </div>
        <div class="modal-body">
          <p class="modal-warning">⚠️ Attention !</p>
          <p>Vous êtes sur le point de confirmer votre participation. Votre crédit sera débité de façon définitive.</p>
          <div class="modal-info">
            <p><strong>Montant final :</strong> <span id="modal2-amount">---</span> €</p>
          </div>
          <p class="modal-note">
            Cette action est irréversible. Êtes-vous certain ?
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-action="close-modal2">Annuler</button>
          <button class="btn btn-primary btn-danger" data-action="confirm-participation">Valider et débiter</button>
        </div>
      </div>
    </div>

    <!-- Message d'erreur -->
    <div id="errorMessage" class="error-box" style="display: none;">
      <p id="errorText">Une erreur est survenue. Veuillez réessayer.</p>
      <a href="/vue-des-covoiturages" class="btn btn-secondary">Retour à la recherche</a>
    </div>
  </main>

  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

 
  <script src="/frontend/js/vue-covoiturage-detail.js"></script>
</body>
</html>