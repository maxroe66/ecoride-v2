<?php
/**
 * Template de base pour créer une nouvelle page EcoRide
 * 
 * Instructions:
 * 1. Copier ce fichier vers /frontend/pages/[nom_page].php
 * 2. Remplacer [PAGE_TITLE] par le titre de la page
 * 3. Remplacer [PAGE_CSS] par le nom du fichier CSS (ex: rides.css)
 * 4. Remplacer [PAGE_JS] par le nom du fichier JS (ex: rides.js)
 * 5. Remplacer le contenu du <main>
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoRide - [PAGE_TITLE]</title>
  
  <!-- Styles globaux -->
  <link rel="stylesheet" href="/frontend/css/global.css">
  <link rel="stylesheet" href="/frontend/css/components/header.css">
  <link rel="stylesheet" href="/frontend/css/components/footer.css">
  <link rel="stylesheet" href="/frontend/css/[PAGE_CSS]">
</head>
<body>
  <!-- Header dynamique (avec logique connexion) -->
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main>
    <!-- Contenu spécifique à cette page -->
    <h1>[PAGE_TITLE]</h1>
    <!-- Ajouter le contenu principal ici -->
  </main>

  <!-- Footer dynamique -->
  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <!-- Scripts -->
  <script src="/frontend/js/header.js"></script>
  <script src="/frontend/js/[PAGE_JS]"></script>
</body>
</html>
