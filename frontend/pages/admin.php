<?php
/**
 * Admin Dashboard Page
 * Visible only to authenticated admin users
 */

// Check authentication and admin role via PHP session (set on login)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionUser = $_SESSION['user'] ?? null;
$allowedAdminTypes = ['administrateur', 'admin'];

if (!$sessionUser || !in_array($sessionUser['type_utilisateur'] ?? 'standard', $allowedAdminTypes, true)) {
    header('Location: /');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoRide - Espace administrateur</title>

    <link rel="stylesheet" href="/frontend/css/global.css">
    <link rel="stylesheet" href="/frontend/css/components/header.css">
    <link rel="stylesheet" href="/frontend/css/components/footer.css">
    <link rel="stylesheet" href="/frontend/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../templates/layouts/header.php'; ?>

    <main class="admin-page">
        <section class="admin-intro">
            <p class="admin-eyebrow">Espace administrateur</p>
            <div class="admin-intro__text">
                <h1>Centre de contrôle EcoRide</h1>
                <p>Synchronisez les équipes, surveillez les indicateurs clés et sécurisez les comptes utilisateurs depuis un seul et même espace.</p>
            </div>
        </section>

        <div class="admin-shell">
            <aside class="admin-sidebar">
                <div class="admin-sidebar__header">
                    <p class="admin-eyebrow">Navigation</p>
                    <h2>Panneau principal</h2>
                    <p>Choisissez un module pour agir en temps réel sur la plateforme.</p>
                </div>
                <nav class="admin-nav">
                    <button type="button" class="nav-btn active" data-section="employees">
                        <span class="nav-btn__icon">👥</span>
                        <span class="nav-btn__texts">
                            <strong>Employés</strong>
                            <small>Création & suivi</small>
                        </span>
                    </button>
                    <button type="button" class="nav-btn" data-section="statistics">
                        <span class="nav-btn__icon">📊</span>
                        <span class="nav-btn__texts">
                            <strong>Statistiques</strong>
                            <small>Trajets & crédits</small>
                        </span>
                    </button>
                    <button type="button" class="nav-btn" data-section="users">
                        <span class="nav-btn__icon">👨‍💼</span>
                        <span class="nav-btn__texts">
                            <strong>Utilisateurs</strong>
                            <small>Suspension & réactivation</small>
                        </span>
                    </button>
                </nav>
            </aside>

            <div class="admin-content">
                <section id="employees" class="section admin-section active">
                    <div class="section-header">
                        <p class="admin-eyebrow">Équipe interne</p>
                        <div>
                            <h2>Gestion des employés</h2>
                            <p>Créez de nouveaux accès employé et visualisez les comptes actifs en un coup d'œil.</p>
                        </div>
                    </div>

                    <div class="alert admin-alert" id="employeeAlert"></div>

                    <div class="admin-grid admin-grid--split">
                        <article class="admin-card admin-card--form">
                            <div class="card-header">
                                <div>
                                    <p class="card-eyebrow">Création</p>
                                    <h3>Inviter un employé</h3>
                                </div>
                            </div>

                            <form id="createEmployeeForm" class="admin-form">
                                <div class="form-group">
                                    <label for="employeeEmail">Email professionnel</label>
                                    <input
                                        type="email"
                                        id="employeeEmail"
                                        name="email"
                                        placeholder="employe@ecoride.fr"
                                        required
                                    >
                                    <p class="error-text" id="emailError"></p>
                                </div>

                                <div class="form-group">
                                    <label for="employeePseudo">Pseudo</label>
                                    <input
                                        type="text"
                                        id="employeePseudo"
                                        name="pseudo"
                                        placeholder="Pseudo (3-30 caractères)"
                                        minlength="3"
                                        maxlength="30"
                                        required
                                    >
                                    <p class="error-text" id="pseudoError"></p>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    Créer le compte
                                </button>
                            </form>

                            <div class="password-panel" id="passwordDisplay">
                                <div class="password-panel__content">
                                    <p class="card-eyebrow">Mot de passe temporaire</p>
                                    <p class="password-panel__value" id="passwordValue"></p>
                                </div>
                                <div class="password-panel__actions">
                                    <button type="button" class="btn btn-ghost" id="copyPasswordBtn">Copier</button>
                                </div>
                                <p class="password-panel__hint">Communiquez ce mot de passe au collaborateur : il sera invité à le changer lors de sa première connexion.</p>
                            </div>
                        </article>

                        <article class="admin-card admin-card--list">
                            <div class="card-header">
                                <div>
                                    <p class="card-eyebrow">Équipe</p>
                                    <h3>Employés actifs</h3>
                                </div>
                            </div>

                            <div id="employeesList" class="admin-list">
                                <div class="admin-loader">
                                    <span class="loader-dot"></span>
                                    <span>Chargement des comptes...</span>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section id="statistics" class="section admin-section">
                    <div class="section-header">
                        <p class="admin-eyebrow">Données en direct</p>
                        <div>
                            <h2>Trajets & crédits</h2>
                            <p>Visualisez la dynamique des trajets quotidiens et du chiffre d'affaires crédit.</p>
                        </div>
                    </div>

                    <div class="alert admin-alert" id="statsAlert"></div>

                    <div class="stat-grid">
                        <article class="admin-card stat-card">
                            <p class="card-eyebrow">Flux financier</p>
                            <h3>Crédits totaux</h3>
                            <p class="stat-value" id="totalCredits">
                                <span class="loader-dot loader-dot--lg"></span>
                            </p>
                            <p class="stat-hint">Somme des crédits cumulés sur EcoRide.</p>
                        </article>
                    </div>

                    <div class="charts-grid">
                        <article class="admin-card chart-card">
                            <div class="card-header">
                                <h3>Trajets confirmés</h3>
                                <span>30 derniers jours</span>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="tripsChart"></canvas>
                            </div>
                        </article>
                        <article class="admin-card chart-card">
                            <div class="card-header">
                                <h3>Crédits gagnés</h3>
                                <span>30 derniers jours</span>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="creditsChart"></canvas>
                            </div>
                        </article>
                    </div>

                    <div class="section-actions">
                        <button class="btn btn-secondary" id="refreshStatsBtn">🔄 Actualiser les données</button>
                    </div>
                </section>

                <section id="users" class="section admin-section">
                    <div class="section-header">
                        <p class="admin-eyebrow">Base utilisateurs</p>
                        <div>
                            <h2>Gestion des comptes</h2>
                            <p>Suspendez ou réactivez les profils en un clic pour sécuriser la communauté.</p>
                        </div>
                    </div>

                    <div class="alert admin-alert" id="usersAlert"></div>

                    <article class="admin-card">
                        <div class="card-header card-header--table">
                            <div>
                                <p class="card-eyebrow">Annuaire</p>
                                <h3>Tous les utilisateurs</h3>
                            </div>
                            <div class="search-box">
                                <input type="search" id="userSearch" placeholder="Rechercher par email ou pseudo">
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table class="admin-table" id="usersTable">
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
                                        <td colspan="5">
                                            <div class="admin-loader">
                                                <span class="loader-dot"></span>
                                                <span>Chargement des utilisateurs...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </main>

    <div id="confirmationModal" class="admin-modal" style="display: none;">
        <div class="admin-modal__dialog">
            <h2 id="confirmTitle"></h2>
            <p id="confirmMessage"></p>
            <div class="admin-modal__actions">
                <button class="btn btn-ghost" id="confirmCancel">Annuler</button>
                <button class="btn btn-danger" id="confirmAction">Confirmer</button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../templates/layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/frontend/js/admin-space.js"></script>
</body>
</html>
