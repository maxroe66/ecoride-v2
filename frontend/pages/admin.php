<?php
/**
 * Admin Dashboard Page
 * Visible only to authenticated admin users
 */

// Check authentication and admin role
$token = $_COOKIE['ecoride_token'] ?? null;
if (!$token) {
    header('Location: /login');
    exit;
}

// Decode JWT to check role (basic validation without verification for client-side protection)
try {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new Exception('Invalid token');
    }
    
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!$payload || ($payload['type_utilisateur'] ?? null) !== 'admin') {
        header('Location: /');
        exit;
    }
} catch (Exception $e) {
    header('Location: /login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Administrateur - EcoRide</title>
    <link rel="stylesheet" href="/frontend/css/styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 0;
            min-height: 100vh;
        }

        .admin-sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 250px;
            overflow-y: auto;
        }

        .admin-sidebar h2 {
            padding: 1rem 1.5rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .admin-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .admin-nav button {
            background: none;
            border: none;
            color: white;
            padding: 1rem 1.5rem;
            text-align: left;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .admin-nav button:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: #ffd700;
        }

        .admin-nav button.active {
            background-color: rgba(255, 255, 255, 0.15);
            border-left-color: #ffd700;
            font-weight: 600;
        }

        .admin-main {
            margin-left: 250px;
            padding: 2rem;
        }

        .section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .section.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-title {
            font-size: 2rem;
            margin-bottom: 2rem;
            color: #333;
        }

        /* Employees Section */
        .employees-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .form-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .form-card h3 {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: #667eea;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input.error {
            border-color: #dc3545;
        }

        .error-text {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }

        .error-text.show {
            display: block;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        /* Success/Error Messages */
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: none;
            animation: slideDown 0.3s ease;
        }

        .alert.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .password-display {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
            border: 1px solid #dee2e6;
            display: none;
        }

        .password-display.show {
            display: block;
        }

        .password-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .password-value {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #667eea;
            flex: 1;
            word-break: break-all;
        }

        .btn-copy {
            background: #17a2b8;
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-copy:hover {
            background: #138496;
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            color: #667eea;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .chart-card h3 {
            color: #667eea;
            margin-bottom: 1rem;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        /* Users Management Section */
        .users-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            color: #667eea;
            margin: 0;
        }

        .search-box {
            display: flex;
            gap: 0.5rem;
        }

        .search-box input {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 250px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #f8f9fa;
        }

        th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #667eea;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-suspended {
            background-color: #f8d7da;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
        }

        /* Loading State */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .admin-container {
                grid-template-columns: 1fr;
            }

            .admin-sidebar {
                position: relative;
                width: 100%;
                height: auto;
                display: flex;
                flex-direction: column;
            }

            .admin-main {
                margin-left: 0;
                padding: 1rem;
            }

            .employees-grid {
                grid-template-columns: 1fr;
            }

            .charts-container {
                grid-template-columns: 1fr;
            }

            .admin-nav {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .admin-nav button {
                flex: 1;
                min-width: 150px;
                padding: 0.75rem 1rem;
            }
        }

        @media (max-width: 768px) {
            .admin-nav button {
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
            }

            .employees-grid {
                gap: 1rem;
            }

            .search-box input {
                width: 100%;
            }

            table {
                font-size: 0.9rem;
            }

            th, td {
                padding: 0.75rem;
            }

            .btn-small {
                padding: 0.4rem 0.6rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <h2>🔧 Admin</h2>
            <nav class="admin-nav">
                <button class="nav-btn active" data-section="employees">
                    👥 Employés
                </button>
                <button class="nav-btn" data-section="statistics">
                    📊 Statistiques
                </button>
                <button class="nav-btn" data-section="users">
                    👨‍💼 Gestion Utilisateurs
                </button>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Employees Section -->
            <section id="employees" class="section active">
                <h1 class="section-title">Gestion des Employés</h1>

                <div class="alert" id="employeeAlert"></div>

                <div class="employees-grid">
                    <!-- Create Employee Form -->
                    <div class="form-card">
                        <h3>Créer un Employé</h3>
                        <form id="createEmployeeForm">
                            <div class="form-group">
                                <label for="employeeEmail">Email</label>
                                <input 
                                    type="email" 
                                    id="employeeEmail" 
                                    name="email" 
                                    required
                                    placeholder="employe@ecoride.fr"
                                >
                                <div class="error-text" id="emailError"></div>
                            </div>

                            <div class="form-group">
                                <label for="employeePseudo">Pseudo</label>
                                <input 
                                    type="text" 
                                    id="employeePseudo" 
                                    name="pseudo" 
                                    required
                                    placeholder="Pseudo (3-30 caractères)"
                                    minlength="3"
                                    maxlength="30"
                                >
                                <div class="error-text" id="pseudoError"></div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                Créer l'Employé
                            </button>
                        </form>

                        <div class="password-display" id="passwordDisplay">
                            <h4 style="margin-bottom: 1rem; color: #667eea;">
                                ✓ Employé créé avec succès!
                            </h4>
                            <div class="password-box">
                                <div>
                                    <p style="margin-bottom: 0.5rem; color: #555;">Mot de passe temporaire:</p>
                                    <div class="password-value" id="passwordValue"></div>
                                </div>
                                <button type="button" class="btn btn-copy" id="copyPasswordBtn">
                                    Copier
                                </button>
                            </div>
                            <p style="font-size: 0.85rem; color: #dc3545; margin-top: 1rem;">
                                ⚠️ Communiquez ce mot de passe à l'employé. Il doit le changer à la première connexion.
                            </p>
                        </div>
                    </div>

                    <!-- Employees List -->
                    <div class="form-card">
                        <h3>Employés Actifs</h3>
                        <div id="employeesList">
                            <div class="loading">
                                <div class="spinner"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Statistics Section -->
            <section id="statistics" class="section">
                <h1 class="section-title">📊 Statistiques</h1>

                <div class="alert" id="statsAlert"></div>

                <!-- Total Credits Card -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Crédits Totaux Plateforme</h3>
                        <div class="stat-value" id="totalCredits">
                            <div class="spinner" style="width: 30px; height: 30px;"></div>
                        </div>
                        <p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                            Crédits gagnés par tous les utilisateurs
                        </p>
                    </div>
                </div>

                <!-- Charts -->
                <div class="charts-container">
                    <div class="chart-card">
                        <h3>Trajets par Jour (30 derniers jours)</h3>
                        <div class="chart-wrapper">
                            <canvas id="tripsChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <h3>Crédits Gagnés par Jour (30 derniers jours)</h3>
                        <div class="chart-wrapper">
                            <canvas id="creditsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 2rem;">
                    <button class="btn btn-secondary" id="refreshStatsBtn">
                        🔄 Actualiser les données
                    </button>
                </div>
            </section>

            <!-- Users Management Section -->
            <section id="users" class="section">
                <h1 class="section-title">👨‍💼 Gestion des Utilisateurs</h1>

                <div class="alert" id="usersAlert"></div>

                <!-- Users Table -->
                <div class="users-table-container">
                    <div class="table-header">
                        <h3>Tous les Utilisateurs</h3>
                        <div class="search-box">
                            <input 
                                type="text" 
                                id="userSearch" 
                                placeholder="Rechercher par email ou pseudo..."
                            >
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table id="usersTable">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Pseudo</th>
                                    <th>Type</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <tr>
                                    <td colspan="5" class="loading">
                                        <div class="spinner"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1000; display: flex; justify-content: center; align-items: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); max-width: 400px;">
            <h2 id="confirmTitle" style="margin-bottom: 1rem; color: #333;"></h2>
            <p id="confirmMessage" style="margin-bottom: 1.5rem; color: #666;"></p>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button class="btn btn-secondary" id="confirmCancel">Annuler</button>
                <button class="btn btn-danger" id="confirmAction">Confirmer</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/frontend/js/admin-space.js"></script>
</body>
</html>
