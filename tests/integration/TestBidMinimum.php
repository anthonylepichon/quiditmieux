<?php

/**
 * Description générale : Test du montant minimal d'une enchère.
 * Rôle : Vérifier qu'un montant qui n'est pas supérieur au prix courant est refusé.
 * Tâches : Créer une annonce temporaire, évaluer une enchère insuffisante puis supprimer l'annonce.
 * Liens : Utilise Database, ListingModel, BidModel et le lanceur de tests.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\core\Database;
use App\models\BidModel;
use App\models\ListingModel;

date_default_timezone_set('Europe/Paris');

$configurationBidMinimum = require __DIR__ . '/../../private/database-secret.php';
$databaseBidMinimum = new Database($configurationBidMinimum);
$listingModelBidMinimum = new ListingModel($databaseBidMinimum);
$bidModelMinimum = new BidModel($databaseBidMinimum);
$listingIdBidMinimum = null;

$lanceurTests->verifierEgalite(
    true,
    $databaseBidMinimum->isConnected(),
    'La base doit être disponible pour tester le montant minimal'
);

if ($databaseBidMinimum->isConnected()) {
    $ownerBidMinimum = $databaseBidMinimum->fetchOne(
        'SELECT id FROM `UTILISATEUR` ORDER BY id ASC LIMIT 1'
    );
    $ownerBidMinimumAvailable = is_array($ownerBidMinimum) && isset($ownerBidMinimum['id']);

    $lanceurTests->verifierEgalite(
        true,
        $ownerBidMinimumAvailable,
        'Un utilisateur doit être disponible pour créer l’annonce temporaire'
    );

    if ($ownerBidMinimumAvailable) {
        try {
            $listingIdBidMinimum = $listingModelBidMinimum->createListing(
                (int) $ownerBidMinimum['id'],
                'TEST_MONTANT_MINIMUM_' . uniqid(),
                'Annonce temporaire pour contrôler le montant minimal.',
                'Bon état',
                100,
                (new DateTime('+2 days'))->format('Y-m-d H:i:s'),
                1
            );

            $listingBidMinimumCreated = is_int($listingIdBidMinimum) && $listingIdBidMinimum > 0;
            $lanceurTests->verifierEgalite(
                true,
                $listingBidMinimumCreated,
                'L’annonce nécessaire au test doit être créée'
            );

            if ($listingBidMinimumCreated) {
                $bidEvaluation = $bidModelMinimum->evaluateBidAmountInEuros(
                    $listingIdBidMinimum,
                    100
                );

                $lanceurTests->verifierEgalite(
                    false,
                    $bidEvaluation['accepted'] ?? null,
                    'Une enchère égale au prix de départ doit être refusée'
                );
                $lanceurTests->verifierEgalite(
                    101,
                    $bidEvaluation['minimum_amount_in_euros'] ?? null,
                    'Le montant minimal attendu doit être supérieur d’un euro'
                );
            }
        } finally {
            if (is_int($listingIdBidMinimum)) {
                $listingModelBidMinimum->deleteListing($listingIdBidMinimum);
            }
        }
    }
}
