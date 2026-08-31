<?php

/**
 * Description générale : Contrôleur public de l'inscription et de l'authentification.
 * Rôle : Préparer les formulaires, valider les informations de compte et gérer la session utilisateur.
 * Tâches : Afficher et traiter l'inscription, puis accueillir ultérieurement la connexion et la déconnexion.
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
        $csrfToken = $this->readPostString('csrf_token');
        $errors = $this->validateRegistration($values, $password, $confirmation, $honeypot, $csrfToken);
        $userModel = new UserModel($this->database);

        if ($errors === []) {
            if ($userModel->pseudoExists($values['pseudo'])) {
                $errors['pseudo'] = 'Ce nom d’utilisateur est déjà utilisé.';
            }

            if ($userModel->emailExists($values['email'])) {
                $errors['email'] = 'Cette adresse électronique est déjà utilisée.';
            }
        }

        if ($errors !== []) {
            $this->renderRegisterForm($values, $errors, null);
            return;
        }

        $created = $userModel->create([
            'pseudo' => $values['pseudo'],
            'email' => $values['email'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        if (!$created) {
            $errors['form'] = 'Le compte ne peut pas être créé pour le moment.';
            $this->renderRegisterForm($values, $errors, null);
            return;
        }

        $this->session->enregistrerMessageTemporaire(
            'success',
            'Votre compte a été créé. Vous pouvez maintenant vous connecter.'
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
        if ($this->session->estUtilisateurConnecte()) {
            $this->redirect('dashboard');
        }

        $destination = $this->sanitizeDestination($this->readGetString('destination'));
        $this->renderLoginForm(
            ['login' => '', 'destination' => $destination],
            [],
            $this->session->recupererMessageTemporaire('success')
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
        $csrfToken = $this->readPostString('csrf_token');
        $destination = $this->sanitizeDestination($this->readPostString('destination'));
        $errors = [];

        if (!$this->session->estJetonCsrfValide($csrfToken)) {
            $errors['form'] = 'Le formulaire a expiré. Rechargez la page puis recommencez.';
        }

        if ($login === '' || $password === '') {
            $errors['form'] = 'Saisissez votre identifiant et votre mot de passe.';
        }

        $account = null;

        if ($errors === []) {
            $userModel = new UserModel($this->database);
            $account = $userModel->findByLogin($login, str_contains($login, '@'));

            if ($account === null
                || !isset($account['id'], $account['password_hash'])
                || !is_string($account['password_hash'])
                || !password_verify($password, $account['password_hash'])
            ) {
                $errors['form'] = 'L’identifiant ou le mot de passe est incorrect.';
            }
        }

        if ($errors !== [] || $account === null) {
            $this->renderLoginForm(['login' => $login, 'destination' => $destination], $errors, null);
            return;
        }

        $this->session->connecterUtilisateur((int) $account['id']);
        $this->session->enregistrerMessageTemporaire('success', 'Vous êtes maintenant connecté.');
        $this->redirect($destination);
    }

    /**
     * Rôle : Fermer complètement la session authentifiée puis revenir à l'accueil.
     * Paramètres : Aucun, le jeton de sécurité est lu dans la requête POST.
     * Retour : Aucun, une redirection vers l'accueil est envoyée.
     */
    public function logout(): void
    {
        $csrfToken = $this->readPostString('csrf_token');

        if (!$this->session->estUtilisateurConnecte()
            || !$this->session->estJetonCsrfValide($csrfToken)
        ) {
            $this->session->enregistrerMessageTemporaire('notice', 'La déconnexion ne peut pas être confirmée.');
            $this->redirect('home');
        }

        $this->session->deconnecterUtilisateur();
        $this->session->enregistrerMessageTemporaire('success', 'Vous êtes maintenant déconnecté.');
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
     * Rôle : Lire une valeur POST simple sans accepter de tableau inattendu.
     * Paramètres : Nom du champ demandé.
     * Retour : Valeur reçue ou chaîne vide lorsqu'elle est absente ou invalide.
     */
    private function readPostString(string $name): string
    {
        if (!isset($_POST[$name]) || !is_string($_POST[$name])) {
            return '';
        }

        return trim($_POST[$name]);
    }

    /**
     * Rôle : Lire une valeur GET simple sans accepter de tableau inattendu.
     * Paramètres : Nom du champ demandé.
     * Retour : Valeur reçue ou chaîne vide lorsqu'elle est absente ou invalide.
     */
    private function readGetString(string $name): string
    {
        if (!isset($_GET[$name]) || !is_string($_GET[$name])) {
            return '';
        }

        return trim($_GET[$name]);
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
     * Paramètres : Valeurs publiques, mot de passe, confirmation, champ anti-robot et jeton CSRF.
     * Retour : Erreurs indexées par champ, éventuellement vides.
     */
    private function validateRegistration(
        array &$values,
        string $password,
        string $confirmation,
        string $honeypot,
        string $csrfToken
    ): array {
        $errors = [];
        $values['pseudo'] = trim((string) $values['pseudo']);
        $values['email'] = mb_strtolower(trim((string) $values['email']));

        if (!$this->session->estJetonCsrfValide($csrfToken)) {
            $errors['form'] = 'Le formulaire a expiré. Rechargez la page puis recommencez.';
        }

        if ($honeypot !== '') {
            $errors['form'] = 'La demande ne peut pas être traitée.';
        }

        if (mb_strlen($values['pseudo']) < 3 || mb_strlen($values['pseudo']) > 30) {
            $errors['pseudo'] = 'Le nom d’utilisateur doit contenir entre 3 et 30 caractères.';
        } elseif (preg_match('/^[A-Za-z0-9_-]+$/D', $values['pseudo']) !== 1) {
            $errors['pseudo'] = 'Utilisez uniquement des lettres, chiffres, tirets ou tirets bas.';
        }

        if (mb_strlen($values['email']) > 254
            || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Saisissez une adresse électronique valide de 254 caractères au maximum.';
        }

        if (!$this->isStrongPassword($password)) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
        }

        if ($confirmation === '' || !hash_equals($password, $confirmation)) {
            $errors['password_confirmation'] = 'La confirmation doit être identique au mot de passe.';
        }

        return $errors;
    }

    /**
     * Rôle : Vérifier la robustesse minimale obligatoire d'un mot de passe.
     * Paramètres : Mot de passe à contrôler.
     * Retour : true lorsque toutes les règles sont respectées, sinon false.
     */
    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }
}
