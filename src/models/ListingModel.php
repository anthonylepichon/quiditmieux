<?php

/**
 * Description générale : Modèle des annonces proposées aux enchères.
 * Rôle : Rechercher les annonces et appliquer leurs règles d'autorisation métier.
 * Tâches : Déclarer la table ANNONCE, construire la recherche et décider des modifications ou suppressions autorisées.
 * Liens avec les autres fichiers : Étend Model.php, utilise Clock.php et fournit les annonces aux contrôleurs.
 */

namespace App\models;

use App\core\Clock;
use App\core\Model;
use DateTimeImmutable;

class ListingModel extends Model
{
    // ====================
    // ATTRIBUTS
    // ====================

    protected string $tableName = 'ANNONCE';
    protected string $primaryKeyName = 'id';
    protected array $writableFields = [
        'utilisateur_id',
        'titre',
        'description',
        'etat_objet',
        'prix_depart',
        'date_heure_fin',
        'categorie_id',
    ];
    private string $lastManagementRestriction = 'error';
    private string $lastParticipationRestriction = 'error';

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Créer une annonce à partir des informations déjà validées par le contrôleur.
     * Paramètres : Auteur, titre, description, état, prix en euros, échéance UTC et catégorie.
     * Retour : Identifiant de l'annonce créée ou null lorsque la création échoue.
     */
    public function createListing(
        int $userId,
        string $title,
        string $description,
        string $itemState,
        int $startingPriceInEuros,
        string $deadlineUtc,
        int $categoryId
    ): ?int {
        $created = $this->create([
            'utilisateur_id' => $userId,
            'titre' => $title,
            'description' => $description,
            'etat_objet' => $itemState,
            'prix_depart' => $startingPriceInEuros,
            'date_heure_fin' => $deadlineUtc,
            'categorie_id' => $categoryId,
        ]);

        if (!$created) {
            return null;
        }

        $listingId = $this->getValue($this->primaryKeyName);

        if (!is_int($listingId)) {
            return null;
        }

        return $listingId;
    }

    /**
     * Rôle : Modifier les informations autorisées d'une annonce déjà validées par le contrôleur.
     * Paramètres : Identifiant, titre, description, état, prix en euros, échéance UTC et catégorie.
     * Retour : true lorsque la mise à jour est exécutée, sinon false.
     */
    public function updateListing(
        int $listingId,
        string $title,
        string $description,
        string $itemState,
        int $startingPriceInEuros,
        string $deadlineUtc,
        int $categoryId
    ): bool {
        return $this->update($listingId, [
            'titre' => $title,
            'description' => $description,
            'etat_objet' => $itemState,
            'prix_depart' => $startingPriceInEuros,
            'date_heure_fin' => $deadlineUtc,
            'categorie_id' => $categoryId,
        ]);
    }

    /**
     * Rôle : Supprimer une annonce dont l'autorisation a déjà été vérifiée.
     * Paramètres : Identifiant de l'annonce.
     * Retour : true lorsque la suppression est exécutée, sinon false.
     */
    public function deleteListing(int $listingId): bool
    {
        return $this->delete($listingId);
    }

    /**
     * Rôle : Vérifier rapidement si une annonce appartient à l'utilisateur demandé.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur.
     * Retour : true si l'utilisateur est propriétaire, false sinon ou null en cas d'erreur SQL.
     */
    public function isOwnedBy(int $listingId, int $userId): ?bool
    {
        $listing = $this->database->fetchOne(
            'SELECT utilisateur_id FROM `ANNONCE` WHERE id = :listing_id LIMIT 1',
            ['listing_id' => $listingId]
        );

        if ($listing === false) {
            return null;
        }

        if ($listing === null || !isset($listing['utilisateur_id'])) {
            return false;
        }

        return (int) $listing['utilisateur_id'] === $userId;
    }

    /**
     * Rôle : Vérifier si une annonce peut être modifiée par l'utilisateur demandé.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur, puis instant UTC de référence.
     * Retour : true si la modification est autorisée, false si elle est interdite ou null en cas d'erreur SQL.
     */
    public function canBeModifiedBy(
        int $listingId,
        int $userId,
        DateTimeImmutable $currentTimeUtc
    ): ?bool
    {
        return $this->canBeManagedBy($listingId, $userId, $currentTimeUtc);
    }

