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
        // Un éventuel message de réussite est lu une seule fois après une inscription terminée.
        $this->renderRegisterForm([], [], $this->session->getFlashMessage('success'));
    }

    /**
     * Rôle : Valider une demande d'inscription et créer le compte lorsqu'elle est conforme.
     * Paramètres : Aucun, les informations sont lues dans la requête POST.
     * Retour : Aucun, le formulaire est réaffiché ou une redirection est envoyée.
     */
    public function register(): void
    {
        // Seules les valeurs réaffichables sont conservées : aucun mot de passe n'est renvoyé au template.
        $values = [
            'pseudo' => $this->readPostString('pseudo'),
            'email' => $this->readPostString('email'),
        ];
        $password = $this->readPostString('password');
        $confirmation = $this->readPostString('password_confirmation');
        $honeypot = $this->readPostString('website');
        $privacyPolicyAccepted = $this->readPostString('privacy_policy') === '1';
        $values['privacy_policy'] = $privacyPolicyAccepted;
        // Les règles communes sont appliquées avant toute consultation ou écriture en base.
        $errors = $this->validateRegistration(
            $values,
            $password,
            $confirmation,
            $honeypot,
            $privacyPolicyAccepted
        );
        $userModel = new UserModel($this->database);

        // L'unicité du pseudo et de l'adresse est vérifiée uniquement lorsque le format est déjà valide.
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

        // En cas d'erreur, le formulaire est réaffiché sans créer de compte.
        if ($errors !== []) {
            $this->renderRegisterForm($values, $errors, null);
            return;
        }

        // Le modèle reçoit uniquement des données validées pour créer le compte et hacher le mot de passe.
        $created = $userModel->createAccount($values['pseudo'], $values['email'], $password);

        if (!$created) {
            $errors['form'] = 'Veuillez réessayer. Aucun mécanisme technique n’est affiché.';
            $this->renderRegisterForm($values, $errors, null);
            return;
        }

        // Le message temporaire sera affiché sur la page vers laquelle le navigateur est redirigé.
        $this->session->setFlashMessage(
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
        // Ce message existe après une connexion réussie, juste avant la redirection vers la destination demandée.
        $successMessage = $this->session->getFlashMessage('login_success');

        // Un utilisateur déjà connecté n'a pas besoin de revenir au formulaire de connexion.
        if ($this->session->isUserConnected() && $successMessage === null) {
            $this->redirect('dashboard');
        }

        // La destination est réduite à une liste de routes internes autorisées.
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
        // Les champs de connexion sont lus comme du texte afin de refuser les structures inattendues.
        $login = $this->readPostString('login');
        $password = $this->readPostString('password');
        $destination = $this->sanitizeDestination($this->readPostString('destination'));
        $errors = [];

        // Le formulaire doit provenir de la session courante.
        if (!$this->isSubmittedCsrfTokenValid()) {
            $errors['form'] = 'Vérifiez vos informations puis essayez de nouveau.';
        }

        if ($login === '' || $password === '') {
            $errors['form'] = 'Vérifiez vos informations puis essayez de nouveau.';
        }

        // Le compte reste nul tant que les contrôles ou l'authentification n'ont pas réussi.
        $account = null;

        // Le modèle compare le mot de passe fourni avec le hash stocké en base.
        if ($errors === []) {
            $userModel = new UserModel($this->database);
            $account = $userModel->authenticate($login, $password);

            if ($account === false) {
                $errors['form'] = 'Vérifiez vos informations puis essayez de nouveau.';
                $account = null;
            // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
            } elseif ($account === null || !isset($account['id'])) {
                $errors['form'] = 'Vérifiez vos informations puis essayez de nouveau.';
            }
        }

        // Les erreurs restent génériques pour ne pas révéler si un compte précis existe.
        if ($errors !== [] || $account === null) {
            $this->renderLoginForm(['login' => $login, 'destination' => $destination], $errors, null);
            return;
        }

        // La session est renouvelée par Session avant de mémoriser l'identifiant authentifié.
        $this->session->connectUser((int) $account['id']);
        $this->session->setFlashMessage('login_success', 'Redirection en cours…');
        $this->redirect('login_form', ['destination' => $destination]);
    }

    /**
     * Rôle : Fermer complètement la session authentifiée puis revenir à l'accueil.
     * Paramètres : Aucun, le jeton de sécurité est lu dans la requête POST.
     * Retour : Aucun, une redirection vers l'accueil est envoyée.
     */
    public function logout(): void
    {
        // Une déconnexion non authentifiée ou sans jeton valide est simplement renvoyée vers l'accueil.
        if (!$this->session->isUserConnected()
            || !$this->isSubmittedCsrfTokenValid()
        ) {
            $this->redirect('home');
        }

        // Les données de session sont retirées avant le retour à la page publique.
        $this->session->deconnectUser();
        $this->redirect('home');
    }

    /**
     * Rôle : Afficher le formulaire de connexion avec ses valeurs réaffichables et ses messages.
     * Paramètres : Valeurs publiques, erreurs de validation et message temporaire éventuel.
     * Retour : Aucun.
     */
    private function renderLoginForm(array $values, array $errors, ?string $successMessage): void
    {
        // Le jeton CSRF est fourni au formulaire afin que son envoi POST puisse être vérifié.
        $this->render('pages/login.php', [
            'values' => $values,
            'errors' => $errors,
            'success_message' => $successMessage,
            'alert' => $this->buildLoginAlert($errors, $successMessage),
            'csrf_token' => $this->session->getCsrfToken(),
        ]);
    }

    /**
     * Rôle : Afficher le formulaire d'inscription avec ses valeurs réaffichables et ses messages.
     * Paramètres : Valeurs publiques, erreurs de validation et message temporaire éventuel.
     * Retour : Aucun.
     */
    private function renderRegisterForm(array $values, array $errors, ?string $successMessage): void
    {
        // Le jeton CSRF est fourni au formulaire afin que son envoi POST puisse être vérifié.
        $this->render('pages/register.php', [
            'values' => $values,
            'errors' => $errors,
            'success_message' => $successMessage,
            'alert' => $this->buildRegisterAlert($errors, $successMessage),
            'csrf_token' => $this->session->getCsrfToken(),
        ]);
    }

    /**
     * Rôle : Préparer le message de synthèse adapté à l'état du formulaire de connexion.
     * Paramètres : Erreurs de validation et message temporaire de connexion éventuel.
     * Retour : Variante, titre, contenu et rôle ARIA de l'alerte à afficher.
     */
    private function buildLoginAlert(array $errors, ?string $successMessage): array
    {
        // La décision et le texte restent dans le contrôleur ; le template se limite à les présenter.
        if ($errors !== []) {
            return [
                'variant' => 'error',
                'title' => 'Identifiants invalides',
                'message' => 'Vérifiez vos informations puis essayez de nouveau.',
                'role' => 'alert',
                'heading' => 'Se connecter',
                'field_error' => 'Identifiants invalides.',
                'illustrated' => true,
            ];
        }

        if ($successMessage !== null) {
            return [
                'variant' => 'success',
                'title' => 'Connexion réussie',
                'message' => $successMessage,
                'role' => 'status',
                'heading' => 'Bienvenue !',
                'field_error' => '',
                'illustrated' => true,
            ];
        }

        return [
            'variant' => 'info',
            'title' => 'Connexion sécurisée',
            'message' => 'Utilisez votre pseudo ou votre adresse électronique.',
            'role' => 'note',
            'heading' => 'Se connecter',
            'field_error' => '',
            'illustrated' => false,
        ];
    }

    /**
     * Rôle : Préparer le message de synthèse adapté à l'état du formulaire d'inscription.
     * Paramètres : Erreurs de validation et message temporaire d'inscription éventuel.
     * Retour : Variante, titre, contenu et rôle ARIA de l'alerte à afficher.
     */
    private function buildRegisterAlert(array $errors, ?string $successMessage): array
    {
        // Les erreurs par champ restent disponibles pour le formulaire, tandis que leur synthèse est décidée ici.
        if ($errors !== []) {
            $errorKeys = array_keys($errors);
            sort($errorKeys);
            $title = 'Vérifiez les informations indiquées';
            $message = 'Plusieurs champs doivent être corrigés avant l’inscription.';

            if ($errorKeys === ['form']) {
                $title = 'Votre inscription n’a pas pu être validée';
                $message = 'Veuillez réessayer. Aucun mécanisme technique n’est affiché.';
            } elseif ($errorKeys === ['pseudo'] && $errors['pseudo'] === 'Ce pseudo est déjà utilisé.') {
                $title = 'Ce pseudo est déjà utilisé';
                $message = 'Choisissez un autre pseudo pour continuer.';
            } elseif ($errorKeys === ['email']
                && $errors['email'] === 'Cette adresse électronique est déjà utilisée.'
            ) {
                $title = 'Cette adresse électronique est déjà utilisée';
                $message = 'Utilisez une autre adresse électronique pour continuer.';
            } elseif (array_diff($errorKeys, ['password', 'password_confirmation']) === []
                && (isset($errors['password']) || isset($errors['password_confirmation']))
            ) {
                $title = 'Le mot de passe doit être corrigé';
                $message = 'Respectez toutes les règles et saisissez une confirmation identique.';
            }

            return [
                'variant' => 'error',
                'title' => $title,
                'message' => $message,
                'role' => 'alert',
                'heading' => 'Créer votre compte',
                'illustrated' => true,
            ];
        }

        if ($successMessage !== null) {
            return [
                'variant' => 'success',
                'title' => 'Compte créé avec succès',
                'message' => 'Vous pouvez maintenant vous connecter et participer aux enchères.',
                'role' => 'status',
                'heading' => 'Bienvenue !',
                'illustrated' => true,
            ];
        }

        return [
            'variant' => 'info',
            'title' => 'Un compte, simplement',
            'message' => 'Renseignez les quatre champs puis vérifiez les règles indiquées.',
            'role' => 'note',
            'heading' => 'Créer votre compte',
            'illustrated' => false,
        ];
    }

    /**
     * Rôle : Limiter une destination de connexion aux routes internes protégées prévues.
     * Paramètres : Nom de destination candidat.
     * Retour : Route interne autorisée ou tableau de bord par défaut.
     */
    private function sanitizeDestination(string $destination): string
    {
        // Cette liste blanche empêche une redirection vers une adresse ou une route non prévue.
        $allowedDestinations = ['dashboard', 'listing_create_form', 'account_form'];

        // NATIF PHP : in_array() recherche une valeur dans un tableau ; il vérifie ici que la donnée appartient à la liste autorisée.
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
        // Les erreurs sont indexées par champ pour être affichées au bon emplacement dans le template.
        $errors = [];
        // NATIF PHP : trim() retire les espaces placés au début et à la fin du texte ; il normalise ici une valeur reçue avant son contrôle.
        $values['pseudo'] = trim((string) $values['pseudo']);
        // NATIF PHP : mb_strtolower() convertit un texte UTF-8 en minuscules ; il normalise ici la comparaison sans perdre les caractères accentués.
        $values['email'] = mb_strtolower(trim((string) $values['email']));

        // Les contrôles de sécurité et de format sont réalisés avant toute création de compte.
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

        // NATIF PHP : hash_equals() compare deux chaînes en limitant les attaques basées sur le temps de réponse ; il sécurise ici la vérification du jeton.
        if ($confirmation === '' || !hash_equals($password, $confirmation)) {
            $errors['password_confirmation'] = 'La confirmation ne correspond pas.';
        }

        if (!$privacyPolicyAccepted) {
            $errors['privacy_policy'] = 'Vous devez accepter la politique de confidentialité.';
        }

        return $errors;
    }

}


