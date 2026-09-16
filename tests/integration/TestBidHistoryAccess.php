<?php

/**
 * Description générale : Test de confidentialité de l'historique des enchères.
 * Rôle : Vérifier qu'un utilisateur qui n'est ni vendeur ni enchérisseur ne voit pas l'historique détaillé.
 * Tâches : Créer une annonce et une enchère, afficher le détail avec un troisième compte puis nettoyer les données.
 * Liens : Utilise Database, Session, ListingModel, BidModel, ListingDetailController et le lanceur de tests.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\controllers\ListingDetailController;
use App\core\Database;
use App\core\Session;
use App\models\BidModel;
use App\models\ListingModel;

date_default_timezone_set('Europe/Paris');

$configurationHistoryAccess = require __DIR__ . '/../../private/database-secret.php';
$databaseHistoryAccess = new Database($configurationHistoryAccess);
$sessionHistoryAccess = new Session();
$listingModelHistoryAccess = new ListingModel($databaseHistoryAccess);
$bidModelHistoryAccess = new BidModel($databaseHistoryAccess);
$listingIdHistoryAccess = null;
$historyAccessGetBackup = $_GET;
$historyAccessErrorHandlerActive = false;

$lanceurTests->verifierEgalite(
    true,
    $databaseHistoryAccess->isConnected(),
    'La base doit être disponible pour tester l’accès à l’historique'
);

if ($databaseHistoryAccess->isConnected()) {
    $historyAccessUsers = $databaseHistoryAccess->fetchAll(
        'SELECT id, pseudo FROM `UTILISATEUR` ORDER BY id ASC LIMIT 3'
    );
    $historyAccessUsersAvailable = is_array($historyAccessUsers)
        && count($historyAccessUsers) === 3;

    $lanceurTests->verifierEgalite(
        true,
        $historyAccessUsersAvailable,
        'Trois utilisateurs doivent être disponibles pour réaliser le test'
    );

    if ($historyAccessUsersAvailable) {
        $historyAccessOwnerId = (int) $historyAccessUsers[0]['id'];
        $historyAccessBidderId = (int) $historyAccessUsers[1]['id'];
        $historyAccessVisitorId = (int) $historyAccessUsers[2]['id'];
        $historyAccessBidderPseudo = (string) $historyAccessUsers[1]['pseudo'];

        try {
            $listingIdHistoryAccess = $listingModelHistoryAccess->createListing(
                $historyAccessOwnerId,
                'TEST_HISTORIQUE_' . uniqid(),
                'Annonce temporaire pour contrôler l’accès à l’historique.',
                'Bon état',
                100,
                (new DateTime('+2 days'))->format('Y-m-d H:i:s'),
                1
            );

            $historyAccessListingCreated = is_int($listingIdHistoryAccess)
                && $listingIdHistoryAccess > 0;
            $lanceurTests->verifierEgalite(
                true,
                $historyAccessListingCreated,
                'L’annonce nécessaire au test doit être créée'
            );

            if ($historyAccessListingCreated) {
                $historyAccessBidCreated = $bidModelHistoryAccess->placeBid(
                    $historyAccessBidderId,
                    $listingIdHistoryAccess,
                    101,
                    new DateTime()
                );
                $lanceurTests->verifierEgalite(
                    true,
                    $historyAccessBidCreated,
                    'Une enchère doit être enregistrée avant le contrôle de l’historique'
                );

                $sessionHistoryAccess->startSession();
                $_SESSION['user_id'] = $historyAccessVisitorId;
                $_GET = ['id' => (string) $listingIdHistoryAccess];

                set_error_handler(static function (): bool {
                    return true;
                });
                $historyAccessErrorHandlerActive = true;
                ob_start();
                (new ListingDetailController(
                    $databaseHistoryAccess,
                    $sessionHistoryAccess
                ))->showDetail();
                $historyAccessOutput = (string) ob_get_clean();
                restore_error_handler();
                $historyAccessErrorHandlerActive = false;

                $lanceurTests->verifierEgalite(
                    false,
                    str_contains($historyAccessOutput, $historyAccessBidderPseudo),
                    'Le pseudo de l’enchérisseur ne doit pas être visible par un simple visiteur'
                );
                $lanceurTests->verifierEgalite(
                    true,
                    str_contains(
                        $historyAccessOutput,
                        'Historique accessible après votre première enchère'
                    ),
                    'La page doit expliquer que l’historique détaillé est protégé'
                );
            }
        } finally {
            if ($historyAccessErrorHandlerActive) {
                restore_error_handler();
            }
            $_GET = $historyAccessGetBackup;
            unset($_SESSION['user_id']);

            if (is_int($listingIdHistoryAccess)) {
                $databaseHistoryAccess->execute(
                    'DELETE FROM `ENCHERE` WHERE annonce_id = :listing_id',
                    ['listing_id' => $listingIdHistoryAccess]
                );
                $listingModelHistoryAccess->deleteListing($listingIdHistoryAccess);
            }
        }
    }
}
