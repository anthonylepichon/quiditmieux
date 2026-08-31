<?php

/**
 * Description générale : Modèle des photographies associées aux annonces.
 * Rôle : Fournir la photographie principale nécessaire aux cartes d'annonce.
 * Tâches : Déclarer la table PHOTOGRAPHIE et sélectionner la première photographie dans l'ordre.
 * Liens avec les autres fichiers : Étend Model.php et complète les résultats affichés par home.php.
 */

namespace App\models;

use App\core\Database;
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
     * Rôle : Initialiser le modèle avec le gestionnaire de base de données et des données éventuelles.
     * Paramètres : Gestionnaire de base de données et tableau facultatif de données de photographie.
     * Retour : Aucun.
     */
    public function __construct(Database $database, array $data = [])
    {
        parent::__construct($database, $data);
    }

    /**
     * Rôle : Obtenir la première photographie ordonnée de chaque annonce demandée.
     * Paramètres : Liste d'identifiants d'annonces.
     * Retour : Références de fichiers indexées par identifiant d'annonce.
     */
    public function getPrimaryPhotos(array $listingIds): array
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
     * Retour : Liste des photographies avec un nom de fichier sûr.
     */
    public function getListingPhotos(int $listingId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT id, ref_fichier, ordre FROM `PHOTOGRAPHIE`'
            . ' WHERE annonce_id = :listing_id ORDER BY ordre ASC, id ASC',
            ['listing_id' => $listingId]
        );
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
     * Rôle : Nettoyer une liste d'identifiants avant de construire une requête préparée.
     * Paramètres : Identifiants candidats.
     * Retour : Identifiants entiers strictement positifs et uniques.
     */
    private function normalizeIdentifiers(array $identifiers): array
    {
        $normalizedIdentifiers = [];

        foreach ($identifiers as $identifier) {
            if (filter_var($identifier, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                continue;
            }

            $normalizedIdentifiers[(int) $identifier] = (int) $identifier;
        }

        return array_values($normalizedIdentifiers);
    }
}
