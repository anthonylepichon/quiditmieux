<?php

/**
 * Description générale : Tests d'intégration des huit scénarios prioritaires pour le jury.
 * Rôle : Reproduire les refus et autorisations les plus importants de l'application afin de détecter une régression avant la présentation. Les données créées pour les tests sont supprimées à la fin du fichier.
 * Tâches : Tester les enchères, la modification d'une annonce, le consentement RGPD, la confidentialité de l'historique, les photographies et la réponse 404.
 * Liens : Utilise les modèles, les contrôleurs, le routeur et la base locale de l'application.
 */

// L'autoloader charge les classes de src uniquement lorsqu'elles sont utilisées par un scénario.
require_once __DIR__ . '/../../vendor/autoload.php';

use App\controllers\AuthController;
use App\controllers\ListingDetailController;
use App\core\Database;
use App\core\Router;
use App\core\Session;
use App\models\BidModel;
use App\models\ListingModel;
use App\models\PhotoModel;
date_default_timezone_set('Europe/Paris');

$databaseConfig = require __DIR__ . '/../../private/database-secret.php';
$database = new Database($databaseConfig);
$session = new Session();
$listingModel = new ListingModel($database);
$bidModel = new BidModel($database);
$photoModel = new PhotoModel($database);

$activeListingId = null;
$expiredListingId = null;
$testPhotoId = null;

$lanceurTests->verifierEgalite(
    true,
    $database->isConnected(),
    'La base doit être disponible pour exécuter les scénarios prioritaires'
);

