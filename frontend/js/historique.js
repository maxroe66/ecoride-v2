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
    
    displayTrips(allTrips);
    
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
  const statut = trip.statut || trip.statut_participation || 'inconnu';
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
            <p class="trip-detail-value">${trip.prix || trip.price || '---'} crédits</p>
          </div>
        </div>
        
        ${trip.role === 'chauffeur' ? `
          <div class="trip-detail-item">
            <div class="trip-detail-icon">👥</div>
            <div class="trip-detail-content">
              <p class="trip-detail-label">Places disponibles</p>
              <p class="trip-detail-value">${trip.nb_places_disponibles || '0'} place(s)</p>
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
                  data-trip-id="${trip.role === 'chauffeur' ? (trip.id || trip.covoiturage_id) : trip.participation_id}"
                  data-type="${cancelType}">
            ❌ Annuler
          </button>
        ` : ''}
        <button class="btn btn-primary" onclick="viewDetails(this)">
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
    'validee': 'Validée'
  };
  return map[statut] || statut;
}

/**
 * Filtre les trajets par statut
 */
function filterTrips() {
  const statusFilter = document.getElementById('statusFilter').value;
  
  if (!statusFilter) {
    displayTrips(allTrips);
  } else {
    const filtered = allTrips.filter(trip => {
      const statut = trip.statut || trip.statut_participation || 'inconnu';
      return statut === statusFilter;
    });
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
    // Récupérer le token CSRF
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    
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
    
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken || ''
      },
      body: body
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error?.message || 'Erreur lors de l\'annulation');
    }
    
    const data = await response.json();
    
    showMessage('Covoiturage annulé avec succès! Les participants ont été notifiés.', 'success');
    closeModal();
    
    // Recharger l'historique
    setTimeout(() => {
      loadHistory();
    }, 1000);
    
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
  
  // Auto-remove après 5 secondes
  setTimeout(() => {
    messageEl.classList.remove('show');
    setTimeout(() => messageEl.remove(), 300);
  }, 5000);
}

/**
 * Affiche les détails complets d'un trajet
 */
function viewDetails(btn) {
  // TODO: Implémenter la navigation vers la page de détail
  alert('Affichage des détails à implémenter');
}
