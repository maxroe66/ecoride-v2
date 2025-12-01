document.addEventListener('DOMContentLoaded', async () => {
  // Récupérer l'ID du covoiturage depuis les paramètres URL
  const urlParams = new URLSearchParams(window.location.search);
  const covoiturageId = urlParams.get('id');

  // Vérifier que l'ID est présent et valide
  if (!covoiturageId || isNaN(covoiturageId) || covoiturageId <= 0) {
    showError('ID invalide. Impossible de charger le détail du covoiturage.');
    return;
  }

  // Afficher le spinner
  document.getElementById('loadingSpinner').style.display = 'block';

  try {
    // Appeler l'API pour récupérer le détail
    const response = await fetch(`/api/trajets/detail?id=${covoiturageId}`);
    
    if (!response.ok) {
      if (response.status === 404) {
        showError('Ce covoiturage n\'existe pas ou a été supprimé.');
      } else if (response.status === 400) {
        showError('Paramètres invalides.');
      } else {
        showError('Erreur serveur. Veuillez réessayer plus tard.');
      }
      return;
    }

    const result = await response.json();

    // Vérifier si la réponse est valide
    if (!result.success || !result.data) {
      showError('Impossible de charger le détail du covoiturage.');
      return;
    }

    const data = result.data;

    // Remplir les informations du trajet
    document.getElementById('detail-departure').textContent = data.trajet.lieu_depart;
    document.getElementById('detail-arrival').textContent = data.trajet.lieu_arrivee;
    document.getElementById('detail-date').textContent = formatDate(data.trajet.date_depart);
    document.getElementById('detail-time').textContent = formatTime(data.trajet.heure_depart, data.trajet.heure_arrivee);
    document.getElementById('detail-duration').textContent = calculateDuration(data.trajet.heure_depart, data.trajet.heure_arrivee);
    document.getElementById('detail-seats').textContent = `${data.trajet.nb_places} place${data.trajet.nb_places > 1 ? 's' : ''}`;
    document.getElementById('detail-price').textContent = `${parseFloat(data.trajet.prix_personne).toFixed(2)}€`;

    // Afficher le badge écologique si applicable
    if (data.trajet.est_ecologique) {
      document.getElementById('ecoLabel').style.display = 'block';
    }

    // Remplir les informations du conducteur
    document.getElementById('driver-name').textContent = data.conducteur.pseudo;
    if (data.conducteur.photo_url) {
      document.getElementById('driver-photo').src = data.conducteur.photo_url;
    }
    document.getElementById('driver-rating-value').textContent = `${parseFloat(data.rating.average).toFixed(1)}/5`;
    document.getElementById('driver-rating-count').textContent = `(${data.rating.count} avis)`;

    // Remplir les informations du véhicule
    document.getElementById('vehicle-brand').textContent = data.vehicule.marque;
    document.getElementById('vehicle-model').textContent = data.vehicule.modele;
    document.getElementById('vehicle-energy').textContent = data.vehicule.energie;

      // Afficher les préférences du conducteur
      if (data.preferences_conducteur) {
        const prefList = document.getElementById('driver-preferences');
        prefList.innerHTML = '';
        ['fumeur', 'animaux'].forEach((key) => {
          if (key in data.preferences_conducteur) {
            const li = document.createElement('li');
            li.textContent = `${formatPreference(key)} : ${data.preferences_conducteur[key] ? 'Oui' : 'Non'}`;
            prefList.appendChild(li);
          }
        });
        // Afficher autres préférences (texte libre)
        if (data.preferences_conducteur.autres_preferences && data.preferences_conducteur.autres_preferences.trim() !== '') {
          const li = document.createElement('li');
          li.textContent = `Autres préférences : ${data.preferences_conducteur.autres_preferences}`;
          li.style.fontStyle = 'italic';
          prefList.appendChild(li);
        }
      } else {
        document.getElementById('driver-preferences').innerHTML = '<li>Aucune préférence renseignée.</li>';
      }
      // Afficher les avis du conducteur
      const reviewsDiv = document.getElementById('driver-reviews');
      const toggleBtn = document.getElementById('toggle-reviews-btn');
      reviewsDiv.innerHTML = '';
      toggleBtn.style.display = 'none';
      
      if (Array.isArray(data.avis_conducteur) && data.avis_conducteur.length > 0) {
        const maxVisibleReviews = 3;
        const totalReviews = data.avis_conducteur.length;
        
        data.avis_conducteur.forEach((avis, index) => {
          const avisEl = document.createElement('div');
          avisEl.className = 'review-item';
          
          // Masquer les avis au-delà du 3ème
          if (index >= maxVisibleReviews) {
            avisEl.classList.add('hidden');
          }
          
          avisEl.innerHTML = `<strong>Note :</strong> ${avis.note}/5<br><strong>Commentaire :</strong> ${avis.commentaire ? avis.commentaire : '(aucun)'}<br><span class='review-date'>${avis.date}</span>`;
          reviewsDiv.appendChild(avisEl);
        });
        
        // Afficher le bouton si plus de 3 avis
        if (totalReviews > maxVisibleReviews) {
          toggleBtn.style.display = 'block';
          toggleBtn.textContent = `Voir tous les avis (${totalReviews})`;
          toggleBtn.onclick = () => toggleAllReviews();
        }
      } else {
        reviewsDiv.innerHTML = '<p>Aucun avis pour ce conducteur.</p>';
      }
// Fonction pour afficher le libellé des préférences
function formatPreference(key) {
  const labels = {
    fumeur: 'Fumeur',
    animaux: 'Animaux acceptés',
    musique: 'Musique',
    discussion: 'Discussion',
    // Ajouter d'autres clés si besoin
  };
  return labels[key] ? labels[key] : key;
}

    // Afficher le contenu et masquer le spinner
    document.getElementById('loadingSpinner').style.display = 'none';
    document.getElementById('detailContent').style.display = 'block';

    // Initialiser les événements des modales de participation
    initializeParticipationEvents();

  } catch (error) {
    console.error('Erreur lors du chargement:', error);
    showError('Une erreur réseau est survenue. Veuillez vérifier votre connexion.');
  }
});

