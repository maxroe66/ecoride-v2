<?php
/**
 * Page d'inscription
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoRide - Inscription</title>
  
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
        <h1>Inscription</h1>
        <p class="auth-subtitle">Rejoignez EcoRide et commencez le covoiturage</p>

        <form class="auth-form" id="signupForm">
          <div class="form-group">
            <label for="pseudo">Pseudo</label>
            <input 
              type="text" 
              id="pseudo" 
              name="pseudo" 
              placeholder="votrepseudo"
              minlength="3"
              maxlength="20"
              required
            >
            <small class="form-hint">3 à 20 caractères, lettres et chiffres uniquement</small>
          </div>

          <div class="form-group">
            <label for="email">Email</label>
            <input 
              type="email" 
              id="email" 
              name="email" 
              placeholder="votre@email.com"
              autocomplete="email"
              required
            >
            <small class="form-hint">Format email valide requis</small>
          </div>

          <div class="form-group">
            <label for="password">Mot de passe</label>
            <input 
              type="password" 
              id="password" 
              name="password" 
              placeholder="••••••••"
              autocomplete="new-password"
              minlength="8"
              required
            >
            <small class="form-hint">Au moins 8 caractères, 1 majuscule, 1 chiffre</small>
            <div class="password-strength">
              <div class="strength-bar"></div>
            </div>
          </div>

          <div class="form-group">
            <label for="confirmPassword">Confirmer le mot de passe</label>
            <input 
              type="password" 
              id="confirmPassword" 
              name="confirmPassword" 
              placeholder="••••••••"
              autocomplete="new-password"
              required
            >
          </div>

          <div class="form-group terms">
            <input type="checkbox" id="terms" name="terms" required>
            <label for="terms">
              J'accepte les <a href="/terms" target="_blank">conditions d'utilisation</a> et la <a href="/privacy" target="_blank">politique de confidentialité</a>
            </label>
          </div>

          <div id="errorMessage" class="error-message" style="display: none;"></div>

          <button type="submit" class="btn btn-primary btn-block">
            Créer mon compte
          </button>
        </form>

        <div class="auth-links">
          <p>Vous avez déjà un compte ? <a href="/login">Se connecter</a></p>
        </div>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <script src="/frontend/js/auth.js"></script>
</body>
</html>
