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
    const manageVehiclesBtn = document.getElementById('manageVehiclesBtn');
    const updateProfileBtn = document.getElementById('updateProfileBtn');
    const goToHomeBtn = document.getElementById('goToHomeBtn');
    const logoutBtn = document.getElementById('logoutBtn');
    const vehiclesToggle = document.getElementById('vehiclesToggle');
    const editProfileToggle = document.getElementById('editProfileToggle');

    // ÉTAPE 2 : Ajouter les event listeners sur les boutons
    // Quand on clique sur addVehicleBtn, appeller addVehicleForm()
    if (addVehicleBtn) {
        addVehicleBtn.addEventListener('click', addVehicleForm);
    }

    if (manageVehiclesBtn) {
        manageVehiclesBtn.addEventListener('click', toggleVehiclesManager);
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

    // ÉTAPE 3 : Event listeners pour les dropdowns/accordions
    if (vehiclesToggle) {
        vehiclesToggle.addEventListener('click', () => {
            toggleDropdown('vehiclesToggle', 'vehiclesDropdown');
        });
    }

    if (editProfileToggle) {
        editProfileToggle.addEventListener('click', () => {
            toggleAccordion('editProfileToggle', 'editProfileSection');
        });
    }

    // ÉTAPE 4 : Ajouter les event listeners sur les radio buttons de rôle
    const roleRadios = document.querySelectorAll('input[name="role"]');
    roleRadios.forEach(radio => {
        // Quand un radio button change, appeller onRoleChange() avec la valeur
        radio.addEventListener('change', (e) => {
            onRoleChange(e.target.value);
        });
    });
}

/**
 * Toggle dropdown visibility
 */
function toggleDropdown(toggleId, contentId) {
    const toggle = document.getElementById(toggleId);
    const content = document.getElementById(contentId);
    
    if (toggle && content) {
        toggle.classList.toggle('open');
        if (content.style.display === 'none') {
            content.style.display = 'block';
        } else {
            content.style.display = 'none';
        }
    }
}

/**
 * Toggle accordion visibility
 */
function toggleAccordion(toggleId, contentId) {
    const toggle = document.getElementById(toggleId);
    const content = document.getElementById(contentId);
    
    if (toggle && content) {
        toggle.classList.toggle('open');
        if (content.style.display === 'none') {
            content.style.display = 'block';
        } else {
            content.style.display = 'none';
        }
    }
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

    // ÉTAPE 7 : Charger les véhicules existants
    loadExistingVehicles();

    // ÉTAPE 8 : Charger les préférences existantes
    loadExistingPreferences();
}

/**
 * Charge les véhicules existants de l'utilisateur depuis l'API
 */
