<?php
  // Gestion de l'état utilisateur
  $isConnected = isset($_SESSION['user']);
  $userName = $isConnected ? htmlspecialchars($_SESSION['user']['name'] ?? 'Utilisateur') : null;
  $userEmail = $isConnected ? htmlspecialchars($_SESSION['user']['email'] ?? '') : null;
?>
<header class="site-header">
  <nav class="navbar">
    <!-- Logo EcoRide -->
    <a href="/" class="navbar-brand">
      <img src="/images-icons/ecorideicon-removebg-preview.png" alt="EcoRide" class="logo-icon">
      <span class="logo-text">EcoRide</span>
    </a>

    <!-- Menu toggle (pour mobile) -->
    <button class="navbar-toggle" id="navbarToggle" aria-label="Basculer le menu">
      <span class="hamburger"></span>
      <span class="hamburger"></span>
      <span class="hamburger"></span>
    </button>

    <!-- Navigation links -->
    <ul class="nav-links" id="navLinks">
      <li class="nav-item">
        <a href="/" class="nav-link">Accueil</a>
      </li>
      <li class="nav-item">
        <a href="/vue-des-covoiturages" class="nav-link">Covoiturages</a>
      </li>
      <li class="nav-item">
        <a href="/avis" class="nav-link">Avis</a>
      </li>
      <li class="nav-item">
        <a href="/contact" class="nav-link">Contact</a>
      </li>

      <!-- Séparation avant les actions utilisateur -->
      <li class="nav-divider"></li>

      <!-- Conteneur dynamique pour l'authentification -->
      <li class="nav-item" id="authContainer">
        <!-- Rempli par JavaScript (header.js) -->
      </li>
    </ul>
  </nav>
</header>

