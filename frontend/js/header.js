/**
 * Header Interactivity
 * Gère le menu burger, dropdowns utilisateur, et authentification dynamique
 */

document.addEventListener('DOMContentLoaded', function() {
  // D'abord, initialiser le menu d'authentification
  initializeAuthMenu();

  // Éléments du DOM
  const navbarToggle = document.getElementById('navbarToggle');
  const navLinks = document.getElementById('navLinks');

  // Menu burger (mobile)
  if (navbarToggle) {
    navbarToggle.addEventListener('click', function() {
      navbarToggle.classList.toggle('active');
      navLinks.classList.toggle('active');
    });

    // Fermer le menu quand on clique sur un lien
    const navItems = navLinks.querySelectorAll('.nav-link:not(.user-button)');
    navItems.forEach(item => {
      item.addEventListener('click', function() {
        navbarToggle.classList.remove('active');
        navLinks.classList.remove('active');
      });
    });
  }

  // Écouter les changements d'authentification
  window.addEventListener('userLoggedIn', () => {
    initializeAuthMenu();
    attachUserMenuListeners();
  });

  window.addEventListener('userLoggedOut', () => {
    initializeAuthMenu();
  });

  // Initialiser les écouteurs du menu utilisateur
  attachUserMenuListeners();

  // Fermer le menu mobile au scroll
  let lastScrollTop = 0;
  window.addEventListener('scroll', function() {
    if (navbarToggle && navLinks.classList.contains('active')) {
      const st = window.pageYOffset || document.documentElement.scrollTop;
      if (Math.abs(st - lastScrollTop) > 50) {
        navbarToggle.classList.remove('active');
        navLinks.classList.remove('active');
      }
      lastScrollTop = st <= 0 ? 0 : st;
    }
  });
});

/**
 * Initialise le menu d'authentification en fonction de l'état de SessionManager
 */
function initializeAuthMenu() {
  const authContainer = document.getElementById('authContainer');
  if (!authContainer) return;

  const isAuthenticated = SessionManager.isAuthenticated();
  const user = SessionManager.getUser();

  if (isAuthenticated && user) {
    // Menu utilisateur connecté
    authContainer.innerHTML = `
      <li class="nav-item user-menu">
        <button class="nav-link user-button" id="userMenuBtn">
          <span class="user-icon">👤</span>
          <span class="user-name">${escapeHtml(user.pseudo)}</span>
          <span class="dropdown-icon">▼</span>
        </button>
        <ul class="dropdown-menu" id="userDropdown">
          <li><a href="/profile" class="dropdown-link">Mon profil</a></li>
          <li><a href="/my-rides" class="dropdown-link">Mes trajets</a></li>
          <li><a href="/settings" class="dropdown-link">Paramètres</a></li>
          <li class="dropdown-divider"></li>
          <li><a href="#" class="dropdown-link logout" onclick="logout(); return false;">Déconnexion</a></li>
        </ul>
      </li>
    `;
  } else {
    // Boutons connexion/inscription
    authContainer.innerHTML = `
      <li class="nav-item">
        <a href="/login" class="nav-link btn btn-outline">Connexion</a>
      </li>
      <li class="nav-item">
        <a href="/signup" class="nav-link btn btn-primary">Inscription</a>
      </li>
    `;
  }
}

/**
 * Attache les écouteurs au menu utilisateur
 */
function attachUserMenuListeners() {
  const userMenuBtn = document.getElementById('userMenuBtn');
  const userDropdown = document.getElementById('userDropdown');

  if (userMenuBtn && userDropdown) {
    userMenuBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      userDropdown.classList.toggle('active');
      userMenuBtn.querySelector('.dropdown-icon').style.transform = 
        userDropdown.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0)';
    });

    // Fermer le dropdown au clic dehors
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.user-menu')) {
        userDropdown.classList.remove('active');
        if (userMenuBtn.querySelector('.dropdown-icon')) {
          userMenuBtn.querySelector('.dropdown-icon').style.transform = 'rotate(0)';
        }
      }
    });

    // Fermer le dropdown au clic sur un lien
    const dropdownLinks = userDropdown.querySelectorAll('.dropdown-link');
    dropdownLinks.forEach(link => {
      link.addEventListener('click', function() {
        userDropdown.classList.remove('active');
        if (userMenuBtn.querySelector('.dropdown-icon')) {
          userMenuBtn.querySelector('.dropdown-icon').style.transform = 'rotate(0)';
        }
      });
    });
  }
}

/**
 * Échappe les caractères HTML pour éviter les injections XSS
 */
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}