function loadExistingVehicles() {
    fetch('/api/user/vehicles')
        .then(response => response.json())
        .then(result => {
            console.log('Véhicules chargés:', result.data);
            const vehiclesDisplayContainer = document.getElementById('vehiclesDisplayContainer');
            // Stocker le nombre de véhicules existants pour la validation ultérieure
            window._existingVehiclesCount = (result.success && Array.isArray(result.data)) ? result.data.length : 0;
            
            if (result.success && result.data.length > 0) {
                // Créer la liste de véhicules pour le dropdown
                const vehiclesList = document.createElement('div');
                vehiclesList.className = 'vehicles-list';

                result.data.forEach((vehicle) => {
                    const vehicleCard = document.createElement('div');
                    vehicleCard.className = 'vehicle-card';
                    vehicleCard.innerHTML = `
                        <h4>🚗 ${vehicle.modele}</h4>
                        <p><strong>Marque:</strong> ${vehicle.marque_id}</p>
                        <p><strong>Couleur:</strong> ${vehicle.couleur}</p>
                        <p><strong>Immatriculation:</strong> ${vehicle.immatriculation}</p>
                        <p><strong>Places:</strong> ${vehicle.nb_places}</p>
                        <p><strong>Énergie:</strong> ${vehicle.energie}</p>
                        <p><strong>Date immatriculation:</strong> ${vehicle.date_premiere_immatriculation}</p>
                    `;
                    vehiclesList.appendChild(vehicleCard);
                });

                vehiclesDisplayContainer.innerHTML = '';
                vehiclesDisplayContainer.appendChild(vehiclesList);
                // Si le manager est ouvert, re-render la liste avec actions
                const manager = document.getElementById('vehiclesManager');
                if (manager && manager.style.display === 'block') {
                    renderVehiclesManagerList(result.data);
                }
            } else {
                vehiclesDisplayContainer.innerHTML = '<p style="text-align: center; color: #999;">Aucun véhicule enregistré</p>';
                const managerList = document.getElementById('vehiclesManagerList');
                if (managerList) managerList.innerHTML = '<p style="text-align: center; color: #999;">Aucun véhicule enregistré</p>';
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des véhicules:', error);
            document.getElementById('vehiclesDisplayContainer').innerHTML = '<p style="text-align: center; color: #999;">Erreur lors du chargement</p>';
        });
}

/**
 * Ouvre/ferme le gestionnaire de véhicules et charge la liste
 */
function toggleVehiclesManager() {
    const manager = document.getElementById('vehiclesManager');
    if (!manager) return;
    const isHidden = manager.style.display === 'none' || manager.style.display === '';
    manager.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        loadVehiclesManager();
    }
}

/**
 * Charge et affiche les véhicules dans le gestionnaire (avec suppression)
 */
function loadVehiclesManager() {
    fetch('/api/user/vehicles')
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                renderVehiclesManagerList(result.data || []);
            }
        })
        .catch(err => console.error('Erreur chargement véhicules manager:', err));
}

function renderVehiclesManagerList(vehicles) {
    const list = document.getElementById('vehiclesManagerList');
    if (!list) return;
    if (!vehicles || vehicles.length === 0) {
        list.innerHTML = '<p style="text-align: center; color: #999;">Aucun véhicule enregistré</p>';
        return;
    }
    list.innerHTML = '';
    vehicles.forEach(v => {
        const card = document.createElement('div');
        card.className = 'vehicle-card';
        card.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
              <div>
                <h4 style="margin:0;">🚗 ${v.modele}</h4>
                <p style="margin:4px 0 0 0;"><strong>Immatriculation:</strong> ${v.immatriculation} · <strong>Places:</strong> ${v.nb_places} · <strong>Énergie:</strong> ${v.energie}</p>
              </div>
              <button type="button" class="btn btn-danger" data-vid="${v.id}">Supprimer</button>
            </div>
        `;
        const btn = card.querySelector('button');
        btn.addEventListener('click', () => deleteVehicle(v.id));
        list.appendChild(card);
    });
}

function deleteVehicle(vehicleId) {
    if (!confirm('Supprimer ce véhicule ?')) return;
    fetch(`/api/user/vehicles?id=${encodeURIComponent(vehicleId)}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                showMessage('Véhicule supprimé', 'success');
                // Recharger les 2 vues
                loadExistingVehicles();
                loadVehiclesManager();
                // Mettre à jour le compteur local pour la validation chauffeur
                window._existingVehiclesCount = Math.max(0, (window._existingVehiclesCount || 1) - 1);
            } else {
                showMessage(result.error?.message || 'Suppression impossible', 'error');
            }
        })
        .catch(err => {
            console.error('Erreur suppression véhicule:', err);
            showMessage('Erreur réseau lors de la suppression', 'error');
        });
}

/**
 * Charge les préférences existantes de l'utilisateur
 */
