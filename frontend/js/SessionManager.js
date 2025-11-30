

/**
 * Gestionnaire de session utilisateur côté client
 * Gère l'authentification, le stockage du token et l'état utilisateur
 */


class SessionManager {
    static STORAGE_KEY = 'ecoride_user';

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
        return null;
    }

    /**
     * Enregistre la session après connexion réussie
     * @param {Object} userData - Données utilisateur de l'API (id, pseudo, email, credit, token)
     */
    static setUser(userData) {
        try {
            // Stocker les données utilisateur (sans le token)
            const pseudo = userData.pseudo || userData.username || (userData.email ? userData.email.split('@')[0] : 'Utilisateur');
            const userToStore = {
                utilisateur_id: userData.utilisateur_id,
                pseudo: pseudo,
                email: userData.email,
                credit: userData.credit,
                type_utilisateur: userData.type_utilisateur || 'standard'
            };

            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(userToStore));

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
     * Initialise le SessionManager (appelé au chargement de la page)
     * Peut être utilisé pour nettoyer les tokens expirés
     * 
     * NOTE: On ne nettoie plus automatiquement les tokens expirés ici
     * car l'API serveur fera la validation vraiment nécessaire.
     * On laisse l'utilisateur avoir une meilleure UX
     */
    static initialize() {
        // Les tokens expirés seront nettoyés quand on essaie de les utiliser (401 API)
        // pour une meilleure UX - afficher le menu de connexion d'abord
    }
}

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', () => {
    SessionManager.initialize();
    
    // Initialiser aussi le header si la fonction existe
    if (typeof initializeAuthMenu === 'function') {
        initializeAuthMenu();
    }
});
