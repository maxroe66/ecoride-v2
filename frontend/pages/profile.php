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
          </table>
        </section>

        <!-- Section édition du profil (US8) -->
        <section class="profile-section" id="editProfileSection">
          <h2>Éditer mon profil</h2>
          
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

          <!-- Formulaire véhicules (affiché seulement si chauffeur) -->
          <div id="vehiclesSection" style="display: none;">
            <h3>Mes véhicules</h3>
            <div id="vehiclesContainer"></div>
            <button type="button" id="addVehicleBtn" class="btn btn-primary">+ Ajouter un véhicule</button>
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
          <button type="button" id="updateProfileBtn" class="btn btn-primary">Enregistrer les modifications</button>
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