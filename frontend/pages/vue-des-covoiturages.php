<?php
/**
 * Frontend - Page d'accueil
 * Affiche le contenu principal avec header et footer
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoRide - Accueil</title>
  
  <!-- Styles globaux -->
  <link rel="stylesheet" href="/frontend/css/global.css">
  <link rel="stylesheet" href="/frontend/css/components/header.css">
  <link rel="stylesheet" href="/frontend/css/components/footer.css">
  <link rel="stylesheet" href="/frontend/css/vue-des-covoiturages.css">
</head>
<body>
  <!-- Header dynamique (avec logique connexion) -->
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main>
    <!-- Section Welcome -->
    <div class="header-text">
      <h1>Rechercher un covoiturage</h1>
    </div>

    <!-- Formulaire de recherche -->
    <form class="search-form" id="searchForm">
      <div class="input-group">
        <input type="text" id="searchInput" placeholder="Ville de départ">
        <input type="text" id="destinationInput" placeholder="Ville d'arrivée">
        <input type="date" id="dateInput">
        <button type="submit" id="searchButton">Rechercher</button>
      </div>
    </form>

    <!-- Conteneur des filtres (sera rempli par JS après recherche) -->
    <div id="filtersContainer" class="filters-wrapper"></div>

    <!-- Conteneur des résultats -->
    <div id="resultsContainer" class="results-wrapper"></div>

    <!-- Image héros (visible desktop seulement) -->
    <img src="/images-icons/voiture-2.jpeg" alt="EcoRide" class="hero-image">
  </main>

  <!-- Footer dynamique -->
  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <!-- Scripts -->
  <script src="/frontend/js/vue-des-covoiturages.js"></script>
</body>
</html>
