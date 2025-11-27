/**
 * Gestionnaire de session utilisateur côté client
 * Gère l'authentification, le stockage du token et l'état utilisateur
 */
class SessionManager {
    static STORAGE_KEY = 'ecoride_user';
    static TOKEN_KEY = 'ecoride_token';

    /**
     * Retourne true si l'utilisateur est connecté
     */
    static isAuthenticated() {
        return this.getUser() !== null;
    }

    /**
     * Récupère les données de l'utilisateur stockées
     * @returns {Object|null}
     */
    static getUser() {
        try {
            const data = localStorage.getItem(this.STORAGE_KEY);
            return data ? JSON.parse(data) : null;
        } catch (e) {
            console.error('Erreur parsing localStorage:', e);
            return null;
        }
    }

    /**
     * Récupère le token JWT stocké
     * @returns {string|null}
     */
    static getToken() {
        return localStorage.getItem(this.TOKEN_KEY);
    }

    /**
     * Enregistre la session après connexion réussie
     * @param {Object} userData - Données utilisateur de l'API (id, pseudo, email, credit, token)
     */
    static setUser(userData) {
        try {
            // Stocker les données utilisateur (sans le token)
            const userToStore = {
                utilisateur_id: userData.utilisateur_id,
                pseudo: userData.pseudo,
                email: userData.email,
                credit: userData.credit,
                type_utilisateur: userData.type_utilisateur || 'standard'
            };

            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(userToStore));

            // Stocker le token séparément
            if (userData.token) {
                localStorage.setItem(this.TOKEN_KEY, userData.token);
            }

            // Dispatcher un événement personnalisé pour que les composants réagissent
            window.dispatchEvent(new CustomEvent('userLoggedIn', { detail: userToStore }));
        } catch (e) {
            console.error('Erreur stockage localStorage:', e);
        }
    }

    /**
     * Efface la session (déconnexion)
     */
    static clearSession() {
        try {
            localStorage.removeItem(this.STORAGE_KEY);
            localStorage.removeItem(this.TOKEN_KEY);
            
            // Dispatcher un événement pour que les composants réagissent
            window.dispatchEvent(new CustomEvent('userLoggedOut'));
        } catch (e) {
            console.error('Erreur nettoyage localStorage:', e);
        }
    }

    /**
     * Appelle l'API de déconnexion et efface la session
     * @returns {Promise<boolean>}
     */
    static async logout() {
        try {
            const response = await fetch('/api/auth/logout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });

            if (response.ok) {
                this.clearSession();
                return true;
            }
        } catch (error) {
            console.error('Erreur logout:', error);
        }

        // Même si l'API échoue, on efface la session locale
        this.clearSession();
        return false;
    }

    /**
     * Récupère les headers HTTP pour les requêtes authentifiées
     * @returns {Object}
     */
    static getAuthHeaders() {
        const headers = { 'Content-Type': 'application/json' };
        const token = this.getToken();
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        return headers;
    }

    /**
     * Effectue une requête fetch authentifiée
     * @param {string} url
     * @param {Object} options
     * @returns {Promise<Response>}
     */
    static async fetchAuthenticated(url, options = {}) {
        const mergedOptions = {
            ...options,
            headers: {
                ...this.getAuthHeaders(),
                ...(options.headers || {})
            }
        };

        return fetch(url, mergedOptions);
    }

    /**
     * Décode manuellement le JWT pour lire les données (sans vérifier la signature)
     * À utiliser UNIQUEMENT pour l'affichage, la validation doit se faire côté serveur
     * @returns {Object|null}
     */
    static decodeToken() {
        const token = this.getToken();
        if (!token) return null;

        try {
            const parts = token.split('.');
            if (parts.length !== 3) return null;

            // Décoder le payload (partie 2)
            const payload = parts[1];
            const decoded = JSON.parse(atob(payload));
            
            return decoded;
        } catch (e) {
            console.error('Erreur décodage token:', e);
            return null;
        }
    }

    /**
     * Vérifie si le token a expiré
     * @returns {boolean}
     */
    static isTokenExpired() {
        const decoded = this.decodeToken();
        if (!decoded || !decoded.exp) return true;

        const now = Math.floor(Date.now() / 1000);
        return decoded.exp < now;
    }

    /**
     * Initialise le SessionManager (appelé au chargement de la page)
     * Peut être utilisé pour nettoyer les tokens expirés
     */
    static initialize() {
        if (this.isTokenExpired()) {
            console.warn('Token expiré, nettoyage de la session');
            this.clearSession();
        }
    }
}

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', () => {
    SessionManager.initialize();
});