    /**
     * Rôle : Vérifier si une annonce peut être supprimée par l'utilisateur demandé.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur, puis instant UTC de référence.
     * Retour : true si la suppression est autorisée, false si elle est interdite ou null en cas d'erreur SQL.
     */
    public function canBeDeletedBy(
        int $listingId,
        int $userId,
        DateTimeImmutable $currentTimeUtc
    ): ?bool
    {
        return $this->canBeManagedBy($listingId, $userId, $currentTimeUtc);
    }

    /**
     * Rôle : Fournir le motif de la dernière interdiction de modification ou de suppression.
     * Paramètres : Aucun.
     * Retour : Motif `missing`, `owner`, `ended`, `bid`, `error` ou chaîne vide si l'action est autorisée.
     */
    public function getLastManagementRestriction(): string
    {
        return $this->lastManagementRestriction;
    }

    /**
     * Rôle : Vérifier si un utilisateur peut suivre une annonce ou y déposer une enchère.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur, puis instant UTC de référence.
     * Retour : true si la participation est autorisée, false si elle est interdite ou null en cas d'erreur SQL.
     */
    public function canReceiveParticipationFrom(
        int $listingId,
        int $userId,
        DateTimeImmutable $currentTimeUtc
    ): ?bool
    {
        $this->lastParticipationRestriction = 'error';
        $listing = $this->database->fetchOne(
            'SELECT utilisateur_id, date_heure_fin FROM `ANNONCE`'
            . ' WHERE id = :listing_id LIMIT 1 FOR UPDATE',
            ['listing_id' => $listingId]
        );

        if ($listing === false) {
            return null;
        }

        if ($listing === null
            || !isset($listing['utilisateur_id'], $listing['date_heure_fin'])
        ) {
            $this->lastParticipationRestriction = 'missing';
            return false;
        }

        if ((int) $listing['utilisateur_id'] === $userId) {
            $this->lastParticipationRestriction = 'owner';
            return false;
        }

        if ((string) $listing['date_heure_fin'] <= Clock::formatForDatabase($currentTimeUtc)) {
            $this->lastParticipationRestriction = 'ended';
            return false;
        }

        $this->lastParticipationRestriction = '';
        return true;
    }

    /**
     * Rôle : Fournir le motif de la dernière interdiction de suivi ou d'enchère.
     * Paramètres : Aucun.
     * Retour : Motif `missing`, `owner`, `ended`, `error` ou chaîne vide si l'action est autorisée.
     */
    public function getLastParticipationRestriction(): string
    {
        return $this->lastParticipationRestriction;
    }

    /**
     * Rôle : Rechercher et paginer les annonces à partir de critères déjà validés.
     * Paramètres : Critères normalisés, page, nombre d'annonces par page et instant UTC de référence.
     * Retour : Résultat structuré contenant le succès, les annonces et la pagination.
     */
    public function searchListings(
        array $criteria,
        int $requestedPage,
        int $itemsPerPage,
        DateTimeImmutable $currentTimeUtc
    ): array
    {
        $whereParts = [];
        $parameters = [];
        $currentTimeForDatabase = Clock::formatForDatabase($currentTimeUtc);
        $currentPriceSql = 'COALESCE((SELECT MAX(price_bid.montant)'
            . ' FROM `ENCHERE` price_bid'
            . ' WHERE price_bid.annonce_id = listing.id), listing.prix_depart)';

        $this->addTextCriteria($criteria, $whereParts, $parameters);
        $this->addCategoryCriteria($criteria, $whereParts, $parameters);
        $this->addItemStateCriteria($criteria, $whereParts, $parameters);
        $this->addPriceCriteria($criteria, $whereParts, $parameters, $currentPriceSql);
        $this->addSaleStateCriteria(
            $criteria,
            $whereParts,
            $parameters,
            $currentTimeForDatabase
        );

        $whereSql = '';

        if ($whereParts !== []) {
            $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
        }

        $countSql = 'SELECT COUNT(*) AS total FROM `ANNONCE` listing' . $whereSql;
        $countRow = $this->database->fetchOne($countSql, $parameters);

        if ($countRow === false
            || $countRow === null
            || !isset($countRow['total'])
            || !is_numeric($countRow['total'])
        ) {
            return $this->failedSearchResult($itemsPerPage);
        }

        $totalItems = (int) $countRow['total'];
        $totalPages = max(1, (int) ceil($totalItems / $itemsPerPage));
        $currentPage = min(max(1, $requestedPage), $totalPages);
        $offset = ($currentPage - 1) * $itemsPerPage;
        $listParameters = $parameters;
        $orderSql = $this->buildOrderSql(
            $criteria['sale_state'],
            $listParameters,
            $currentTimeForDatabase
        );
        $listSql = 'SELECT listing.id, listing.titre, listing.description,'
            . ' listing.etat_objet, listing.prix_depart, listing.date_heure_fin,'
            . ' listing.categorie_id'
            . ' FROM `ANNONCE` listing'
            . $whereSql
            . $orderSql
            . ' LIMIT ' . $itemsPerPage . ' OFFSET ' . $offset;
        $listings = $this->database->fetchAll($listSql, $listParameters);

        if ($listings === false) {
            return $this->failedSearchResult($itemsPerPage);
        }

        if ($totalItems > 0 && $listings === []) {
            return $this->failedSearchResult($itemsPerPage);
        }

        return [
            'success' => true,
            'listings' => $listings,
            'total_items' => $totalItems,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'items_per_page' => $itemsPerPage,
        ];
    }

