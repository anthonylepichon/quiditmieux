<?php

/**
 * Description générale : Test de la date de fin d'une vente.
 * Rôle : Vérifier qu'une annonce terminée ne peut plus recevoir de participation.
 * Tâches : Créer une annonce déjà terminée, contrôler le refus puis supprimer l'annonce.
 * Liens : Utilise Database, ListingModel et le lanceur de tests.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\core\Database;
use App\models\ListingModel;

date_default_timezone_set('Europe/Paris');

$configurationExpiredListing = require __DIR__ . '/../../private/database-secret.php';
$databaseExpiredListing = new Database($configurationExpiredListing);
$listingModelExpired = new ListingModel($databaseExpiredListing);
$expiredListingId = null;

$lanceurTests->verifierEgalite(
    true,
    $databaseExpiredListing->isConnected(),
    'La base doit être disponible pour tester une vente terminée'
);

if ($databaseExpiredListing->isConnected()) {
    $expiredListingUsers = $databaseExpiredListing->fetchAll(
        'SELECT id FROM `UTILISATEUR` ORDER BY id ASC LIMIT 2'
    );
    $expiredListingUsersAvailable = is_array($expiredListingUsers)
        && count($expiredListingUsers) === 2;

    $lanceurTests->verifierEgalite(
        true,
        $expiredListingUsersAvailable,
        'Deux utilisateurs doivent être disponibles pour réaliser le test'
    );

    if ($expiredListingUsersAvailable) {
        $expiredListingOwnerId = (int) $expiredListingUsers[0]['id'];
        $expiredListingBidderId = (int) $expiredListingUsers[1]['id'];

        try {
            $expiredListingId = $listingModelExpired->createListing(
                $expiredListingOwnerId,
                'TEST_ECHEANCE_' . uniqid(),
                'Annonce temporaire dont la date de fin est dépassée.',
                'Bon état',
                70,
                (new DateTime('-1 hour'))->format('Y-m-d H:i:s'),
                1
            );

            $expiredListingCreated = is_int($expiredListingId) && $expiredListingId > 0;
            $lanceurTests->verifierEgalite(
                true,
                $expiredListingCreated,
                'L’annonce terminée nécessaire au test doit être créée'
            );

            if ($expiredListingCreated) {
                $canBidAfterDeadline = $listingModelExpired->canReceiveParticipationFrom(
                    $expiredListingId,
                    $expiredListingBidderId,
                    new DateTime()
                );

                $lanceurTests->verifierEgalite(
                    false,
                    $canBidAfterDeadline,
                    'Une enchère doit être refusée après la date de fin'
                );
                $lanceurTests->verifierEgalite(
                    'ended',
                    $listingModelExpired->getLastParticipationRestriction(),
                    'Le motif du refus doit indiquer que la vente est terminée'
                );
            }
        } finally {
            if (is_int($expiredListingId)) {
                $listingModelExpired->deleteListing($expiredListingId);
            }
        }
    }
}
