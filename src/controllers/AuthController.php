<?php

/**
 * Description générale : Contrôleur public de l'inscription et de l'authentification.
 * Rôle : Préparer les formulaires, coordonner le modèle de compte et gérer la session utilisateur.
 * Tâches : Afficher et traiter l'inscription, la connexion et la déconnexion.
 * Liens avec les autres fichiers : Étend Controller.php, utilise UserModel.php, Session.php et les templates register.php et login.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\models\UserModel;

class AuthController extends Controller
{
    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Préparer et afficher le formulaire public d'inscription.
     * Paramètres : Aucun.
     * Retour : Aucun, le template d'inscription est affiché.
     */
    public function showRegisterForm(): void
    {
        $this->renderRegisterForm([], [], $this->session->recupererMessageTemporaire('success'));
    }

    /**
     * Rôle : Valider une demande d'inscription et créer le compte lorsqu'elle est conforme.
     * Paramètres : Aucun, les informations sont lues dans la requête POST.
     * Retour : Aucun, le formulaire est réaffiché ou une redirection est envoyée.
     */
    public function register(): void
    {
        $values = [
            'pseudo' => $this->readPostString('pseudo'),
            'email' => $this->readPostString('email'),
        ];
        $password = $this->readPostString('password');
        $confirmation = $this->readPostString('password_confirmation');
        $honeypot = $this->readPostString('website');
        $privacyPolicyAccepted = $this->readPostString('privacy_policy') === '1';
        $values['privacy_policy'] = $privacyPolicyAccepted;
        $errors = $this->validateRegistration(
            $values,
            $password,
            $confirmation,
            $honeypot,
            $privacyPolicyAccepted
        );
        $userModel = new UserModel($this->database);

        if ($errors === []) {
            $pseudoExists = $userModel->pseudoExists($values['pseudo']);
            $emailExists = $userModel->emailExists($values['email']);

            if ($pseudoExists === null || $emailExists === null) {
                $errors['form'] = 'Veuillez réessayer. Aucun mécanisme technique n’est affiché.';
            }

            if ($pseudoExists === true) {
                $errors['pseudo'] = 'Ce pseudo est déjà utilisé.';
            }

            if ($emailExists === true) {
                $errors['email'] = 'Cette adresse électronique est déjà utilisée.';
            }
        }

        if ($errors !== []) {
            $this->renderRegisterForm($values, $errors, null);
            return;
        }

        $created = $userModel->createAccount($values['pseudo'], $values['email'], $password);

        if (!$created) {
            $errors['form'] = 'Veuillez réessayer. Aucun mécanisme technique n’est affiché.';
            $this->renderRegisterForm($values, $errors, null);
            return;
        }

        $this->session->enregistrerMessageTemporaire(
            'success',
            'Vous pouvez maintenant vous connecter et participer aux enchères.'
        );
        $this->redirect('register_form');
    }

    /**
     * Rôle : Préparer et afficher le formulaire public de connexion.
     * Paramètres : Aucun, la destination interne éventuelle est lue dans la requête GET.
     * Retour : Aucun, le template de connexion est affiché.
     */
    public function showLoginForm(): void
    {
        $successMessage = $this->session->recupererMessageTemporaire('login_success');

        if ($this->session->estUtilisateurConnecte() && $successMessage === null) {
            $this->redirect('dashboard');
        }

        $destination = $this->sanitizeDestination($this->readGetString('destination'));
        $this->renderLoginForm(
            ['login' => '', 'destination' => $destination],
            [],
            $successMessage
        );
    }

    /**
     * Rôle : Vérifier les identifiants reçus, ouvrir la session et choisir une destination sûre.
     * Paramètres : Aucun, les identifiants sont lus dans la requête POST.
     * Retour : Aucun, une redirection interne ou le formulaire est envoyé.
     */
    public function login(): void
    {
        $login = $this->readPostString('login');
        $password = $this->readPostString('password');
        $destination = $this->sanitizeDestination($this->readPostString('destination'));
        $errors = [];

        if (!$this->isSubmittedCsrfTokenValid()) {
            $errors['form'] = 'Vérifiez vos informations puis essayez de nouveau.';
        }

        if ($login === '' || $password === '') {
            $errors['form'] = 'Vérifiez vos informations puis essayez de nouveau.';
        }

        $account = null;

        if ($errors === []) {
            $userModel = new UserModel($this->database);
            $account = $userModel->authenticate($login, $password);

            if ($account === false) {
                $errors['form'] = 'Vérifiez vos informations puis essayez de nouveau.';
                $account = null;
            } elseif ($account === null || !isset($account['id'])) {
                $errors['form'] = 'Vérifiez vos informations puis essayez de nouveau.';
            }
        }

        if ($errors !== [] || $account === null) {
            $this->renderLoginForm(['login' => $login, 'destination' => $destination], $errors, null);
            return;
        }

        $this->session->connecterUtilisateur((int) $account['id']);
        $this->session->enregistrerMessageTemporaire('login_success', 'Redirection en cours…');
        $this->redirect('login_form', ['destination' => $destination]);
    }

    /**
     * Rôle : Fermer complètement la session authentifiée puis revenir à l'accueil.
     * Paramètres : Aucun, le jeton de sécurité est lu dans la requête POST.
     * Retour : Aucun, une redirection vers l'accueil est envoyée.
     */
    public function logout(): void
    {
        if (!$this->session->estUtilisateurConnecte()
            || !$this->isSubmittedCsrfTokenValid()
        ) {
            $this->redirect('home');
        }

        $this->session->deconnecterUtilisateur();
        $this->redirect('home');
    }

    /**
     * Rôle : Afficher le formulaire de connexion avec ses valeurs réaffichables et ses messages.
     * Paramètres : Valeurs publiques, erreurs de validation et message temporaire éventuel.
     * Retour : Aucun.
     */
    private function renderLoginForm(array $values, array $errors, ?string $successMessage): void
    {
        $this->render('pages/login.php', [
            'values' => $values,
            'errors' => $errors,
            'success_message' => $successMessage,
            'csrf_token' => $this->session->obtenirJetonCsrf(),
        ]);
    }

    /**
     * Rôle : Afficher le formulaire d'inscription avec ses valeurs réaffichables et ses messages.
     * Paramètres : Valeurs publiques, erreurs de validation et message temporaire éventuel.
     * Retour : Aucun.
     */
    private function renderRegisterForm(array $values, array $errors, ?string $successMessage): void
    {
        $this->render('pages/register.php', [
            'values' => $values,
            'errors' => $errors,
            'success_message' => $successMessage,
            'csrf_token' => $this->session->obtenirJetonCsrf(),
        ]);
    }

    /**
     * Rôle : Limiter une destination de connexion aux routes internes protégées prévues.
     * Paramètres : Nom de destination candidat.
     * Retour : Route interne autorisée ou tableau de bord par défaut.
     */
    private function sanitizeDestination(string $destination): string
    {
        $allowedDestinations = ['dashboard', 'listing_create_form', 'account_form'];

        if (in_array($destination, $allowedDestinations, true)) {
            return $destination;
        }

        return 'dashboard';
    }

    /**
     * Rôle : Appliquer toutes les règles de validation du formulaire d'inscription.
     * Paramètres : Valeurs publiques, mot de passe, confirmation, champ anti-robot et acceptation de la politique.
     * Retour : Erreurs indexées par champ, éventuellement vides.
     */
    private function validateRegistration(
        array &$values,
        string $password,
        string $confirmation,
        string $honeypot,
        bool $privacyPolicyAccepted
    ): array {
        $errors = [];
        $values['pseudo'] = trim((string) $values['pseudo']);
        $values['email'] = mb_strtolower(trim((string) $values['email']));

        if (!$this->isSubmittedCsrfTokenValid()) {
            $errors['form'] = 'Veuillez réessayer. Aucun mécanisme technique n’est affiché.';
        }

        if ($honeypot !== '') {
            $errors['form'] = 'Veuillez réessayer. Aucun mécanisme technique n’est affiché.';
        }

        if (!$this->isValidPseudo($values['pseudo'])) {
            $errors['pseudo'] = 'Format du pseudo invalide.';
        }

        if (!$this->isValidEmail($values['email'])) {
            $errors['email'] = 'Adresse électronique invalide.';
        }

        if (!$this->isStrongPassword($password)) {
            $errors['password'] = 'Le mot de passe ne respecte pas les règles.';
        }

        if ($confirmation === '' || !hash_equals($password, $confirmation)) {
            $errors['password_confirmation'] = 'La confirmation ne correspond pas.';
        }

        if (!$privacyPolicyAccepted) {
            $errors['privacy_policy'] = 'Vous devez accepter la politique de confidentialité.';
        }

        return $errors;
    }

}