    /**
     * Rôle : Récupérer toutes les informations publiques d'une annonce et le pseudo de son vendeur.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Données de l'annonce, null si elle est absente ou false en cas d'erreur SQL.
     */
    public function getDetail(int $listingId): array|false|null
    {
        $sql = 'SELECT listing.id, listing.utilisateur_id, listing.titre, listing.description,'
            . ' listing.etat_objet, listing.prix_depart, listing.date_heure_fin,'
            . ' listing.categorie_id, seller.pseudo AS seller_pseudo'
            . ' FROM `ANNONCE` listing'
            . ' INNER JOIN `UTILISATEUR` seller ON seller.id = listing.utilisateur_id'
            . ' WHERE listing.id = :listing_id LIMIT 1';
        return $this->database->fetchOne($sql, ['listing_id' => $listingId]);
    }

    /**
     * Rôle : Récupérer les annonces vendues par l'utilisateur avec leur prix courant.
     * Paramètres : Identifiant de l'utilisateur connecté et instant UTC de référence.
     * Retour : Liste des ventes ou false en cas d'erreur SQL.
     */
    public function getDashboardSales(int $userId, DateTimeImmutable $currentTimeUtc): array|false
    {
        $sql = 'SELECT listing.id, listing.titre, listing.etat_objet, listing.prix_depart,'
            . ' listing.date_heure_fin, listing.categorie_id,'
            . ' COUNT(bid.id) AS bid_count, MAX(bid.montant) AS best_bid'
            . ' FROM `ANNONCE` listing'
            . ' LEFT JOIN `ENCHERE` bid ON bid.annonce_id = listing.id'
            . ' WHERE listing.utilisateur_id = :user_id'
            . ' GROUP BY listing.id, listing.titre, listing.etat_objet, listing.prix_depart,'
            . ' listing.date_heure_fin, listing.categorie_id'
            . ' ORDER BY CASE WHEN listing.date_heure_fin > :current_time_order THEN 0 ELSE 1 END,'
            . ' listing.date_heure_fin ASC, listing.id ASC';
        return $this->database->fetchAll($sql, [
            'user_id' => $userId,
            'current_time_order' => Clock::formatForDatabase($currentTimeUtc),
        ]);
    }

