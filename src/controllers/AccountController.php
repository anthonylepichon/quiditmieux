<?php

/**
 * Description générale : Contrôleur du compte de l'utilisateur connecté.
 * Rôle : Afficher et traiter la modification sécurisée du compte utilisateur. Cela empêche qu'une donnée de compte invalide ou sensible soit enregistrée, exposée ou utilisée pour ouvrir une session.
 * Tâches : Protéger l'accès, valider le formulaire et demander au modèle de modifier le compte.
 * Liens avec les autres fichiers : Étend Controller.php, utilise UserModel.php et affiche account.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\models\UserModel;

class AccountController extends Controller
{
    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Afficher le formulaire privé avec les informations actuelles du compte. L'utilisateur peut ainsi vérifier son pseudo et son adresse sans que son mot de passe ou son empreinte soient renvoyés dans la page.
     * Paramètres : Aucun.
     * Retour : Aucun, le formulaire est affiché ou une redirection est envoyée.
     */
    public function showAccountForm(): void
    {
        // L'accès au compte est réservé à l'utilisateur authentifié par la session.
        $userId = $this->requireConnectedUser('account_form');
        if ($userId === null) {
            return;
        }
        // Le modèle fournit uniquement les informations réaffichables du compte.
        $account = (new UserModel($this->database))->getAccount($userId);

        if ($account === false) {
            $this->renderAccountForm(
                ['pseudo' => '', 'email' => ''],
                ['form' => 'Les informations du compte sont momentanément indisponibles.'],
                null
            );
            return;
        }

        if ($account === null) {
            $this->session->deconnectUser();
            $this->redirect('home');
            return;
        }
        $this->renderAccountForm(
            ['pseudo' => (string) $account['pseudo'], 'email' => (string) $account['email']],
            [],
            $this->session->getFlashMessage('success')
        );
    }

    /**
     * Rôle : Valider puis modifier l'identité et éventuellement le mot de passe du compte connecté. Cela empêche qu'une donnée de compte invalide ou sensible soit enregistrée, exposée ou utilisée pour ouvrir une session.
     * Paramètres : Aucun, les informations sont lues dans la requête POST.
     * Retour : Aucun, le formulaire est réaffiché ou une redirection est envoyée.
     */
    public function updateAccount(): void
    {
        // L'identité du compte à modifier provient de la session, jamais d'un champ de formulaire.
        $userId = $this->requireConnectedUser('account_form');
        if ($userId === null) {
            return;
        }

        // Les mots de passe sont lus séparément et ne seront jamais renvoyés au template.
        $values = [
            // NATIF PHP : trim() retire les espaces placés au début et à la fin du texte ; il normalise ici une valeur reçue avant son contrôle.
            'pseudo' => trim($this->readPostString('pseudo')),
            // NATIF PHP : mb_strtolower() convertit un texte UTF-8 en minuscules ; il normalise ici la comparaison sans perdre les caractères accentués.
            'email' => mb_strtolower(trim($this->readPostString('email'))),
        ];
        $currentPassword = $this->readPostString('current_password');
        $newPassword = $this->readPostString('new_password');
        $confirmation = $this->readPostString('new_password_confirmation');
        $errors = $this->validateAccountValues($values, $currentPassword, $newPassword, $confirmation);
        $model = new UserModel($this->database);
        $account = $model->getAccount($userId);

        if ($account === false) {
            $this->renderAccountForm(
                $values,
                ['form' => 'Les informations du compte sont momentanément indisponibles.'],
                null
            );
            return;
        }

        if ($account === null) {
            $this->session->deconnectUser();
            $this->redirect('home');
            return;
        }

        if ($currentPassword !== '') {
            $passwordIsValid = $model->verifyPassword($userId, $currentPassword);

            if ($passwordIsValid === null) {
                $errors['form'] = 'Les informations du compte sont momentanément indisponibles.';
            } elseif (!$passwordIsValid) {
                $errors['current_password'] = 'Le mot de passe actuel est incorrect.';
            }
        }

        if ($errors === []) {
            $pseudoExists = $model->pseudoExists($values['pseudo'], $userId);
            $emailExists = $model->emailExists($values['email'], $userId);

            if ($pseudoExists === null || $emailExists === null) {
                $errors['form'] = 'Les informations du compte sont momentanément indisponibles.';
            }

            if ($pseudoExists === true) {
                $errors['pseudo'] = 'Ce pseudo est déjà utilisé.';
            }

            if ($emailExists === true) {
                $errors['email'] = 'Cette adresse électronique est déjà utilisée.';
            }
        }

        if ($errors !== []) {
            $this->renderAccountForm($values, $errors, null);
            return;
        }

        $passwordToUpdate = null;

        if ($newPassword !== '') {
            $passwordToUpdate = $newPassword;
        }

        $accountUpdated = $model->updateAccount(
            $userId,
            $values['pseudo'],
            $values['email'],
            $passwordToUpdate
        );

        if ($accountUpdated === null) {
            $this->renderAccountForm(
                $values,
                ['form' => 'Le nouveau mot de passe ne peut pas être sécurisé pour le moment.'],
                null
            );
            return;
        }

        if (!$accountUpdated) {
            $this->renderAccountForm(
                $values,
                ['form' => 'Les modifications ne peuvent pas être enregistrées pour le moment.'],
                null
            );
            return;
        }

        $this->session->connectUser($userId);
        $this->session->setFlashMessage('success', 'Les champs de mot de passe ont été vidés après l’enregistrement.');
        $this->redirect('account_form');
    }

    /**
     * Rôle : Afficher le formulaire de compte sans réafficher les mots de passe reçus. Cela empêche qu'une donnée de compte invalide ou sensible soit enregistrée, exposée ou utilisée pour ouvrir une session.
     * Paramètres : Valeurs publiques, erreurs et message de réussite éventuel.
     * Retour : Aucun.
     */
    private function renderAccountForm(array $values, array $errors, ?string $successMessage): void
    {
        // Le message global est déterminé ici afin que le template n'interprète pas les erreurs métier.
        $this->render('pages/account.php', [
            'values' => $values,
            'errors' => $errors,
            'success_message' => $successMessage,
            'alert' => $this->buildAccountAlert($errors, $successMessage),
            'csrf_token' => $this->session->getCsrfToken(),
        ]);
    }

    /**
     * Rôle : Préparer le message de synthèse adapté à l'état du formulaire de compte. L'utilisateur comprend ainsi si la modification a réussi, si des champs sont invalides ou si le compte est indisponible.
     * Paramètres : Erreurs de validation et message temporaire de réussite éventuel.
     * Retour : Variante, titre, contenu et rôle ARIA de l'alerte à afficher.
     */
    private function buildAccountAlert(array $errors, ?string $successMessage): array
    {
        if ($errors !== []) {
            $errorKeys = array_keys($errors);
            sort($errorKeys);
            $title = 'Vérifiez les informations';
            $message = 'Plusieurs champs doivent être corrigés avant l’enregistrement.';
            $newPasswordKeys = array_diff($errorKeys, ['new_password', 'new_password_confirmation']);

            if ($errorKeys === ['email', 'pseudo']
                && $errors['pseudo'] === 'Ce pseudo est déjà utilisé.'
                && $errors['email'] === 'Cette adresse électronique est déjà utilisée.'
            ) {
                $title = 'Informations déjà utilisées';
                $message = 'Choisissez un autre pseudo et une autre adresse électronique.';
            } elseif ($errorKeys === ['current_password']
                && $errors['current_password'] === 'Le mot de passe actuel est incorrect.'
            ) {
                $title = 'Vérification impossible';
                $message = 'Le mot de passe actuel indiqué est incorrect.';
            } elseif ($newPasswordKeys === []) {
                $title = 'Nouveau mot de passe invalide';
                $message = 'Respectez les règles indiquées et confirmez exactement le nouveau mot de passe.';
            } elseif ($errorKeys === ['form']) {
                $title = 'Vérification impossible';
                $message = (string) $errors['form'];
            }

            return [
                'variant' => 'error',
                'title' => $title,
                'message' => $message,
                'role' => 'alert',
                'heading' => 'Mon compte',
                'illustrated' => true,
            ];
        }

        if ($successMessage !== null) {
            return [
                'variant' => 'success',
                'title' => 'Votre compte a été mis à jour',
                'message' => $successMessage,
                'role' => 'status',
                'heading' => 'Compte mis à jour',
                'illustrated' => true,
            ];
        }

        return [
            'variant' => 'info',
            'title' => 'Protégez vos modifications',
            'message' => 'Votre mot de passe actuel est requis pour enregistrer toute modification.',
            'role' => 'note',
            'heading' => 'Mon compte',
            'illustrated' => false,
        ];
    }

    /**
     * Rôle : Appliquer les règles de validation des informations modifiables du compte. Cela empêche qu'une donnée de compte invalide ou sensible soit enregistrée, exposée ou utilisée pour ouvrir une session.
     * Paramètres : Valeurs publiques, mot de passe actuel, nouveau mot de passe et confirmation.
     * Retour : Erreurs indexées par champ, éventuellement vides.
     */
    private function validateAccountValues(
        array $values,
        string $currentPassword,
        string $newPassword,
        string $confirmation
    ): array {
        $errors = [];

        if (!$this->isSubmittedCsrfTokenValid()) {
            $errors['form'] = 'Plusieurs champs doivent être corrigés avant l’enregistrement.';
        }

        if (!$this->isValidPseudo($values['pseudo'])) {
            $errors['pseudo'] = 'Format du pseudo invalide.';
        }

        if (!$this->isValidEmail($values['email'])) {
            $errors['email'] = 'Adresse électronique invalide.';
        }

        if ($currentPassword === '') {
            $errors['current_password'] = 'Le mot de passe actuel est requis.';
        }

        if ($newPassword !== '' || $confirmation !== '') {
            if (!$this->isStrongPassword($newPassword)) {
                $errors['new_password'] = 'Le nouveau mot de passe ne respecte pas les règles requises.';
            }

            // NATIF PHP : hash_equals() compare deux chaînes en limitant les attaques basées sur le temps de réponse ; il sécurise ici la vérification du jeton.
            if ($confirmation === '' || !hash_equals($newPassword, $confirmation)) {
                $errors['new_password_confirmation'] = 'La confirmation ne correspond pas au nouveau mot de passe.';
            }
        }

        return $errors;
    }

}
