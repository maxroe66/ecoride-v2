/**
 * Historique Page JavaScript
 * Gère l'affichage et l'annulation des covoiturages (US10)
 */

let currentTripId = null;
let currentCancelType = null; // 'trip' ou 'participation'
let allTrips = [];

document.addEventListener('DOMContentLoaded', () => {
  console.log('Historique page loaded');
  
  // Charger l'historique
  // Définir le filtre par défaut sur "planifie" pour n'afficher que les trajets planifiés
  const statusFilterEl = document.getElementById('statusFilter');
  if (statusFilterEl) {
    statusFilterEl.value = 'planifie';
  }
  loadCreditSummary();
  loadHistory();
  
  // Setup event listeners
  setupEventListeners();
});

/**
 * Configure les event listeners
 */
function setupEventListeners() {
  const statusFilter = document.getElementById('statusFilter');
  const closeModalBtn = document.querySelector('.close-btn');
  const closeModalBtn2 = document.getElementById('closeModal');
  const cancelForm = document.getElementById('cancelForm');
  const cancelReason = document.getElementById('cancelReason');
  
  if (statusFilter) {
    statusFilter.addEventListener('change', filterTrips);
  }
  
  if (closeModalBtn) {
    closeModalBtn.addEventListener('click', closeModal);
  }
  
  if (closeModalBtn2) {
    closeModalBtn2.addEventListener('click', closeModal);
  }
  
  if (cancelForm) {
    cancelForm.addEventListener('submit', submitCancellation);
  }
  
  if (cancelReason) {
    cancelReason.addEventListener('input', updateCharCount);
  }
  
  // Fermer modal au clic en dehors
  const modal = document.getElementById('cancelModal');
  if (modal) {
    window.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeModal();
      }
    });
  }
}
/**
 * Charge et affiche le résumé des crédits (gagnés, utilisés, solde)
 */
async function loadCreditSummary() {
  const summary = document.getElementById('creditSummary');
  const totalCreditEl = document.getElementById('totalCredit');
  const totalDebitEl = document.getElementById('totalDebit');
  const balanceEl = document.getElementById('currentBalance');
  if (!summary || !totalCreditEl || !totalDebitEl || !balanceEl) return;

  try {
    const resp = await fetch('/api/user/credit/operations', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include'
    });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    const json = await resp.json();
    if (!json.success) throw new Error(json.error?.message || 'Erreur API');
    const { total_credit, total_debit, balance } = json.data || {};
    totalCreditEl.textContent = formatCreditNumber(total_credit);
    totalDebitEl.textContent = formatCreditNumber(total_debit);
    balanceEl.textContent = formatCreditNumber(balance);
    summary.style.display = '';
  } catch (e) {
    console.warn('Résumé des crédits indisponible:', e);
    summary.style.display = 'none';
  }
}

function formatCreditNumber(n) {
  const num = Number(n || 0);
  return num.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

/**
 * Charge l'historique des trajets
 */
async function loadHistory() {
  const container = document.getElementById('tripsContainer');
  
  try {
    const response = await fetch('/api/historique/trajets', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      }
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error?.message || 'Erreur lors du chargement');
    }
    
    const data = await response.json();
    allTrips = data.data || [];
    
    if (allTrips.length === 0) {
      container.innerHTML = '<p class="loading">Vous n\'avez aucun covoiturage pour le moment.</p>';
      return;
    }
    
    // Appliquer le filtre actif si présent
    const statusFilterEl = document.getElementById('statusFilter');
    const activeStatus = statusFilterEl ? statusFilterEl.value : '';
    if (activeStatus) {
      const filtered = allTrips.filter(t => resolveTripStatus(t) === activeStatus);
      displayTrips(filtered);
    } else {
      displayTrips(allTrips);
    }
    
  } catch (error) {
    console.error('Erreur:', error);
    showMessage('Erreur lors du chargement de l\'historique: ' + error.message, 'error');
    container.innerHTML = '<p class="loading">Erreur lors du chargement. Veuillez recharger la page.</p>';
  }
}

/**
 * Affiche les trajets
 */
