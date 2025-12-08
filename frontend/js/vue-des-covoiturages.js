document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('searchForm');
    const resultsContainer = document.getElementById('resultsContainer');
    const filtersContainer = document.getElementById('filtersContainer');
    
    // Variables pour stocker les derniers paramètres de recherche
    let lastDeparture = '';
    let lastArrival = '';
    let filtersCreated = false; // Flag pour éviter de recréer les filtres

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

    // Afficher les suggestions de dates alternatives
    async function displaySuggestions(departure, arrival, economique = '', maxPrice = '', maxDuration = '', minRating = '') {
        try {
            // Construire l'URL avec les filtres
            let url = `/api/trajets/suggestions?departure=${encodeURIComponent(departure)}&arrival=${encodeURIComponent(arrival)}`;
            if (economique) url += `&economique=${economique}`;
            if (maxPrice) url += `&maxPrice=${maxPrice}`;
            if (maxDuration) url += `&maxDuration=${maxDuration}`;
            if (minRating) url += `&minRating=${minRating}`;
            
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.success && result.data.suggestions.length > 0) {
                const suggestionsHTML = `
                    <div class="no-results">
                        <p>Aucun trajet trouvé pour ces critères.</p>
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
                resultsContainer.innerHTML = '<p class="no-results">Aucun trajet trouvé pour ces critères.</p>';
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

    async function displayResults(trajets) {
        if (trajets.length === 0) {
            // Appeler l'API pour les suggestions avec les filtres actuels
            const economique = document.getElementById('filterEcologique')?.checked ? '1' : '';
            const maxPrice = document.getElementById('filterMaxPrice')?.value ? parseFloat(document.getElementById('filterMaxPrice').value) : '';
            const maxDuration = document.getElementById('filterMaxDuration')?.value ? parseInt(document.getElementById('filterMaxDuration').value) : '';
            const minRating = document.getElementById('filterMinRating')?.value ? parseInt(document.getElementById('filterMinRating').value) : '';
            
            await displaySuggestions(lastDeparture, lastArrival, economique, maxPrice, maxDuration, minRating);
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
                <a href="/vue-covoiturage-detail?id=${trajet.covoiturage_id}" class="btn-detail">Détail</a>
            </div>
        `).join('');

        resultsContainer.innerHTML = trajetCards;
        resultsContainer.classList.add('show');
        
        // Afficher les filtres SEULEMENT la première fois
        if (!filtersCreated) {
            displayFilters();
            filtersCreated = true;
        }
        
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

    // Afficher les filtres avancés
    function displayFilters() {
        const filtersContainer = document.getElementById('filtersContainer');
        
        const filtersHTML = `
            <div class="filters-wrapper">
                <div class="filters-content">
                    <h3 class="filters-title">Affiner la recherche</h3>
                    <div class="filters-grid">
                        <!-- Filtre écologique -->
                        <div class="filter-item filter-eco-item">
                            <label for="filterEcologique" class="filter-label filter-eco-label">
                                <input type="checkbox" id="filterEcologique" class="filter-input filter-checkbox">
                                <span class="filter-eco-text">🌱 Écologique</span>
                            </label>
                        </div>

                        <!-- Filtre prix max -->
                        <div class="filter-item filter-price-item">
                            <label for="filterMaxPrice" class="filter-label filter-price-label">Prix max (€)</label>
                            <input type="number" id="filterMaxPrice" class="filter-input filter-input-number filter-price-input" placeholder="150" min="0" step="5">
                        </div>

                        <!-- Filtre durée max -->
                        <div class="filter-item filter-duration-item">
                            <label for="filterMaxDuration" class="filter-label filter-duration-label">Durée max (minutes)</label>
                            <input type="number" id="filterMaxDuration" class="filter-input filter-input-number filter-duration-input" placeholder="240" min="0" step="15">
                        </div>

                        <!-- Filtre rating min -->
                        <div class="filter-item filter-rating-item">
                            <label for="filterMinRating" class="filter-label filter-rating-label">Note minimale</label>
                            <select id="filterMinRating" class="filter-input filter-select filter-rating-select">
                                <option value="">Toutes</option>
                                <option value="1">1+ ⭐</option>
                                <option value="2">2+ ⭐⭐</option>
                                <option value="3">3+ ⭐⭐⭐</option>
                                <option value="4">4+ ⭐⭐⭐⭐</option>
                                <option value="5">5 ⭐⭐⭐⭐⭐</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        `;

        filtersContainer.innerHTML = filtersHTML;
        filtersContainer.classList.add('show');

        // Ajouter les listeners sur les filtres (étape 5)
        attachFilterListeners();
    }

    // Listeners pour les changements de filtres (appelée par displayFilters)
    function attachFilterListeners() {
        const ecoFilter = document.getElementById('filterEcologique');
        const maxPriceFilter = document.getElementById('filterMaxPrice');
        const maxDurationFilter = document.getElementById('filterMaxDuration');
        const minRatingFilter = document.getElementById('filterMinRating');

        const applyFilters = async () => {
            // Récupérer les valeurs actuelles des filtres
            const economique = ecoFilter.checked ? '1' : '';
            const maxPrice = maxPriceFilter.value ? parseFloat(maxPriceFilter.value) : '';
            const maxDuration = maxDurationFilter.value ? parseInt(maxDurationFilter.value) : '';
            const minRating = minRatingFilter.value ? parseInt(minRatingFilter.value) : '';

            // Construire les paramètres de la requête (GARDER les noms originaux pour le backend)
            let url = `/api/trajets?departure=${encodeURIComponent(lastDeparture)}&arrival=${encodeURIComponent(lastArrival)}&date=${document.getElementById('dateInput').value}`;
            if (economique) url += `&economique=${economique}`;
            if (maxPrice) url += `&maxPrice=${maxPrice}`;
            if (maxDuration) url += `&maxDuration=${maxDuration}`;
            if (minRating) url += `&minRating=${minRating}`;

            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    displayResults(result.data.items);
                } else {
                    alert('Erreur : ' + result.error.message);
                }
            } catch (error) {
                alert('Erreur de connexion : ' + error.message);
            }
        };

        // Ajouter les listeners
        ecoFilter.addEventListener('change', applyFilters);
        maxPriceFilter.addEventListener('change', applyFilters);
        maxDurationFilter.addEventListener('change', applyFilters);
        minRatingFilter.addEventListener('change', applyFilters);
    }

});