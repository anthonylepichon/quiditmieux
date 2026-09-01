/**
 * Description générale : Redirection de l’état de connexion réussie.
 * Rôle : Laisser le message de confirmation visible brièvement avant d’ouvrir la destination protégée.
 * Tâches : Lire uniquement l’adresse interne préparée par le serveur puis déclencher la navigation.
 * Liens avec les autres fichiers : Est chargé par login.php pour l’état LOGIN-03.
 */

const qdmLoginConfirmation = document.querySelector('[data-login-redirect-url]');

if (qdmLoginConfirmation instanceof HTMLElement) {
    const qdmLoginRedirectUrl = qdmLoginConfirmation.dataset.loginRedirectUrl;

    if (typeof qdmLoginRedirectUrl === 'string' && qdmLoginRedirectUrl !== '') {
        /**
         * Rôle : Ouvrir la destination protégée après le court affichage de confirmation.
         * Paramètres : Aucun.
         * Retour : Aucun.
         */
        window.setTimeout(function redirectAfterLogin() {
            window.location.assign(qdmLoginRedirectUrl);
        }, 1500);
    }
}
