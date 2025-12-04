<?php
/**
 * Page de connexion
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoRide - Connexion</title>
  
  <link rel="stylesheet" href="/frontend/css/global.css">
  <link rel="stylesheet" href="/frontend/css/components/header.css">
  <link rel="stylesheet" href="/frontend/css/components/footer.css">
  <link rel="stylesheet" href="/frontend/css/auth.css">
</head>
<body>
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main>
    <div class="auth-container">
      <div class="auth-box">
        <h1>Connexion</h1>
        <p class="auth-subtitle">Connectez-vous à votre compte EcoRide</p>

        <form class="auth-form" id="loginForm">
          <div class="form-group">
            <label for="email">Email ou Pseudo</label>
            <input 
              type="text" 
              id="email" 
              name="email" 
              placeholder="votre@email.com ou votrepseudo"
              autocomplete="username"
              required
            >
          </div>

          <div class="form-group">
            <label for="password">Mot de passe</label>
            <input 
              type="password" 
              id="password" 
              name="password" 
              placeholder="••••••••"
              autocomplete="current-password"
              required
            >
          </div>

          <div class="form-group remember-me">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Se souvenir de moi</label>
          </div>

          <div id="errorMessage" class="error-message" style="display: none;"></div>

          <button type="submit" class="btn btn-primary btn-block">
            Se connecter
          </button>
        </form>

        <div class="auth-links">
          <p>Pas encore inscrit ? <a href="/signup">Créer un compte</a></p>
          <p><a href="/forgot-password">Mot de passe oublié ?</a></p>
        </div>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <script src="/frontend/js/auth.js"></script>
</body>
</html>
