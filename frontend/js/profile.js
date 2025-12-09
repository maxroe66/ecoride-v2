/**
 * Profile Page JavaScript
 * Gère l'espace utilisateur et la mise à jour de profil (US8)
 */

document.addEventListener('DOMContentLoaded', () => {
  console.log('Profile page loaded');
  
  // Ajouter les event listeners
  setupEventListeners();

    // Charger les informations du profil
    try {
        loadProfile();
    } catch (e) {
        console.error('Erreur chargement profil:', e);
    }
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
    const createTripToggle = document.getElementById('createTripToggle');
    const myTripsToggle = document.getElementById('myTripsToggle');
    const createTripBtn = document.getElementById('createTripBtn');
    const openVehiclesManagerFromTrip = document.getElementById('openVehiclesManagerFromTrip');

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

    if (createTripToggle) {
        createTripToggle.addEventListener('click', () => {
            toggleAccordion('createTripToggle', 'createTripSection');
        });
    }

    if (myTripsToggle) {
        myTripsToggle.addEventListener('click', () => {
            const section = document.getElementById('myTripsSection');
            const wasClosed = section && (section.style.display === 'none' || section.style.display === '');
            toggleAccordion('myTripsToggle', 'myTripsSection');
            if (wasClosed) setTimeout(loadUpcomingTrips, 50);
        });
    }

    if (createTripBtn) {
        createTripBtn.addEventListener('click', createTrip);
    }

    if (openVehiclesManagerFromTrip) {
        openVehiclesManagerFromTrip.addEventListener('click', () => {
            // Ouvre la gestion des véhicules et scroll vers la section
            const manageBtn = document.getElementById('manageVehiclesBtn');
            if (manageBtn) {
                const manager = document.getElementById('vehiclesManager');
                const wasHidden = !manager || manager.style.display === 'none' || manager.style.display === '';
                manageBtn.click();
                if (wasHidden) {
                    setTimeout(() => manager?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
                } else {
                    manager?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    }

    // Aperçu du prix net (prix - 2)
    const priceInput = document.getElementById('createTripPrice');
    if (priceInput) {
        const updateNet = () => {
            const v = parseFloat(priceInput.value || '0');
            const net = Math.max(0, (isNaN(v) ? 0 : v) - 2);
            const lbl = document.getElementById('createTripNetPreview');
            if (lbl) lbl.textContent = `Vous recevrez ${net.toFixed(2)} crédit(s) nets (prix - 2)`;
        };
        priceInput.addEventListener('input', updateNet);
        updateNet();
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
                        <p><strong>Marque:</strong> ${vehicle.marque || vehicle.marque_id || ''}</p>
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
                // Remplir le select de création de trajet
                populateTripVehicleSelect(result.data);
                // Si le manager est ouvert, re-render la liste avec actions
                const manager = document.getElementById('vehiclesManager');
                if (manager && manager.style.display === 'block') {
                    renderVehiclesManagerList(result.data);
                }
            } else {
                vehiclesDisplayContainer.innerHTML = '<p style="text-align: center; color: #999;">Aucun véhicule enregistré</p>';
                const managerList = document.getElementById('vehiclesManagerList');
                if (managerList) managerList.innerHTML = '<p style="text-align: center; color: #999;">Aucun véhicule enregistré</p>';
                // Vider le select de création de trajet
                populateTripVehicleSelect([]);
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des véhicules:', error);
            document.getElementById('vehiclesDisplayContainer').innerHTML = '<p style="text-align: center; color: #999;">Erreur lors du chargement</p>';
        });
}

/**
 * Remplit le select des véhicules dans la section création de trajet
 */
function populateTripVehicleSelect(vehicles) {
    const select = document.getElementById('createTripVehicle');
    if (!select) return;
    select.innerHTML = '<option value="">-- Sélectionner un véhicule --</option>';
    if (Array.isArray(vehicles)) {
        vehicles.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.id;
            opt.textContent = `${v.modele} • ${v.immatriculation} • ${v.nb_places} places`;
            select.appendChild(opt);
        });
    }
}

/**
 * Charge et affiche les prochains trajets du chauffeur
 */
function loadUpcomingTrips() {
        const container = document.getElementById('createTripUpcomingList');
        if (!container) return;
        container.innerHTML = '<p style="text-align:center;color:#999;">Chargement...</p>';

        fetch('/api/user/trajets')
                .then(r => r.json())
                .then(result => {
                        if (!result.success) {
                                container.innerHTML = '<p style="text-align:center;color:#999;">Impossible de charger les trajets</p>';
                                return;
                        }
                        const trips = Array.isArray(result.data) ? result.data : [];
                        if (trips.length === 0) {
                                container.innerHTML = '<p style="text-align:center;color:#999;">Aucun trajet à venir</p>';
                                return;
                        }
                        container.innerHTML = '';
                        trips
                            .sort((a,b) => (a.date_depart + ' ' + (a.heure_depart||'')).localeCompare(b.date_depart + ' ' + (b.heure_depart||'')))
                            .forEach(t => {
                                const net = Math.max(0, (parseFloat(t.prix_personne || 0) - 2));
                                const card = document.createElement('div');
                                card.className = 'vehicle-card';
                                card.innerHTML = `
                                    <div class="trip-row">
                                        <div>
                                            <h4 class="trip-title">${escapeHtml(t.lieu_depart)} ➝ ${escapeHtml(t.lieu_arrivee)}</h4>
                                            <p class="trip-meta">
                                                ${escapeHtml(t.date_depart)} • ${escapeHtml(t.heure_depart || '')} • ${t.nb_places} places
                                            </p>
                                        </div>
                                        <div class="trip-price">
                                            <div><strong>${Number(t.prix_personne).toFixed(2)}</strong> cr./pers</div>
                                            <div class="net">net: ${net.toFixed(2)} cr.</div>
                                        </div>
                                    </div>
                                `;
                                container.appendChild(card);
                            });
                })
                .catch(err => {
                        console.error('Erreur chargement trajets chauffeur:', err);
                        container.innerHTML = '<p style="text-align:center;color:#999;">Erreur réseau</p>';
                });
}

/**
 * Création de trajet (US9)
 */
async function createTrip() {
    const user = SessionManager.getUser();
    if (!user) {
        window.location.href = '/login';
        return;
    }

    // Ne permettre qu'aux rôles chauffeur / chauffeur_passager
    const role = user.role || 'passager';
    if (!(role === 'chauffeur' || role === 'chauffeur_passager')) {
        showMessage('Seuls les chauffeurs peuvent créer des trajets.', 'error');
        return;
    }

    const departure = (document.getElementById('createTripDeparture')?.value || '').trim();
    const arrival = (document.getElementById('createTripArrival')?.value || '').trim();
    const date = document.getElementById('createTripDate')?.value || '';
    const time = document.getElementById('createTripTime')?.value || '';
    const arrivalTime = document.getElementById('createTripArrivalTime')?.value || '';
    const seats = parseInt(document.getElementById('createTripSeats')?.value || '0', 10);
    const price = parseFloat(document.getElementById('createTripPrice')?.value || '0');
    const vehicleId = parseInt(document.getElementById('createTripVehicle')?.value || '0', 10);

    // Validation côté client (miroir du backend)
    if (!departure || !arrival) {
        showMessage('Adresse de départ et d\'arrivée requises', 'error');
        return;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        showMessage('Date invalide (YYYY-MM-DD)', 'error');
        return;
    }
    if (!/^\d{2}:\d{2}$/.test(time)) {
        showMessage('Heure de départ invalide (HH:MM)', 'error');
        return;
    }
    if (!/^\d{2}:\d{2}$/.test(arrivalTime)) {
        showMessage('Heure d\'arrivée invalide (HH:MM)', 'error');
        return;
    }
    if (!(seats >= 1 && seats <= 8)) {
        showMessage('Nombre de places entre 1 et 8', 'error');
        return;
    }
    if (!(price >= 2)) {
        showMessage('Prix par personne doit être ≥ 2 crédits', 'error');
        return;
    }
    if (!(vehicleId > 0)) {
        showMessage('Veuillez sélectionner un véhicule', 'error');
        return;
    }

    const payload = {
        lieu_depart: departure,
        lieu_arrivee: arrival,
        date_depart: date,
        heure_depart: time,
        heure_arrivee: arrivalTime,
        nb_places: seats,
        prix_personne: price,
        voiture_id: vehicleId
    };

    try {
        const csrf = await SessionManager.csrfHeaders();
        const r = await fetch('/api/trajets', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrf },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const result = await r.json();
        if (result.success) {
            showMessage('Trajet créé avec succès ! ' + (result.message || ''), 'success');
            document.getElementById('createTripDeparture').value = '';
            document.getElementById('createTripArrival').value = '';
            document.getElementById('createTripDate').value = '';
            document.getElementById('createTripTime').value = '';
            document.getElementById('createTripArrivalTime').value = '';
            document.getElementById('createTripSeats').value = '';
            document.getElementById('createTripPrice').value = '';
            document.getElementById('createTripVehicle').value = '';
            if (document.getElementById('createTripUpcomingList')) {
                loadUpcomingTrips();
            }
        } else {
            showMessage(result.error?.message || 'Création de trajet impossible', 'error');
        }
    } catch (err) {
        console.error('Erreur création trajet:', err);
        showMessage('Erreur réseau lors de la création du trajet', 'error');
    }
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

async function deleteVehicle(vehicleId) {
    if (!confirm('Supprimer ce véhicule ?')) return;
    try {
        const csrf = await SessionManager.csrfHeaders();
        const r = await fetch(`/api/user/vehicles?id=${encodeURIComponent(vehicleId)}`, { method: 'DELETE', headers: { ...csrf } });
        const result = await r.json();
        if (result.success) {
            showMessage('Véhicule supprimé', 'success');
            loadExistingVehicles();
            loadVehiclesManager();
            window._existingVehiclesCount = Math.max(0, (window._existingVehiclesCount || 1) - 1);
        } else {
            showMessage(result.error?.message || 'Suppression impossible', 'error');
        }
    } catch (err) {
        console.error('Erreur suppression véhicule:', err);
        showMessage('Erreur réseau lors de la suppression', 'error');
    }
}

/**
 * Charge les préférences existantes de l'utilisateur
 */
async function loadExistingPreferences() {
    const r = await fetch('/api/user/preferences');
    const result = await r.json();
            if (!result.success) return;
            const prefs = result.data || {};
            const selFumeur = document.querySelector('select[name="preference_fumeur"]');
            const selAnimaux = document.querySelector('select[name="preference_animaux"]');
            const txtAutres = document.querySelector('textarea[name="autres_preferences"]');
            if (selFumeur && prefs.fumeur) selFumeur.value = prefs.fumeur;
            if (selAnimaux && prefs.animaux) selAnimaux.value = prefs.animaux;
            if (txtAutres && typeof prefs.autres_preferences === 'string') txtAutres.value = prefs.autres_preferences;
            
            // Afficher préférences dans la table d'infos si chauffeur
            const user = SessionManager.getUser();
            if (user && (user.role === 'chauffeur' || user.role === 'chauffeur_passager')) {
                const prefRow = document.getElementById('infoPrefRow');
                if (prefRow) {
                    prefRow.style.display = 'table-row';
                    // Remplir les colonnes
                    const fumeur = prefs.fumeur || '---';
                    const animaux = prefs.animaux || '---';
                    const autres = prefs.autres_preferences || '---';
                    
                    document.getElementById('prefFumeur').textContent = fumeur;
                    document.getElementById('prefAnimaux').textContent = animaux;
                    document.getElementById('prefAutres').textContent = autres !== '---' ? autres : '---';
                }
            }
        
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

    // ÉTAPE 3 : Si chauffeur, valider puis récupérer véhicules & préférences
    if (role !== 'passager') {
        const vehicleForms = document.querySelectorAll('.vehicle-form');

        // Vérifier qu'il y a au moins un véhicule (existant OU à ajouter)
        const hasExisting = (window._existingVehiclesCount || 0) > 0;
        if (!hasExisting && vehicleForms.length === 0) {
            showMessage('Vous devez avoir au moins un véhicule pour être chauffeur', 'error');
            // Ouvrir la section véhicules si elle est fermée
            const vehiclesSection = document.getElementById('vehiclesSection');
            if (vehiclesSection && vehiclesSection.style.display !== 'block') {
                onRoleChange('chauffeur');
            }
            return;
        }

        // Réinitialiser les états d'erreur visuels
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));

        // Valider et collecter les véhicules saisis
        const vehicleErrors = [];
        const firstInvalidRefs = [];
        vehicleForms.forEach((form, index) => {
            const get = (selector) => form.querySelector(selector);
            const marqueEl = get(`input[name="vehicles[${index}][marque]"]`);
            const modeleEl = get(`input[name="vehicles[${index}][modele]"]`);
            const couleurEl = get(`input[name="vehicles[${index}][couleur]"]`);
            const immatEl = get(`input[name="vehicles[${index}][immatriculation]"]`);
            const dateEl = get(`input[name="vehicles[${index}][date_premiere_immatriculation]"]`);
            const placesEl = get(`input[name="vehicles[${index}][nb_places]"]`);
            const energieEl = get(`select[name="vehicles[${index}][energie]"]`);

            const fields = [
                { el: marqueEl, name: 'marque' },
                { el: modeleEl, name: 'modèle' },
                { el: couleurEl, name: 'couleur' },
                { el: immatEl, name: "plaque d'immatriculation" },
                { el: dateEl, name: 'date de première immatriculation' },
                { el: placesEl, name: 'nombre de places' },
                { el: energieEl, name: "type d'énergie" }
            ];

            let localInvalid = false;
            fields.forEach(f => {
                const v = (f.el?.value || '').toString().trim();
                const isEmpty = v === '';
                const isPlacesInvalid = f.el === placesEl && (v === '' || isNaN(Number(v)) || Number(v) <= 0);
                if (isEmpty || isPlacesInvalid) {
                    localInvalid = true;
                    f.el?.classList.add('input-error');
                    if (firstInvalidRefs.length === 0) firstInvalidRefs.push(f.el);
                }
            });

            if (localInvalid) {
                vehicleErrors.push(`Formulaire véhicule #${index + 1}: champs obligatoires manquants.`);
            } else {
                data.vehicules.push({
                    marque: marqueEl.value,
                    modele: modeleEl.value,
                    couleur: couleurEl.value,
                    immatriculation: immatEl.value,
                    date_premiere_immatriculation: dateEl.value,
                    nb_places: parseInt(placesEl.value, 10),
                    energie: energieEl.value,
                    est_ecologique: energieEl.value === 'electrique' ? 1 : 0
                });
            }
        });

        // Préférences requises
        const fumeurEl = document.querySelector('select[name="preference_fumeur"]');
        const animauxEl = document.querySelector('select[name="preference_animaux"]');
        const autresEl = document.querySelector('textarea[name="autres_preferences"]');
        const prefErrors = [];
        if (!fumeurEl || fumeurEl.value === '') {
            prefErrors.push('Veuillez indiquer votre préférence fumeur.');
            fumeurEl?.classList.add('input-error');
            if (firstInvalidRefs.length === 0 && fumeurEl) firstInvalidRefs.push(fumeurEl);
        }
        if (!animauxEl || animauxEl.value === '') {
            prefErrors.push('Veuillez indiquer votre préférence animaux.');
            animauxEl?.classList.add('input-error');
            if (firstInvalidRefs.length === 0 && animauxEl) firstInvalidRefs.push(animauxEl);
        }

        data.preferences = {
            fumeur: fumeurEl ? fumeurEl.value : '',
            animaux: animauxEl ? animauxEl.value : '',
            autres_preferences: autresEl ? autresEl.value : ''
        };

        const allErrors = [...vehicleErrors, ...prefErrors];
        if (allErrors.length > 0) {
            showMessage(allErrors[0], 'error');
            // Ouvrir l'accordion si fermé
            const editToggle = document.getElementById('editProfileToggle');
            const editSection = document.getElementById('editProfileSection');
            if (editToggle && editSection && editSection.style.display !== 'block') {
                toggleAccordion('editProfileToggle', 'editProfileSection');
            }
            // S'assurer que les sections visibles (rôle chauffeur)
            onRoleChange(role);
            // Scroll jusqu'au premier champ invalide
            if (firstInvalidRefs[0] && typeof firstInvalidRefs[0].scrollIntoView === 'function') {
                firstInvalidRefs[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
    }

    // ÉTAPE 5 : Envoyer les données à l'API
    console.log('Données à envoyer:', data);

    ;(async () => {
        try {
            const csrf = await SessionManager.csrfHeaders();
            const response = await fetch('/api/user/profile', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', ...csrf },
                body: JSON.stringify(data)
            });
            const result = await response.json();
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
        } catch (error) {
            console.error('Erreur:', error);
            showMessage('Une erreur est survenue : ' + error.message, 'error');
        }
    })();
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
    const prefRow = document.getElementById('infoPrefRow');

    // ÉTAPE 2 : Vérifier le rôle et afficher/cacher les sections
    if (role === 'passager') {
        // Les passagers n'ont pas besoin de véhicules ni de préférences
        vehiclesSection.style.display = 'none';
        preferencesSection.style.display = 'none';
        if (prefRow) prefRow.style.display = 'none';
    } else if (role === 'chauffeur' || role === 'chauffeur_passager') {
        // Les chauffeurs doivent déclarer des véhicules et préférences
        vehiclesSection.style.display = 'block';
        preferencesSection.style.display = 'block';
        if (prefRow) prefRow.style.display = 'table-row';
        // Afficher la section création de trajet si présente
        const createToggle = document.getElementById('createTripToggle');
        const createSection = document.getElementById('createTripSection');
        if (createToggle && createSection && createSection.style.display !== 'block') {
            // Laisser fermé par défaut; l'utilisateur peut l'ouvrir.
        }
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
async function saveVehicleDirectly(formIndex) {
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
        marque: marque,
        modele: modele,
        couleur: couleur,
        immatriculation: immatriculation,
        date_premiere_immatriculation: date_premiere_immatriculation,
        nb_places: parseInt(nb_places),
        energie: energie
    };

    // Envoyer à l'API
    try {
        const csrf = await SessionManager.csrfHeaders();
        const response = await fetch('/api/user/vehicles', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrf },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
            showMessage(`Véhicule "${result.data.modele}" ajouté avec succès !`, 'success');
            vehicleForm.remove();
            loadExistingVehicles();
            loadVehiclesManager();
        } else {
            showMessage(result.error?.message || 'Erreur lors de l\'ajout du véhicule', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showMessage('Une erreur est survenue : ' + error.message, 'error');
    }
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