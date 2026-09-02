/**
 * Description générale : Comportement du consentement affiché sur le formulaire d'inscription.
 * Rôle : Empêcher visuellement l'envoi tant que la politique de confidentialité n'est pas acceptée.
 * Tâches : Observer la case dédiée et actualiser l'état du bouton de création du compte.
 * Liens avec les autres fichiers : Est chargé par register.php et complète la validation serveur d'AuthController.php.
 */

const privacyPolicyCheckbox = document.querySelector('[data-privacy-policy]');
const registerButton = document.querySelector('[data-register-button]');

/**
 * Rôle : Synchroniser l'état du bouton avec celui de la case de confidentialité.
 * Paramètres : Aucun.
 * Retour : Aucun.
 */
function updateRegisterButton() {
    if (privacyPolicyCheckbox === null || registerButton === null) {
        return;
    }

    const policyIsAccepted = privacyPolicyCheckbox.checked;

    registerButton.disabled = !policyIsAccepted;
    registerButton.setAttribute('aria-disabled', String(!policyIsAccepted));
}

if (privacyPolicyCheckbox !== null && registerButton !== null) {
    privacyPolicyCheckbox.addEventListener('change', updateRegisterButton);
    updateRegisterButton();
}
