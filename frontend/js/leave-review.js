/**
 * Gestion du modal de laisser un avis (US11)
 * Permet aux passagers de noter et commenter un trajet après sa conclusion
 */

let currentLeaveReviewTrajetId = null;
let selectedReviewRating = 0;

/**
 * Initialise les gestionnaires d'événements pour le modal d'avis
 */
function initializeLeaveReviewListeners() {
  // Bouton pour ouvrir le modal
  document.addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-leave-review')) {
      const trajetId = e.target.dataset.trajetId;
      openLeaveReviewModal(trajetId);
    }
  });

  // Fermeture du modal
  const closeReviewBtn = document.getElementById('closeReviewModal');
  if (closeReviewBtn) {
    closeReviewBtn.addEventListener('click', closeLeaveReviewModal);
  }

  // Gestion de la notation par étoiles
  const stars = document.querySelectorAll('.rating-stars .star');
  stars.forEach(star => {
    star.addEventListener('click', (e) => {
      selectedReviewRating = parseInt(e.target.dataset.value);
      document.getElementById('selectedRating').value = selectedReviewRating;
      
      // Mettre à jour l'affichage des étoiles
      stars.forEach((s, index) => {
        if (index < selectedReviewRating) {
          s.classList.add('active');
        } else {
          s.classList.remove('active');
        }
      });
    });
  });

  // Compteur de caractères pour le commentaire
  const reviewComment = document.getElementById('reviewComment');
  if (reviewComment) {
    reviewComment.addEventListener('input', (e) => {
      const count = e.target.value.length;
      document.getElementById('reviewCharCount').textContent = `${count}/500`;
    });
  }

  // Soumission du formulaire
  const leaveReviewForm = document.getElementById('leaveReviewForm');
  if (leaveReviewForm) {
    leaveReviewForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      await submitLeaveReview(e);
    });
  }
}

/**
 * Ouvre le modal pour laisser un avis
 */
function openLeaveReviewModal(trajetId) {
  currentLeaveReviewTrajetId = trajetId;
  const modal = document.getElementById('leaveReviewModal');
  if (modal) {
    modal.classList.add('show');
    resetReviewForm();
  }
}

/**
 * Ferme le modal pour laisser un avis
 */
function closeLeaveReviewModal() {
  const modal = document.getElementById('leaveReviewModal');
  if (modal) {
    modal.classList.remove('show');
  }
  currentLeaveReviewTrajetId = null;
  resetReviewForm();
}

/**
 * Réinitialise le formulaire d'avis
 */
function resetReviewForm() {
  const form = document.getElementById('leaveReviewForm');
  if (form) {
    form.reset();
  }
  selectedReviewRating = 0;
  document.getElementById('selectedRating').value = 0;
  document.getElementById('reviewCharCount').textContent = '0/500';
  
  // Réinitialiser les étoiles
  document.querySelectorAll('.rating-stars .star').forEach(star => {
    star.classList.remove('active');
  });
}

/**
 * Soumet l'avis au serveur
 */
async function submitLeaveReview(evt = null) {
  if (!currentLeaveReviewTrajetId) return;

  const rating = document.getElementById('selectedRating').value;
  const comment = document.getElementById('reviewComment').value;

  if (!rating || rating === '0') {
    showMessage('❌ Veuillez sélectionner une note', 'error');
    return;
  }

  const form = document.getElementById('leaveReviewForm');
  const btn = evt?.target?.querySelector('button[type="submit"]') || form?.querySelector('button[type="submit"]');
  const originalText = btn?.textContent || 'Soumettre l\'avis';
  
  try {
    if (btn) {
      btn.disabled = true;
      btn.textContent = '⏳ Envoi...';
    }

    // Récupérer le token CSRF
    const csrf = await SessionManager.csrfHeaders();

    const response = await fetch(`/api/avis`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...csrf
      },
      credentials: 'include',
      body: JSON.stringify({
        covoiturage_id: parseInt(currentLeaveReviewTrajetId),
        note: parseInt(rating),
        commentaire: comment
      })
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error?.message || 'Erreur lors de la soumission de l\'avis');
    }

    const data = await response.json();
    
    showMessage('✅ Avis soumis avec succès ! Merci de votre retour.', 'success');
    closeLeaveReviewModal();
    
    // Rafraîchir l'historique
    setTimeout(() => {
      loadHistory();
    }, 500);

  } catch (error) {
    console.error('Erreur:', error);
    showMessage('❌ ' + error.message, 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = originalText;
    }
  }
}

/**
 * Affiche un message temporaire à l'utilisateur
 */
function showMessage(message, type) {
  const container = document.getElementById('messageContainer');
  if (!container) return;

  const msgEl = document.createElement('div');
  msgEl.className = `message ${type}`;
  msgEl.textContent = message;
  container.appendChild(msgEl);
  msgEl.classList.add('show');

  // Scroll vers le message pour qu'il soit visible
  msgEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

  setTimeout(() => {
    msgEl.classList.remove('show');
    setTimeout(() => msgEl.remove(), 300);
  }, 5000);
}

// Initialiser les écouteurs quand le DOM est prêt
document.addEventListener('DOMContentLoaded', initializeLeaveReviewListeners);

// Réinitialiser aussi après chaque chargement d'historique
if (window.loadHistory) {
  const originalLoadHistory = window.loadHistory;
  window.loadHistory = function(...args) {
    originalLoadHistory.apply(this, args);
    // Les écouteurs persisteront grâce à l'event delegation
  };
}
