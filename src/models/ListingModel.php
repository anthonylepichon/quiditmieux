<?php

/**
 * Description générale : Modèle des annonces proposées aux enchères.
 * Rôle : Rechercher et paginer les annonces selon les critères validés par le contrôleur.
 * Tâches : Déclarer la table ANNONCE, construire la recherche multicritère et ordonner les ventes.
 * Liens avec les autres fichiers : Étend Model.php et fournit les annonces à ListingController.php.
 */

namespace App\models;

use App\core\Database;
use App\core\Model;

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
        'categorie_id_externe',
        'categorie_libelle',
    ];

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Initialiser le modèle avec le gestionnaire de base de données et des données éventuelles.
     * Paramètres : Gestionnaire de base de données et tableau facultatif de données d'annonce.
     * Retour : Aucun.
     */
    public function __construct(Database $database, array $data = [])
    {
        parent::__construct($database, $data);
    }

    /**
     * Rôle : Rechercher et paginer les annonces à partir de critères déjà validés.
     * Paramètres : Critères normalisés, numéro de page demandé et nombre d'annonces par page.
     * Retour : Résultat structuré contenant le succès, les annonces et la pagination.
     */
    public function searchListings(array $criteria, int $requestedPage, int $itemsPerPage): array
    {
        $whereParts = [];
        $parameters = [];
        $currentPriceSql = 'COALESCE((SELECT MAX(price_bid.montant)'
            . ' FROM `ENCHERE` price_bid'
            . ' WHERE price_bid.annonce_id = listing.id), listing.prix_depart)';

        $this->addTextCriteria($criteria, $whereParts, $parameters);
        $this->addCategoryCriteria($criteria, $whereParts, $parameters);
        $this->addItemStateCriteria($criteria, $whereParts, $parameters);
        $this->addPriceCriteria($criteria, $whereParts, $parameters, $currentPriceSql);
        $this->addSaleStateCriteria($criteria, $whereParts);

        $whereSql = '';

        if ($whereParts !== []) {
            $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
        }

        $countSql = 'SELECT COUNT(*) AS total FROM `ANNONCE` listing' . $whereSql;
        $countRow = $this->database->fetchOne($countSql, $parameters);

        if ($countRow === null || !isset($countRow['total']) || !is_numeric($countRow['total'])) {
            return $this->failedSearchResult($itemsPerPage);
        }

        $totalItems = (int) $countRow['total'];
        $totalPages = max(1, (int) ceil($totalItems / $itemsPerPage));
        $currentPage = min(max(1, $requestedPage), $totalPages);
        $offset = ($currentPage - 1) * $itemsPerPage;
        $orderSql = $this->buildOrderSql($criteria['sale_state']);
        $listSql = 'SELECT listing.id, listing.titre, listing.description,'
            . ' listing.etat_objet, listing.prix_depart, listing.date_heure_fin,'
            . ' listing.categorie_id_externe, listing.categorie_libelle'
            . ' FROM `ANNONCE` listing'
            . $whereSql
            . $orderSql
            . ' LIMIT ' . $itemsPerPage . ' OFFSET ' . $offset;
        $listings = $this->database->fetchAll($listSql, $parameters);

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
     * Retour : Données de l'annonce ou null lorsqu'elle est absente.
     */
    public function getDetail(int $listingId): ?array
    {
        $sql = 'SELECT listing.id, listing.utilisateur_id, listing.titre, listing.description,'
            . ' listing.etat_objet, listing.prix_depart, listing.date_heure_fin,'
            . ' listing.categorie_id_externe, listing.categorie_libelle, seller.pseudo AS seller_pseudo'
            . ' FROM `ANNONCE` listing'
            . ' INNER JOIN `UTILISATEUR` seller ON seller.id = listing.utilisateur_id'
            . ' WHERE listing.id = :listing_id LIMIT 1';
        return $this->database->fetchOne($sql, ['listing_id' => $listingId]);
    }

    /**
     * Rôle : Verrouiller et récupérer une annonce pendant une opération concurrente.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Données essentielles verrouillées ou null lorsque l'annonce est absente.
     */
    public function getForUpdate(int $listingId): ?array
    {
        $sql = 'SELECT id, utilisateur_id, prix_depart, date_heure_fin FROM `ANNONCE`'
            . ' WHERE id = :listing_id LIMIT 1 FOR UPDATE';
        return $this->database->fetchOne($sql, ['listing_id' => $listingId]);
    }

    /**
     * Rôle : Récupérer les annonces vendues par l'utilisateur avec leur prix courant.
     * Paramètres : Identifiant de l'utilisateur connecté.
     * Retour : Liste des ventes ordonnées des plus proches aux plus anciennes.
     */
    public function getDashboardSales(int $userId): array
    {
        $sql = 'SELECT listing.id, listing.titre, listing.etat_objet, listing.prix_depart,'
            . ' listing.date_heure_fin, listing.categorie_libelle,'
            . ' COUNT(bid.id) AS bid_count, MAX(bid.montant) AS best_bid'
            . ' FROM `ANNONCE` listing'
            . ' LEFT JOIN `ENCHERE` bid ON bid.annonce_id = listing.id'
            . ' WHERE listing.utilisateur_id = :user_id'
            . ' GROUP BY listing.id, listing.titre, listing.etat_objet, listing.prix_depart,'
            . ' listing.date_heure_fin, listing.categorie_libelle'
            . ' ORDER BY CASE WHEN listing.date_heure_fin > UTC_TIMESTAMP() THEN 0 ELSE 1 END,'
            . ' listing.date_heure_fin ASC, listing.id ASC';
        return $this->database->fetchAll($sql, ['user_id' => $userId]);
    }

    /**
     * Rôle : Récupérer les suivis et enchères de l'utilisateur utiles au tableau de bord.
     * Paramètres : Identifiant de l'utilisateur connecté.
     * Retour : Participations actives, enchères perdues et enchères remportées.
     */
    public function getDashboardParticipations(int $userId): array
    {
        $winnerSql = '(SELECT winning_bid.utilisateur_id FROM `ENCHERE` winning_bid'
            . ' WHERE winning_bid.annonce_id = listing.id'
            . ' ORDER BY winning_bid.montant DESC, winning_bid.date_heure_enchere ASC,'
            . ' winning_bid.id ASC LIMIT 1)';
        $sql = 'SELECT listing.id, listing.titre, listing.etat_objet, listing.prix_depart,'
            . ' listing.date_heure_fin, listing.categorie_libelle,'
            . ' (SELECT MAX(all_bid.montant) FROM `ENCHERE` all_bid WHERE all_bid.annonce_id = listing.id) AS best_bid,'
            . ' (SELECT COUNT(*) FROM `ENCHERE` counted_bid WHERE counted_bid.annonce_id = listing.id) AS bid_count,'
            . ' (SELECT MAX(user_bid.montant) FROM `ENCHERE` user_bid WHERE user_bid.annonce_id = listing.id'
            . ' AND user_bid.utilisateur_id = :best_user_id) AS user_best_bid,'
            . ' ' . $winnerSql . ' AS winner_id,'
            . ' EXISTS(SELECT 1 FROM `ASSOC_UTILISATEUR_ANNONCE` followed WHERE followed.annonce_id = listing.id'
            . ' AND followed.utilisateur_id = :follow_user_id) AS is_following'
            . ' FROM `ANNONCE` listing'
            . ' WHERE listing.utilisateur_id <> :owner_user_id'
            . ' AND ((listing.date_heure_fin > UTC_TIMESTAMP() AND ('
            . ' EXISTS(SELECT 1 FROM `ASSOC_UTILISATEUR_ANNONCE` active_follow WHERE active_follow.annonce_id = listing.id'
            . ' AND active_follow.utilisateur_id = :active_follow_user_id)'
            . ' OR EXISTS(SELECT 1 FROM `ENCHERE` active_bid WHERE active_bid.annonce_id = listing.id'
            . ' AND active_bid.utilisateur_id = :active_bid_user_id)))'
            . ' OR (listing.date_heure_fin <= UTC_TIMESTAMP() AND EXISTS(SELECT 1 FROM `ENCHERE` ended_bid'
            . ' WHERE ended_bid.annonce_id = listing.id AND ended_bid.utilisateur_id = :ended_bid_user_id)))'
            . ' ORDER BY CASE WHEN listing.date_heure_fin > UTC_TIMESTAMP() THEN 0 ELSE 1 END,'
            . ' listing.date_heure_fin ASC, listing.id ASC';
        return $this->database->fetchAll($sql, [
            'best_user_id' => $userId,
            'follow_user_id' => $userId,
            'owner_user_id' => $userId,
            'active_follow_user_id' => $userId,
            'active_bid_user_id' => $userId,
            'ended_bid_user_id' => $userId,
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
     * Rôle : Ajouter le filtre de catégorie externe en conservant la compatibilité avec son libellé enregistré.
     * Paramètres : Critères normalisés, conditions SQL et paramètres de requête à compléter.
     * Retour : Aucun.
     */
    private function addCategoryCriteria(array $criteria, array &$whereParts, array &$parameters): void
    {
        if ($criteria['category_id'] === null || $criteria['category_label'] === null) {
            return;
        }

        $whereParts[] = '(listing.categorie_id_externe = :category_id'
            . ' OR listing.categorie_libelle = :category_label)';
        $parameters['category_id'] = $criteria['category_id'];
        $parameters['category_label'] = $criteria['category_label'];
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
        if ($criteria['minimum_price'] !== null) {
            $whereParts[] = $currentPriceSql . ' >= :minimum_price';
            $parameters['minimum_price'] = $criteria['minimum_price'];
        }

        if ($criteria['maximum_price'] !== null) {
            $whereParts[] = $currentPriceSql . ' <= :maximum_price';
            $parameters['maximum_price'] = $criteria['maximum_price'];
        }
    }

    /**
     * Rôle : Limiter la recherche aux ventes en cours ou terminées selon le choix reçu.
     * Paramètres : Critères normalisés et conditions SQL à compléter.
     * Retour : Aucun.
     */
    private function addSaleStateCriteria(array $criteria, array &$whereParts): void
    {
        if ($criteria['sale_state'] === 'active') {
            $whereParts[] = 'listing.date_heure_fin > UTC_TIMESTAMP()';
        } elseif ($criteria['sale_state'] === 'ended') {
            $whereParts[] = 'listing.date_heure_fin <= UTC_TIMESTAMP()';
        }
    }

    /**
     * Rôle : Définir un ordre déterministe adapté à l'état des ventes recherché.
     * Paramètres : État de vente normalisé.
     * Retour : Fragment SQL contenant uniquement l'ordre interne prévu par le modèle.
     */
    private function buildOrderSql(string $saleState): string
    {
        if ($saleState === 'active') {
            return ' ORDER BY listing.date_heure_fin ASC, listing.id ASC';
        }

        if ($saleState === 'ended') {
            return ' ORDER BY listing.date_heure_fin DESC, listing.id DESC';
        }

        return ' ORDER BY CASE WHEN listing.date_heure_fin > UTC_TIMESTAMP() THEN 0 ELSE 1 END ASC,'
            . ' CASE WHEN listing.date_heure_fin > UTC_TIMESTAMP()'
            . ' THEN listing.date_heure_fin END ASC,'
            . ' CASE WHEN listing.date_heure_fin <= UTC_TIMESTAMP()'
            . ' THEN listing.date_heure_fin END DESC,'
            . ' listing.id ASC';
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
