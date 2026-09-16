<?php

/**
 * Description générale : Test du consentement demandé pendant l'inscription.
 * Rôle : Vérifier qu'aucun compte n'est créé lorsque la politique de confidentialité n'est pas acceptée.
 * Tâches : Préparer un formulaire valide sans consentement, appeler le contrôleur puis contrôler la base et le message.
 * Liens : Utilise Database, Session, AuthController et le lanceur de tests.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\controllers\AuthController;
use App\core\Database;
use App\core\Session;

$configurationPrivacyConsent = require __DIR__ . '/../../private/database-secret.php';
$databasePrivacyConsent = new Database($configurationPrivacyConsent);
$sessionPrivacyConsent = new Session();

$lanceurTests->verifierEgalite(
    true,
    $databasePrivacyConsent->isConnected(),
    'La base doit être disponible pour tester le consentement'
);

if ($databasePrivacyConsent->isConnected()) {
    $sessionPrivacyConsent->startSession();
    $privacyConsentSuffix = str_replace('.', '', uniqid('', true));
    $privacyConsentPseudo = 'Test' . substr($privacyConsentSuffix, -10);
    $privacyConsentEmail = 'test-' . $privacyConsentSuffix . '@example.test';
    $privacyConsentToken = $sessionPrivacyConsent->getCsrfToken();
    $privacyConsentPostBackup = $_POST;

    try {
        $_POST = [
            'pseudo' => $privacyConsentPseudo,
            'email' => $privacyConsentEmail,
            'password' => 'Test-consent9!',
            'password_confirmation' => 'Test-consent9!',
            'website' => '',
            'csrf_token' => $privacyConsentToken,
            // Le champ privacy_policy est volontairement absent.
        ];

        ob_start();
        (new AuthController($databasePrivacyConsent, $sessionPrivacyConsent))->register();
        $privacyConsentOutput = (string) ob_get_clean();

        $accountWithoutConsent = $databasePrivacyConsent->fetchOne(
            'SELECT id FROM `UTILISATEUR` WHERE email = :email LIMIT 1',
            ['email' => $privacyConsentEmail]
        );

        $lanceurTests->verifierEgalite(
            null,
            $accountWithoutConsent,
            'Aucun compte ne doit être créé sans acceptation de la politique'
        );
        $lanceurTests->verifierEgalite(
            true,
            str_contains(
                $privacyConsentOutput,
                'Vous devez accepter la politique de confidentialité.'
            ),
            'Le formulaire doit expliquer pourquoi l’inscription est refusée'
        );
    } finally {
        $_POST = $privacyConsentPostBackup;
        $databasePrivacyConsent->execute(
            'DELETE FROM `UTILISATEUR` WHERE email = :email',
            ['email' => $privacyConsentEmail]
        );
    }
}
