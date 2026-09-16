<?php

/**
 * Description générale : Test de la règle interdisant au vendeur de participer à sa vente.
 * Rôle : Vérifier que le propriétaire d'une annonce ne peut pas enchérir sur celle-ci.
 * Tâches : Créer une annonce active, contrôler le refus et vérifier son motif.
 * Liens : Utilise Database, ListingModel et le lanceur de tests.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\core\Database;
use App\models\ListingModel;

date_default_timezone_set('Europe/Paris');

$configurationOwnerBid = require __DIR__ . '/../../private/database-secret.php';
$databaseOwnerBid = new Database($configurationOwnerBid);
$listingModelOwnerBid = new ListingModel($databaseOwnerBid);
$listingIdOwnerBid = null;

$lanceurTests->verifierEgalite(
    true,
    $databaseOwnerBid->isConnected(),
    'La base doit être disponible pour tester la règle du propriétaire'
);

if ($databaseOwnerBid->isConnected()) {
    $ownerBid = $databaseOwnerBid->fetchOne(
        'SELECT id FROM `UTILISATEUR` ORDER BY id ASC LIMIT 1'
    );
    $ownerBidAvailable = is_array($ownerBid) && isset($ownerBid['id']);

    $lanceurTests->verifierEgalite(
        true,
        $ownerBidAvailable,
        'Un propriétaire doit être disponible pour réaliser le test'
    );

    if ($ownerBidAvailable) {
        $ownerBidId = (int) $ownerBid['id'];

        try {
            $listingIdOwnerBid = $listingModelOwnerBid->createListing(
                $ownerBidId,
                'TEST_PROPRIETAIRE_' . uniqid(),
                'Annonce temporaire appartenant au vendeur testé.',
                'Bon état',
                80,
                (new DateTime('+2 days'))->format('Y-m-d H:i:s'),
                1
            );

            $listingOwnerBidCreated = is_int($listingIdOwnerBid) && $listingIdOwnerBid > 0;
            $lanceurTests->verifierEgalite(
                true,
                $listingOwnerBidCreated,
                'L’annonce du propriétaire doit être créée'
            );

            if ($listingOwnerBidCreated) {
                $ownerCanBid = $listingModelOwnerBid->canReceiveParticipationFrom(
                    $listingIdOwnerBid,
                    $ownerBidId,
                    new DateTime()
                );

                $lanceurTests->verifierEgalite(
                    false,
                    $ownerCanBid,
                    'Le propriétaire ne doit pas pouvoir enchérir sur sa propre annonce'
                );
                $lanceurTests->verifierEgalite(
                    'owner',
                    $listingModelOwnerBid->getLastParticipationRestriction(),
                    'Le motif du refus doit identifier la règle du propriétaire'
                );
            }
        } finally {
            if (is_int($listingIdOwnerBid)) {
                $listingModelOwnerBid->deleteListing($listingIdOwnerBid);
            }
        }
    }
}
