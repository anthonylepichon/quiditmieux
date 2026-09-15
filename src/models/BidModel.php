<?php

/**
 * Description générale : Modèle des enchères déposées sur les annonces.
 * Rôle : Centraliser le calcul, la validation, l'enregistrement et la consultation des enchères. Les contrôleurs utilisent ainsi les mêmes règles et les mêmes données pour afficher le prix courant et accepter un nouveau montant.
 * Tâches : Déclarer la table ENCHERE, appliquer la progression minimale et calculer les meilleurs montants.
 * Liens avec les autres fichiers : Étend Model.php, complète les résultats fournis par ListingModel.php.
 */

namespace App\models;

use App\core\Model;
// NATIF PHP : DateTime est la classe native de gestion des dates et des heures ; elle permet ici de formater l’instant d’une enchère pour MySQL.
use DateTime;

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
     * Rôle : Obtenir le prix courant d'une annonce en comparant son prix de départ avec sa meilleure enchère. Le montant retourné correspond ainsi toujours au prix le plus élevé enregistré pour cette vente.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Montant courant en euros ou null si l'annonce est absente ou la requête échoue.
     */
    public function getCurrentAmountInEuros(int $listingId): ?int
    {
        // COALESCE conserve le prix de départ lorsqu'aucune enchère n'a encore été enregistrée.
        $row = $this->database->fetchOne(
            'SELECT COALESCE(MAX(bid.montant), listing.prix_depart) AS current_amount'
            . ' FROM `ANNONCE` listing'
            . ' LEFT JOIN `ENCHERE` bid ON bid.annonce_id = listing.id'
            . ' WHERE listing.id = :listing_id'
            . ' GROUP BY listing.id, listing.prix_depart',
            ['listing_id' => $listingId]
        );

        // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
        if ($row === false || $row === null || !isset($row['current_amount'])) {
            return null;
        }

        // Le montant reste un entier en euros afin que les comparaisons métier ne dépendent pas du format d'affichage.
        return (int) $row['current_amount'];
    }

    /**
     * Rôle : Calculer le prochain montant accepté en ajoutant un euro au prix courant. Une nouvelle enchère ne peut ainsi pas être égale ou inférieure au montant déjà atteint.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Montant minimal en euros ou null si le montant courant est indisponible.
     */
    public function getMinimumAmountInEuros(int $listingId): ?int
    {
        // Le minimum attendu correspond toujours au prix courant augmenté d'un euro.
        $currentAmount = $this->getCurrentAmountInEuros($listingId);

        if ($currentAmount === null) {
            return null;
        }

        return $currentAmount + 1;
    }

    /**
     * Rôle : Comparer le montant proposé avec le minimum actuellement exigé. Le contrôleur reçoit ainsi la décision et le minimum à afficher sans recalculer lui-même la règle d'enchère.
     * Paramètres : Identifiant de l'annonce et montant proposé en euros.
     * Retour : Décision métier et minimum attendu, ou null si le montant courant est indisponible.
     */
    public function evaluateBidAmountInEuros(int $listingId, int $amountInEuros): ?array
    {
        // Le minimum est calculé par le modèle afin que la même règle s'applique à toutes les actions.
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
     * Rôle : Calculer le prix courant de plusieurs annonces dans une seule requête. Cela évite d'interroger séparément la base pour chaque carte affichée dans une liste de résultats.
     * Paramètres : Liste d'identifiants d'annonces et prix de départ indexés par annonce.
     * Retour : Prix courants en euros indexés par annonce ou false en cas de donnée invalide ou d'erreur SQL.
     */
    public function getCurrentAmountsInEuros(array $listingIds, array $startingPrices): array|false
    {
        // Les identifiants sont normalisés avant de construire la liste de paramètres SQL.
        $identifiers = $this->normalizePositiveIdentifiers($listingIds);
        $currentAmountsInEuros = [];

        foreach ($identifiers as $identifier) {
            if (!isset($startingPrices[$identifier])) {
                return false;
            }

            $startingAmountInEuros = (int) $startingPrices[$identifier];

            $currentAmountsInEuros[$identifier] = $startingAmountInEuros;
        }

        if ($identifiers === []) {
            return $currentAmountsInEuros;
        }

        $parameters = [];
        $placeholders = [];

        // Chaque identifiant reçoit son propre paramètre préparé ; aucune valeur n'est concaténée dans la requête.
        foreach ($identifiers as $index => $identifier) {
            $parameterName = 'listing_' . $index;
            $placeholders[] = ':' . $parameterName;
            $parameters[$parameterName] = $identifier;
        }

        $sql = 'SELECT annonce_id, MAX(montant) AS best_bid'
            . ' FROM `ENCHERE`'
            // NATIF PHP : implode() assemble les éléments d’un tableau dans une chaîne ; il construit ici une liste ou une partie de requête.
            . ' WHERE annonce_id IN (' . implode(', ', $placeholders) . ')'
            . ' GROUP BY annonce_id';
        $rows = $this->database->fetchAll($sql, $parameters);

        if ($rows === false) {
            return false;
        }

        // Les meilleurs montants trouvés remplacent le prix de départ préparé pour chaque annonce concernée.
        foreach ($rows as $row) {
            if (!isset($row['annonce_id'], $row['best_bid'])) {
                return false;
            }

            $identifier = (int) $row['annonce_id'];
            $bestBidInEuros = (int) $row['best_bid'];

            $currentAmountsInEuros[$identifier] = $bestBidInEuros;
        }

        return $currentAmountsInEuros;
    }

    /**
     * Rôle : Regrouper le nombre d'enchères, le meilleur montant et l'identifiant du meilleur enchérisseur. Une seule lecture fournit ainsi au détail de l'annonce un résumé cohérent de la vente.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Résumé des enchères ou false en cas d'erreur SQL.
     */
    public function getSummary(int $listingId): array|false
    {
        // La requête regroupe le nombre d'enchères, le meilleur montant et son auteur dans une seule lecture.
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
            $bestBidInEuros = (int) $summary['best_bid'];
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
     * Rôle : Indiquer si un utilisateur a déjà déposé une enchère sur une annonce. Le contrôleur peut ainsi reconnaître sa participation et décider s'il est autorisé à consulter l'historique détaillé.
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
     * Rôle : Enregistrer définitivement une enchère dont l'utilisateur, l'annonce et le montant ont déjà été validés. La date conservée permet ensuite de départager et de retracer les propositions.
     * Paramètres : Identifiants, montant proposé en euros et instant de référence.
     * Retour : true lorsque l'enchère est enregistrée, sinon false.
     */
    public function placeBid(
        int $userId,
        int $listingId,
        int $amountInEuros,
        DateTime $placedAt
    ): bool
    {
        return $this->create([
            'utilisateur_id' => $userId,
            'annonce_id' => $listingId,
            'montant' => $amountInEuros,
            'date_heure_enchere' => $placedAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Rôle : Récupérer l'historique des enchères d'une annonce avec le pseudo de chaque participant. L'ordre choisi affiche d'abord le meilleur montant et départage les montants identiques par leur date puis leur identifiant.
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

