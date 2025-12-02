/**
 * Profile Page JavaScript
 * Gère l'espace utilisateur et la mise à jour de profil (US8)
 */

document.addEventListener('DOMContentLoaded', () => {
  console.log('Profile page loaded');
  
    // Charger le profil utilisateur
    loadProfile();
    
    // Ajouter les event listeners
    setupEventListeners();
});

/**
 * Configure les event listeners des boutons
 */
function setupEventListeners() {
    // ÉTAPE 1 : Récupérer les éléments par leur ID
    const addVehicleBtn = document.getElementById('addVehicleBtn');
    const updateProfileBtn = document.getElementById('updateProfileBtn');
    const goToHomeBtn = document.getElementById('goToHomeBtn');
    const logoutBtn = document.getElementById('logoutBtn');

    // ÉTAPE 2 : Ajouter les event listeners sur les boutons
    // Quand on clique sur addVehicleBtn, appeller addVehicleForm()
    if (addVehicleBtn) {
        addVehicleBtn.addEventListener('click', addVehicleForm);
    }

    // Quand on clique sur updateProfileBtn, appeller updateProfile()
    if (updateProfileBtn) {
        updateProfileBtn.addEventListener('click', updateProfile);
    }

    // Quand on clique sur goToHomeBtn, appeller goToHome()
    if (goToHomeBtn) {
        goToHomeBtn.addEventListener('click', goToHome);
    }

    // Quand on clique sur logoutBtn, appeller logout()
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logout);
    }

    // ÉTAPE 3 : Ajouter les event listeners sur les radio buttons de rôle
    const roleRadios = document.querySelectorAll('input[name="role"]');
    roleRadios.forEach(radio => {
        // Quand un radio button change, appeller onRoleChange() avec la valeur
        radio.addEventListener('change', (e) => {
            onRoleChange(e.target.value);
        });
    });
}
function loadProfile() {
    // ÉTAPE 1 : Récupérer l'utilisateur du SessionManager
    const user = SessionManager.getUser();

    // ÉTAPE 2 : Vérifier s'il est connecté
    if (!user) {
        window.location.href = '/login';
        return;
    }

    // ÉTAPE 3 : Remplir l'en-tête du profil
    document.getElementById('userName').textContent = user.pseudo;
    document.getElementById('userStatus').textContent = 'Connecté en tant que ' + user.pseudo;

    // ÉTAPE 4 : Remplir la table d'informations
    document.getElementById('infoPseudo').textContent = user.pseudo;
    document.getElementById('infoEmail').textContent = user.email;
    document.getElementById('infoCredit').textContent = user.credit + ' crédits';
    document.getElementById('infoRole').textContent = user.role || 'Non défini';

    // ÉTAPE 5 : Présélectionner le rôle actuel
    const currentRole = user.role || 'passager';
    const roleRadio = document.querySelector(`input[name="role"][value="${currentRole}"]`);
    if (roleRadio) {
        roleRadio.checked = true;
    }

    // ÉTAPE 6 : Afficher/cacher les sections selon le rôle
    onRoleChange(currentRole);
}

/**
 * Met à jour le profil utilisateur
 */
function updateProfile() {
    // ÉTAPE 1 : Récupérer le rôle sélectionné
    const selectedRole = document.querySelector('input[name="role"]:checked');
    if (!selectedRole) {
        showMessage('Veuillez sélectionner un rôle', 'error');
        return;
    }

    const role = selectedRole.value;

    // ÉTAPE 2 : Construire l'objet de données à envoyer
    const data = {
        role: role,
        vehicules: [],
        preferences: {}
    };

    // ÉTAPE 3 : Si chauffeur, récupérer les véhicules
    if (role !== 'passager') {
        const vehicleForms = document.querySelectorAll('.vehicle-form');

        // Vérifier qu'il y a au moins un véhicule
        if (vehicleForms.length === 0) {
            showMessage('Vous devez ajouter au moins un véhicule en tant que chauffeur', 'error');
            return;
        }

        // Parcourir tous les formulaires de véhicules
        vehicleForms.forEach((form, index) => {
            // Récupérer les valeurs des inputs
            const marque = form.querySelector(`input[name="vehicles[${index}][marque]"]`).value;
            const modele = form.querySelector(`input[name="vehicles[${index}][modele]"]`).value;
            const couleur = form.querySelector(`input[name="vehicles[${index}][couleur]"]`).value;
            const immatriculation = form.querySelector(`input[name="vehicles[${index}][immatriculation]"]`).value;
            const date_premiere_immatriculation = form.querySelector(`input[name="vehicles[${index}][date_premiere_immatriculation]"]`).value;
            const nb_places = form.querySelector(`input[name="vehicles[${index}][nb_places]"]`).value;
            const energie = form.querySelector(`select[name="vehicles[${index}][energie]"]`).value;

            // Ajouter le véhicule à l'array
            data.vehicules.push({
                marque_id: 1, // À adapter selon votre système
                modele: modele,
                couleur: couleur,
                immatriculation: immatriculation,
                date_premiere_immatriculation: date_premiere_immatriculation,
                nb_places: parseInt(nb_places),
                energie: energie,
                est_ecologique: energie === 'electrique' ? 1 : 0
            });
        });

        // ÉTAPE 4 : Récupérer les préférences
        data.preferences = {
            fumeur: document.querySelector('select[name="preference_fumeur"]').value,
            animaux: document.querySelector('select[name="preference_animaux"]').value,
            autres_preferences: document.querySelector('textarea[name="autres_preferences"]').value
        };
    }

    // ÉTAPE 5 : Envoyer les données à l'API
    console.log('Données à envoyer:', data);

    fetch('/api/user/profile', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        // ÉTAPE 6 : Gérer la réponse
        if (result.success) {
            showMessage('Profil mis à jour avec succès !', 'success');
            // Recharger la page après 1.5 secondes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showMessage(result.error?.message || 'Erreur lors de la mise à jour', 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showMessage('Une erreur est survenue : ' + error.message, 'error');
    });
}

