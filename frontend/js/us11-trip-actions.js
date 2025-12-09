/**
 * US11 Trip Actions JavaScript - Démarrage et arrêt de covoiturage
 * Gère les modals et appels API pour les 4 actions principales
 */

// Variables globales
let currentStartTripId = null;
let currentEndTripId = null;
let currentValidateParticipationId = null;
let currentReportProblemParticipationId = null;

/**
 * Initialiser les event listeners pour US11
 * Appelée après le chargement des trajets
 */
function initializeUS11EventListeners() {
  // Boutons démarrer trajet (chauffeur)
  document.querySelectorAll('.btn-start-trip').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      openStartTripModal(e.currentTarget.dataset.tripId);
    });
  });

  // Boutons arrêter trajet (chauffeur)
  document.querySelectorAll('.btn-end-trip').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      openEndTripModal(e.currentTarget.dataset.tripId);
    });
  });

  // Boutons valider participation (passager)
  document.querySelectorAll('.btn-validate-participation').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      openValidateParticipationModal(e.currentTarget.dataset.participationId);
    });
  });

  // Boutons signaler problème (passager)
  document.querySelectorAll('.btn-report-problem').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      openReportProblemModal(e.currentTarget.dataset.participationId);
    });
  });

  // Fermer modals par boutons X
  document.querySelectorAll('#startTripModal .close-btn').forEach(btn => {
    btn.addEventListener('click', closeStartModal);
  });

  document.querySelectorAll('#endTripModal .close-btn').forEach(btn => {
    btn.addEventListener('click', closeEndModal);
  });

  document.querySelectorAll('#validateParticipationModal .close-btn').forEach(btn => {
    btn.addEventListener('click', closeValidateModal);
  });

  document.querySelectorAll('#reportProblemModal .close-btn').forEach(btn => {
    btn.addEventListener('click', closeReportModal);
  });

  // Fermer modals par boutons Annuler
  document.getElementById('closeStartModal')?.addEventListener('click', closeStartModal);
  document.getElementById('closeEndModal')?.addEventListener('click', closeEndModal);
  document.getElementById('closeValidateModal')?.addEventListener('click', closeValidateModal);
  document.getElementById('closeReportModal')?.addEventListener('click', closeReportModal);

  // Confirmer actions
  document.getElementById('confirmStartTrip')?.addEventListener('click', confirmStartTrip);
  document.getElementById('confirmEndTrip')?.addEventListener('click', confirmEndTrip);
  document.getElementById('confirmValidateParticipation')?.addEventListener('click', confirmValidateParticipation);

  // Form submission
  document.getElementById('reportProblemForm')?.addEventListener('submit', submitReportProblem);

  // Compteur de caractères pour raison problème
  document.getElementById('problemReason')?.addEventListener('input', (e) => {
    const count = e.target.value.length;
    const countEl = document.getElementById('problemCharCount');
    if (countEl) {
      countEl.textContent = `${count}/500`;
      
      // Avertissement à 80%
      if (count > 400) {
        countEl.classList.add('warning');
      } else {
        countEl.classList.remove('warning');
      }
      
      // Limite atteinte à 100%
      if (count >= 500) {
        countEl.classList.add('limit');
      } else {
        countEl.classList.remove('limit');
      }
    }
  });

  // Fermer modals au clic en dehors
  window.addEventListener('click', (event) => {
    const startModal = document.getElementById('startTripModal');
    const endModal = document.getElementById('endTripModal');
    const validateModal = document.getElementById('validateParticipationModal');
    const reportModal = document.getElementById('reportProblemModal');

    if (event.target === startModal) {
      closeStartModal();
    }
    if (event.target === endModal) {
      closeEndModal();
    }
    if (event.target === validateModal) {
      closeValidateModal();
    }
    if (event.target === reportModal) {
      closeReportModal();
    }
  });
}

/**
 * MODAL: DÉMARRER TRAJET (CHAUFFEUR)
 */

function openStartTripModal(tripId) {
  currentStartTripId = tripId;
  const modal = document.getElementById('startTripModal');
  if (modal) {
    modal.classList.add('show');
  }
}

function closeStartModal() {
  const modal = document.getElementById('startTripModal');
  if (modal) {
    modal.classList.remove('show');
  }
  currentStartTripId = null;
}

async function confirmStartTrip() {
  if (!currentStartTripId) return;

  const btn = document.getElementById('confirmStartTrip');
  const originalText = btn?.textContent || 'Démarrer';
  
  try {
    if (btn) {
      btn.disabled = true;
      btn.textContent = '⏳ Démarrage...';
    }

    // Récupérer les headers CSRF avec fallback
    let csrfHeaders = {};
    try {
      csrfHeaders = await (window.SessionManager?.csrfHeaders?.() || Promise.resolve({}));
    } catch (e) {
      console.warn('CSRF headers fallback');
    }

    const response = await fetch(`/api/trajets/${currentStartTripId}/start`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...csrfHeaders
      },
      credentials: 'include',
      body: JSON.stringify({})
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error?.message || 'Erreur lors du démarrage du trajet');
    }

    const data = await response.json();
    
    showMessage('✅ Trajet démarré avec succès ! Les participants ont été notifiés.', 'success');
    closeStartModal();
    
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
 * MODAL: ARRÊTER TRAJET (CHAUFFEUR)
 */