function displayTrips(trips) {
  const container = document.getElementById('tripsContainer');
  
  if (trips.length === 0) {
    container.innerHTML = '<p class="loading">Aucun covoiturage ne correspond à vos critères.</p>';
    return;
  }
  
  container.innerHTML = trips.map(trip => createTripCard(trip)).join('');
  
  // Ajouter les event listeners sur les boutons d'annulation
  document.querySelectorAll('.btn-cancel-trip').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const tripId = e.target.dataset.tripId;
      const type = e.target.dataset.type;
      openCancelModal(tripId, type);
    });
  });
  
  // Initialiser les event listeners US11
  initializeUS11EventListeners();
}

/**
 * Crée une carte de trajet
 */
function createTripCard(trip) {
  const role = trip.role === 'chauffeur' ? 'Chauffeur' : 'Passager';
  const dateDepart = new Date(trip.date_depart);
  const formattedDate = dateDepart.toLocaleDateString('fr-FR');
  const formattedTime = trip.heure_depart || '00:00';
  
  // Déterminer le statut (trajet ou participation)
  const statut = resolveTripStatus(trip);
  const canCancel = ['planifie', 'en_cours'].includes(statut);
  
  // ID pour l'annulation
  const tripIdentifier = trip.role === 'chauffeur' ? 
    `trip-${trip.id || trip.covoiturage_id}` : 
    `participation-${trip.participation_id || trip.id}`;
  
  const cancelType = trip.role === 'chauffeur' ? 'trip' : 'participation';
  
  return `
    <div class="trip-card">
      <div class="trip-header">
        <h3 class="trip-title">
          ${trip.lieu_depart} → ${trip.lieu_arrivee}
        </h3>
        <div class="trip-badges">
          <span class="trip-role ${trip.role === 'chauffeur' ? 'chauffeur' : 'passager'}">
            ${role}
          </span>
          <span class="trip-status ${statut}">
            ${formatStatut(statut)}
          </span>
        </div>
      </div>
      
      <div class="trip-details">
        <div class="trip-detail-item">
          <div class="trip-detail-icon">📅</div>
          <div class="trip-detail-content">
            <p class="trip-detail-label">Date et heure</p>
            <p class="trip-detail-value">${formattedDate} à ${formattedTime}</p>
          </div>
        </div>
        
        <div class="trip-detail-item">
          <div class="trip-detail-icon">💰</div>
          <div class="trip-detail-content">
            <p class="trip-detail-label">Prix par personne</p>
            <p class="trip-detail-value">${trip.prix_personne || trip.prix || trip.price || '---'} crédits</p>
          </div>
        </div>
        
        ${trip.role === 'chauffeur' ? `
          <div class="trip-detail-item">
            <div class="trip-detail-icon">👥</div>
            <div class="trip-detail-content">
              <p class="trip-detail-label">Places disponibles</p>
              <p class="trip-detail-value">${trip.nb_places || trip.nb_places_disponibles || '0'} place(s)</p>
            </div>
          </div>
          
          <div class="trip-detail-item">
            <div class="trip-detail-icon">🚗</div>
            <div class="trip-detail-content">
              <p class="trip-detail-label">Véhicule</p>
              <p class="trip-detail-value">${trip.marque || '---'} ${trip.modele || ''}</p>
            </div>
          </div>
        ` : `
          <div class="trip-detail-item">
            <div class="trip-detail-icon">👤</div>
            <div class="trip-detail-content">
              <p class="trip-detail-label">Chauffeur</p>
              <p class="trip-detail-value">${trip.chauffeur_nom || '---'}</p>
            </div>
          </div>
        `}
      </div>
      
      <div class="trip-actions">
        ${canCancel ? `
          <button class="btn btn-danger btn-cancel-trip" 
                  data-trip-id="${trip.role === 'chauffeur' ? (trip.trajet_id || trip.covoiturage_id || trip.id) : trip.participation_id}"
                  data-type="${cancelType}">
            ❌ Annuler
          </button>
        ` : ''}
        
        <!-- US11: Boutons Chauffeur -->
        ${trip.role === 'chauffeur' && statut === 'planifie' ? `
          <button class="btn btn-start-trip" data-trip-id="${trip.trajet_id}">
            🚀 Démarrer
          </button>
        ` : ''}
        
        ${trip.role === 'chauffeur' && statut === 'en_cours' ? `
          <button class="btn btn-end-trip" data-trip-id="${trip.trajet_id}">
            ⏸️ Arrivée à destination
          </button>
        ` : ''}
        
        <!-- US11: Actions Passager regroupées -->
        ${trip.role === 'passager' && hasTripEnded(trip) ? `
          ${shouldShowFinalizeActions(trip, statut) ? `
            <button class="btn btn-finalize-trip"
                    data-participation-id="${getParticipationId(trip)}"
                    data-trajet-id="${getTripIdForActions(trip)}"
                    data-statut="${statut}">
              ${getFinalizeButtonLabel(statut)}
            </button>
          ` : ''}
        ` : ''}
        
        <button class="btn btn-primary" data-trip-id="${trip.trajet_id}" onclick="viewDetails(this)">
          👁️ Détails
        </button>
      </div>
    </div>
  `;
}