/**
 * Affiche un message à l'utilisateur
 */
function showMessage(text, type) {
    const messageContainer = document.getElementById('messageContainer');
    const message = document.createElement('div');
    message.className = `message ${type} show`;
    message.textContent = text;

    messageContainer.innerHTML = '';
    messageContainer.appendChild(message);

    // Masquer le message après 5 secondes
    setTimeout(() => {
        message.classList.remove('show');
    }, 5000);
}

/**
 * Retour à l'accueil
 */
function goToHome() {
    window.location.href = '/';
}

/**
 * Déconnexion
 */
function logout() {
    SessionManager.logout();
    window.location.href = '/login';
}

/**
 * Gère le changement de rôle
 * Affiche/cache les sections véhicules et préférences
 */
function onRoleChange(role) {
    // ÉTAPE 1 : Récupérer les sections à afficher/cacher
    const vehiclesSection = document.getElementById('vehiclesSection');
    const preferencesSection = document.getElementById('preferencesSection');

    // ÉTAPE 2 : Vérifier le rôle et afficher/cacher les sections
    if (role === 'passager') {
        // Les passagers n'ont pas besoin de véhicules ni de préférences
        vehiclesSection.style.display = 'none';
        preferencesSection.style.display = 'none';
    } else if (role === 'chauffeur' || role === 'chauffeur_passager') {
        // Les chauffeurs doivent déclarer des véhicules et préférences
        vehiclesSection.style.display = 'block';
        preferencesSection.style.display = 'block';
    }
}

/**
 * Ajoute un nouveau formulaire de véhicule
 */
function addVehicleForm() {
    // ÉTAPE 1 : Récupérer le conteneur où ajouter le formulaire
    const vehiclesContainer = document.getElementById('vehiclesContainer');

    // ÉTAPE 2 : Compter combien de véhicules existent déjà
    // On utilise la longueur pour créer un index unique
    const vehicleIndex = vehiclesContainer.children.length;

    // ÉTAPE 3 : Créer un nouveau div avec la classe 'vehicle-form'
    const vehicleForm = document.createElement('div');
    vehicleForm.className = 'vehicle-form';

    // ÉTAPE 4 : Remplir le div avec le HTML du formulaire
    vehicleForm.innerHTML = `
        <h4>Véhicule ${vehicleIndex + 1}</h4>
        
        <div class="form-group">
            <label>Marque</label>
            <input type="text" name="vehicles[${vehicleIndex}][marque]" placeholder="Ex: Peugeot" required>
        </div>
        
        <div class="form-group">
            <label>Modèle</label>
            <input type="text" name="vehicles[${vehicleIndex}][modele]" placeholder="Ex: 308" required>
        </div>
        
        <div class="form-group">
            <label>Couleur</label>
            <input type="text" name="vehicles[${vehicleIndex}][couleur]" placeholder="Ex: Bleu" required>
        </div>
        
        <div class="form-group">
            <label>Plaque d'immatriculation</label>
            <input type="text" name="vehicles[${vehicleIndex}][immatriculation]" placeholder="Ex: AB-123-CD" required>
        </div>
        
        <div class="form-group">
            <label>Date de première immatriculation</label>
            <input type="date" name="vehicles[${vehicleIndex}][date_premiere_immatriculation]" required>
        </div>
        
        <div class="form-group">
            <label>Nombre de places disponibles</label>
            <input type="number" name="vehicles[${vehicleIndex}][nb_places]" min="1" max="9" placeholder="Ex: 4" required>
        </div>
        
        <div class="form-group">
            <label>Type d'énergie</label>
            <select name="vehicles[${vehicleIndex}][energie]" required>
                <option value="">-- Choisir --</option>
                <option value="essence">Essence</option>
                <option value="diesel">Diesel</option>
                <option value="electrique">Électrique</option>
                <option value="hybride">Hybride</option>
            </select>
        </div>
        
        <button type="button" class="remove-vehicle-btn" onclick="removeVehicleForm(${vehicleIndex})">Supprimer ce véhicule</button>
    `;

    // ÉTAPE 5 : Ajouter le formulaire au conteneur
    vehiclesContainer.appendChild(vehicleForm);
}

/**
 * Retire un formulaire de véhicule
 */
function removeVehicleForm(index) {
    // ÉTAPE 1 : Récupérer le conteneur des véhicules
    const vehiclesContainer = document.getElementById('vehiclesContainer');

    // ÉTAPE 2 : Trouver tous les formulaires de véhicules
    const vehicleForms = vehiclesContainer.querySelectorAll('.vehicle-form');

    // ÉTAPE 3 : Vérifier que l'index existe
    if (vehicleForms[index]) {
        // ÉTAPE 4 : Supprimer le formulaire à cet index
        vehicleForms[index].remove();
    }
}