if ($database->isConnected()) {
    // Trois comptes réels sont utilisés pour respecter les clés étrangères : vendeur, enchérisseur et simple visiteur.
    $users = $database->fetchAll(
        'SELECT id, pseudo FROM `UTILISATEUR` ORDER BY id ASC LIMIT 3'
    );
    $usersAvailable = is_array($users) && count($users) === 3;

    $lanceurTests->verifierEgalite(
        true,
        $usersAvailable,
        'Trois comptes de démonstration doivent être disponibles pour les tests'
    );

    if ($usersAvailable) {
        $ownerId = (int) $users[0]['id'];
        $bidderId = (int) $users[1]['id'];
        $visitorId = (int) $users[2]['id'];
        $bidderPseudo = (string) $users[1]['pseudo'];
        $uniqueSuffix = uniqid('', true);

        try {
            // Une annonce active sert aux enchères, à l'historique et aux photographies.
            $activeListingId = $listingModel->createListing(
                $ownerId,
                'TEST_JURY_ACTIF_' . $uniqueSuffix,
                'Annonce temporaire créée par les tests prioritaires.',
                'Bon état',
                100,
                (new DateTime('+2 days'))->format('Y-m-d H:i:s'),
                1
            );

            // Une annonce terminée permet de contrôler la règle d'échéance sans modifier l'annonce active.
            $expiredListingId = $listingModel->createListing(
                $ownerId,
                'TEST_JURY_TERMINE_' . $uniqueSuffix,
                'Annonce temporaire déjà arrivée à échéance.',
                'Bon état',
                80,
                (new DateTime('-1 hour'))->format('Y-m-d H:i:s'),
                1
            );

            $listingsCreated = is_int($activeListingId) && $activeListingId > 0
                && is_int($expiredListingId) && $expiredListingId > 0;

            $lanceurTests->verifierEgalite(
                true,
                $listingsCreated,
                'Les annonces temporaires nécessaires aux scénarios doivent être créées'
            );

            if ($listingsCreated) {
                /*
                 * PRIORITÉ 1 : refus d'une enchère insuffisante.
                 * Le prix de départ est de 100 €, le minimum attendu est donc de 101 €.
                 */
                $bidEvaluation = $bidModel->evaluateBidAmountInEuros($activeListingId, 100);
                $lanceurTests->verifierEgalite(
                    false,
                    $bidEvaluation['accepted'] ?? null,
                    'Une enchère inférieure au minimum doit être refusée'
                );

                /*
                 * PRIORITÉ 2 : refus du propriétaire sur sa propre annonce.
                 */
                $ownerCanBid = $listingModel->canReceiveParticipationFrom(
                    $activeListingId,
                    $ownerId,
                    new DateTime()
                );
                $lanceurTests->verifierEgalite(
                    false,
                    $ownerCanBid,
                    'Le propriétaire ne doit pas pouvoir enchérir sur sa propre annonce'
                );
                $lanceurTests->verifierEgalite(
                    'owner',
                    $listingModel->getLastParticipationRestriction(),
                    'Le refus doit identifier clairement la règle du propriétaire'
                );

                /*
                 * PRIORITÉ 3 : refus après l'échéance.
                 */
                $canBidAfterDeadline = $listingModel->canReceiveParticipationFrom(
                    $expiredListingId,
                    $bidderId,
                    new DateTime()
                );
                $lanceurTests->verifierEgalite(
                    false,
                    $canBidAfterDeadline,
                    'Une enchère doit être refusée après la date de fin'
                );
                $lanceurTests->verifierEgalite(
                    'ended',
                    $listingModel->getLastParticipationRestriction(),
                    'Le refus doit identifier clairement la fin de la vente'
                );

                // Une enchère valide est ensuite ajoutée pour tester le verrouillage et la confidentialité.
                $validBidCreated = $bidModel->placeBid(
                    $bidderId,
                    $activeListingId,
                    101,
                    new DateTime()
                );
                $lanceurTests->verifierEgalite(
                    true,
                    $validBidCreated,
                    'Une enchère valide doit pouvoir être enregistrée pour poursuivre les tests'
                );

                /*
                 * PRIORITÉ 4 : impossibilité de modifier après une enchère.
                 */
                $ownerCanEdit = $listingModel->canBeModifiedBy(
                    $activeListingId,
                    $ownerId,
                    new DateTime()
                );
                $lanceurTests->verifierEgalite(
                    false,
                    $ownerCanEdit,
                    'Le vendeur ne doit plus pouvoir modifier une annonce après la première enchère'
                );
                $lanceurTests->verifierEgalite(
                    'bid',
                    $listingModel->getLastManagementRestriction(),
                    'Le refus de modification doit identifier la présence d\'une enchère'
                );

                /*
                 * PRIORITÉ 5 : inscription refusée sans politique de confidentialité.
                 * Tous les autres champs sont valides afin d'isoler uniquement cette règle.
                 */
                $session->startSession();
                $csrfToken = $session->getCsrfToken();
                $registrationEmail = 'jury-' . str_replace('.', '-', $uniqueSuffix) . '@example.test';
                $postBackup = $_POST;
                $_POST = [
                    'pseudo' => 'Jury' . substr(str_replace('.', '', $uniqueSuffix), -10),
                    'email' => $registrationEmail,
                    'password' => 'Test-jury9!',
                    'password_confirmation' => 'Test-jury9!',
                    'website' => '',
                    'csrf_token' => $csrfToken,
                    // Le champ privacy_policy est volontairement absent.
                ];

                ob_start();
                (new AuthController($database, $session))->register();
                $registrationOutput = (string) ob_get_clean();
                $_POST = $postBackup;

                $createdWithoutConsent = $database->fetchOne(
                    'SELECT id FROM `UTILISATEUR` WHERE email = :email LIMIT 1',
                    ['email' => $registrationEmail]
                );
                $lanceurTests->verifierEgalite(
                    null,
                    $createdWithoutConsent,
                    'Aucun compte ne doit être créé sans acceptation de la politique de confidentialité'
                );
                $lanceurTests->verifierEgalite(
                    true,
                    str_contains($registrationOutput, 'Vous devez accepter la politique de confidentialité.'),
                    'Le formulaire doit expliquer pourquoi l\'inscription est refusée'
                );

                /*
                 * PRIORITÉ 6 : historique invisible pour un simple visiteur connecté
                 * qui n'est ni vendeur ni enchérisseur.
                 */
                $getBackup = $_GET;
                $_GET = ['id' => (string) $activeListingId];
                $_SESSION['user_id'] = $visitorId;

                // L'API de catégories peut être inaccessible dans un terminal isolé ; le détail prévoit déjà un libellé de remplacement.
                set_error_handler(static function (): bool {
                    return true;
                });
                ob_start();
                (new ListingDetailController($database, $session))->showDetail();
                $detailOutput = (string) ob_get_clean();
                restore_error_handler();
                $_GET = $getBackup;
                unset($_SESSION['user_id']);

                $lanceurTests->verifierEgalite(
                    false,
                    str_contains($detailOutput, $bidderPseudo),
                    'Le pseudo de l\'enchérisseur ne doit pas être visible par un simple visiteur'
                );
                $lanceurTests->verifierEgalite(
                    true,
                    str_contains($detailOutput, 'Historique accessible après votre première enchère'),
                    'La page doit signaler que l\'historique détaillé est verrouillé'
                );

                /*
                 * PRIORITÉ 7 : ajout et suppression d'une photographie.
                 * Le test porte sur la référence en base ; aucun faux fichier n'est laissé sur le disque.
                 */
                $photoFilename = 'test-jury-' . str_replace('.', '-', $uniqueSuffix) . '.jpg';
                $photoAdded = $photoModel->addPhoto($activeListingId, $photoFilename, 1);
                $photoRow = $database->fetchOne(
                    'SELECT id FROM `PHOTOGRAPHIE`'
                    . ' WHERE annonce_id = :listing_id AND ref_fichier = :filename LIMIT 1',
                    ['listing_id' => $activeListingId, 'filename' => $photoFilename]
                );
                $testPhotoId = is_array($photoRow) && isset($photoRow['id']) ? (int) $photoRow['id'] : null;

                $lanceurTests->verifierEgalite(
                    true,
                    $photoAdded && is_int($testPhotoId),
                    'Une photographie doit pouvoir être ajoutée à une annonce'
                );

                $photoDeleted = is_int($testPhotoId)
                    && $photoModel->deleteFromListing($testPhotoId, $activeListingId);
                $photoAfterDeletion = is_int($testPhotoId)
                    ? $database->fetchOne(
                        'SELECT id FROM `PHOTOGRAPHIE` WHERE id = :photo_id LIMIT 1',
                        ['photo_id' => $testPhotoId]
                    )
                    : false;
                $lanceurTests->verifierEgalite(
                    true,
                    $photoDeleted && $photoAfterDeletion === null,
                    'La photographie ajoutée doit pouvoir être supprimée de l\'annonce'
                );

                /*
                 * PRIORITÉ 8 : une route inconnue retourne le statut HTTP 404.
                 */
                $router = new Router($database, $session);
                $router->registerRoutes([]);
                http_response_code(200);
                ob_start();
                $router->dispatch('route_inconnue_pour_test', 'GET');
                $notFoundOutput = (string) ob_get_clean();
                $notFoundStatus = http_response_code();
                http_response_code(200);

                $lanceurTests->verifierEgalite(
                    404,
                    $notFoundStatus,
                    'Une route inconnue doit retourner le statut HTTP 404'
                );
                $lanceurTests->verifierEgalite(
                    'Page introuvable.',
                    $notFoundOutput,
                    'Une route inconnue doit afficher un message compréhensible'
                );
            }
        } finally {
            // Le nettoyage est exécuté même si une assertion échoue afin de préserver les données locales.
            if (is_int($activeListingId)) {
                $database->execute(
                    'DELETE FROM `PHOTOGRAPHIE` WHERE annonce_id = :listing_id',
                    ['listing_id' => $activeListingId]
                );
                $database->execute(
                    'DELETE FROM `ENCHERE` WHERE annonce_id = :listing_id',
                    ['listing_id' => $activeListingId]
                );
                $listingModel->deleteListing($activeListingId);
            }

            if (is_int($expiredListingId)) {
                $listingModel->deleteListing($expiredListingId);
            }

            unset($_SESSION['user_id']);
            $_GET = [];
            $_POST = [];
        }
    }
}
