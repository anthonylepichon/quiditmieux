<?php

/**
 * Description générale : Modèle des enchères déposées sur les annonces.
 * Rôle : Fournir les prix courants nécessaires à la consultation des annonces.
 * Tâches : Déclarer la table ENCHERE et calculer le meilleur montant pour plusieurs annonces.
 * Liens avec les autres fichiers : Étend Model.php et complète les résultats fournis par ListingModel.php.
 */

namespace App\models;

use App\core\Database;
use App\core\Model;

class BidModel extends Model
{
    // ====================
    // ATTRIBUTS
    // ====================

    protected string $tableName = 'ENCHERE';
    protected string $primaryKeyName = 'id';
    protected array $writableFields = [
        'utilisateur_id',
        'annonce_id',
        'montant',
        'date_heure_enchere',
    ];

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Initialiser le modèle avec le gestionnaire de base de données et des données éventuelles.
     * Paramètres : Gestionnaire de base de données et tableau facultatif de données d'enchère.
     * Retour : Aucun.
     */
    public function __construct(Database $database, array $data = [])
    {
        parent::__construct($database, $data);
    }

    /**
     * Rôle : Calculer le prix courant de chaque annonce demandée.
     * Paramètres : Liste d'identifiants d'annonces et prix de départ indexés par annonce.
     * Retour : Prix courants indexés par identifiant d'annonce.
     */
    public function getCurrentPrices(array $listingIds, array $startingPrices): array
    {
        $identifiers = $this->normalizeIdentifiers($listingIds);
        $currentPrices = [];

        foreach ($identifiers as $identifier) {
            if (isset($startingPrices[$identifier]) && is_numeric($startingPrices[$identifier])) {
                $currentPrices[$identifier] = (float) $startingPrices[$identifier];
            }
        }

        if ($identifiers === []) {
            return $currentPrices;
        }

        $parameters = [];
        $placeholders = [];

        foreach ($identifiers as $index => $identifier) {
            $parameterName = 'listing_' . $index;
            $placeholders[] = ':' . $parameterName;
            $parameters[$parameterName] = $identifier;
        }

        $sql = 'SELECT annonce_id, MAX(montant) AS best_bid'
            . ' FROM `ENCHERE`'
            . ' WHERE annonce_id IN (' . implode(', ', $placeholders) . ')'
            . ' GROUP BY annonce_id';
        $rows = $this->database->fetchAll($sql, $parameters);

        foreach ($rows as $row) {
            if (!isset($row['annonce_id'], $row['best_bid']) || !is_numeric($row['best_bid'])) {
                continue;
            }

            $identifier = (int) $row['annonce_id'];
            $currentPrices[$identifier] = (float) $row['best_bid'];
        }

        return $currentPrices;
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


