/**
 * Authentification (Login/Signup) - Logique côté client
 */

document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const signupForm = document.getElementById('signupForm');

  // ==========================================
  // Formulaire de connexion
  // ==========================================
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const remember = document.getElementById('remember')?.checked || false;
      const errorMsg = document.getElementById('errorMessage');

      // Validation basique
      if (!email || !password) {
        showError(errorMsg, 'Veuillez remplir tous les champs');
        return;
      }

      try {
        // Appeler l'API de connexion
        const response = await fetch('/api/auth/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, password, remember })
        });

        const data = await response.json();

        if (!response.ok) {
          showError(errorMsg, data.error?.message || 'Erreur de connexion');
          return;
        }

        // Succès - rediriger vers la page d'accueil ou tableau de bord
        window.location.href = data.redirect || '/';
      } catch (error) {
        showError(errorMsg, 'Erreur réseau : ' + error.message);
      }
    });
  }

  // ==========================================
  // Formulaire d'inscription
  // ==========================================
  if (signupForm) {
    const pseudoInput = document.getElementById('pseudo');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const errorMsg = document.getElementById('errorMessage');

    // Indicateur de force du mot de passe
    if (passwordInput) {
      passwordInput.addEventListener('input', () => {
        const strength = calculatePasswordStrength(passwordInput.value);
        updatePasswordStrength(strength);
      });
    }

    signupForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const pseudo = pseudoInput.value.trim();
      const email = emailInput.value.trim();
      const password = passwordInput.value;
      const confirmPassword = confirmPasswordInput.value;

      // Validations
      const validation = validateSignupForm(pseudo, email, password, confirmPassword);
      if (!validation.valid) {
        showError(errorMsg, validation.message);
        return;
      }

      try {
        // Appeler l'API d'inscription
        const response = await fetch('/api/auth/signup', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ pseudo, email, password })
        });

        const data = await response.json();

        if (!response.ok) {
          showError(errorMsg, data.error?.message || 'Erreur lors de l\'inscription');
          return;
        }

        // Succès - afficher message et rediriger
        alert('Compte créé avec succès ! Vous allez être redirigé vers la page de connexion.');
        window.location.href = '/login';
      } catch (error) {
        showError(errorMsg, 'Erreur réseau : ' + error.message);
      }
    });
  }
});

// ==========================================
// Fonctions utilitaires
// ==========================================

/**
 * Affiche un message d'erreur
 */
function showError(element, message) {
  if (!element) return;
  element.textContent = message;
  element.style.display = 'block';
  element.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * Calcule la force du mot de passe
 * @returns {number} 0-100
 */
function calculatePasswordStrength(password) {
  let strength = 0;

  if (!password) return 0;

  // Longueur
  if (password.length >= 8) strength += 25;
  if (password.length >= 12) strength += 10;
  if (password.length >= 16) strength += 10;

  // Caractères minuscules
  if (/[a-z]/.test(password)) strength += 15;

  // Caractères majuscules
  if (/[A-Z]/.test(password)) strength += 15;

  // Chiffres
  if (/[0-9]/.test(password)) strength += 15;

  // Caractères spéciaux
  if (/[^a-zA-Z0-9]/.test(password)) strength += 10;

  return Math.min(strength, 100);
}

/**
 * Met à jour l'indicateur de force du mot de passe
 */
function updatePasswordStrength(strength) {
  const bar = document.querySelector('.strength-bar');
  if (!bar) return;

  bar.style.width = strength + '%';

  // Changer la couleur selon la force
  if (strength < 25) {
    bar.style.background = '#e74c3c'; // Rouge
  } else if (strength < 50) {
    bar.style.background = '#f39c12'; // Orange
  } else if (strength < 75) {
    bar.style.background = '#f1c40f'; // Jaune
  } else {
    bar.style.background = '#2ecc71'; // Vert
  }
}

/**
 * Valide les données du formulaire d'inscription
 */
function validateSignupForm(pseudo, email, password, confirmPassword) {
  // Pseudo
  if (!pseudo || pseudo.length < 3) {
    return { valid: false, message: 'Le pseudo doit contenir au moins 3 caractères' };
  }
  if (pseudo.length > 20) {
    return { valid: false, message: 'Le pseudo ne doit pas dépasser 20 caractères' };
  }
  if (!/^[a-zA-Z0-9_-]+$/.test(pseudo)) {
    return { valid: false, message: 'Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores' };
  }

  // Email
  if (!email) {
    return { valid: false, message: 'L\'email est requis' };
  }
  if (!isValidEmail(email)) {
    return { valid: false, message: 'Veuillez entrer une adresse email valide' };
  }

  // Mot de passe
  if (!password || password.length < 8) {
    return { valid: false, message: 'Le mot de passe doit contenir au moins 8 caractères' };
  }
  if (!/[A-Z]/.test(password)) {
    return { valid: false, message: 'Le mot de passe doit contenir au moins une majuscule' };
  }
  if (!/[0-9]/.test(password)) {
    return { valid: false, message: 'Le mot de passe doit contenir au moins un chiffre' };
  }

  // Confirmation
  if (password !== confirmPassword) {
    return { valid: false, message: 'Les mots de passe ne correspondent pas' };
  }

  return { valid: true };
}

/**
 * Valide le format d'une adresse email
 */
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}