    /**
     * Rôle : Récupérer les suivis et enchères de l'utilisateur utiles au tableau de bord.
     * Paramètres : Identifiant de l'utilisateur connecté et instant UTC de référence.
     * Retour : Participations de l'utilisateur ou false en cas d'erreur SQL.
     */
    public function getDashboardParticipations(int $userId, DateTimeImmutable $currentTimeUtc): array|false
    {
        $winnerSql = '(SELECT winning_bid.utilisateur_id FROM `ENCHERE` winning_bid'
            . ' WHERE winning_bid.annonce_id = listing.id'
            . ' ORDER BY winning_bid.montant DESC, winning_bid.date_heure_enchere ASC,'
            . ' winning_bid.id ASC LIMIT 1)';
        $sql = 'SELECT participation.id, participation.titre, participation.etat_objet,'
            . ' participation.prix_depart, participation.date_heure_fin, participation.categorie_id,'
            . ' participation.best_bid, participation.bid_count, participation.user_best_bid,'
            . ' participation.winner_id, participation.is_following FROM ('
            . 'SELECT listing.id, listing.titre, listing.etat_objet, listing.prix_depart,'
            . ' listing.date_heure_fin, listing.categorie_id,'
            . ' MAX(all_bid.montant) AS best_bid, COUNT(all_bid.id) AS bid_count,'
            . ' MAX(CASE WHEN all_bid.utilisateur_id = :bid_user_id'
            . ' THEN all_bid.montant ELSE NULL END) AS user_best_bid,'
            . ' ' . $winnerSql . ' AS winner_id,'
            . ' CASE WHEN followed.id IS NULL THEN 0 ELSE 1 END AS is_following'
            . ' FROM `ANNONCE` listing'
            . ' LEFT JOIN `ENCHERE` all_bid ON all_bid.annonce_id = listing.id'
            . ' LEFT JOIN `ASSOC_UTILISATEUR_ANNONCE` followed'
            . ' ON followed.annonce_id = listing.id AND followed.utilisateur_id = :follow_user_id'
            . ' WHERE listing.utilisateur_id <> :owner_user_id'
            . ' GROUP BY listing.id, listing.titre, listing.etat_objet, listing.prix_depart,'
            . ' listing.date_heure_fin, listing.categorie_id, followed.id) participation'
            . ' WHERE ((participation.date_heure_fin > :current_time_active'
            . ' AND (participation.is_following = 1 OR participation.user_best_bid IS NOT NULL))'
            . ' OR (participation.date_heure_fin <= :current_time_ended'
            . ' AND participation.user_best_bid IS NOT NULL))'
            . ' ORDER BY CASE WHEN participation.date_heure_fin > :current_time_order THEN 0 ELSE 1 END,'
            . ' participation.date_heure_fin ASC, participation.id ASC';
        $currentTimeForDatabase = Clock::formatForDatabase($currentTimeUtc);
        return $this->database->fetchAll($sql, [
            'bid_user_id' => $userId,
            'follow_user_id' => $userId,
            'owner_user_id' => $userId,
            'current_time_active' => $currentTimeForDatabase,
            'current_time_ended' => $currentTimeForDatabase,
            'current_time_order' => $currentTimeForDatabase,
        ]);
    }

    /**
     * Rôle : Ajouter chaque mot recherché comme condition obligatoire sur le titre ou la description.
     * Paramètres : Critères normalisés, conditions SQL et paramètres de requête à compléter.
     * Retour : Aucun.
     */
    private function addTextCriteria(array $criteria, array &$whereParts, array &$parameters): void
    {
        if (!isset($criteria['words']) || !is_array($criteria['words'])) {
            return;
        }

        foreach ($criteria['words'] as $index => $word) {
            if (!is_string($word) || $word === '') {
                continue;
            }

            $parameterName = 'word_' . $index;
            $whereParts[] = 'LOCATE(:' . $parameterName
                . ", CONCAT(listing.titre, ' ', listing.description)) > 0";
            $parameters[$parameterName] = $word;
        }
    }

    /**
     * Rôle : Ajouter le filtre exact sur l'identifiant de catégorie fourni par l'API externe.
     * Paramètres : Critères normalisés, conditions SQL et paramètres de requête à compléter.
     * Retour : Aucun.
     */
    private function addCategoryCriteria(array $criteria, array &$whereParts, array &$parameters): void
    {
        if ($criteria['category_id'] === null) {
            return;
        }

        $whereParts[] = 'listing.categorie_id = :category_id';
        $parameters['category_id'] = $criteria['category_id'];
    }

    /**
     * Rôle : Ajouter le filtre exact sur l'état de l'objet lorsqu'il est renseigné.
     * Paramètres : Critères normalisés, conditions SQL et paramètres de requête à compléter.
     * Retour : Aucun.
     */
    private function addItemStateCriteria(array $criteria, array &$whereParts, array &$parameters): void
    {
        if ($criteria['item_state'] === null) {
            return;
        }

        $whereParts[] = 'listing.etat_objet = :item_state';
        $parameters['item_state'] = $criteria['item_state'];
    }

    /**
     * Rôle : Ajouter les limites éventuelles appliquées au prix courant calculé.
     * Paramètres : Critères normalisés, conditions SQL, paramètres et expression du prix courant.
     * Retour : Aucun.
     */
    private function addPriceCriteria(
        array $criteria,
        array &$whereParts,
        array &$parameters,
        string $currentPriceSql
    ): void {
        if ($criteria['minimum_price_in_euros'] !== null
            && $criteria['maximum_price_in_euros'] !== null
        ) {
            $whereParts[] = $currentPriceSql . ' BETWEEN :minimum_price AND :maximum_price';
            $parameters['minimum_price'] = $criteria['minimum_price_in_euros'];
            $parameters['maximum_price'] = $criteria['maximum_price_in_euros'];
            return;
        }

        if ($criteria['minimum_price_in_euros'] !== null) {
            $whereParts[] = $currentPriceSql . ' >= :minimum_price';
            $parameters['minimum_price'] = $criteria['minimum_price_in_euros'];
        }

        if ($criteria['maximum_price_in_euros'] !== null) {
            $whereParts[] = $currentPriceSql . ' <= :maximum_price';
            $parameters['maximum_price'] = $criteria['maximum_price_in_euros'];
        }
    }

