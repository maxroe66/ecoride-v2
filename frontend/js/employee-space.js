/**
 * Gestion de l'espace employé
 * Charge et affiche les avis en attente et les incidents
 */

document.addEventListener('DOMContentLoaded', () => {
  // Vérifier que l'utilisateur est connecté et est un employé
  const user = SessionManager.getUser();
  if (!user || user.type_utilisateur !== 'employe') {
    window.location.href = '/login';
    return;
  }

  setupTabs();
  loadPendingReviews();
  loadIncidents();
});

// === TABS ===
function setupTabs() {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const tabName = e.target.dataset.tab;
      switchTab(tabName);
    });
  });
}

function switchTab(tabName) {
  // Cacher tous les tabs
  document.querySelectorAll('.tab-content').forEach(t => {
    t.classList.remove('active');
  });

  // Afficher tab sélectionné
  const tabContent = document.getElementById(`${tabName}-tab`);
  if (tabContent) {
    tabContent.classList.add('active');
  }

  // Marquer bouton actif
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  event.target.classList.add('active');
}

// === AVIS EN ATTENTE ===
async function loadPendingReviews() {
  try {
    const response = await fetch('/api/employee/reviews/pending', {
      method: 'GET',
      credentials: 'include',
      headers: {
        'Accept': 'application/json'
      }
    });

    const result = await response.json();

    if (!result.success) {
      displayError('reviewsContainer', 'Erreur lors du chargement des avis');
      return;
    }

    const reviews = result.data.items || [];
    if (reviews.length === 0) {
      document.getElementById('reviewsContainer').innerHTML =
        '<div class="empty-state">✅ Aucun avis en attente - Tous les avis sont modérés !</div>';
      return;
    }

    const html = reviews.map(review => `
      <div class="card review-card">
        <div class="card-header">
          <div>
            <strong>${escapeHtml(review.user_id || 'Anonyme')}</strong>
            <span class="review-date" style="margin-left: 15px; font-size: 12px; color: #999;">
              ${formatDate(review.created_at)}
            </span>
          </div>
          <div style="font-size: 18px;">${'⭐'.repeat(review.rating)}</div>
        </div>
        <div class="card-body">
          <p><strong>Note :</strong> ${review.rating}/5</p>
          ${review.comment ? `<p><strong>Commentaire :</strong> <em>"${escapeHtml(review.comment)}"</em></p>` : ''}
          <p><strong>Date :</strong> ${formatDateTime(review.created_at)}</p>
        </div>
        <div class="card-actions">
          <button class="btn-approve" onclick="moderateReview('${review._id || review.avis_id}', 'approuve')">
            ✅ Approuver
          </button>
          <button class="btn-reject" onclick="moderateReview('${review._id || review.avis_id}', 'refuse')">
            ❌ Refuser
          </button>
        </div>
      </div>
    `).join('');

    document.getElementById('reviewsContainer').innerHTML = html;
  } catch (error) {
    console.error('Erreur:', error);
    displayError('reviewsContainer', 'Erreur lors du chargement : ' + error.message);
  }
}

async function moderateReview(avisId, action) {
  try {
    const csrfHeaders = await SessionManager.csrfHeaders();

    const response = await fetch(`/api/employee/reviews/${avisId}/moderation`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...csrfHeaders
      },
      body: JSON.stringify({
        action: action
      })
    });

    const result = await response.json();

    if (result.success) {
      alert(`✅ Avis ${action === 'approuve' ? 'approuvé' : 'refusé'} avec succès`);
      loadPendingReviews();
    } else {
      alert('❌ Erreur : ' + (result.error?.message || 'Erreur inconnue'));
    }
  } catch (error) {
    alert('❌ Erreur : ' + error.message);
  }
}

