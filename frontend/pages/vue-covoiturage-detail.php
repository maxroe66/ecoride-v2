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
        <button class="btn btn-primary btn-large" onclick="contactDriver()">Participer</button>
      </section>
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