    /**
     * Rôle : Limiter la recherche aux ventes en cours ou terminées selon le choix reçu.
     * Paramètres : Critères normalisés, conditions, paramètres SQL et instant UTC formaté.
     * Retour : Aucun.
     */
    private function addSaleStateCriteria(
        array $criteria,
        array &$whereParts,
        array &$parameters,
        string $currentTimeUtc
    ): void
    {
        if ($criteria['sale_state'] === 'active') {
            $whereParts[] = 'listing.date_heure_fin > :current_time_filter';
            $parameters['current_time_filter'] = $currentTimeUtc;
        } elseif ($criteria['sale_state'] === 'ended') {
            $whereParts[] = 'listing.date_heure_fin <= :current_time_filter';
            $parameters['current_time_filter'] = $currentTimeUtc;
        }
    }

    /**
     * Rôle : Définir un ordre déterministe adapté à l'état des ventes recherché.
     * Paramètres : État de vente normalisé, paramètres SQL et instant UTC formaté.
     * Retour : Fragment SQL contenant uniquement l'ordre interne prévu par le modèle.
     */
    private function buildOrderSql(
        string $saleState,
        array &$parameters,
        string $currentTimeUtc
    ): string
    {
        if ($saleState === 'active') {
            return ' ORDER BY listing.date_heure_fin ASC, listing.id ASC';
        }

        if ($saleState === 'ended') {
            return ' ORDER BY listing.date_heure_fin DESC, listing.id DESC';
        }

        $parameters['current_time_order_state'] = $currentTimeUtc;
        $parameters['current_time_order_active'] = $currentTimeUtc;
        $parameters['current_time_order_ended'] = $currentTimeUtc;

        return ' ORDER BY CASE WHEN listing.date_heure_fin > :current_time_order_state THEN 0 ELSE 1 END ASC,'
            . ' CASE WHEN listing.date_heure_fin > :current_time_order_active'
            . ' THEN listing.date_heure_fin END ASC,'
            . ' CASE WHEN listing.date_heure_fin <= :current_time_order_ended'
            . ' THEN listing.date_heure_fin END DESC,'
            . ' listing.id ASC';
    }

    /**
     * Rôle : Appliquer les règles communes de modification et de suppression d'une annonce.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur, puis instant UTC de référence.
     * Retour : true si l'action est autorisée, false si elle est interdite ou null en cas d'erreur SQL.
     */
    private function canBeManagedBy(
        int $listingId,
        int $userId,
        DateTimeImmutable $currentTimeUtc
    ): ?bool
    {
        $this->lastManagementRestriction = 'error';
        $listing = $this->database->fetchOne(
            'SELECT listing.utilisateur_id, listing.date_heure_fin,'
            . ' EXISTS(SELECT 1 FROM `ENCHERE` bid WHERE bid.annonce_id = listing.id) AS has_bid'
            . ' FROM `ANNONCE` listing WHERE listing.id = :listing_id LIMIT 1 FOR UPDATE',
            ['listing_id' => $listingId]
        );

        if ($listing === false) {
            return null;
        }

        if ($listing === null
            || !isset($listing['utilisateur_id'], $listing['date_heure_fin'], $listing['has_bid'])
        ) {
            $this->lastManagementRestriction = 'missing';
            return false;
        }

        if ((int) $listing['utilisateur_id'] !== $userId) {
            $this->lastManagementRestriction = 'owner';
            return false;
        }

        if ((string) $listing['date_heure_fin'] <= Clock::formatForDatabase($currentTimeUtc)) {
            $this->lastManagementRestriction = 'ended';
            return false;
        }

        if ((bool) $listing['has_bid']) {
            $this->lastManagementRestriction = 'bid';
            return false;
        }

        $this->lastManagementRestriction = '';
        return true;
    }

    /**
     * Rôle : Fournir un résultat uniforme lorsqu'une requête de recherche échoue.
     * Paramètres : Nombre d'annonces prévu par page.
     * Retour : Résultat structuré signalant l'échec de la recherche.
     */
    private function failedSearchResult(int $itemsPerPage): array
    {
        return [
            'success' => false,
            'listings' => [],
            'total_items' => 0,
            'current_page' => 1,
            'total_pages' => 1,
            'items_per_page' => $itemsPerPage,
        ];
    }
}
