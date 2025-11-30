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

    // Afficher le contenu et masquer le spinner
    document.getElementById('loadingSpinner').style.display = 'none';
    document.getElementById('detailContent').style.display = 'block';

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
 * Affiche un message d'erreur
 */
function showError(message) {
  document.getElementById('loadingSpinner').style.display = 'none';
  document.getElementById('detailContent').style.display = 'none';
  document.getElementById('errorMessage').style.display = 'block';
  document.getElementById('errorText').textContent = message;
}

/**
 * Placeholder : action pour contacter le conducteur
 */
function contactDriver() {
  alert('La fonctionnalité de contact est en développement.');
}