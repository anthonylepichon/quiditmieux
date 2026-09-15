<?php

/**
 * Description générale : Modèle des annonces proposées aux enchères.
 * Rôle : Rechercher les annonces et appliquer leurs règles d'autorisation métier.
 * Tâches : Déclarer la table ANNONCE, construire la recherche et décider des modifications ou suppressions autorisées.
 * Liens avec les autres fichiers : Étend Model.php, fournit les annonces aux contrôleurs.
 */

namespace App\models;

use App\core\Model;
// NATIF PHP : DateTime est la classe native de gestion des dates et des heures ; elle permet ici de comparer les échéances avec l’instant reçu.
use DateTime;

class ListingModel extends Model
{
    // ====================
    // ATTRIBUTS
    // ====================

    // Métadonnées utilisées par le modèle parent pour les colonnes autorisées de l'annonce.
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
     * Paramètres : Auteur, titre, description, état, prix en euros, échéance et catégorie.
     * Retour : Identifiant de l'annonce créée ou null lorsque la création échoue.
     */
    public function createListing(
        int $userId,
        string $title,
        string $description,
        string $itemState,
        int $startingPriceInEuros,
        string $deadline,
        int $categoryId
    ): ?int {
        // Le modèle parent reçoit uniquement les données déjà validées par le contrôleur.
        $created = $this->create([
            'utilisateur_id' => $userId,
            'titre' => $title,
            'description' => $description,
            'etat_objet' => $itemState,
            'prix_depart' => $startingPriceInEuros,
            'date_heure_fin' => $deadline,
            'categorie_id' => $categoryId,
        ]);

        if (!$created) {
            // Aucune lecture de l'identifiant n'est tentée si l'insertion a échoué.
            return null;
        }

        // L'identifiant généré est conservé par le modèle parent après l'insertion.
        $listingId = $this->getValue($this->primaryKeyName);

        // NATIF PHP : is_int() vérifie qu’une valeur est un entier ; il évite ici d’utiliser un autre type dans un traitement numérique.
        if (!is_int($listingId)) {
            return null;
        }

        return $listingId;
    }

