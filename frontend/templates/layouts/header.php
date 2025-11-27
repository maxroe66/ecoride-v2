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
        <a href="/rides" class="nav-link">Covoiturage</a>
      </li>
      <li class="nav-item">
        <a href="/avis" class="nav-link">Avis</a>
      </li>
      <li class="nav-item">
        <a href="/contact" class="nav-link">Contact</a>
      </li>

      <!-- Séparation avant les actions utilisateur -->
      <li class="nav-divider"></li>

      <?php if ($isConnected): ?>
        <!-- Menu utilisateur connecté -->
        <li class="nav-item user-menu">
          <button class="nav-link user-button" id="userMenuBtn">
            <span class="user-icon">👤</span>
            <span class="user-name"><?php echo $userName; ?></span>
            <span class="dropdown-icon">▼</span>
          </button>
          <ul class="dropdown-menu" id="userDropdown">
            <li><a href="/profile" class="dropdown-link">Mon profil</a></li>
            <li><a href="/my-rides" class="dropdown-link">Mes trajets</a></li>
            <li><a href="/settings" class="dropdown-link">Paramètres</a></li>
            <li class="dropdown-divider"></li>
            <li><a href="/logout" class="dropdown-link logout">Déconnexion</a></li>
          </ul>
        </li>
      <?php else: ?>
        <!-- Boutons connexion/inscription -->
        <li class="nav-item">
          <a href="/login" class="nav-link btn btn-outline">Connexion</a>
        </li>
        <li class="nav-item">
          <a href="/signup" class="nav-link btn btn-primary">Inscription</a>
        </li>
      <?php endif; ?>
    </ul>
  </nav>
</header>
