document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('searchForm');
    const resultsContainer = document.createElement('div');
    resultsContainer.id = 'resultsContainer';
    resultsContainer.style.display = 'none';
    document.querySelector('main').appendChild(resultsContainer);

    searchForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // 1. Récupérer les valeurs du formulaire
        const departure = document.getElementById('searchInput').value.trim();
        const arrival = document.getElementById('destinationInput').value.trim();
        const date = document.getElementById('dateInput').value.trim();

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

    function displayResults(trajets) {
        const resultsContainer = document.getElementById('resultsContainer');
        
        if (trajets.length === 0) {
            resultsContainer.innerHTML = '<p>Aucun trajet trouvé</p>';
            resultsContainer.style.display = 'block';
            return;
        }

        // Générer le HTML pour chaque trajet
        const trajetCards = trajets.map(trajet => `
            <div class="trajet-card">
                <div class="trajet-header">
                    <h3>${trajet.lieu_depart} → ${trajet.lieu_arrivee}</h3>
                    <p class="price">${trajet.prix_personne}€ par personne</p>
                </div>
                <div class="trajet-details">
                    <p><strong>Date :</strong> ${trajet.date_depart}</p>
                    <p><strong>Départ :</strong> ${trajet.heure_depart}</p>
                    <p><strong>Arrivée :</strong> ${trajet.heure_arrivee}</p>
                    <p><strong>Places :</strong> ${trajet.nb_places} disponibles</p>
                    <p><strong>Conducteur :</strong> ${trajet.conducteur_pseudo}</p>
                    ${trajet.est_ecologique ? '<p class="eco">🌱 Véhicule écologique</p>' : ''}
                </div>
                <button class="btn-reserve">Réserver</button>
            </div>
        `).join('');

        resultsContainer.innerHTML = trajetCards;
        resultsContainer.style.display = 'block';
    }
});