function loadExistingPreferences() {
    fetch('/api/user/preferences')
        .then(r => r.json())
        .then(result => {
            if (!result.success) return;
            const prefs = result.data || {};
            const selFumeur = document.querySelector('select[name="preference_fumeur"]');
            const selAnimaux = document.querySelector('select[name="preference_animaux"]');
            const txtAutres = document.querySelector('textarea[name="autres_preferences"]');
            if (selFumeur && prefs.fumeur) selFumeur.value = prefs.fumeur;
            if (selAnimaux && prefs.animaux) selAnimaux.value = prefs.animaux;
            if (txtAutres && typeof prefs.autres_preferences === 'string') txtAutres.value = prefs.autres_preferences;
        })
        .catch(err => console.error('Erreur préférences:', err));
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

        // Vérifier qu'il y a au moins un véhicule (existant OU à ajouter)
        const hasExisting = (window._existingVehiclesCount || 0) > 0;
        if (!hasExisting && vehicleForms.length === 0) {
            showMessage('Vous devez avoir au moins un véhicule pour être chauffeur', 'error');
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
        console.log('Réponse API complète:', result);
        if (result.success) {
            console.log('Données reçues de l\'API:', result.data);
            // Mettre à jour le SessionManager avec les nouvelles données
            const updatedUser = {
                utilisateur_id: result.data.utilisateur_id,
                pseudo: result.data.pseudo,
                email: result.data.email,
                credit: result.data.credit,
                role: result.data.role
            };
            console.log('Objet à sauvegarder dans SessionManager:', updatedUser);
            SessionManager.setUser(updatedUser);

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
    vehicleForm.id = `vehicle-form-${vehicleIndex}`;

    // ÉTAPE 4 : Remplir le div avec le HTML du formulaire
    vehicleForm.innerHTML = `
        <h4>Nouveau véhicule</h4>
        
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
        
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-primary" onclick="saveVehicleDirectly(${vehicleIndex})">✓ Ajouter ce véhicule</button>
            <button type="button" class="btn btn-secondary" onclick="removeVehicleForm(${vehicleIndex})">✕ Annuler</button>
        </div>
    `;

    // ÉTAPE 5 : Ajouter le formulaire au conteneur
    vehiclesContainer.appendChild(vehicleForm);
}

/**
 * Sauvegarde un véhicule directement via l'API
 */
function saveVehicleDirectly(formIndex) {
    const vehicleForm = document.getElementById(`vehicle-form-${formIndex}`);
    
    if (!vehicleForm) return;

    // Récupérer les valeurs du formulaire
    const marque = vehicleForm.querySelector(`input[name="vehicles[${formIndex}][marque]"]`).value;
    const modele = vehicleForm.querySelector(`input[name="vehicles[${formIndex}][modele]"]`).value;
    const couleur = vehicleForm.querySelector(`input[name="vehicles[${formIndex}][couleur]"]`).value;
    const immatriculation = vehicleForm.querySelector(`input[name="vehicles[${formIndex}][immatriculation]"]`).value;
    const date_premiere_immatriculation = vehicleForm.querySelector(`input[name="vehicles[${formIndex}][date_premiere_immatriculation]"]`).value;
    const nb_places = vehicleForm.querySelector(`input[name="vehicles[${formIndex}][nb_places]"]`).value;
    const energie = vehicleForm.querySelector(`select[name="vehicles[${formIndex}][energie]"]`).value;

    // Valider les champs
    if (!modele || !couleur || !immatriculation || !date_premiere_immatriculation || !nb_places || !energie) {
        showMessage('Veuillez remplir tous les champs du véhicule', 'error');
        return;
    }

    // Construire l'objet à envoyer
    const data = {
        marque_id: 1,
        modele: modele,
        couleur: couleur,
        immatriculation: immatriculation,
        date_premiere_immatriculation: date_premiere_immatriculation,
        nb_places: parseInt(nb_places),
        energie: energie
    };

    // Envoyer à l'API
    fetch('/api/user/vehicles', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showMessage(`Véhicule "${result.data.modele}" ajouté avec succès !`, 'success');
            // Supprimer le formulaire du DOM
            vehicleForm.remove();
            // Recharger les véhicules affichés
            loadExistingVehicles();
            loadVehiclesManager();
        } else {
            showMessage(result.error?.message || 'Erreur lors de l\'ajout du véhicule', 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showMessage('Une erreur est survenue : ' + error.message, 'error');
    });
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