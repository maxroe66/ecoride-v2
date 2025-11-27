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
  <link rel="stylesheet" href="/frontend/css/index.css">
</head>
<body>
  <!-- Header dynamique (avec logique connexion) -->
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main>
    <!-- Section Welcome -->
    <div class="welcome" id="welcome">
      <h1>Bienvenue sur EcoRide</h1>
      <h2>Pratiquez le covoiturage en toute sérénité et contribuez à la préservation de l'environnement.</h2>
    </div>

    <!-- Image héros (visible desktop seulement) -->
    <img src="/images-icons/image_accueil.png" alt="EcoRide" class="hero-image">

    <!-- Formulaire de recherche -->
    <form class="search-form" id="searchForm">
      <div class="input-group">
        <input type="text" id="searchInput" placeholder="Ville de départ">
        <input type="text" id="destinationInput" placeholder="Ville d'arrivée">
        <input type="date" id="dateInput">
        <button type="submit" id="searchButton">Rechercher</button>
      </div>
    </form>
  </main>

  <!-- Footer dynamique -->
  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <!-- Scripts -->
  <script src="/frontend/js/SessionManager.js"></script>
  <script src="/frontend/js/header.js"></script>
  <script src="/frontend/js/index.js"></script>
</body>
</html>
