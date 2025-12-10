/**
 * Admin Space JavaScript Module
 * Handles all admin dashboard interactions
 */

// Global state
const AdminSpace = {
    currentUser: null,
    charts: {},
    pendingAction: null,
    usersCache: [],

    /**
     * Initialize admin dashboard
     */
    init() {
        // Get current user from localStorage
        this.currentUser = JSON.parse(localStorage.getItem('user') || '{}');
        
        // Check if user is admin
        if (this.currentUser.type_utilisateur !== 'admin') {
            window.location.href = '/frontend/pages/home.php';
            return;
        }

        this.setupEventListeners();
        this.loadEmployeesList();
        this.loadUsersData();
    },

    /**
     * Setup all event listeners
     */
    setupEventListeners() {
        // Navigation
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.switchSection(e.target));
        });

        // Create Employee Form
        document.getElementById('createEmployeeForm').addEventListener('submit', 
            (e) => this.handleCreateEmployee(e));

        // Copy password button
        document.getElementById('copyPasswordBtn')?.addEventListener('click', 
            () => this.copyPassword());

        // Refresh stats button
        document.getElementById('refreshStatsBtn').addEventListener('click', 
            () => this.loadStatistics());

        // User search
        document.getElementById('userSearch').addEventListener('input', 
            (e) => this.filterUsers(e.target.value));

        // Confirmation modal
        document.getElementById('confirmCancel').addEventListener('click', 
            () => this.closeConfirmation());
        document.getElementById('confirmAction').addEventListener('click', 
            () => this.executeConfirmation());
    },

    /**
     * Switch between sections
     */
    switchSection(button) {
        // Update active button
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        button.classList.add('active');

        // Update active section
        document.querySelectorAll('.section').forEach(section => {
            section.classList.remove('active');
        });

        const sectionId = button.getAttribute('data-section');
        document.getElementById(sectionId).classList.add('active');

        // Load data for specific sections
        if (sectionId === 'statistics') {
            this.loadStatistics();
        }
    },

    /**
     * Handle employee creation
     */
    async handleCreateEmployee(e) {
        e.preventDefault();

        const email = document.getElementById('employeeEmail').value.trim();
        const pseudo = document.getElementById('employeePseudo').value.trim();

        // Clear previous errors
        this.clearErrors();

        // Validate inputs
        const errors = this.validateEmployeeForm(email, pseudo);
        if (Object.keys(errors).length > 0) {
            this.displayErrors(errors);
            return;
        }

        // Submit to API
        try {
            const response = await fetch('/api/admin/employees', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email, pseudo }),
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                // Display password and success message
                const tempPassword = data.data.temp_password;
                document.getElementById('passwordValue').textContent = tempPassword;
                document.getElementById('passwordDisplay').classList.add('show');

                // Reset form
                document.getElementById('createEmployeeForm').reset();
                document.getElementById('employeeEmail').focus();

                // Show success alert
                this.showAlert('employeeAlert', 
                    `✓ Employé créé avec succès! Email: ${email}`, 
                    'success', 3000);

                // Reload employees list
                await this.loadEmployeesList();
            } else {
                this.showAlert('employeeAlert', 
                    `Erreur: ${data.error?.message || 'Impossible de créer l'employé'}`, 
                    'error');

                // Display specific field errors
                if (data.error?.details) {
                    this.displayErrors(data.error.details);
                }
            }
        } catch (error) {
            console.error('Error creating employee:', error);
            this.showAlert('employeeAlert', 
                'Erreur de connexion. Veuillez réessayer.', 
                'error');
        }
    },

    /**
     * Validate employee form
     */
    validateEmployeeForm(email, pseudo) {
        const errors = {};

        if (!email) {
            errors.email = 'L\'email est requis';
        } else if (!this.isValidEmail(email)) {
            errors.email = 'Format d\'email invalide';
        }

        if (!pseudo) {
            errors.pseudo = 'Le pseudo est requis';
        } else if (pseudo.length < 3 || pseudo.length > 30) {
            errors.pseudo = 'Le pseudo doit contenir entre 3 et 30 caractères';
        }

        return errors;
    },

    /**
     * Validate email format
     */
    isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    },

    /**
     * Display form errors
     */
    displayErrors(errors) {
        Object.entries(errors).forEach(([field, message]) => {
            const input = document.getElementById(`employee${field.charAt(0).toUpperCase() + field.slice(1)}`);
            const errorElement = document.getElementById(`${field}Error`);

            if (input) {
                input.classList.add('error');
            }
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.classList.add('show');
            }
        });
    },

    /**
     * Clear form errors
     */
    clearErrors() {
        document.querySelectorAll('.form-group input').forEach(input => {
            input.classList.remove('error');
        });
        document.querySelectorAll('.error-text').forEach(error => {
            error.classList.remove('show');
            error.textContent = '';
        });
    },

    /**
     * Copy password to clipboard
     */
    copyPassword() {
        const password = document.getElementById('passwordValue').textContent;
        navigator.clipboard.writeText(password).then(() => {
            const btn = document.getElementById('copyPasswordBtn');
            const originalText = btn.textContent;
            btn.textContent = '✓ Copié!';
            btn.disabled = true;
            setTimeout(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            }, 2000);
        });
    },

    /**
     * Load employees list
     */
    async loadEmployeesList() {
        const container = document.getElementById('employeesList');
        
        try {
            const response = await fetch('/api/users', {
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success && Array.isArray(data.data)) {
                const employees = data.data.filter(user => user.type_utilisateur === 'employe');
                
                if (employees.length === 0) {
                    container.innerHTML = '<p style="color: #999; text-align: center; padding: 1rem;">Aucun employé trouvé</p>';
                } else {
                    container.innerHTML = '<ul style="list-style: none; padding: 0;">' +
                        employees.map(emp => `
                            <li style="padding: 0.75rem 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong>${emp.pseudo}</strong><br>
                                    <small style="color: #999;">${emp.email}</small>
                                </div>
                                <span class="status-badge ${emp.statut === 'actif' ? 'status-active' : 'status-suspended'}">
                                    ${emp.statut === 'actif' ? '✓ Actif' : '⊘ Suspendu'}
                                </span>
                            </li>
                        `).join('') +
                        '</ul>';
                }
            }
        } catch (error) {
            console.error('Error loading employees:', error);
            container.innerHTML = '<p style="color: #dc3545; text-align: center; padding: 1rem;">Erreur lors du chargement</p>';
        }
    },

    /**
     * Load statistics
     */
    async loadStatistics() {
        try {
            // Load total credits
            this.loadTotalCredits();

            // Load charts
            this.loadChartsData();
        } catch (error) {
            console.error('Error loading statistics:', error);
            this.showAlert('statsAlert', 'Erreur lors du chargement des statistiques', 'error');
        }
    },

    /**
     * Load total credits
     */
    async loadTotalCredits() {
        try {
            const response = await fetch('/api/admin/stats/total-credits', {
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                const totalCredits = data.data.total || 0;
                document.getElementById('totalCredits').textContent = 
                    totalCredits.toLocaleString('fr-FR') + ' €';
            }
        } catch (error) {
            console.error('Error loading total credits:', error);
        }
    },

    /**
     * Load charts data
     */
    async loadChartsData() {
        try {
            const [tripsResponse, creditsResponse] = await Promise.all([
                fetch('/api/admin/stats/trips-per-day?days=30', { credentials: 'include' }),
                fetch('/api/admin/stats/credits-per-day?days=30', { credentials: 'include' })
            ]);

            const tripsData = await tripsResponse.json();
            const creditsData = await creditsResponse.json();

            if (tripsData.success) {
                this.renderTripsChart(tripsData.data);
            }

            if (creditsData.success) {
                this.renderCreditsChart(creditsData.data);
            }
        } catch (error) {
            console.error('Error loading charts data:', error);
            this.showAlert('statsAlert', 'Erreur lors du chargement des graphiques', 'error');
        }
    },

    /**
     * Render trips chart
     */
    renderTripsChart(data) {
        const ctx = document.getElementById('tripsChart');
        
        // Destroy previous chart if exists
        if (this.charts.trips) {
            this.charts.trips.destroy();
        }

        const labels = data.map(d => this.formatDate(d.date));
        const values = data.map(d => parseInt(d.count) || 0);

        this.charts.trips = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Nombre de trajets',
                    data: values,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: { font: { size: 12 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    },

    /**
     * Render credits chart
     */
    renderCreditsChart(data) {
        const ctx = document.getElementById('creditsChart');
        
        // Destroy previous chart if exists
        if (this.charts.credits) {
            this.charts.credits.destroy();
        }

        const labels = data.map(d => this.formatDate(d.date));
        const values = data.map(d => parseFloat(d.total) || 0);

        this.charts.credits = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Crédits gagnés (€)',
                    data: values,
                    backgroundColor: '#764ba2',
                    borderColor: '#667eea',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: { font: { size: 12 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    },

    /**
     * Format date for display
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR', { 
            month: 'short', 
            day: 'numeric' 
        });
    },

    /**
     * Load users data
     */
    async loadUsersData() {
        try {
            const response = await fetch('/api/users', {
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success && Array.isArray(data.data)) {
                this.usersCache = data.data;
                this.renderUsersTable(this.usersCache);
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showAlert('usersAlert', 'Erreur lors du chargement des utilisateurs', 'error');
        }
    },

    /**
     * Render users table
     */
    renderUsersTable(users) {
        const tbody = document.getElementById('usersTableBody');

        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #999;">Aucun utilisateur trouvé</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(user => `
            <tr>
                <td>${user.email}</td>
                <td>${user.pseudo}</td>
                <td>
                    <span style="font-size: 0.85rem; color: #667eea; font-weight: 500;">
                        ${user.type_utilisateur === 'admin' ? '🔒 Admin' : 
                          user.type_utilisateur === 'employe' ? '👔 Employé' : 
                          '👤 Utilisateur'}
                    </span>
                </td>
                <td>
                    <span class="status-badge ${user.statut === 'actif' ? 'status-active' : 'status-suspended'}">
                        ${user.statut === 'actif' ? '✓ Actif' : '⊘ Suspendu'}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${user.statut === 'actif' ? 
                            `<button class="btn btn-small btn-danger" onclick="AdminSpace.confirmSuspend(${user.id}, '${user.pseudo}')">
                                Suspendre
                            </button>` :
                            `<button class="btn btn-small btn-success" onclick="AdminSpace.confirmUnsuspend(${user.id}, '${user.pseudo}')">
                                Réactiver
                            </button>`
                        }
                    </div>
                </td>
            </tr>
        `).join('');
    },

    /**
     * Filter users by search term
     */
    filterUsers(searchTerm) {
        const filtered = this.usersCache.filter(user => 
            user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
            user.pseudo.toLowerCase().includes(searchTerm.toLowerCase())
        );
        this.renderUsersTable(filtered);
    },

    /**
     * Confirm suspension
     */
    confirmSuspend(userId, pseudo) {
        this.pendingAction = {
            type: 'suspend',
            userId: userId,
            pseudo: pseudo
        };

        this.showConfirmation(
            'Suspendre l\'utilisateur?',
            `Êtes-vous sûr de vouloir suspendre ${pseudo}? Il ne pourra plus accéder à son compte.`,
            'Suspendre'
        );
    },

    /**
     * Confirm unsuspension
     */
    confirmUnsuspend(userId, pseudo) {
        this.pendingAction = {
            type: 'unsuspend',
            userId: userId,
            pseudo: pseudo
        };

        this.showConfirmation(
            'Réactiver l\'utilisateur?',
            `Êtes-vous sûr de vouloir réactiver ${pseudo}? Il pourra à nouveau accéder à son compte.`,
            'Réactiver'
        );
    },

    /**
     * Show confirmation modal
     */
    showConfirmation(title, message, actionText) {
        document.getElementById('confirmTitle').textContent = title;
        document.getElementById('confirmMessage').textContent = message;
        document.getElementById('confirmAction').textContent = actionText;
        document.getElementById('confirmationModal').style.display = 'flex';
    },

    /**
     * Close confirmation modal
     */
    closeConfirmation() {
        document.getElementById('confirmationModal').style.display = 'none';
        this.pendingAction = null;
    },

    /**
     * Execute pending action
     */
    async executeConfirmation() {
        if (!this.pendingAction) return;

        const { type, userId, pseudo } = this.pendingAction;
        const endpoint = type === 'suspend' ? 'suspend' : 'unsuspend';

        try {
            const response = await fetch(`/api/admin/users/${userId}/${endpoint}`, {
                method: 'POST',
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                this.showAlert('usersAlert', 
                    `✓ ${pseudo} a été ${type === 'suspend' ? 'suspendu' : 'réactivé'} avec succès!`, 
                    'success', 2000);
                
                await this.loadUsersData();
            } else {
                this.showAlert('usersAlert', 
                    `Erreur: ${data.error?.message || 'Impossible de modifier le statut'}`, 
                    'error');
            }
        } catch (error) {
            console.error('Error executing action:', error);
            this.showAlert('usersAlert', 'Erreur de connexion', 'error');
        }

        this.closeConfirmation();
    },

    /**
     * Show alert message
     */
    showAlert(elementId, message, type, duration = null) {
        const alert = document.getElementById(elementId);
        alert.textContent = message;
        alert.className = `alert alert-${type} show`;

        if (duration) {
            setTimeout(() => {
                alert.classList.remove('show');
            }, duration);
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    AdminSpace.init();
});
