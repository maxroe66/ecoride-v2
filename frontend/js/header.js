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
    updateCreditBalance();
  });

  window.addEventListener('userLoggedOut', () => {
    initializeAuthMenu();
    hideCreditBalance();
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
  const creditContainer = document.getElementById('creditContainer');
  if (!authContainer) return;

  // Vérifier que SessionManager est chargé
  if (typeof SessionManager === 'undefined') {
    console.warn('SessionManager not loaded yet');
    return;
  }

  const isAuthenticated = SessionManager.isAuthenticated();
  const user = SessionManager.getUser();

  if (isAuthenticated && user) {
    // Menu utilisateur connecté
    const displayName = escapeHtml(getDisplayName(user));
    
    // Construire le dropdown menu
    let dropdownItems = `
      <li><a href="/profile" class="dropdown-link">Mon profil</a></li>
      <li><a href="/historique" class="dropdown-link">Mes covoiturages</a></li>
      <li><a href="/my-rides" class="dropdown-link">Mes trajets</a></li>
      <li><a href="/settings" class="dropdown-link">Paramètres</a></li>
    `;
    
    // Ajouter lien Espace Employé si type_utilisateur = 'employe'
    if (user.type_utilisateur === 'employe') {
      dropdownItems += `<li class="dropdown-divider"></li>
      <li><a href="/employee" class="dropdown-link" style="color: #27ae60; font-weight: bold;">🛠️ Espace Employé</a></li>`;
    }
    
    // Ajouter lien Espace Admin si type_utilisateur = 'admin'
    if (user.type_utilisateur === 'admin') {
      dropdownItems += `<li class="dropdown-divider"></li>
      <li><a href="/admin" class="dropdown-link" style="color: #667eea; font-weight: bold;">🔧 Espace Admin</a></li>`;
    }
    
    dropdownItems += `<li class="dropdown-divider"></li>
      <li><a href="#" class="dropdown-link logout" onclick="logout(); return false;">Déconnexion</a></li>`;
    
    authContainer.innerHTML = `
      <div class="user-menu">
        <button class="nav-link user-button" id="userMenuBtn">
          <span class="user-icon">👤</span>
          <span class="user-name">${displayName}</span>
          <span class="dropdown-icon">▼</span>
        </button>
        <ul class="dropdown-menu" id="userDropdown">
          ${dropdownItems}
        </ul>
      </div>
    `;
    // Réattacher les écouteurs après avoir créé le menu
    attachUserMenuListeners();
    // Afficher le solde de crédits
    if (creditContainer) {
      creditContainer.style.display = '';
      updateCreditBalance();
    }
  } else {
    // Boutons connexion/inscription
    authContainer.innerHTML = `
      <div class="auth-buttons">
        <a href="/login" class="nav-link btn btn-outline">Connexion</a>
        <a href="/signup" class="nav-link btn btn-primary">Inscription</a>
      </div>
    `;
    hideCreditBalance();
  }
}
async function updateCreditBalance() {
  const creditContainer = document.getElementById('creditContainer');
  const creditBalanceEl = document.getElementById('creditBalance');
  if (!creditContainer || !creditBalanceEl) return;

  // Seulement si authentifié
  if (!SessionManager.isAuthenticated()) {
    hideCreditBalance();
    return;
  }

  try {
    const resp = await fetch('/api/user/credit', {
      method: 'GET',
      headers: {
        'Accept': 'application/json'
      },
      credentials: 'include'
    });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    const json = await resp.json();
    if (json && json.success && json.data && typeof json.data.credit !== 'undefined') {
      creditBalanceEl.textContent = String(json.data.credit);
      creditContainer.style.display = '';
    } else {
      hideCreditBalance();
    }
  } catch (e) {
    console.warn('Impossible de récupérer le crédit utilisateur:', e);
    hideCreditBalance();
  }
}

function hideCreditBalance() {
  const creditContainer = document.getElementById('creditContainer');
  const creditBalanceEl = document.getElementById('creditBalance');
  if (creditContainer) creditContainer.style.display = 'none';
  if (creditBalanceEl) creditBalanceEl.textContent = '0';
}

/**
 * Attache les écouteurs au menu utilisateur
 */
function attachUserMenuListeners() {
  const userMenuBtn = document.getElementById('userMenuBtn');
  const userDropdown = document.getElementById('userDropdown');

  if (!userMenuBtn || !userDropdown) {
    return;  // Le menu utilisateur n'existe pas
  }

  // Supprimer les anciens écouteurs en clonant et remplaçant le bouton
  const newUserMenuBtn = userMenuBtn.cloneNode(true);
  userMenuBtn.parentNode.replaceChild(newUserMenuBtn, userMenuBtn);

  // Réattacher à la nouvelle instance
  newUserMenuBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    userDropdown.classList.toggle('active');
    newUserMenuBtn.querySelector('.dropdown-icon').style.transform = 
      userDropdown.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0)';
  });

  // Fermer le dropdown au clic dehors
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-menu')) {
      userDropdown.classList.remove('active');
      const icon = newUserMenuBtn.querySelector('.dropdown-icon');
      if (icon) {
        icon.style.transform = 'rotate(0)';
      }
    }
  });

  // Fermer le dropdown au clic sur un lien
  const dropdownLinks = userDropdown.querySelectorAll('.dropdown-link');
  dropdownLinks.forEach(link => {
    link.addEventListener('click', function() {
      userDropdown.classList.remove('active');
      const icon = newUserMenuBtn.querySelector('.dropdown-icon');
      if (icon) {
        icon.style.transform = 'rotate(0)';
      }
    });
  });
}

/**
 * Échappe les caractères HTML pour éviter les injections XSS
 */
function escapeHtml(text) {
  if (text === undefined || text === null) return '';
  const str = String(text);
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return str.replace(/[&<>"']/g, m => map[m]);
}

function getDisplayName(user) {
  if (!user || typeof user !== 'object') return 'Utilisateur';
  return user.pseudo || user.username || (user.email ? user.email.split('@')[0] : 'Utilisateur');
}

/**
 * Effectue la déconnexion
 */
async function logout() {
  if (!confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
    return;
  }

  try {
    await SessionManager.logout();
    // Rediriger vers la page d'accueil après déconnexion
    window.location.href = '/';
  } catch (error) {
    console.error('Erreur déconnexion:', error);
    // Même en cas d'erreur, on efface la session locale
    SessionManager.clearSession();
    window.location.href = '/';
  }
}

// Initialiser le menu dès le chargement du script (pas attendre DOMContentLoaded)
if (document.readyState === 'loading') {
  // Le DOM n'est pas encore complètement chargé
  document.addEventListener('DOMContentLoaded', initializeAuthMenu);
} else {
  // Le DOM est déjà chargé (cas où le script est en footer)
  initializeAuthMenu();
}
