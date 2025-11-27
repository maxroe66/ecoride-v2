<?php
/**
 * Page de profil utilisateur
 * Accessible uniquement si connecté
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
  <style>
    .profile-container {
      max-width: 600px;
      margin: 4rem auto;
      padding: 2rem;
    }

    .profile-box {
      background: white;
      border-radius: 8px;
      padding: 2rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .profile-header {
      text-align: center;
      margin-bottom: 2rem;
      padding-bottom: 2rem;
      border-bottom: 1px solid #eee;
    }

    .profile-header h1 {
      margin: 0 0 0.5rem 0;
      color: #333;
    }

    .profile-info {
      display: grid;
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem;
      background: #f9f9f9;
      border-radius: 4px;
    }

    .info-row label {
      font-weight: 600;
      color: #666;
    }

    .info-row value {
      color: #333;
      font-size: 1.1rem;
    }

    .credit-badge {
      display: inline-block;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 600;
      font-size: 1.1rem;
    }

    .profile-actions {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }

    .btn {
      flex: 1;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 4px;
      font-size: 1rem;
      cursor: pointer;
      text-align: center;
      text-decoration: none;
      display: inline-block;
      transition: all 0.3s ease;
    }

    .btn-primary {
      background: #667eea;
      color: white;
    }

    .btn-primary:hover {
      background: #5568d3;
    }

    .btn-danger {
      background: #e74c3c;
      color: white;
    }

    .btn-danger:hover {
      background: #c0392b;
    }

    .loading {
      text-align: center;
      padding: 2rem;
      color: #999;
    }

    .error {
      background: #f8d7da;
      color: #721c24;
      padding: 1rem;
      border-radius: 4px;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main>
    <div class="profile-container">
      <div class="profile-box">
        <div class="profile-header">
          <h1 id="userName">Chargement...</h1>
          <p id="userStatus" style="margin: 0; color: #999;"></p>
        </div>

        <div id="errorContainer" class="error" style="display: none;"></div>

        <div class="profile-info" id="profileInfo">
          <div class="info-row">
            <label>Pseudo</label>
            <span id="infoPseudo" style="color: #999;">---</span>
          </div>
          <div class="info-row">
            <label>Email</label>
            <span id="infoEmail" style="color: #999;">---</span>
          </div>
          <div class="info-row">
            <label>Crédits</label>
            <span class="credit-badge" id="infoCredit">---</span>
          </div>
          <div class="info-row">
            <label>Type de compte</label>
            <span id="infoType" style="color: #999;">---</span>
          </div>
        </div>

        <div class="profile-actions">
          <button class="btn btn-primary" onclick="goToHome()">Retour à l'accueil</button>
          <button class="btn btn-danger" onclick="logout()">Se déconnecter</button>
        </div>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <script src="/frontend/js/auth.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      loadProfile();
    });

    function loadProfile() {
      const user = SessionManager.getUser();

      if (!user) {
        // Rediriger vers login si pas connecté
        window.location.href = '/login';
        return;
      }

      // Remplir les données
      document.getElementById('userName').textContent = user.pseudo;
      document.getElementById('infoPseudo').textContent = user.pseudo;
      document.getElementById('infoEmail').textContent = user.email;
      document.getElementById('infoCredit').textContent = user.credit + ' crédits';
      document.getElementById('infoType').textContent = user.type_utilisateur || 'standard';
      document.getElementById('userStatus').textContent = 'Connecté en tant que ' + user.pseudo;
    }

    function goToHome() {
      window.location.href = '/';
    }
  </script>
</body>
</html>
