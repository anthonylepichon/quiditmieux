<?php

/**
 * Description générale : Test des références de photographies d'une annonce.
 * Rôle : Vérifier qu'une référence de photographie peut être ajoutée puis supprimée.
 * Tâches : Créer une annonce, ajouter une référence, la relire, la supprimer puis nettoyer les données.
 * Liens : Utilise Database, ListingModel, PhotoModel et le lanceur de tests.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\core\Database;
use App\models\ListingModel;
use App\models\PhotoModel;

date_default_timezone_set('Europe/Paris');

$configurationPhotoModel = require __DIR__ . '/../../private/database-secret.php';
$databasePhotoModel = new Database($configurationPhotoModel);
$listingModelPhoto = new ListingModel($databasePhotoModel);
$photoModelTested = new PhotoModel($databasePhotoModel);
$listingIdPhotoModel = null;
$photoIdPhotoModel = null;

$lanceurTests->verifierEgalite(
    true,
    $databasePhotoModel->isConnected(),
    'La base doit être disponible pour tester les photographies'
);

if ($databasePhotoModel->isConnected()) {
    $photoModelOwner = $databasePhotoModel->fetchOne(
        'SELECT id FROM `UTILISATEUR` ORDER BY id ASC LIMIT 1'
    );
    $photoModelOwnerAvailable = is_array($photoModelOwner) && isset($photoModelOwner['id']);

    $lanceurTests->verifierEgalite(
        true,
        $photoModelOwnerAvailable,
        'Un utilisateur doit être disponible pour créer l’annonce temporaire'
    );

    if ($photoModelOwnerAvailable) {
        try {
            $photoModelSuffix = str_replace('.', '-', uniqid('', true));
            $listingIdPhotoModel = $listingModelPhoto->createListing(
                (int) $photoModelOwner['id'],
                'TEST_PHOTOGRAPHIE_' . $photoModelSuffix,
                'Annonce temporaire pour tester les références de photographies.',
                'Bon état',
                90,
                (new DateTime('+2 days'))->format('Y-m-d H:i:s'),
                1
            );

            $photoModelListingCreated = is_int($listingIdPhotoModel)
                && $listingIdPhotoModel > 0;
            $lanceurTests->verifierEgalite(
                true,
                $photoModelListingCreated,
                'L’annonce nécessaire au test doit être créée'
            );

            if ($photoModelListingCreated) {
                $photoModelFilename = 'test-photo-' . $photoModelSuffix . '.jpg';
                $photoModelAdded = $photoModelTested->addPhoto(
                    $listingIdPhotoModel,
                    $photoModelFilename,
                    1
                );
                $photoModelRow = $databasePhotoModel->fetchOne(
                    'SELECT id FROM `PHOTOGRAPHIE`'
                    . ' WHERE annonce_id = :listing_id AND ref_fichier = :filename LIMIT 1',
                    [
                        'listing_id' => $listingIdPhotoModel,
                        'filename' => $photoModelFilename,
                    ]
                );
                $photoIdPhotoModel = is_array($photoModelRow) && isset($photoModelRow['id'])
                    ? (int) $photoModelRow['id']
                    : null;

                $lanceurTests->verifierEgalite(
                    true,
                    $photoModelAdded && is_int($photoIdPhotoModel),
                    'Une référence de photographie doit pouvoir être ajoutée'
                );

                $photoModelDeleted = is_int($photoIdPhotoModel)
                    && $photoModelTested->deleteFromListing(
                        $photoIdPhotoModel,
                        $listingIdPhotoModel
                    );
                $photoModelAfterDeletion = is_int($photoIdPhotoModel)
                    ? $databasePhotoModel->fetchOne(
                        'SELECT id FROM `PHOTOGRAPHIE` WHERE id = :photo_id LIMIT 1',
                        ['photo_id' => $photoIdPhotoModel]
                    )
                    : false;

                $lanceurTests->verifierEgalite(
                    true,
                    $photoModelDeleted && $photoModelAfterDeletion === null,
                    'La référence ajoutée doit pouvoir être supprimée'
                );
            }
        } finally {
            if (is_int($listingIdPhotoModel)) {
                $databasePhotoModel->execute(
                    'DELETE FROM `PHOTOGRAPHIE` WHERE annonce_id = :listing_id',
                    ['listing_id' => $listingIdPhotoModel]
                );
                $listingModelPhoto->deleteListing($listingIdPhotoModel);
            }
        }
    }
}