// === INCIDENTS ===
async function loadIncidents() {
  try {
    const response = await fetch('/api/employee/incidents', {
      method: 'GET',
      credentials: 'include',
      headers: {
        'Accept': 'application/json'
      }
    });

    const result = await response.json();

    if (!result.success) {
      displayError('incidentsContainer', 'Erreur lors du chargement des incidents');
      return;
    }

    const incidents = result.data.items || [];
    if (incidents.length === 0) {
      document.getElementById('incidentsContainer').innerHTML =
        '<div class="empty-state">✅ Aucun incident signalé - Tous les covoiturages se sont bien déroulés !</div>';
      return;
    }

    const html = incidents.map(incident => `
      <div class="card incident-card">
        <div class="card-header">
          <div>
            <strong>#${incident.participation_id || incident.incident_id}</strong>
            <span style="margin-left: 15px; font-size: 12px; color: #999;">
              ${formatDate(incident.date_probleme || incident.date_creation)}
            </span>
          </div>
          <span style="color: #e74c3c; font-weight: bold;">⚠️ Incident</span>
        </div>
        <div class="card-body" style="line-height: 1.8;">
          <p><strong>Passager :</strong> ${escapeHtml(incident.passager_pseudo || 'N/A')} (${escapeHtml(incident.passager_email || 'N/A')})</p>
          <p><strong>Conducteur :</strong> ${escapeHtml(incident.conducteur_pseudo || 'N/A')} (${escapeHtml(incident.conducteur_email || 'N/A')})</p>
          <p><strong>Trajet :</strong> ${escapeHtml(incident.lieu_depart || 'N/A')} → ${escapeHtml(incident.lieu_arrivee || 'N/A')}</p>
          <p><strong>Date de départ :</strong> ${incident.date_depart || 'N/A'} à ${incident.heure_depart || 'N/A'}</p>
          <p><strong>Heure d'arrivée :</strong> ${incident.heure_arrivee || 'N/A'}</p>
          <p><strong>Problème :</strong> ${escapeHtml(incident.raison_probleme || incident.description || '(non décrit)')}</p>
          ${incident.statut ? `<p><strong>Statut :</strong> <span style="color: #27ae60;">${escapeHtml(incident.statut)}</span></p>` : ''}
        </div>
        <div class="card-actions">
          <button class="btn-detail" onclick="viewIncidentDetail(${incident.participation_id || incident.incident_id})">
            📋 Détails
          </button>
        </div>
      </div>
    `).join('');

    document.getElementById('incidentsContainer').innerHTML = html;
  } catch (error) {
    console.error('Erreur:', error);
    displayError('incidentsContainer', 'Erreur lors du chargement : ' + error.message);
  }
}

async function viewIncidentDetail(participationId) {
  try {
    const response = await fetch(`/api/employee/incidents/${participationId}`, {
      method: 'GET',
      credentials: 'include',
      headers: {
        'Accept': 'application/json'
      }
    });

    const result = await response.json();

    if (result.success) {
      const incident = result.data;
      const details = `
Participation #${incident.participation_id || incident.incident_id}
━━━━━━━━━━━━━━━━━━━━━━━
Passager: ${incident.passager_pseudo || 'N/A'} (${incident.passager_email || 'N/A'})
Conducteur: ${incident.conducteur_pseudo || 'N/A'} (${incident.conducteur_email || 'N/A'})
━━━━━━━━━━━━━━━━━━━━━━━
Trajet: ${incident.lieu_depart || 'N/A'} → ${incident.lieu_arrivee || 'N/A'}
Date: ${incident.date_depart || 'N/A'} à ${incident.heure_depart || 'N/A'}
Problème: ${incident.raison_probleme || incident.description || '(non décrit)'}
Statut: ${incident.statut || 'en_attente'}
      `;
      alert(details);
    } else {
      alert('❌ Erreur : Incident non trouvé');
    }
  } catch (error) {
    alert('❌ Erreur : ' + error.message);
  }
}

// === HELPERS ===
function displayError(containerId, message) {
  const container = document.getElementById(containerId);
  if (container) {
    container.innerHTML = `<div class="error">${escapeHtml(message)}</div>`;
  }
}

function escapeHtml(text) {
  if (!text) return '';
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return String(text).replace(/[&<>"']/g, m => map[m]);
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR');
  } catch (e) {
    return dateString;
  }
}

function formatDateTime(dateString) {
  if (!dateString) return 'N/A';
  try {
    const date = new Date(dateString);
    return date.toLocaleString('fr-FR');
  } catch (e) {
    return dateString;
  }
}