/**
 * Formate le statut pour l'affichage
 */
function formatStatut(statut) {
  const map = {
    'planifie': 'Planifié',
    'en_cours': 'En cours',
    'termine': 'Terminé',
    'annule': 'Annulé',
    'demandee': 'En attente',
    'confirmee': 'Confirmée',
    'refusee': 'Refusée',
    'en_attente_validation': 'En attente',
    'validee': 'Validée',
    'probleme': 'Problème signalé'
  };
  return map[statut] || statut;
}

function resolveTripStatus(trip) {
  if (!trip) {
    return 'inconnu';
  }
  if (trip.role === 'passager') {
    return trip.statut_participation || trip.statut || 'inconnu';
  }
  return trip.statut || trip.statut_participation || 'inconnu';
}

function hasTripEnded(trip) {
  const state = trip.trajet_statut || trip.statut;
  return state === 'termine';
}

function getParticipationId(trip) {
  return trip.participation_id || trip.id;
}

function getTripIdForActions(trip) {
  return trip.trajet_id || trip.covoiturage_id || trip.id;
}

function shouldShowFinalizeActions(trip, statut) {
  if (trip.role !== 'passager' || !hasTripEnded(trip)) {
    return false;
  }
  const usableStatuses = ['confirmee', 'en_attente_validation', 'validee', 'probleme'];
  return usableStatuses.includes(statut);
}

function getFinalizeButtonLabel(statut) {
  switch (statut) {
    case 'validee':
      return '⭐ Donner mon avis';
    case 'probleme':
      return '⚠️ Incident en cours';
    case 'confirmee':
    case 'en_attente_validation':
      return '✨ Finaliser ce trajet';
    default:
      return 'Gérer ce trajet';
  }
}

/**
 * Filtre les trajets par statut
 */
function filterTrips() {
  const statusFilter = document.getElementById('statusFilter').value;
  
  if (!statusFilter) {
    displayTrips(allTrips);
  } else {
    const filtered = allTrips.filter(trip => resolveTripStatus(trip) === statusFilter);
    displayTrips(filtered);
  }
}

/**
 * Ouvre le modal d'annulation
 */
function openCancelModal(tripId, type) {
  currentTripId = tripId;
  currentCancelType = type;
  
  // Réinitialiser le formulaire
  const form = document.getElementById('cancelForm');
  if (form) {
    form.reset();
    document.getElementById('charCount').textContent = '0/500';
  }
  
  // Ouvrir le modal
  const modal = document.getElementById('cancelModal');
  if (modal) {
    modal.classList.add('show');
  }
}

/**
 * Ferme le modal d'annulation
 */
function closeModal() {
  const modal = document.getElementById('cancelModal');
  if (modal) {
    modal.classList.remove('show');
  }
  currentTripId = null;
  currentCancelType = null;
}

/**
 * Met à jour le compteur de caractères
 */
function updateCharCount() {
  const textarea = document.getElementById('cancelReason');
  const count = textarea.value.length;
  document.getElementById('charCount').textContent = `${count}/500`;
}

/**
 * Soumet l'annulation
 */
