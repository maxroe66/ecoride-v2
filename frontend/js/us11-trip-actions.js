/**
 * US11 Trip Actions JavaScript - Démarrage et arrêt de covoiturage
 * Gère les modals et appels API pour les 4 actions principales
 */

// Variables globales
let currentStartTripId = null;
let currentEndTripId = null;
let currentValidateParticipationId = null;
let currentReportProblemParticipationId = null;
let currentFinalizeParticipationId = null;
let currentFinalizeTrajetId = null;
let currentFinalizeStatus = null;

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

  // Boutons finaliser / gérer trajet (passager)
  document.querySelectorAll('.btn-finalize-trip').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const participationId = e.currentTarget.dataset.participationId;
      const trajetId = e.currentTarget.dataset.trajetId;
      const statut = e.currentTarget.dataset.statut;
      if (!participationId) {
        showMessage('Identifiant de participation manquant pour cette action.', 'error');
        return;
      }
      openFinalizeParticipationModal(participationId, trajetId, statut);
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

  document.querySelectorAll('#finalizeParticipationModal .close-btn').forEach(btn => {
    btn.addEventListener('click', closeFinalizeModal);
  });

  // Fermer modals par boutons Annuler
  document.getElementById('closeStartModal')?.addEventListener('click', closeStartModal);
  document.getElementById('closeEndModal')?.addEventListener('click', closeEndModal);
  document.getElementById('closeValidateModal')?.addEventListener('click', closeValidateModal);
  document.getElementById('closeReportModal')?.addEventListener('click', closeReportModal);
  document.getElementById('closeFinalizeModal')?.addEventListener('click', closeFinalizeModal);

  // Confirmer actions
  document.getElementById('confirmStartTrip')?.addEventListener('click', confirmStartTrip);
  document.getElementById('confirmEndTrip')?.addEventListener('click', confirmEndTrip);
  document.getElementById('confirmValidateParticipation')?.addEventListener('click', confirmValidateParticipation);
  document.getElementById('finalizeReviewCTA')?.addEventListener('click', handleFinalizeReviewCTA);

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
    const finalizeModal = document.getElementById('finalizeParticipationModal');

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
    if (event.target === finalizeModal) {
      closeFinalizeModal();
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

    // Rafraîchir le token CSRF avant l'appel
    const csrf = await SessionManager.csrfHeaders();

    const response = await fetch(`/api/trajets/${currentStartTripId}/start`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...csrf
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

    // Rafraîchir le token CSRF avant l'appel
    const csrf = await SessionManager.csrfHeaders();

    const response = await fetch(`/api/trajets/${currentEndTripId}/end`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        ...csrf
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
 * MODAL: FINALISER TRAJET (PASSAGER)
 */

function openFinalizeParticipationModal(participationId, trajetId = null, statut = 'confirmee', focusSection = null) {
  const parsedParticipationId = parseInt(participationId, 10);
  if (Number.isNaN(parsedParticipationId)) {
    showMessage('Participation invalide pour cette action.', 'error');
    return;
  }

  const parsedTrajetId = trajetId ? parseInt(trajetId, 10) : null;
  currentFinalizeParticipationId = parsedParticipationId;
  currentFinalizeTrajetId = parsedTrajetId;
  currentFinalizeStatus = statut || 'confirmee';

  currentValidateParticipationId = parsedParticipationId;
  currentReportProblemParticipationId = parsedParticipationId;

  clearFinalizeHints();
  resetFinalizeProblemForm();
  updateFinalizeModalView();

  const modal = document.getElementById('finalizeParticipationModal');
  if (modal) {
    modal.classList.add('show');
    focusFinalizeSection(focusSection);
  }
}

function closeFinalizeModal(resetState = true) {
  const modal = document.getElementById('finalizeParticipationModal');
  if (modal) {
    modal.classList.remove('show');
  }

  if (!resetState) {
    return;
  }

  currentFinalizeParticipationId = null;
  currentFinalizeTrajetId = null;
  currentFinalizeStatus = null;
  currentValidateParticipationId = null;
  currentReportProblemParticipationId = null;
  clearFinalizeHints();
  resetFinalizeProblemForm();
}

function resetFinalizeProblemForm() {
  const form = document.getElementById('reportProblemForm');
  if (form) {
    form.reset();
  }
  const textarea = document.getElementById('problemReason');
  if (textarea) {
    textarea.readOnly = false;
  }
  const charCount = document.getElementById('problemCharCount');
  if (charCount) {
    charCount.textContent = '0/500';
    charCount.classList.remove('warning', 'limit');
  }
}

function clearFinalizeHints() {
  ['finalizeValidateHint', 'finalizeProblemHint', 'finalizeReviewHint'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.textContent = '';
    }
  });
}

function updateFinalizeModalView() {
  const status = currentFinalizeStatus || 'confirmee';
  const formatStatus = typeof formatStatut === 'function'
    ? formatStatut
    : (value) => value;
  const badge = document.getElementById('finalizeStatusBadge');
  if (badge) {
    badge.textContent = formatStatus(status);
    badge.dataset.status = status;
    badge.className = `finalize-status-chip ${status}`;
  }

  const { label, hint } = describeFinalizeStatus(status);
  const labelEl = document.getElementById('finalizeStatusLabel');
  if (labelEl) {
    labelEl.textContent = label;
  }
  const hintEl = document.getElementById('finalizeStatusHint');
  if (hintEl) {
    hintEl.textContent = hint;
  }

  const canValidate = isPendingFinalizeStatus(status);
  const validateBtn = document.getElementById('confirmValidateParticipation');
  if (validateBtn) {
    validateBtn.disabled = !canValidate;
  }
  const validateHint = document.getElementById('finalizeValidateHint');
  if (validateHint) {
    if (canValidate) {
      validateHint.textContent = 'Cliquez pour débloquer les crédits du chauffeur.';
    } else if (status === 'validee') {
      validateHint.textContent = 'Trajet déjà validé.';
    } else if (status === 'probleme') {
      validateHint.textContent = 'Incident déclaré : validation suspendue.';
    } else {
      validateHint.textContent = '';
    }
  }

  const problemForm = document.getElementById('reportProblemForm');
  const problemHint = document.getElementById('finalizeProblemHint');
  const canSignalProblem = status !== 'validee' && status !== 'probleme';
  if (problemForm) {
    const textarea = problemForm.querySelector('#problemReason');
    const submitBtn = problemForm.querySelector('button[type="submit"]');
    if (textarea) {
      textarea.readOnly = !canSignalProblem;
    }
    if (submitBtn) {
      submitBtn.disabled = !canSignalProblem;
    }
  }
  if (problemHint) {
    if (!canSignalProblem && status === 'validee') {
      problemHint.textContent = 'Vous avez validé ce trajet. Le signalement n’est plus disponible.';
    } else if (!canSignalProblem && status === 'probleme') {
      problemHint.textContent = 'Incident déjà signalé. Notre équipe revient vers vous rapidement.';
    } else {
      problemHint.textContent = '';
    }
  }

  const reviewBtn = document.getElementById('finalizeReviewCTA');
  const reviewHint = document.getElementById('finalizeReviewHint');
  const canReview = canLeaveReviewForStatus(status);
  if (reviewBtn) {
    reviewBtn.disabled = !canReview;
  }
  if (reviewHint) {
    reviewHint.textContent = canReview
      ? 'Merci de contribuer à la qualité de la communauté EcoRide.'
      : 'Disponible après validation ou signalement d’un incident.';
  }
}

function describeFinalizeStatus(status) {
  switch (status) {
    case 'validee':
      return {
        label: 'Trajet validé',
        hint: 'Les crédits ont été envoyés au chauffeur. Vous pouvez maintenant laisser un avis.'
      };
    case 'probleme':
      return {
        label: 'Incident en cours',
        hint: 'Un employé EcoRide analyse votre signalement avant tout paiement.'
      };
    case 'en_attente_validation':
    case 'confirmee':
    default:
      return {
        label: 'Action requise',
        hint: 'Validez le trajet ou signalez un incident pour clôturer ce covoiturage.'
      };
  }
}

function isPendingFinalizeStatus(status) {
  return ['confirmee', 'en_attente_validation'].includes(status);
}

function canLeaveReviewForStatus(status) {
  return ['validee', 'probleme'].includes(status);
}

function handleFinalizeReviewCTA() {
  if (!canLeaveReviewForStatus(currentFinalizeStatus)) {
    showMessage('Validez ou signalez le trajet avant de laisser un avis.', 'info');
    return;
  }
  if (!currentFinalizeTrajetId) {
    showMessage('Impossible d’ouvrir le formulaire d’avis pour ce trajet.', 'error');
    return;
  }

  const targetTrajetId = currentFinalizeTrajetId;
  closeFinalizeModal();
  if (typeof openLeaveReviewModal === 'function') {
    openLeaveReviewModal(targetTrajetId);
  } else {
    showMessage('Le module d’avis est temporairement indisponible.', 'error');
  }
}

function focusFinalizeSection(section) {
  if (!section) {
    return;
  }
  if (section === 'problem') {
    document.getElementById('problemReason')?.focus();
  }
  if (section === 'validate') {
    document.getElementById('confirmValidateParticipation')?.focus();
  }
}

/**
 * MODAL: VALIDER PARTICIPATION (PASSAGER)
 */

function openValidateParticipationModal(participationId) {
  openFinalizeParticipationModal(participationId, currentFinalizeTrajetId, currentFinalizeStatus || 'confirmee', 'validate');
}

function closeValidateModal() {
  closeFinalizeModal();
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

    // Rafraîchir le token CSRF avant l'appel
    const csrf = await SessionManager.csrfHeaders();

    const response = await fetch(`/api/participations/${currentValidateParticipationId}/validate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...csrf
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
    currentFinalizeStatus = 'validee';
    resetFinalizeProblemForm();
    updateFinalizeModalView();
    
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
  openFinalizeParticipationModal(participationId, currentFinalizeTrajetId, currentFinalizeStatus || 'confirmee', 'problem');
}

function closeReportModal() {
  closeFinalizeModal();
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

    // Rafraîchir le token CSRF avant l'appel
    const csrf = await SessionManager.csrfHeaders();

    const response = await fetch(`/api/participations/${currentReportProblemParticipationId}/problem`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...csrf
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
    currentFinalizeStatus = 'probleme';
    resetFinalizeProblemForm();
    updateFinalizeModalView();
    
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