/**
 * Formate une date au format YYYY-MM-DD en français
 */
function formatDate(dateStr) {
  if (!dateStr) return '---';
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('fr-FR', options);
}

/**
 * Formate les heures de départ et arrivée
 */
function formatTime(depart, arrivee) {
  if (!depart || !arrivee) return '---';
  return `${depart} → ${arrivee}`;
}

/**
 * Calcule la durée entre deux heures (format HH:MM:SS)
 */
function calculateDuration(depart, arrivee) {
  if (!depart || !arrivee) return '---';
  
  const [hD, mD] = depart.split(':').map(Number);
  const [hA, mA] = arrivee.split(':').map(Number);
  
  let minutes = (hA * 60 + mA) - (hD * 60 + mD);
  
  // Gérer le passage minuit (rare mais possible)
  if (minutes < 0) {
    /**
     * Formate une préférence conducteur pour affichage
     */
    function formatPreference(key, value) {
      // Adapter ici pour affichage user-friendly
      const labels = {
        fumeur: 'Fumeur',
        animaux: 'Animaux acceptés',
        musique: 'Musique',
        discussion: 'Discussion',
        // Ajouter d'autres clés si besoin
      };
      return labels[key] ? labels[key] : key;
    }
    minutes += 24 * 60;
  }
  
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  
  if (hours === 0) {
    return `${mins}min`;
  }
  return `${hours}h ${mins}min`;
}

/**
 * Affiche/masque tous les avis du conducteur
 */
function toggleAllReviews() {
  const reviewItems = document.querySelectorAll('.review-item.hidden');
  const toggleBtn = document.getElementById('toggle-reviews-btn');
  
  if (reviewItems.length > 0) {
    // Les avis masqués existent, on les affiche
    reviewItems.forEach(item => {
      item.classList.remove('hidden');
    });
    toggleBtn.textContent = 'Masquer les avis';
  } else {
    // Les avis sont tous visibles, on masque les avis au-delà du 3ème
    const allReviews = document.querySelectorAll('.review-item');
    const maxVisibleReviews = 3;
    const totalReviews = allReviews.length;
    
    allReviews.forEach((item, index) => {
      if (index >= maxVisibleReviews) {
        item.classList.add('hidden');
      }
    });
    toggleBtn.textContent = `Voir tous les avis (${totalReviews})`;
  }
}

/**
 * Affiche un message d'erreur
 */
function showError(message) {
  document.getElementById('loadingSpinner').style.display = 'none';
  document.getElementById('detailContent').style.display = 'none';
  document.getElementById('errorMessage').style.display = 'block';
  document.getElementById('errorText').textContent = message;
}

/**
 * Gestion des modales et participations
 */

// Variables globales pour tracker l'état de la participation
let currentCovoiturageId = null;
let currentParticipationId = null;
let currentNbPlaces = 1;
let currentPrice = 0;

/**
 * Initialiser les événements des modales et du bouton participer
 */
function initializeParticipationEvents() {
  const btnParticipate = document.getElementById('btn-participate');
  if (btnParticipate) {
    btnParticipate.addEventListener('click', openModal1);
  }

  // Fermer les modales avec le bouton X
  document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const modal = e.target.closest('.modal');
      if (modal.id === 'modal1-participation') {
        closeModal1();
      } else if (modal.id === 'modal2-confirmation') {
        closeModal2();
      }
    });
  });

  // Gérer les actions des boutons avec data-action
  document.querySelectorAll('[data-action]').forEach(btn => {
    btn.addEventListener('click', handleModalAction);
  });

  // Fermer modal au clic sur le fond (backdrop)
  document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        if (modal.id === 'modal1-participation') {
          closeModal1();
        } else if (modal.id === 'modal2-confirmation') {
          closeModal2();
        }
      }
    });
  });
}

