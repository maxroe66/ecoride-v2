document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('searchForm');
    const resultsContainer = document.getElementById('resultsContainer');
    
    // Variables pour stocker les derniers paramètres de recherche
    let lastDeparture = '';
    let lastArrival = '';

    searchForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // 1. Récupérer les valeurs du formulaire
        const departure = document.getElementById('searchInput').value.trim();
        const arrival = document.getElementById('destinationInput').value.trim();
        const date = document.getElementById('dateInput').value.trim();
        
        // Sauvegarder pour les suggestions
        lastDeparture = departure;
        lastArrival = arrival;

        // 2. Vérifier que les champs ne sont pas vides
        if (!departure || !arrival || !date) {
            alert('Tous les champs sont requis');
            return;
        }

        // 3. Envoyer la requête à l'API
        try {
            const response = await fetch(`/api/trajets?departure=${encodeURIComponent(departure)}&arrival=${encodeURIComponent(arrival)}&date=${date}`);
            const result = await response.json();

            if (result.success) {
                // 4. Afficher les résultats
                displayResults(result.data.items);
            } else {
                alert('Erreur : ' + result.error.message);
            }
        } catch (error) {
            alert('Erreur de connexion : ' + error.message);
        }
    });

    async function displayResults(trajets) {
        if (trajets.length === 0) {
            // Appeler l'API pour les suggestions
            await displaySuggestions(lastDeparture, lastArrival);
            return;
        }

        // Générer le HTML pour chaque trajet
        const trajetCards = trajets.map(trajet => `
            <div class="trajet-card">
                ${trajet.est_ecologique ? '<div class="eco-badge">🌱 Écologique</div>' : ''}
                <div class="conducteur-section">
                    <img class="conducteur-photo" src="/images-icons/icons8-avatar-50.png" alt="Avatar">
                    <h3 class="conducteur-pseudo">${trajet.conducteur_pseudo}</h3>
                    <div class="conducteur-rating" id="rating-${trajet.covoiturage_id}">
                        <span class="stars">★★★★★</span>
                        <span class="rating-text">-- / 5 (0 avis)</span>
                    </div>
                </div>
                <div class="trajet-header">
                    <h3>${trajet.lieu_depart} → ${trajet.lieu_arrivee}</h3>
                    <p class="price">${trajet.prix_personne}€ par personne</p>
                </div>
                <div class="trajet-details">
                    <p><strong>Date :</strong> ${trajet.date_depart}</p>
                    <p><strong>Départ :</strong> ${trajet.heure_depart}</p>
                    <p><strong>Arrivée :</strong> ${trajet.heure_arrivee}</p>
                    <p><strong>Places :</strong> ${trajet.nb_places} disponibles</p>
                </div>
                <a href="/vue-covoiturage-detail.php?id=${trajet.covoiturage_id}" class="btn-detail">Détail</a>
            </div>
        `).join('');

        resultsContainer.innerHTML = trajetCards;
        resultsContainer.classList.add('show');
        
        // Charger les notes des conducteurs
        loadRatings(trajets);
    }

    // Charger les notes moyennes pour tous les trajets
    async function loadRatings(trajets) {
        for (const trajet of trajets) {
            try {
                const response = await fetch(`/api/avis/stats?covoiturage_id=${trajet.covoiturage_id}`);
                const result = await response.json();
                
                if (result.success) {
                    const ratingElement = document.getElementById(`rating-${trajet.covoiturage_id}`);
                    if (ratingElement) {
                        displayStars(ratingElement, result.data.average, result.data.count);
                    }
                }
            } catch (error) {
                console.error('Erreur chargement note:', error);
            }
        }
    }

    // Afficher les étoiles et la note
    function displayStars(element, average, count) {
        const stars = Math.round(average); // Arrondir à l'entier plus proche
        const starDisplay = '★'.repeat(stars) + '☆'.repeat(5 - stars); // ★★★☆☆
        element.innerHTML = `
            <span class="stars">${starDisplay}</span>
            <span class="rating-text">${average.toFixed(1)} / 5 (${count} avis)</span>
        `;
        element.classList.add('loaded');
    }

    // Afficher les suggestions de dates alternatives
    async function displaySuggestions(departure, arrival) {
        try {
            const response = await fetch(`/api/trajets-suggestions?departure=${encodeURIComponent(departure)}&arrival=${encodeURIComponent(arrival)}`);
            const result = await response.json();
            
            if (result.success && result.data.suggestions.length > 0) {
                const suggestionsHTML = `
                    <div class="no-results">
                        <p>Aucun trajet trouvé pour cette date.</p>
                        <p><strong>Dates avec trajets disponibles :</strong></p>
                        <ul class="suggestions-list">
                            ${result.data.suggestions.map(s => `
                                <li class="suggestion-item" data-date="${s.date}">
                                    <strong>${s.date}</strong> - ${s.count} trajet(s) disponible(s)
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                `;
                resultsContainer.innerHTML = suggestionsHTML;
            } else {
                resultsContainer.innerHTML = '<p class="no-results">Aucun trajet trouvé pour cette route.</p>';
            }
            resultsContainer.classList.add('show');
            
            // Ajouter les listeners pour les suggestions cliquables
            const suggestionItems = document.querySelectorAll('.suggestion-item');
            suggestionItems.forEach(item => {
                item.addEventListener('click', function() {
                    const selectedDate = this.getAttribute('data-date');
                    // Remplir le champ date et relancer la recherche
                    document.getElementById('dateInput').value = selectedDate;
                    
                    // Déclencher la soumission du formulaire
                    searchForm.dispatchEvent(new Event('submit'));
                });
            });
        } catch (error) {
            console.error('Erreur chargement suggestions:', error);
            resultsContainer.innerHTML = '<p class="no-results">Aucun trajet trouvé</p>';
            resultsContainer.classList.add('show');
        }
    }
});