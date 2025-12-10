<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoRide - Espace Employé</title>
  <link rel="stylesheet" href="/frontend/css/global.css">
  <link rel="stylesheet" href="/frontend/css/components/header.css">
  <link rel="stylesheet" href="/frontend/css/components/footer.css">
  <style>
    /* Styles temporaires en attente du CSS dédié */
    .employee-space {
      max-width: 1200px;
      margin: 40px auto;
      padding: 0 20px;
    }

    .employee-space h1 {
      text-align: center;
      font-size: 32px;
      margin-bottom: 40px;
      color: #333;
    }

    .tabs {
      display: flex;
      gap: 20px;
      margin-bottom: 40px;
      border-bottom: 2px solid #e0e0e0;
      justify-content: center;
      flex-wrap: wrap;
    }

    .tab-btn {
      padding: 12px 24px;
      background: transparent;
      border: none;
      font-size: 16px;
      font-weight: 600;
      color: #666;
      cursor: pointer;
      border-bottom: 3px solid transparent;
      transition: all 0.3s;
    }

    .tab-btn.active {
      color: #27ae60;
      border-bottom-color: #27ae60;
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
    }

    .card {
      background: white;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .card:hover {
      box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid #f0f0f0;
    }

    .card-actions {
      display: flex;
      gap: 10px;
      margin-top: 15px;
      flex-wrap: wrap;
    }

    button {
      padding: 10px 16px;
      border: none;
      border-radius: 4px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }

    .btn-approve {
      background: #27ae60;
      color: white;
      flex: 1;
      min-width: 120px;
    }

    .btn-approve:hover {
      background: #229954;
    }

    .btn-reject {
      background: #e74c3c;
      color: white;
      flex: 1;
      min-width: 120px;
    }

    .btn-reject:hover {
      background: #c0392b;
    }

    .btn-detail {
      background: #3498db;
      color: white;
      width: 100%;
    }

    .btn-detail:hover {
      background: #2980b9;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #999;
    }

    .loading {
      text-align: center;
      padding: 20px;
      color: #666;
    }

    .error {
      background: #fee;
      border: 1px solid #fcc;
      color: #c33;
      padding: 15px;
      border-radius: 4px;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

  <main class="employee-space">
    <h1>🛠️ Espace Employé</h1>

    <!-- Tabs Navigation -->
    <div class="tabs">
      <button class="tab-btn active" data-tab="reviews">📋 Avis en Attente</button>
      <button class="tab-btn" data-tab="incidents">⚠️ Incidents</button>
    </div>

    <!-- Tab 1 : Avis en Attente -->
    <div id="reviews-tab" class="tab-content active">
      <h2>Avis en attente de modération</h2>
      <div id="reviewsContainer" class="reviews-list">
        <div class="loading">⏳ Chargement des avis...</div>
      </div>
    </div>

    <!-- Tab 2 : Incidents -->
    <div id="incidents-tab" class="tab-content">
      <h2>Incidents signalés</h2>
      <div id="incidentsContainer" class="incidents-list">
        <div class="loading">⏳ Chargement des incidents...</div>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

  <script src="/frontend/js/employee-space.js"></script>
</body>
</html>
