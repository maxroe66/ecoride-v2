<?php
/**
 * Page de profil utilisateur (US8)
 * Accessible uniquement si connecté
 * Permet de modifier : rôle, véhicules, préférences
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoRide - Mon Profil</title>
  
  <link rel="stylesheet" href="/frontend/css/global.css">
  <link rel="stylesheet" href="/frontend/css/components/header.css">
  <link rel="stylesheet" href="/frontend/css/components/footer.css">
  <link rel="stylesheet" href="/frontend/css/profile.css">
</head>
<body>
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main>
    <div class="profile-container">
      <div class="profile-box">
        <!-- En-tête du profil -->
        <div class="profile-header">
          <h1 id="userName">Chargement...</h1>
          <p id="userStatus" style="margin: 0; color: #999;"></p>
        </div>

        <!-- Messages d'erreur/succès -->
        <div id="messageContainer"></div>

        <!-- Section infos actuelles -->
        <section class="profile-section">
          <h2>Mes informations</h2>
          <table class="info-table">
            <tr>
              <td>Pseudo</td>
              <td id="infoPseudo">---</td>
            </tr>
            <tr>
              <td>Email</td>
              <td id="infoEmail">---</td>
            </tr>
            <tr>
              <td>Crédits</td>
              <td id="infoCredit">---</td>
            </tr>
            <tr>
              <td>Rôle actuel</td>
              <td id="infoRole">---</td>
            </tr>
            <tr id="infoPrefRow" style="display: none;">
              <td>Préférences</td>
              <td id="infoPreferences">
                <div class="pref-container">
                  <div class="pref-col">
                    <span class="pref-label">Fumeur</span>
                    <span class="pref-value" id="prefFumeur">---</span>
                  </div>
                  <div class="pref-col">
                    <span class="pref-label">Animaux</span>
                    <span class="pref-value" id="prefAnimaux">---</span>
                  </div>
                  <div class="pref-autres" id="prefAutres">---</div>
                </div>
              </td>
            </tr>
          </table>

          <!-- Mes véhicules (dropdown) -->
          <div class="vehicles-dropdown">
            <button type="button" class="dropdown-toggle" id="vehiclesToggle">
              <span>📋 Mes véhicules</span>
              <span class="dropdown-icon">▼</span>
            </button>
            <div class="dropdown-content" id="vehiclesDropdown" style="display: none;">
              <div id="vehiclesDisplayContainer">
                <p style="text-align: center; color: #999;">Aucun véhicule enregistré</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Section édition du profil (US8) - ACCORDION -->
        <section class="profile-section">
          <button type="button" class="accordion-toggle" id="editProfileToggle">
            <span>✏️ Éditer mon profil</span>
            <span class="accordion-icon">▼</span>
          </button>
          
          <div class="accordion-content" id="editProfileSection" style="display: none;">
            <!-- Sélection du rôle -->
            <div class="form-group">
              <label>Choisir mon rôle</label>
              <div class="role-selector">
                <div class="role-option">
                  <input type="radio" name="role" value="passager" id="role-passager">
                  <label for="role-passager">Passager</label>
                </div>
                <div class="role-option">
                  <input type="radio" name="role" value="chauffeur" id="role-chauffeur">
                  <label for="role-chauffeur">Chauffeur</label>
                </div>
                <div class="role-option">
                  <input type="radio" name="role" value="chauffeur_passager" id="role-both">
                  <label for="role-both">Chauffeur & Passager</label>
                </div>
              </div>
            </div>

            <!-- Gestion véhicules (affiché seulement si chauffeur) -->
            <div id="vehiclesSection" style="display: none;">
              <h3>Véhicules</h3>
              <button type="button" id="manageVehiclesBtn" class="btn btn-secondary">Ajouter/Supprimer véhicules</button>
              <div id="vehiclesManager" style="display:none; margin-top:12px;">
                <div id="vehiclesManagerList" class="vehicles-list"></div>
                <div style="margin-top:12px;">
                  <div id="vehiclesContainer"></div>
                  <button type="button" id="addVehicleBtn" class="btn btn-primary">+ Ajouter un véhicule</button>
                </div>
              </div>
            </div>

            <!-- Formulaire préférences (affiché seulement si chauffeur) -->
            <div id="preferencesSection" style="display: none;">
              <h3>Mes préférences</h3>
              
              <div class="form-group">
                <label>Fumeur / Non-fumeur</label>
                <select name="preference_fumeur">
                  <option value="">-- Choisir --</option>
                  <option value="accepte">Accepte les fumeurs</option>
                  <option value="refuse">Refuse les fumeurs</option>
                </select>
              </div>

              <div class="form-group">
                <label>Animaux</label>
                <select name="preference_animaux">
                  <option value="">-- Choisir --</option>
                  <option value="accepte">Accepte les animaux</option>
                  <option value="refuse">Refuse les animaux</option>
                </select>
              </div>

              <div class="form-group">
                <label>Autres préférences</label>
                <textarea name="autres_preferences" placeholder="Ex: Musique classique, pas de bavardage..."></textarea>
              </div>
            </div>

            <!-- Bouton soumettre -->
            <button type="button" id="updateProfileBtn" class="btn btn-primary">Enregistré</button>
          </div>
        </section>

        <!-- Section création de trajet (US9) - ACCORDION -->
        <section class="profile-section">
          <button type="button" class="accordion-toggle" id="createTripToggle">
            <span>🚘 Créer un trajet</span>
            <span class="accordion-icon">▼</span>
          </button>
          <div class="accordion-content" id="createTripSection" style="display: none;">
            <p style="margin-top:0;color:#666;">
              Rappel: 2 crédits seront prélevés par la plateforme pour chaque participation.
            </p>

            <div class="form-group">
              <label for="createTripDeparture">Adresse de départ</label>
              <input type="text" id="createTripDeparture" placeholder="Ex: Paris, Gare de Lyon">
            </div>

            <div class="form-group">
              <label for="createTripArrival">Adresse d'arrivée</label>
              <input type="text" id="createTripArrival" placeholder="Ex: Lyon, Part-Dieu">
            </div>

            <div style="display:flex; gap:12px; flex-wrap: wrap;">
              <div class="form-group" style="flex:1 1 180px; min-width:180px;">
                <label for="createTripDate">Date de départ</label>
                <input type="date" id="createTripDate">
              </div>
              <div class="form-group" style="flex:1 1 180px; min-width:180px;">
                <label for="createTripTime">Heure de départ</label>
                <input type="time" id="createTripTime">
              </div>
            </div>

            <div style="display:flex; gap:12px; flex-wrap: wrap;">
              <div class="form-group" style="flex:1 1 180px; min-width:180px;">
                <label for="createTripSeats">Places disponibles</label>
                <input type="number" id="createTripSeats" min="1" max="8" placeholder="Ex: 3">
              </div>
              <div class="form-group" style="flex:1 1 180px; min-width:180px;">
                <label for="createTripPrice">Prix / personne (crédits)</label>
                <input type="number" id="createTripPrice" min="2" step="0.5" placeholder=">= 2">
                <small id="createTripNetPreview" style="display:block;color:#666;margin-top:4px;">Vous recevrez 0 crédit net (prix - 2)</small>
              </div>
            </div>

            <div class="form-group">
              <label for="createTripVehicle">Véhicule</label>
              <select id="createTripVehicle">
                <option value="">-- Sélectionner un véhicule --</option>
              </select>
              <div style="margin-top:6px;">
                <button type="button" id="openVehiclesManagerFromTrip" class="btn btn-secondary" style="padding:6px 10px;">+ Ajouter un véhicule</button>
              </div>
            </div>

            <div class="btn-group">
              <button type="button" id="createTripBtn" class="btn btn-primary">Créer le trajet</button>
            </div>
          </div>
        </section>

        <!-- Section mes prochains trajets (US9) - ACCORDION -->
        <section class="profile-section">
          <button type="button" class="accordion-toggle" id="myTripsToggle">
            <span>📅 Mes prochains trajets</span>
            <span class="accordion-icon">▼</span>
          </button>
          <div class="accordion-content" id="myTripsSection" style="display: none;">
            <div id="createTripUpcomingList">
              <p style="text-align:center;color:#999;">Ouvrez pour charger vos trajets à venir</p>
            </div>
          </div>
        </section>

        <!-- Actions de base -->
        <div class="profile-section">
          <div class="btn-group">
            <button id="goToHomeBtn" class="btn btn-secondary">Retour à l'accueil</button>
            <button id="logoutBtn" class="btn btn-danger">Se déconnecter</button>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <script src="/frontend/js/auth.js"></script>
  <script src="/frontend/js/profile.js"></script>
</body>
</html>