    /**
     * Rôle : Modifier les informations autorisées d'une annonce déjà validées par le contrôleur.
     * Paramètres : Identifiant, titre, description, état, prix en euros, échéance et catégorie.
     * Retour : true lorsque la mise à jour est exécutée, sinon false.
     */
    public function updateListing(
        int $listingId,
        string $title,
        string $description,
        string $itemState,
        int $startingPriceInEuros,
        string $deadline,
        int $categoryId
    ): bool {
        // Seuls les champs modifiables de l'annonce sont transmis à la mise à jour.
        return $this->update($listingId, [
            'titre' => $title,
            'description' => $description,
            'etat_objet' => $itemState,
            'prix_depart' => $startingPriceInEuros,
            'date_heure_fin' => $deadline,
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
        // L'autorisation de suppression est vérifiée avant l'appel de cette méthode.
        return $this->delete($listingId);
    }

    /**
     * Rôle : Vérifier rapidement si une annonce appartient à l'utilisateur demandé.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur.
     * Retour : true si l'utilisateur est propriétaire, false sinon ou null en cas d'erreur SQL.
     */
    public function isOwnedBy(int $listingId, int $userId): ?bool
    {
        // La lecture simple par identifiant réutilise la méthode héritée du modèle parent.
        $listing = $this->findById($listingId);

        if ($listing === false) {
            // Une erreur de lecture reste distincte d'une annonce absente.
            return null;
        }

        // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
        if ($listing === null || !isset($listing['utilisateur_id'])) {
            return false;
        }

        // La comparaison indique si l'utilisateur demandé est le propriétaire de l'annonce.
        return (int) $listing['utilisateur_id'] === $userId;
    }

    /**
     * Rôle : Vérifier si une annonce peut être modifiée par l'utilisateur demandé.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur, puis instant de référence.
     * Retour : true si la modification est autorisée, false si elle est interdite ou null en cas d'erreur SQL.
     */
    public function canBeModifiedBy(
        int $listingId,
        int $userId,
        DateTime $currentTime
    ): ?bool
    {
        // Les règles de modification et de suppression sont volontairement communes.
        return $this->canBeManagedBy($listingId, $userId, $currentTime);
    }

    /**
     * Rôle : Vérifier si une annonce peut être supprimée par l'utilisateur demandé.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur, puis instant de référence.
     * Retour : true si la suppression est autorisée, false si elle est interdite ou null en cas d'erreur SQL.
     */
    public function canBeDeletedBy(
        int $listingId,
        int $userId,
        DateTime $currentTime
    ): ?bool
    {
        // Les règles de modification et de suppression sont volontairement communes.
        return $this->canBeManagedBy($listingId, $userId, $currentTime);
    }

    /**
     * Rôle : Fournir le motif de la dernière interdiction de modification ou de suppression.
     * Paramètres : Aucun.
     * Retour : Motif `missing`, `owner`, `ended`, `bid`, `error` ou chaîne vide si l'action est autorisée.
     */
    public function getLastManagementRestriction(): string
    {
        // Le contrôleur peut afficher un message adapté à la dernière règle refusée.
        return $this->lastManagementRestriction;
    }

    /**
     * Rôle : Vérifier si un utilisateur peut suivre une annonce ou y déposer une enchère.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur, puis instant de référence.
     * Retour : true si la participation est autorisée, false si elle est interdite ou null en cas d'erreur SQL.
     */
    public function canReceiveParticipationFrom(
        int $listingId,
        int $userId,
        DateTime $currentTime
    ): ?bool
    {
        // Le motif par défaut couvre une éventuelle erreur pendant la vérification.
        $this->lastParticipationRestriction = 'error';

        // Lit les informations nécessaires pour vérifier les règles de participation.
        $listing = $this->database->fetchOne(
            'SELECT utilisateur_id, date_heure_fin FROM `ANNONCE`'
            . ' WHERE id = :listing_id LIMIT 1',
            ['listing_id' => $listingId]
        );

        if ($listing === false) {
            // L'appelant peut distinguer une erreur technique d'un refus métier.
            return null;
        }

        if ($listing === null
            || !isset($listing['utilisateur_id'], $listing['date_heure_fin'])
        ) {
            // L'annonce demandée est absente ou incomplète.
            $this->lastParticipationRestriction = 'missing';
            return false;
        }

        if ((int) $listing['utilisateur_id'] === $userId) {
            // Un vendeur ne peut pas participer à sa propre annonce.
            $this->lastParticipationRestriction = 'owner';
            return false;
        }

        if ((string) $listing['date_heure_fin'] <= $currentTime->format('Y-m-d H:i:s')) {
            // Une vente échue n'accepte plus de suivi ni d'enchère.
            $this->lastParticipationRestriction = 'ended';
            return false;
        }

        // Aucune restriction ne s'oppose à la participation demandée.
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
        // Le contrôleur récupère le motif associé au dernier refus de participation.
        return $this->lastParticipationRestriction;
    }

    /**
     * Rôle : Rechercher et paginer les annonces à partir de critères déjà validés.
     * Paramètres : Critères normalisés, page, nombre d'annonces par page et instant de référence.
     * Retour : Résultat structuré contenant le succès, les annonces et la pagination.
     */
    public function searchListings(
        array $criteria,
        int $requestedPage,
        int $itemsPerPage,
        DateTime $currentTime
    ): array
    {
        // Les conditions et paramètres sont construits progressivement selon les critères reçus.
        $whereParts = [];
        $parameters = [];
        $currentTimeForDatabase = $currentTime->format('Y-m-d H:i:s');
        $currentPriceSql = 'COALESCE((SELECT MAX(price_bid.montant)'
            . ' FROM `ENCHERE` price_bid'
            . ' WHERE price_bid.annonce_id = listing.id), listing.prix_depart)';

        // Chaque méthode ajoute seulement le filtre qui lui correspond.
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
            // NATIF PHP : implode() assemble les éléments d’un tableau dans une chaîne ; il construit ici une liste ou une partie de requête.
            $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
        }

        // Le comptage précède la requête des annonces pour calculer la pagination.
        $countSql = 'SELECT COUNT(*) AS total FROM `ANNONCE` listing' . $whereSql;
        $countRow = $this->database->fetchOne($countSql, $parameters);

        if ($countRow === false
            || $countRow === null
            || !isset($countRow['total'])
            // NATIF PHP : is_numeric() vérifie qu’une valeur représente un nombre ; il protège ici la conversion ou le calcul qui suit.
            || !is_numeric($countRow['total'])
        ) {
            // Un résultat de comptage inexploitable produit une réponse d'échec uniforme.
            return $this->failedSearchResult($itemsPerPage);
        }

        // Le total détermine la page réellement accessible et son décalage SQL.
        $totalItems = (int) $countRow['total'];
        // NATIF PHP : max() retourne la plus grande valeur ; il empêche ici le calcul de descendre sous la limite minimale.
        // NATIF PHP : ceil() arrondit un nombre au-dessus ; il calcule ici un nombre entier de pages.
        $totalPages = max(1, (int) ceil($totalItems / $itemsPerPage));
        // NATIF PHP : min() retourne la plus petite valeur ; il empêche ici le calcul de dépasser la limite maximale.
        $currentPage = min(max(1, $requestedPage), $totalPages);
        $offset = ($currentPage - 1) * $itemsPerPage;
        $listParameters = $parameters;
        $orderSql = $this->buildOrderSql(
            $criteria['sale_state'],
            $listParameters,
            $currentTimeForDatabase
        );
        // La requête finale reprend les mêmes filtres et ajoute l'ordre puis la pagination.
        $listSql = 'SELECT listing.id, listing.titre, listing.description,'
            . ' listing.etat_objet, listing.prix_depart, listing.date_heure_fin,'
            . ' listing.categorie_id'
            . ' FROM `ANNONCE` listing'
            . $whereSql
            . $orderSql
            . ' LIMIT ' . $itemsPerPage . ' OFFSET ' . $offset;
        $listings = $this->database->fetchAll($listSql, $listParameters);

        if ($listings === false) {
            // Une erreur de lecture est renvoyée au contrôleur dans un format prévisible.
            return $this->failedSearchResult($itemsPerPage);
        }

        if ($totalItems > 0 && $listings === []) {
            // Une liste vide malgré un total positif signale une réponse incohérente.
            return $this->failedSearchResult($itemsPerPage);
        }

        // Le résultat rassemble les annonces et les informations de pagination.
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
        // Le détail associe l'annonce au pseudo public de son vendeur.
        $sql = 'SELECT listing.id, listing.utilisateur_id, listing.titre, listing.description,'
            . ' listing.etat_objet, listing.prix_depart, listing.date_heure_fin,'
            . ' listing.categorie_id, seller.pseudo AS seller_pseudo'
            . ' FROM `ANNONCE` listing'
            . ' INNER JOIN `UTILISATEUR` seller ON seller.id = listing.utilisateur_id'
            . ' WHERE listing.id = :listing_id LIMIT 1';
        // Une seule annonce est attendue pour l'identifiant demandé.
        return $this->database->fetchOne($sql, ['listing_id' => $listingId]);
    }

    /**
     * Rôle : Récupérer les annonces vendues par l'utilisateur avec leur prix courant.
     * Paramètres : Identifiant de l'utilisateur connecté et instant de référence.
     * Retour : Liste des ventes ou false en cas d'erreur SQL.
     */
    public function getDashboardSales(int $userId, DateTime $currentTime): array|false
    {
        // Les enchères sont agrégées afin d'afficher leur nombre et la meilleure offre.
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
        // L'instant de référence place les ventes actives avant les ventes terminées.
        return $this->database->fetchAll($sql, [
            'user_id' => $userId,
            'current_time_order' => $currentTime->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Rôle : Récupérer les suivis et enchères de l'utilisateur utiles au tableau de bord.
     * Paramètres : Identifiant de l'utilisateur connecté et instant de référence.
     * Retour : Participations de l'utilisateur ou false en cas d'erreur SQL.
     */
    public function getDashboardParticipations(int $userId, DateTime $currentTime): array|false
    {
        // Cette sous-requête identifie le gagnant d'une vente terminée.
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
        // Le même instant est envoyé à chaque comparaison de date de la requête.
        $currentTimeForDatabase = $currentTime->format('Y-m-d H:i:s');
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
        // NATIF PHP : is_array() vérifie qu’une valeur est un tableau ; il évite ici de parcourir ou transmettre un type inattendu.
        if (!isset($criteria['words']) || !is_array($criteria['words'])) {
            return;
        }

        foreach ($criteria['words'] as $index => $word) {
            // NATIF PHP : is_string() vérifie qu’une valeur est une chaîne de caractères ; il évite ici de traiter un type inattendu comme du texte.
            if (!is_string($word) || $word === '') {
                continue;
            }

            // Chaque mot possède son propre paramètre pour conserver une requête préparée.
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
            // L'absence de catégorie ne doit pas restreindre la recherche.
            return;
        }

        // Le filtre et sa valeur préparée sont ajoutés ensemble.
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
            // L'absence d'état ne doit pas restreindre la recherche.
            return;
        }

        // Le filtre et sa valeur préparée sont ajoutés ensemble.
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
            // Les deux bornes forment une plage de prix inclusive.
            $whereParts[] = $currentPriceSql . ' BETWEEN :minimum_price AND :maximum_price';
            $parameters['minimum_price'] = $criteria['minimum_price_in_euros'];
            $parameters['maximum_price'] = $criteria['maximum_price_in_euros'];
            return;
        }

        if ($criteria['minimum_price_in_euros'] !== null) {
            // Une borne basse seule fixe le prix minimal accepté.
            $whereParts[] = $currentPriceSql . ' >= :minimum_price';
            $parameters['minimum_price'] = $criteria['minimum_price_in_euros'];
        }

        if ($criteria['maximum_price_in_euros'] !== null) {
            // Une borne haute seule fixe le prix maximal accepté.
            $whereParts[] = $currentPriceSql . ' <= :maximum_price';
            $parameters['maximum_price'] = $criteria['maximum_price_in_euros'];
        }
    }

    /**
     * Rôle : Limiter la recherche aux ventes en cours ou terminées selon le choix reçu.
     * Paramètres : Critères normalisés, conditions, paramètres SQL et instant formaté.
     * Retour : Aucun.
     */
    private function addSaleStateCriteria(
        array $criteria,
        array &$whereParts,
        array &$parameters,
        string $currentTime
    ): void
    {
        if ($criteria['sale_state'] === 'active') {
            // Une vente active se termine après l'instant de référence.
            $whereParts[] = 'listing.date_heure_fin > :current_time_filter';
            $parameters['current_time_filter'] = $currentTime;
        } elseif ($criteria['sale_state'] === 'ended') {
            // Une vente terminée se termine à cet instant ou avant.
            $whereParts[] = 'listing.date_heure_fin <= :current_time_filter';
            $parameters['current_time_filter'] = $currentTime;
        }
    }

    /**
     * Rôle : Définir un ordre déterministe adapté à l'état des ventes recherché.
     * Paramètres : État de vente normalisé, paramètres SQL et instant formaté.
     * Retour : Fragment SQL contenant uniquement l'ordre interne prévu par le modèle.
     */
    private function buildOrderSql(
        string $saleState,
        array &$parameters,
        string $currentTime
    ): string
    {
        if ($saleState === 'active') {
            // Les ventes actives les plus proches de leur fin sont affichées d'abord.
            return ' ORDER BY listing.date_heure_fin ASC, listing.id ASC';
        }

        if ($saleState === 'ended') {
            // Les ventes terminées les plus récentes sont affichées d'abord.
            return ' ORDER BY listing.date_heure_fin DESC, listing.id DESC';
        }

        // Sans filtre d'état, les ventes actives précèdent les ventes terminées.
        $parameters['current_time_order_state'] = $currentTime;
        $parameters['current_time_order_active'] = $currentTime;
        $parameters['current_time_order_ended'] = $currentTime;

        return ' ORDER BY CASE WHEN listing.date_heure_fin > :current_time_order_state THEN 0 ELSE 1 END ASC,'
            . ' CASE WHEN listing.date_heure_fin > :current_time_order_active'
            . ' THEN listing.date_heure_fin END ASC,'
            . ' CASE WHEN listing.date_heure_fin <= :current_time_order_ended'
            . ' THEN listing.date_heure_fin END DESC,'
            . ' listing.id ASC';
    }

    /**
     * Rôle : Appliquer les règles communes de modification et de suppression d'une annonce.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur, puis instant de référence.
     * Retour : true si l'action est autorisée, false si elle est interdite ou null en cas d'erreur SQL.
     */
    private function canBeManagedBy(
        int $listingId,
        int $userId,
        DateTime $currentTime
    ): ?bool
    {
        // Le motif par défaut couvre une éventuelle erreur pendant la vérification.
        $this->lastManagementRestriction = 'error';

        // La lecture rassemble le propriétaire, l'échéance et la présence d'enchères.
        $listing = $this->database->fetchOne(
            'SELECT listing.utilisateur_id, listing.date_heure_fin,'
            . ' EXISTS(SELECT 1 FROM `ENCHERE` bid WHERE bid.annonce_id = listing.id) AS has_bid'
            . ' FROM `ANNONCE` listing WHERE listing.id = :listing_id LIMIT 1',
            ['listing_id' => $listingId]
        );

        if ($listing === false) {
            // L'appelant peut distinguer une erreur technique d'un refus métier.
            return null;
        }

        if ($listing === null
            || !isset($listing['utilisateur_id'], $listing['date_heure_fin'], $listing['has_bid'])
        ) {
            // L'annonce demandée est absente ou ses données sont incomplètes.
            $this->lastManagementRestriction = 'missing';
            return false;
        }

        if ((int) $listing['utilisateur_id'] !== $userId) {
            // Seul le vendeur peut gérer son annonce.
            $this->lastManagementRestriction = 'owner';
            return false;
        }

        if ((string) $listing['date_heure_fin'] <= $currentTime->format('Y-m-d H:i:s')) {
            // Une annonce échue ne peut plus être modifiée ni supprimée.
            $this->lastManagementRestriction = 'ended';
            return false;
        }

        if ((bool) $listing['has_bid']) {
            // Une première enchère fige l'annonce pour préserver les participants.
            $this->lastManagementRestriction = 'bid';
            return false;
        }

        // Toutes les règles sont respectées : l'action de gestion est autorisée.
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
        // La structure reste identique à une recherche réussie pour simplifier le contrôleur.
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
