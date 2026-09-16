<?php

/**
 * Description générale : Test de la modification d'une annonce après une enchère.
 * Rôle : Vérifier que le vendeur ne peut plus modifier une annonce dès qu'une enchère existe.
 * Tâches : Créer une annonce, enregistrer une enchère, contrôler le refus puis nettoyer les données.
 * Liens : Utilise Database, ListingModel, BidModel et le lanceur de tests.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\core\Database;
use App\models\BidModel;
use App\models\ListingModel;

date_default_timezone_set('Europe/Paris');

$configurationEditAfterBid = require __DIR__ . '/../../private/database-secret.php';
$databaseEditAfterBid = new Database($configurationEditAfterBid);
$listingModelEditAfterBid = new ListingModel($databaseEditAfterBid);
$bidModelEditAfterBid = new BidModel($databaseEditAfterBid);
$listingIdEditAfterBid = null;

$lanceurTests->verifierEgalite(
    true,
    $databaseEditAfterBid->isConnected(),
    'La base doit être disponible pour tester le verrouillage de la modification'
);

if ($databaseEditAfterBid->isConnected()) {
    $editAfterBidUsers = $databaseEditAfterBid->fetchAll(
        'SELECT id FROM `UTILISATEUR` ORDER BY id ASC LIMIT 2'
    );
    $editAfterBidUsersAvailable = is_array($editAfterBidUsers)
        && count($editAfterBidUsers) === 2;

    $lanceurTests->verifierEgalite(
        true,
        $editAfterBidUsersAvailable,
        'Un vendeur et un enchérisseur doivent être disponibles'
    );

    if ($editAfterBidUsersAvailable) {
        $editAfterBidOwnerId = (int) $editAfterBidUsers[0]['id'];
        $editAfterBidBidderId = (int) $editAfterBidUsers[1]['id'];

        try {
            $listingIdEditAfterBid = $listingModelEditAfterBid->createListing(
                $editAfterBidOwnerId,
                'TEST_MODIFICATION_ENCHERE_' . uniqid(),
                'Annonce temporaire pour tester le blocage de la modification.',
                'Bon état',
                100,
                (new DateTime('+2 days'))->format('Y-m-d H:i:s'),
                1
            );

            $listingEditAfterBidCreated = is_int($listingIdEditAfterBid)
                && $listingIdEditAfterBid > 0;
            $lanceurTests->verifierEgalite(
                true,
                $listingEditAfterBidCreated,
                'L’annonce nécessaire au test doit être créée'
            );

            if ($listingEditAfterBidCreated) {
                $bidEditAfterBidCreated = $bidModelEditAfterBid->placeBid(
                    $editAfterBidBidderId,
                    $listingIdEditAfterBid,
                    101,
                    new DateTime()
                );
                $lanceurTests->verifierEgalite(
                    true,
                    $bidEditAfterBidCreated,
                    'Une enchère valide doit être enregistrée avant le contrôle'
                );

                $ownerCanEditAfterBid = $listingModelEditAfterBid->canBeModifiedBy(
                    $listingIdEditAfterBid,
                    $editAfterBidOwnerId,
                    new DateTime()
                );
                $lanceurTests->verifierEgalite(
                    false,
                    $ownerCanEditAfterBid,
                    'Le vendeur ne doit plus pouvoir modifier l’annonce après une enchère'
                );
                $lanceurTests->verifierEgalite(
                    'bid',
                    $listingModelEditAfterBid->getLastManagementRestriction(),
                    'Le motif du refus doit indiquer la présence d’une enchère'
                );
            }
        } finally {
            if (is_int($listingIdEditAfterBid)) {
                $databaseEditAfterBid->execute(
                    'DELETE FROM `ENCHERE` WHERE annonce_id = :listing_id',
                    ['listing_id' => $listingIdEditAfterBid]
                );
                $listingModelEditAfterBid->deleteListing($listingIdEditAfterBid);
            }
        }
    }
}