/**
 * Gérer les actions des boutons via data-action
 */
function handleModalAction(e) {
  const action = e.target.getAttribute('data-action');
  
  if (action === 'close-modal1') {
    closeModal1();
  } else if (action === 'proceed-modal2') {
    proceedToModal2();
  } else if (action === 'close-modal2') {
    closeModal2();
  } else if (action === 'confirm-participation') {
    confirmParticipation();
  }
}

/**
 * Ouvrir Modal 1 (première confirmation)
 */
function openModal1() {
  // Récupérer l'ID du covoiturage depuis l'URL
  const urlParams = new URLSearchParams(window.location.search);
  currentCovoiturageId = parseInt(urlParams.get('id'));

  // Récupérer les infos depuis le DOM (déjà chargées dans DOMContentLoaded)
  const detailContent = document.getElementById('detailContent');
  if (!detailContent || detailContent.style.display === 'none') {
    alert('Impossible de charger les informations du covoiturage.');
    return;
  }

  // Récupérer prix et places
  const priceText = document.getElementById('detail-price')?.textContent || '0';
  currentPrice = parseFloat(priceText.replace('€', ''));
  
  const seatsText = document.getElementById('detail-seats')?.textContent || '0';
  const seatsMatch = seatsText.match(/(\d+)/);
  const availableSeats = seatsMatch ? parseInt(seatsMatch[1]) : 0;

  // Valider que les places disponibles > 0
  if (availableSeats <= 0) {
    alert('Aucune place disponible pour ce covoiturage.');
    return;
  }

  // Mettre à jour modal 1
  currentNbPlaces = 1;
  document.getElementById('modal1-amount').textContent = (currentPrice * currentNbPlaces).toFixed(2);
  document.getElementById('modal1-seats').textContent = currentNbPlaces;

  // Afficher la modal 1
  const modal1 = document.getElementById('modal1-participation');
  modal1.classList.add('show');
}

/**
 * Fermer Modal 1
 */
function closeModal1() {
  const modal1 = document.getElementById('modal1-participation');
  modal1.classList.remove('show');
}

/**
 * Passer à Modal 2 (deuxième confirmation)
 */
function proceedToModal2() {
  // Appeler API 1 : requestParticipation
  requestParticipation();
}

/**
 * API 1 : Demander une participation
 */
async function requestParticipation() {
  try {
    const response = await fetch('/api/participations/request', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        covoiturage_id: currentCovoiturageId,
        nb_places: currentNbPlaces
      })
    });

    const result = await response.json();

    if (!result.success) {
      alert('Erreur : ' + (result.error?.message || 'Impossible de créer la participation.'));
      return;
    }

    // Sauvegarder l'ID de participation
    currentParticipationId = result.data.participation_id;

    // Fermer modal 1 et ouvrir modal 2
    closeModal1();
    openModal2();

  } catch (error) {
    console.error('Erreur requestParticipation:', error);
    alert('Erreur de connexion. Veuillez réessayer.');
  }
}

/**
 * Ouvrir Modal 2 (deuxième confirmation)
 */
function openModal2() {
  document.getElementById('modal2-amount').textContent = (currentPrice * currentNbPlaces).toFixed(2);
  
  const modal2 = document.getElementById('modal2-confirmation');
  modal2.classList.add('show');
}

/**
 * Fermer Modal 2
 */
function closeModal2() {
  const modal2 = document.getElementById('modal2-confirmation');
  modal2.classList.remove('show');
}

/**
 * API 2 + 3 : Valider et Confirmer la participation
 */
async function confirmParticipation() {
  try {
    // API 2 : Valider
    const validateResponse = await fetch('/api/participations/validate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        participation_id: currentParticipationId
      })
    });

    const validateResult = await validateResponse.json();

    if (!validateResult.success) {
      alert('Erreur validation : ' + (validateResult.error?.message || 'Impossible de valider.'));
      return;
    }

    // API 3 : Confirmer
    const confirmResponse = await fetch('/api/participations/confirm', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        participation_id: currentParticipationId
      })
    });

    const confirmResult = await confirmResponse.json();

    if (!confirmResult.success) {
      alert('Erreur confirmation : ' + (confirmResult.error?.message || 'Impossible de confirmer.'));
      return;
    }

    // Succès !
    closeModal2();
    alert('✅ Participation confirmée ! Votre crédit a été débité.');
    
    // Recharger la page ou rediriger
    setTimeout(() => {
      window.location.reload();
    }, 1500);

  } catch (error) {
    console.error('Erreur confirmParticipation:', error);
    alert('Erreur de connexion. Veuillez réessayer.');
  }
}

/**
 * Placeholder : action pour contacter le conducteur (legacy)
 */
function contactDriver() {
  alert('La fonctionnalité de contact est en développement.');
}