async function submitCancellation(e) {
  e.preventDefault();
  
  if (!currentTripId || !currentCancelType) {
    showMessage('Erreur: informations manquantes', 'error');
    return;
  }
  
  const reason = document.getElementById('cancelReason').value;
  
  try {
    // Récupérer le token CSRF via le SessionManager (stockage local)
    // NOTE maintenance: si le SessionManager n'est pas chargé ou si
    // la récupération échoue, on utilise un fallback depuis localStorage
    // afin d'envoyer quand même le header `X-CSRF-Token` attendu côté serveur.
    // Si absent, tenter un rafraîchissement avant le premier appel
    let csrfHeaders = await (window.SessionManager?.csrfHeaders?.() || Promise.resolve({}));
    if (!csrfHeaders['X-CSRF-Token']) {
      const refreshed = await (window.SessionManager?.refreshCsrfToken?.() || Promise.resolve(null));
      if (refreshed) {
        csrfHeaders = { 'X-CSRF-Token': refreshed };
      } else {
        // Fallback: lire directement le token depuis le localStorage si présent
        try {
          const localToken = localStorage.getItem('ecoride_csrf');
          if (localToken) {
            csrfHeaders = { 'X-CSRF-Token': localToken };
          }
        } catch (_) {}
      }
    }
    
    let url, body;
    
    if (currentCancelType === 'trip') {
      url = `/api/trajets/${currentTripId}/annuler`;
      body = JSON.stringify({
        raison: reason || null
      });
    } else {
      url = `/api/participations/${currentTripId}/annuler`;
      body = JSON.stringify({});
    }
    
    let response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...csrfHeaders
      },
      credentials: 'include',
      body: body
    });
    
    // Lire la réponse sous forme de texte pour gérer les erreurs HTML
    let raw = await response.text();
    let parsed = null;
    try {
      parsed = raw ? JSON.parse(raw) : null;
    } catch (_) {
      parsed = null; // ce n'est pas du JSON, probablement une page HTML d'erreur
    }

    // Si CSRF échec, tenter un rafraîchissement et réessayer une fois
    if (!response.ok && (parsed?.error?.code === 'CSRF_FAILED' || response.status === 403)) {
      const refreshed = await (window.SessionManager?.refreshCsrfToken?.() || Promise.resolve(null));
      if (refreshed) {
        csrfHeaders = { 'X-CSRF-Token': refreshed };
        response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            ...csrfHeaders
          },
          credentials: 'include',
          body: body
        });
        raw = await response.text();
        try { parsed = raw ? JSON.parse(raw) : null; } catch (_) { parsed = null; }
      }
    }

    if (!response.ok) {
      const serverMsg = parsed?.error?.message || parsed?.message || '';
      const detail = serverMsg || (raw ? raw.slice(0, 200) : '');
      throw new Error(detail || 'Erreur lors de l\'annulation');
    }

    const data = parsed || {};
    
    console.log('📡 Réponse API:', data);
    console.log('🔍 Type annulation:', currentCancelType);
    console.log('📧 Participants notifiés:', data.participants_notified);
    console.log('📧 Driver notifié:', data.driver_notified);
    
    // 📧 Construire le message avec le nombre de participants notifiés
    let successMessage = 'Covoiturage annulé avec succès!';
    
    if (currentCancelType === 'trip' && data.participants_notified !== undefined && data.participants_notified !== null) {
      const count = data.participants_notified;
      successMessage += ` 📧 Email envoyé à ${count} participant${count > 1 ? 's' : ''}`;
    } else if (currentCancelType === 'participation' && data.driver_notified === true) {
      successMessage += ` 📧 Email envoyé au chauffeur`;
      if (data.driver_name) {
        successMessage += ` (${data.driver_name})`;
      }
    }
    
    showMessage(successMessage, 'success');
    closeModal();
    
    // Recharger l'historique immédiatement avec le filtre réinitialisé
    const statusFilterEl = document.getElementById('statusFilter');
    if (statusFilterEl) {
      statusFilterEl.value = ''; // Réinitialiser le filtre
    }
    await loadHistory();
    
  } catch (error) {
    console.error('Erreur:', error);
    showMessage('Erreur lors de l\'annulation: ' + error.message, 'error');
  }
}

/**
 * Affiche un message
 */
function showMessage(message, type) {
  const container = document.getElementById('messageContainer');
  if (!container) return;
  
  const messageEl = document.createElement('div');
  messageEl.className = `message ${type}`;
  messageEl.textContent = message;
  
  container.appendChild(messageEl);
  messageEl.classList.add('show');
  
  // Scroll vers le message pour qu'il soit visible
  messageEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
  
  // Auto-remove après 8 secondes (augmenté de 5s)
  setTimeout(() => {
    messageEl.classList.remove('show');
    setTimeout(() => messageEl.remove(), 300);
  }, 8000);
}

/**
 * Affiche les détails complets d'un trajet
 */
function viewDetails(btn) {
  const tripId = btn.dataset.tripId;
  if (!tripId) {
    showMessage('Erreur: ID du trajet manquant', 'error');
    return;
  }
  
  // Naviguer vers la page de détails
  window.location.href = `/vue-covoiturage-detail?id=${tripId}`;
}