function openEndTripModal(tripId) {
  currentEndTripId = tripId;
  const modal = document.getElementById('endTripModal');
  if (modal) {
    modal.classList.add('show');
  }
}

function closeEndModal() {
  const modal = document.getElementById('endTripModal');
  if (modal) {
    modal.classList.remove('show');
  }
  currentEndTripId = null;
}

async function confirmEndTrip() {
  if (!currentEndTripId) return;

  const btn = document.getElementById('confirmEndTrip');
  const originalText = btn?.textContent || 'Confirmer l\'arrivée';
  
  try {
    if (btn) {
      btn.disabled = true;
      btn.textContent = '⏳ Traitement...';
    }

    // Récupérer les headers CSRF avec fallback
    let csrfHeaders = {};
    try {
      csrfHeaders = await (window.SessionManager?.csrfHeaders?.() || Promise.resolve({}));
    } catch (e) {
      console.warn('CSRF headers fallback');
    }

    const response = await fetch(`/api/trajets/${currentEndTripId}/end`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        ...csrfHeaders
      },
      credentials: 'include',
      body: JSON.stringify({})
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error?.message || 'Erreur lors de l\'arrêt du trajet');
    }

    const data = await response.json();
    const nbNotified = data.data?.nb_participants_notifies || 0;
    
    showMessage(`✅ Trajet terminé ! ${nbNotified} participant(s) ont été notifié(s).`, 'success');
    closeEndModal();
    
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
 * MODAL: VALIDER PARTICIPATION (PASSAGER)
 */

function openValidateParticipationModal(participationId) {
  currentValidateParticipationId = participationId;
  const modal = document.getElementById('validateParticipationModal');
  if (modal) {
    modal.classList.add('show');
  }
}

function closeValidateModal() {
  const modal = document.getElementById('validateParticipationModal');
  if (modal) {
    modal.classList.remove('show');
  }
  currentValidateParticipationId = null;
}

async function confirmValidateParticipation() {
  if (!currentValidateParticipationId) return;

  const btn = document.getElementById('confirmValidateParticipation');
  const originalText = btn?.textContent || 'Valider';
  
  try {
    if (btn) {
      btn.disabled = true;
      btn.textContent = '⏳ Validation...';
    }

    // Récupérer les headers CSRF avec fallback
    let csrfHeaders = {};
    try {
      csrfHeaders = await (window.SessionManager?.csrfHeaders?.() || Promise.resolve({}));
    } catch (e) {
      console.warn('CSRF headers fallback');
    }

    const response = await fetch(`/api/participations/${currentValidateParticipationId}/validate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...csrfHeaders
      },
      credentials: 'include',
      body: JSON.stringify({})
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error?.message || 'Erreur lors de la validation');
    }

    const data = await response.json();
    const montantCredit = data.data?.montant_credit || 0;
    
    showMessage('✅ Participation validée ! Le chauffeur a reçu ses crédits. Vous pouvez maintenant soumettre un avis.', 'success');
    closeValidateModal();
    
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
 * MODAL: SIGNALER UN PROBLÈME (PASSAGER)
 */

function openReportProblemModal(participationId) {
  currentReportProblemParticipationId = participationId;
  const modal = document.getElementById('reportProblemModal');
  const form = document.getElementById('reportProblemForm');
  
  if (form) {
    form.reset();
    document.getElementById('problemCharCount').textContent = '0/500';
    document.getElementById('problemCharCount').classList.remove('warning', 'limit');
  }
  
  if (modal) {
    modal.classList.add('show');
  }
}

function closeReportModal() {
  const modal = document.getElementById('reportProblemModal');
  if (modal) {
    modal.classList.remove('show');
  }
  currentReportProblemParticipationId = null;
}

async function submitReportProblem(event) {
  event.preventDefault();
  
  if (!currentReportProblemParticipationId) return;

  const reason = document.getElementById('problemReason')?.value || '';
  
  if (!reason || reason.trim().length === 0) {
    showMessage('⚠️ Veuillez décrire le problème rencontré', 'error');
    return;
  }

  const btn = event.target.querySelector('button[type="submit"]');
  const originalText = btn?.textContent || 'Signaler le problème';
  
  try {
    if (btn) {
      btn.disabled = true;
      btn.textContent = '⏳ Envoi...';
    }

    // Récupérer les headers CSRF avec fallback
    let csrfHeaders = {};
    try {
      csrfHeaders = await (window.SessionManager?.csrfHeaders?.() || Promise.resolve({}));
    } catch (e) {
      console.warn('CSRF headers fallback');
    }

    const response = await fetch(`/api/participations/${currentReportProblemParticipationId}/problem`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...csrfHeaders
      },
      credentials: 'include',
      body: JSON.stringify({ reason })
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error?.message || 'Erreur lors du signalement');
    }

    const data = await response.json();
    
    showMessage('⚠️ Problème signalé avec succès. Un employé EcoRide vous contactera pour résoudre la situation.', 'success');
    closeReportModal();
    
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
 * Ajouter cette ligne à la fin de loadHistory() dans historique.js :
 * initializeUS11EventListeners();
 */
