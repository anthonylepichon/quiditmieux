<?php

/**
 * Description générale : Modèle des enchères déposées sur les annonces.
 * Rôle : Valider et enregistrer les enchères puis fournir les prix courants des annonces.
 * Tâches : Déclarer la table ENCHERE, appliquer la progression minimale et calculer les meilleurs montants.
 * Liens avec les autres fichiers : Étend Model.php, utilise Clock.php et complète les résultats fournis par ListingModel.php.
 */

namespace App\models;

use App\core\Clock;
use App\core\Money;
use App\core\Model;
use DateTimeImmutable;

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
     * Rôle : Obtenir le montant courant d'une annonce en euros entiers.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Montant courant en euros ou null si l'annonce est absente ou la requête échoue.
     */
    public function getCurrentAmountInEuros(int $listingId): ?int
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

        return Money::databaseValueToEuros((string) $row['current_amount']);
    }

    /**
     * Rôle : Obtenir le prochain montant minimal accepté pour une annonce.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Montant minimal en euros ou null si le montant courant est indisponible.
     */
    public function getMinimumAmountInEuros(int $listingId): ?int
    {
        $currentAmount = $this->getCurrentAmountInEuros($listingId);

        if ($currentAmount === null) {
            return null;
        }

        return $currentAmount + 1;
    }

    /**
     * Rôle : Vérifier qu'une proposition atteint le prochain montant minimal de l'annonce.
     * Paramètres : Identifiant de l'annonce et montant proposé en euros.
     * Retour : Décision métier et minimum attendu, ou null si le montant courant est indisponible.
     */
    public function evaluateBidAmountInEuros(int $listingId, int $amountInEuros): ?array
    {
        $minimumAmount = $this->getMinimumAmountInEuros($listingId);

        if ($minimumAmount === null) {
            return null;
        }

        return [
            'accepted' => $amountInEuros >= $minimumAmount,
            'minimum_amount_in_euros' => $minimumAmount,
        ];
    }

    /**
     * Rôle : Calculer en euros entiers le prix courant de chaque annonce demandée.
     * Paramètres : Liste d'identifiants d'annonces et prix de départ indexés par annonce.
     * Retour : Prix courants en euros indexés par annonce ou false en cas de donnée invalide ou d'erreur SQL.
     */
    public function getCurrentAmountsInEuros(array $listingIds, array $startingPrices): array|false
    {
        $identifiers = $this->normalizePositiveIdentifiers($listingIds);
        $currentAmountsInEuros = [];

        foreach ($identifiers as $identifier) {
            if (!isset($startingPrices[$identifier])) {
                return false;
            }

            $startingAmountInEuros = Money::databaseValueToEuros((string) $startingPrices[$identifier]);

            if ($startingAmountInEuros === null) {
                return false;
            }

            $currentAmountsInEuros[$identifier] = $startingAmountInEuros;
        }

        if ($identifiers === []) {
            return $currentAmountsInEuros;
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
            if (!isset($row['annonce_id'], $row['best_bid'])) {
                return false;
            }

            $identifier = (int) $row['annonce_id'];
            $bestBidInEuros = Money::databaseValueToEuros((string) $row['best_bid']);

            if ($bestBidInEuros === null) {
                return false;
            }

            $currentAmountsInEuros[$identifier] = $bestBidInEuros;
        }

        return $currentAmountsInEuros;
    }

    /**
     * Rôle : Obtenir le nombre d'enchères, le meilleur montant et son auteur pour une annonce.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Résumé des enchères ou false en cas d'erreur SQL.
     */
    public function getSummary(int $listingId): array|false
    {
        $sql = 'SELECT COUNT(*) AS bid_count, MAX(summary_bid.montant) AS best_bid,'
            . ' (SELECT winning_bid.utilisateur_id FROM `ENCHERE` winning_bid'
            . ' WHERE winning_bid.annonce_id = :winner_listing_id'
            . ' ORDER BY winning_bid.montant DESC, winning_bid.date_heure_enchere ASC,'
            . ' winning_bid.id ASC LIMIT 1) AS best_bidder_id'
            . ' FROM `ENCHERE` summary_bid WHERE summary_bid.annonce_id = :summary_listing_id';
        $summary = $this->database->fetchOne($sql, [
            'winner_listing_id' => $listingId,
            'summary_listing_id' => $listingId,
        ]);

        if ($summary === false) {
            return false;
        }

        if ($summary === null) {
            return ['bid_count' => 0, 'best_bid_in_euros' => null, 'best_bidder_id' => null];
        }

        $bidCount = 0;
        $bestBidInEuros = null;
        $bestBidderId = null;

        if (isset($summary['bid_count'])) {
            $bidCount = (int) $summary['bid_count'];
        }

        if (isset($summary['best_bid'])) {
            $bestBidInEuros = Money::databaseValueToEuros((string) $summary['best_bid']);

            if ($bestBidInEuros === null) {
                return false;
            }
        }

        if (isset($summary['best_bidder_id'])) {
            $bestBidderId = (int) $summary['best_bidder_id'];
        }

        return [
            'bid_count' => $bidCount,
            'best_bid_in_euros' => $bestBidInEuros,
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
        $sql = 'SELECT 1 AS found FROM `ENCHERE`'
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
     * Rôle : Enregistrer une enchère validée par le modèle dans la transaction en cours.
     * Paramètres : Identifiants, montant proposé en euros et instant UTC de référence.
     * Retour : true lorsque l'enchère est enregistrée, sinon false.
     */
    public function placeBid(
        int $userId,
        int $listingId,
        int $amountInEuros,
        DateTimeImmutable $placedAtUtc
    ): bool
    {
        return $this->create([
            'utilisateur_id' => $userId,
            'annonce_id' => $listingId,
            'montant' => Money::eurosToDatabaseValue($amountInEuros),
            'date_heure_enchere' => Clock::formatForDatabase($placedAtUtc),
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

}

