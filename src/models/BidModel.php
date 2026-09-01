<?php

/**
 * Description générale : Modèle des enchères déposées sur les annonces.
 * Rôle : Valider et enregistrer les enchères puis fournir les prix courants des annonces.
 * Tâches : Déclarer la table ENCHERE, appliquer la progression minimale et calculer les meilleurs montants.
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
     * Rôle : Obtenir le montant courant d'une annonce en centimes sans calcul décimal flottant.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Montant courant en centimes ou null si l'annonce est absente ou la requête échoue.
     */
    public function getCurrentAmountInCents(int $listingId): ?int
    {
        $row = $this->database->fetchOne(
            'SELECT COALESCE(MAX(bid.montant), listing.prix_depart) AS current_amount'
            . ' FROM `ANNONCE` listing'
            . ' LEFT JOIN `ENCHERE` bid ON bid.annonce_id = listing.id'
            . ' WHERE listing.id = :listing_id'
            . ' GROUP BY listing.id, listing.prix_depart',
            ['listing_id' => $listingId]
        );

        if ($row === false || $row === null || !isset($row['current_amount'])) {
            return null;
        }

        return $this->decimalAmountToCents((string) $row['current_amount']);
    }

    /**
     * Rôle : Obtenir le prochain montant minimal accepté pour une annonce.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Montant minimal en centimes ou null si le montant courant est indisponible.
     */
    public function getMinimumAmountInCents(int $listingId): ?int
    {
        $currentAmount = $this->getCurrentAmountInCents($listingId);

        if ($currentAmount === null) {
            return null;
        }

        return $currentAmount + 1;
    }

    /**
     * Rôle : Vérifier qu'une proposition atteint le prochain montant minimal de l'annonce.
     * Paramètres : Identifiant de l'annonce et montant proposé en centimes.
     * Retour : Décision métier et minimum attendu, ou null si le montant courant est indisponible.
     */
    public function evaluateBidAmountInCents(int $listingId, int $amountInCents): ?array
    {
        $minimumAmount = $this->getMinimumAmountInCents($listingId);

        if ($minimumAmount === null) {
            return null;
        }

        return [
            'accepted' => $amountInCents >= $minimumAmount,
            'minimum_amount_in_cents' => $minimumAmount,
        ];
    }

    /**
     * Rôle : Calculer le prix courant de chaque annonce demandée.
     * Paramètres : Liste d'identifiants d'annonces et prix de départ indexés par annonce.
     * Retour : Prix courants indexés par annonce ou false en cas d'erreur SQL.
     */
    public function getCurrentPrices(array $listingIds, array $startingPrices): array|false
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

        if ($rows === false) {
            return false;
        }

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
     * Rôle : Obtenir le nombre d'enchères, le meilleur montant et son auteur pour une annonce.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Résumé des enchères ou false en cas d'erreur SQL.
     */
    public function getSummary(int $listingId): array|false
    {
        $sql = 'SELECT COUNT(*) AS bid_count, MAX(montant) AS best_bid'
            . ' FROM `ENCHERE` WHERE annonce_id = :listing_id';
        $summary = $this->database->fetchOne($sql, ['listing_id' => $listingId]);

        if ($summary === false) {
            return false;
        }

        if ($summary === null) {
            return ['bid_count' => 0, 'best_bid' => null, 'best_bidder_id' => null];
        }

        $bestBidderId = null;

        if (isset($summary['best_bid']) && is_numeric($summary['best_bid'])) {
            $winner = $this->database->fetchOne(
                'SELECT utilisateur_id FROM `ENCHERE`'
                . ' WHERE annonce_id = :listing_id AND montant = :best_bid'
                . ' ORDER BY date_heure_enchere ASC, id ASC LIMIT 1',
                ['listing_id' => $listingId, 'best_bid' => $summary['best_bid']]
            );

            if ($winner === false) {
                return false;
            }

            if ($winner !== null && isset($winner['utilisateur_id'])) {
                $bestBidderId = (int) $winner['utilisateur_id'];
            }
        }

        $bidCount = 0;
        $bestBid = null;

        if (isset($summary['bid_count'])) {
            $bidCount = (int) $summary['bid_count'];
        }

        if (isset($summary['best_bid']) && is_numeric($summary['best_bid'])) {
            $bestBid = (float) $summary['best_bid'];
        }

        return [
            'bid_count' => $bidCount,
            'best_bid' => $bestBid,
            'best_bidder_id' => $bestBidderId,
        ];
    }

    /**
     * Rôle : Indiquer si un utilisateur a déjà enchéri sur une annonce.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur.
     * Retour : true si une enchère correspond, false sinon, ou null en cas d'erreur SQL.
     */
    public function userHasBid(int $listingId, int $userId): ?bool
    {
        $sql = 'SELECT id FROM `ENCHERE`'
            . ' WHERE annonce_id = :listing_id AND utilisateur_id = :user_id LIMIT 1';
        $bid = $this->database->fetchOne($sql, [
            'listing_id' => $listingId,
            'user_id' => $userId,
        ]);

        if ($bid === false) {
            return null;
        }

        return $bid !== null;
    }

    /**
     * Rôle : Indiquer si une annonce possède déjà au moins une enchère.
     * Paramètres : Identifiant de l'annonce.
     * Retour : true si une enchère existe, false sinon, ou null en cas d'erreur SQL.
     */
    public function listingHasBid(int $listingId): ?bool
    {
        $bid = $this->database->fetchOne(
            'SELECT id FROM `ENCHERE` WHERE annonce_id = :listing_id LIMIT 1',
            ['listing_id' => $listingId]
        );

        if ($bid === false) {
            return null;
        }

        return $bid !== null;
    }

    /**
     * Rôle : Enregistrer une enchère validée par le modèle dans la transaction en cours.
     * Paramètres : Identifiants de l'utilisateur et de l'annonce, puis montant proposé en centimes.
     * Retour : true lorsque l'enchère est enregistrée, sinon false.
     */
    public function placeBid(int $userId, int $listingId, int $amountInCents): bool
    {
        return $this->create([
            'utilisateur_id' => $userId,
            'annonce_id' => $listingId,
            'montant' => $this->formatCentsAsDecimal($amountInCents),
            'date_heure_enchere' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Rôle : Récupérer l'historique détaillé et ordonné des enchères d'une annonce.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Liste des enchères ou false en cas d'erreur SQL.
     */
    public function getHistory(int $listingId): array|false
    {
        $sql = 'SELECT bid.montant, bid.date_heure_enchere, bidder.pseudo'
            . ' FROM `ENCHERE` bid'
            . ' INNER JOIN `UTILISATEUR` bidder ON bidder.id = bid.utilisateur_id'
            . ' WHERE bid.annonce_id = :listing_id'
            . ' ORDER BY bid.montant DESC, bid.date_heure_enchere ASC, bid.id ASC';
        return $this->database->fetchAll($sql, ['listing_id' => $listingId]);
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

    /**
     * Rôle : Convertir une valeur décimale issue de PDO en nombre entier de centimes.
     * Paramètres : Montant décimal positif provenant de la base.
     * Retour : Montant en centimes ou null lorsque le format est inexploitable.
     */
    private function decimalAmountToCents(string $amount): ?int
    {
        if (preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/D', $amount, $matches) !== 1) {
            return null;
        }

        $fraction = '00';

        if (isset($matches[2])) {
            $fraction = str_pad($matches[2], 2, '0');
        }

        return ((int) $matches[1] * 100) + (int) $fraction;
    }

    /**
     * Rôle : Convertir un montant entier en chaîne décimale compatible avec la colonne SQL.
     * Paramètres : Montant positif exprimé en centimes.
     * Retour : Montant avec exactement deux décimales.
     */
    private function formatCentsAsDecimal(int $amountInCents): string
    {
        $euros = intdiv($amountInCents, 100);
        $cents = $amountInCents % 100;
        return $euros . '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }
}

