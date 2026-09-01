<?php

/**
 * Description générale : Modèle des photographies associées aux annonces.
 * Rôle : Fournir la photographie principale nécessaire aux cartes d'annonce.
 * Tâches : Déclarer la table PHOTOGRAPHIE et sélectionner la première photographie dans l'ordre.
 * Liens avec les autres fichiers : Étend Model.php et complète les résultats affichés par home.php.
 */

namespace App\models;

use App\core\Model;

class PhotoModel extends Model
{
    // ====================
    // ATTRIBUTS
    // ====================

    protected string $tableName = 'PHOTOGRAPHIE';
    protected string $primaryKeyName = 'id';
    protected array $writableFields = [
        'annonce_id',
        'ref_fichier',
        'ordre',
    ];

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Associer une nouvelle photographie à une annonce dans son ordre d'affichage.
     * Paramètres : Identifiant de l'annonce, nom sécurisé du fichier et position d'affichage.
     * Retour : true lorsque la photographie est enregistrée, sinon false.
     */
    public function addPhoto(int $listingId, string $filename, int $order): bool
    {
        return $this->create([
            'annonce_id' => $listingId,
            'ref_fichier' => $filename,
            'ordre' => $order,
        ]);
    }

    /**
     * Rôle : Obtenir la première photographie ordonnée de chaque annonce demandée.
     * Paramètres : Liste d'identifiants d'annonces.
     * Retour : Références indexées par annonce ou false en cas d'erreur SQL.
     */
    public function getPrimaryPhotos(array $listingIds): array|false
    {
        $identifiers = $this->normalizeIdentifiers($listingIds);

        if ($identifiers === []) {
            return [];
        }

        $parameters = [];
        $placeholders = [];

        foreach ($identifiers as $index => $identifier) {
            $parameterName = 'listing_' . $index;
            $placeholders[] = ':' . $parameterName;
            $parameters[$parameterName] = $identifier;
        }

        $sql = 'SELECT annonce_id, ref_fichier, ordre'
            . ' FROM `PHOTOGRAPHIE`'
            . ' WHERE annonce_id IN (' . implode(', ', $placeholders) . ')'
            . ' ORDER BY annonce_id ASC, ordre ASC';
        $rows = $this->database->fetchAll($sql, $parameters);

        if ($rows === false) {
            return false;
        }

        $primaryPhotos = [];

        foreach ($rows as $row) {
            if (!isset($row['annonce_id'], $row['ref_fichier']) || !is_string($row['ref_fichier'])) {
                continue;
            }

            $identifier = (int) $row['annonce_id'];

            if (!isset($primaryPhotos[$identifier])) {
                $primaryPhotos[$identifier] = basename($row['ref_fichier']);
            }
        }

        return $primaryPhotos;
    }

    /**
     * Rôle : Récupérer toutes les photographies d'une annonce dans leur ordre d'affichage.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Liste des photographies ou false en cas d'erreur SQL.
     */
    public function getListingPhotos(int $listingId): array|false
    {
        $rows = $this->database->fetchAll(
            'SELECT id, ref_fichier, ordre FROM `PHOTOGRAPHIE`'
            . ' WHERE annonce_id = :listing_id ORDER BY ordre ASC, id ASC',
            ['listing_id' => $listingId]
        );

        if ($rows === false) {
            return false;
        }

        $photos = [];

        foreach ($rows as $row) {
            if (!isset($row['id'], $row['ref_fichier'], $row['ordre']) || !is_string($row['ref_fichier'])) {
                continue;
            }

            $photos[] = [
                'id' => (int) $row['id'],
                'filename' => basename($row['ref_fichier']),
                'order' => (int) $row['ordre'],
            ];
        }

        return $photos;
    }

    /**
     * Rôle : Supprimer une photographie uniquement lorsqu'elle appartient à l'annonce indiquée.
     * Paramètres : Identifiants de la photographie et de l'annonce.
     * Retour : true lorsque la requête est exécutée, sinon false.
     */
    public function deleteFromListing(int $photoId, int $listingId): bool
    {
        return $this->database->execute(
            'DELETE FROM `PHOTOGRAPHIE` WHERE id = :photo_id AND annonce_id = :listing_id',
            ['photo_id' => $photoId, 'listing_id' => $listingId]
        );
    }

    /**
     * Rôle : Refermer les éventuels écarts d'ordre après la suppression de photographies.
     * Paramètres : Identifiant de l'annonce et photographies ordonnées.
     * Retour : true lorsque tous les ordres sont enregistrés, sinon false.
     */
    public function reorder(int $listingId, array $photos): bool
    {
        foreach ($photos as $index => $photo) {
            if (!isset($photo['id'])) {
                return false;
            }

            if ((int) $photo['order'] === $index + 1) {
                continue;
            }

            if (!$this->database->execute(
                'UPDATE `PHOTOGRAPHIE` SET ordre = :photo_order'
                . ' WHERE id = :photo_id AND annonce_id = :listing_id',
                ['photo_order' => $index + 1, 'photo_id' => (int) $photo['id'], 'listing_id' => $listingId]
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rôle : Conserver uniquement des identifiants entiers strictement positifs et uniques.
     * Paramètres : Valeurs candidates à normaliser.
     * Retour : Liste d'identifiants utilisables dans une requête préparée.
     */
    private function normalizeIdentifiers(array $identifiers): array
    {
        $normalizedIdentifiers = [];

        foreach ($identifiers as $identifier) {
            if (!is_int($identifier) && !is_string($identifier)) {
                continue;
            }

            if (filter_var($identifier, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                continue;
            }

            $normalizedIdentifiers[(int) $identifier] = (int) $identifier;
        }

        return array_values($normalizedIdentifiers);
    }
}

