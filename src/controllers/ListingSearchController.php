<?php

/**
 * Description générale : Contrôleur de recherche des annonces proposées aux enchères.
 * Rôle : Afficher l'accueil et traiter les recherches d'annonces.
 * Tâches : Valider les critères, interroger les modèles, enrichir les résultats et préparer la pagination.
 * Liens avec les autres fichiers : Étend Controller.php et utilise les modèles nécessaires à la recherche ainsi que PhotoStorage.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\core\Database;
use App\core\Session;
use App\services\PhotoStorage;
use App\models\BidModel;
use App\models\CategoryModel;
use App\models\ListingModel;
use App\models\PhotoModel;
// NATIF PHP : DateTime est la classe native de gestion des dates et des heures ; elle permet ici de comparer les échéances et de les formater.
use DateTime;

class ListingSearchController extends Controller
{
    // ====================
    // CONSTANTES
    // ====================

    private const ITEMS_PER_PAGE = 12;
    private const ITEM_STATES = ['neuf', 'très bon état', 'bon état', 'état correct'];
    private const SALE_STATES = ['all', 'active', 'ended'];

    // ====================
    // ATTRIBUTS
    // ====================

    private PhotoStorage $photoStorage;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Conserver les dépendances communes et préparer le gestionnaire des fichiers photographiques.
     * Paramètres : Gestionnaires de base de données et de session partagés avec l'application.
     * Retour : Aucun.
     */
    public function __construct(Database $database, Session $session)
    {
        // Le stockage physique est séparé du modèle PhotoModel, qui ne conserve que les références en base.
        parent::__construct($database, $session);
        $this->photoStorage = new PhotoStorage();
    }

    /**
     * Rôle : Afficher l'accueil ou renvoyer les résultats actualisés d'une recherche paginée.
     * Paramètres : Aucun, les critères sont lus dans la requête GET.
     * Retour : Aucun, une réponse HTML ou JSON est envoyée au navigateur.
     */
    public function search(): void
    {
        // Les catégories servent à valider le filtre et à afficher les libellés de chaque annonce.
        $currentTime = new DateTime();
        $categories = (new CategoryModel($this->database))->getAllCategories();
        $categoriesAvailable = $categories !== null;

        if ($categories === null) {
            $categories = [];
        }

        // Les critères sont normalisés avant toute requête de recherche.
        $validation = $this->validateCriteria($categories, $categoriesAvailable);
        $criteria = $validation['criteria'];
        $errors = $validation['errors'];
        $hasCustomCriteria = $validation['has_custom_criteria'];
        $searchResult = $this->emptySearchResult();
        $stateKey = 'invalid_criteria';
        $message = 'Corrigez les champs signalés, puis relancez la recherche. Vos autres critères sont conservés.';
        $success = $errors === [];

        // Le modèle n'est interrogé que lorsque les critères reçus sont valides.
        if ($success) {
            $listingModel = new ListingModel($this->database);
            $searchResult = $listingModel->searchListings(
                $criteria,
                $criteria['page'],
                self::ITEMS_PER_PAGE,
                $currentTime
            );
            $success = $searchResult['success'];

            if ($success) {
                $enrichedListings = $this->enrichListings(
                    $searchResult['listings'],
                    $categories,
                    $currentTime
                );

                if ($enrichedListings === false) {
                    $success = false;
                    $searchResult['success'] = false;
                    $searchResult['listings'] = [];
                    $stateKey = 'search_error';
                    $message = 'Les annonces ne peuvent pas être actualisées pour le moment.';
                } else {
                    $searchResult['listings'] = $enrichedListings;
                    $criteria['page'] = $searchResult['current_page'];
                    $stateKey = $this->determineStateKey(
                        $searchResult,
                        $hasCustomCriteria,
                        $categoriesAvailable
                    );
                    $message = $this->buildStateMessage($stateKey, $searchResult);
                }
            } else {
                $stateKey = 'search_error';
                $message = 'Les annonces ne peuvent pas être actualisées pour le moment.';
            }
        }

        $pagination = $this->buildPagination($criteria, $searchResult);
        $publicCriteria = $this->publicCriteria($criteria);
        $searchDisplay = $this->buildSearchDisplay(
            $stateKey,
            $message,
            $errors,
            $categoriesAvailable,
            $publicCriteria,
            $categories,
            $pagination
        );
        $response = [
            'success' => $success,
            'message' => $message,
            'state_key' => $stateKey,
            'criteria' => $publicCriteria,
            'errors' => $errors,
            'field_error_messages' => $searchDisplay['field_error_messages'],
            'first_search_error' => $searchDisplay['first_search_error'],
            'has_price_range_error' => $searchDisplay['has_price_range_error'],
            'results_state_key' => $searchDisplay['results_state_key'],
            'results_message' => $searchDisplay['results_message'],
            'filter_summary' => $searchDisplay['filter_summary'],
            'categories_available' => $categoriesAvailable,
            'categories' => $categories,
            'listings' => $searchResult['listings'],
            'pagination' => $pagination,
            'total_items' => $searchResult['total_items'],
            'is_connected' => $this->session->isUserConnected(),
            'csrf_token' => $this->session->getCsrfToken(),
            'flash_success' => $this->session->getFlashMessage('success'),
            'flash_notice' => $this->session->getFlashMessage('notice'),
        ];

        // JavaScript attend une structure JSON ; une navigation classique reçoit le template complet.
        if ($this->isJsonRequest()) {
            if ($errors !== []) {
                // NATIF PHP : http_response_code() définit le statut HTTP de la réponse ; il signale ici au client si la demande a réussi ou rencontré une erreur.
                http_response_code(422);
            }

            $this->json($response);
            return;
        }

        $this->render('pages/home.php', $response);
    }

    /**
     * Rôle : Contrôler et normaliser tous les critères reçus depuis la recherche.
     * Paramètres : Catégories disponibles et indicateur de disponibilité de l'API.
     * Retour : Critères normalisés, erreurs associées et présence d'une recherche personnalisée.
     */
    private function validateCriteria(array $categories, bool $categoriesAvailable): array
    {
        $errors = [];
        $text = $this->readStringParameter('q', $errors);
        $categoryInput = $this->readStringParameter('category', $errors);
        $itemStateInput = $this->readStringParameter('item_state', $errors);
        $minimumPriceInput = $this->readStringParameter('minimum_price', $errors);
        $maximumPriceInput = $this->readStringParameter('maximum_price', $errors);
        $saleStateInput = $this->readStringParameter('sale_state', $errors);
        $pageInput = $this->readStringParameter('page', $errors);

        $text = trim($text);
        $categoryInput = trim($categoryInput);
        $itemStateInput = trim($itemStateInput);
        $minimumPriceInput = trim($minimumPriceInput);
        $maximumPriceInput = trim($maximumPriceInput);
        $saleStateInput = trim($saleStateInput);
        $pageInput = trim($pageInput);

        if (mb_strlen($text) > 120) {
            $errors['q'] = 'La recherche ne peut pas dépasser 120 caractères.';
        }

        $words = [];

        if ($text !== '') {
            // NATIF PHP : preg_split() découpe un texte avec une expression régulière ; il sépare ici les différentes valeurs reçues.
            $splitWords = preg_split('/\s+/u', $text);

            if (is_array($splitWords)) {
                // NATIF PHP : array_filter() retire les éléments ne respectant pas le filtre ; il conserve ici uniquement les valeurs utiles.
                $words = array_values(array_filter($splitWords, 'is_string'));
            }
        }

        $categoryId = null;

        if ($categoriesAvailable && $categoryInput !== '') {
            if (
                preg_match('/^[1-9][0-9]*$/D', $categoryInput) !== 1
                || !isset($categories[$categoryInput])
            ) {
                $errors['category'] = 'Choisissez une catégorie proposée dans la liste.';
            } else {
                $categoryId = (int) $categoryInput;
            }
        }

        $itemState = null;

        if ($itemStateInput !== '') {
            if (!in_array($itemStateInput, self::ITEM_STATES, true)) {
                $errors['item_state'] = 'Choisissez un état de l’objet proposé dans la liste.';
            } else {
                $itemState = $itemStateInput;
            }
        }

        $minimumPriceInEuros = $this->normalizePriceInEuros(
            $minimumPriceInput,
            'minimum_price',
            'Le prix minimum',
            $errors
        );
        $maximumPriceInEuros = $this->normalizePriceInEuros(
            $maximumPriceInput,
            'maximum_price',
            'Le prix maximum',
            $errors
        );

        if ($minimumPriceInEuros !== null) {
            $minimumPriceInput = $minimumPriceInEuros;
        }

        if ($maximumPriceInEuros !== null) {
            $maximumPriceInput = $maximumPriceInEuros;
        }

        if (
            $minimumPriceInEuros !== null
            && $maximumPriceInEuros !== null
            && $minimumPriceInEuros > $maximumPriceInEuros
        ) {
            $errors['minimum_price'] = 'Le prix minimum doit être inférieur ou égal au prix maximum.';
            $errors['maximum_price'] = 'Le prix maximum doit être supérieur ou égal au prix minimum.';
        }

        $saleState = 'active';

        if ($saleStateInput !== '') {
            if (!in_array($saleStateInput, self::SALE_STATES, true)) {
                $errors['sale_state'] = 'Choisissez un état de vente proposé dans la liste.';
            } else {
                $saleState = $saleStateInput;
            }
        }

        $page = 1;

        if ($pageInput !== '') {
            $validPage = filter_var($pageInput, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 100000],
            ]);

            if ($validPage !== false) {
                $page = (int) $validPage;
            }
        }

        $hasCustomCriteria = $text !== ''
            || ($categoriesAvailable && $categoryInput !== '')
            || $itemStateInput !== ''
            || $minimumPriceInput !== ''
            || $maximumPriceInput !== ''
            || ($saleStateInput !== '' && $saleStateInput !== 'active');

        return [
            'criteria' => [
                'text' => $text,
                'words' => $words,
                'category_id' => $categoryId,
                'item_state' => $itemState,
                'minimum_price_in_euros' => $minimumPriceInEuros,
                'maximum_price_in_euros' => $maximumPriceInEuros,
                'minimum_price_input' => $minimumPriceInput,
                'maximum_price_input' => $maximumPriceInput,
                'sale_state' => $saleState,
                'page' => $page,
            ],
            'errors' => $errors,
            'has_custom_criteria' => $hasCustomCriteria,
        ];
    }

    /**
     * Rôle : Lire un paramètre GET simple sans accepter de tableau ou d'objet inattendu.
     * Paramètres : Nom du paramètre et tableau d'erreurs à compléter.
     * Retour : Chaîne reçue ou chaîne vide lorsque le paramètre est absent ou invalide.
     */
    private function readStringParameter(string $name, array &$errors): string
    {
        // NATIF PHP : $_GET est un tableau superglobal contenant les paramètres de l’URL ; il récupère ici la route, un identifiant ou un filtre transmis en GET.
        if (!isset($_GET[$name])) {
            return '';
        }

        if (!is_string($_GET[$name])) {
            $errors[$name] = 'La valeur reçue n’est pas utilisable.';
            return '';
        }

        return $_GET[$name];
    }

    /**
     * Rôle : Valider et convertir une limite de prix facultative en euros entiers.
     * Paramètres : Valeur reçue, nom du champ, libellé compréhensible et erreurs à compléter.
     * Retour : Prix en euros ou null lorsque le champ est vide ou invalide.
     */
    private function normalizePriceInEuros(
        string $value,
        string $field,
        string $label,
        array &$errors
    ): ?int {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^[0-9]+$/D', $value) !== 1) {
            $errors[$field] = $label . ' doit être un nombre entier d’euros, sans décimale.';
            return null;
        }

        $priceInEuros = (int) $value;

        if ($priceInEuros <= 0 || $priceInEuros > 99_999) {
            $errors[$field] = $label . ' doit être strictement positif et rester dans la limite autorisée.';
            return null;
        }

        return $priceInEuros;
    }

    /**
     * Rôle : Ajouter le prix courant, la photographie principale et les informations d'affichage aux annonces.
     * Paramètres : Annonces brutes, catégories fournies par l'API et instant de référence.
     * Retour : Annonces prêtes à afficher ou false en cas d'erreur SQL complémentaire.
     */
    private function enrichListings(
        array $listings,
        array $categories,
        DateTime $currentTime
    ): array|false {
        $listingIds = [];
        $startingPrices = [];

        foreach ($listings as $listing) {
            if (!isset($listing['id'], $listing['prix_depart'])) {
                continue;
            }

            $identifier = (int) $listing['id'];
            $listingIds[] = $identifier;
            $startingPrices[$identifier] = $listing['prix_depart'];
        }

        $bidModel = new BidModel($this->database);
        $photoModel = new PhotoModel($this->database);
        $currentAmountsInEuros = $bidModel->getCurrentAmountsInEuros($listingIds, $startingPrices);
        $primaryPhotos = $photoModel->getPrimaryPhotos($listingIds);

        if ($currentAmountsInEuros === false || $primaryPhotos === false) {
            return false;
        }

        $displayListings = [];

        foreach ($listings as $listing) {
            if (!isset(
                $listing['id'],
                $listing['titre'],
                $listing['categorie_id'],
                $listing['date_heure_fin']
            )) {
                continue;
            }

            $identifier = (int) $listing['id'];
            $deadline = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                (string) $listing['date_heure_fin']
            );

            if (!$deadline instanceof DateTime) {
                continue;
            }

            $photoUrl = null;

            if (isset($primaryPhotos[$identifier])) {
                $photoUrl = $this->photoStorage->getPublicUrl($primaryPhotos[$identifier]);
            }

            if (!isset($currentAmountsInEuros[$identifier])) {
                return false;
            }

            $currentAmountInEuros = $currentAmountsInEuros[$identifier];
            $categoryId = (int) $listing['categorie_id'];
            $categoryLabel = 'Catégorie indisponible';

            if (isset($categories[$categoryId])) {
                $categoryLabel = (string) $categories[$categoryId];
            }

            $saleState = 'ended';

            if ($deadline > $currentTime) {
                $saleState = 'active';
            }

            $saleStateLabel = 'Vente terminée';
            $deadlineDisplayLabel = 'Vente terminée';

            if ($saleState === 'active') {
                $saleStateLabel = 'Vente en cours';
                $deadlineDisplayLabel = $this->formatFrenchDateTime(
                    $deadline,
                    false,
                    true
                );
            }

            $displayListings[] = [
                'id' => $identifier,
                'title' => (string) $listing['titre'],
                'category' => $categoryLabel,
                'item_state' => (string) $listing['etat_objet'],
                'current_price' => $currentAmountInEuros,
                'current_price_label' => $this->formatEuros($currentAmountInEuros),
                'deadline' => $deadline->format(DATE_ATOM),
                'deadline_label' => $this->formatFrenchDateTime(
                    $deadline,
                    false,
                    true
                ),
                'sale_state' => $saleState,
                'sale_state_label' => $saleStateLabel,
                'deadline_display_label' => $deadlineDisplayLabel,
                'photo_url' => $photoUrl,
                'detail_url' => $this->buildRouteUrl('listing_detail', ['id' => $identifier]),
            ];
        }

        return $displayListings;
    }

    /**
     * Rôle : Choisir l'état d'affichage correspondant au résultat de la recherche.
     * Paramètres : Résultat paginé, présence de critères personnalisés et disponibilité des catégories.
     * Retour : Clé d'état utilisée par le template et JavaScript.
     */
    private function determineStateKey(
        array $searchResult,
        bool $hasCustomCriteria,
        bool $categoriesAvailable
    ): string {
        if (!$categoriesAvailable) {
            return 'categories_unavailable';
        }

        if ($searchResult['total_items'] === 0) {
            return 'no_results';
        }

        if ($searchResult['current_page'] > 1) {
            return 'pagination';
        }

        if ($hasCustomCriteria) {
            return 'filtered_results';
        }

        return 'initial';
    }

    /**
     * Rôle : Produire un message compréhensible adapté à l'état courant de l'accueil.
     * Paramètres : Clé d'état et résultat paginé.
     * Retour : Message destiné à l'affichage ou à la réponse JSON.
     */
    private function buildStateMessage(string $stateKey, array $searchResult): string
    {
        if ($stateKey === 'categories_unavailable') {
            return 'Les autres critères restent utilisables et les annonces existantes conservent leur catégorie enregistrée.';
        }

        if ($stateKey === 'no_results') {
            return 'Modifiez un ou plusieurs critères pour élargir votre recherche.';
        }

        if ($stateKey === 'pagination') {
            return $searchResult['total_items']
                . ' ventes · page '
                . $searchResult['current_page']
                . '/'
                . $searchResult['total_pages'];
        }

        if ($stateKey === 'filtered_results') {
            $resultLabel = ' annonce correspond à votre recherche';

            if ((int) $searchResult['total_items'] > 1) {
                $resultLabel = ' annonces correspondent à votre recherche';
            }

            return $searchResult['total_items'] . $resultLabel;
        }

        return $searchResult['total_items'] . ' ventes actives · échéance croissante';
    }

    /**
     * Rôle : Préparer les messages et libellés d'affichage de la recherche.
     * Paramètres : État, message métier, erreurs, catégories, critères publics et pagination.
     * Retour : Données de présentation prêtes à afficher sans décision supplémentaire dans le template.
     */
    private function buildSearchDisplay(
        string $stateKey,
        string $message,
        array $errors,
        bool $categoriesAvailable,
        array $criteria,
        array $categories,
        array $pagination
    ): array {
        $fieldErrorMessages = [
            'q' => '',
            'category' => '',
            'item_state' => '',
            'sale_state' => '',
            'minimum_price' => '',
            'maximum_price' => '',
        ];

        foreach (array_keys($fieldErrorMessages) as $fieldName) {
            if (isset($errors[$fieldName]) && is_string($errors[$fieldName])) {
                $fieldErrorMessages[$fieldName] = $errors[$fieldName];
            }
        }

        if (!$categoriesAvailable && $fieldErrorMessages['category'] === '') {
            $fieldErrorMessages['category'] = 'Catégories temporairement indisponibles.';
        }

        $firstSearchError = 'Corrigez les champs signalés, puis relancez la recherche. Vos autres critères sont conservés.';
        $hasPriceRangeError = false;

        if (isset($errors['minimum_price'], $errors['maximum_price'])
            && $errors['maximum_price'] === 'Le prix maximum doit être supérieur ou égal au prix minimum.'
        ) {
            $hasPriceRangeError = true;
            $firstSearchError = 'Le prix maximum doit être supérieur ou égal au prix minimum.';
            $fieldErrorMessages['maximum_price'] = 'Le maximum doit être supérieur ou égal au minimum.';
        }

        $resultsStateKey = $stateKey;

        if ($stateKey === 'initial' && (int) $pagination['total_pages'] > 1) {
            $resultsStateKey = 'pagination';
        }

        $resultsMessage = [
            'title' => '',
            'body' => $message,
            'blocked_title' => '',
            'blocked_body' => '',
        ];

        if ($stateKey === 'invalid_criteria') {
            $resultsMessage = [
                'title' => 'Corrigez les critères indiqués',
                'body' => $firstSearchError,
                'blocked_title' => 'La recherche n’a pas été exécutée.',
                'blocked_body' => 'Corrigez les champs signalés, puis relancez la recherche. Vos autres critères sont conservés.',
            ];
        } elseif ($stateKey === 'search_error') {
            $resultsMessage['title'] = 'Recherche temporairement indisponible';
        } elseif ($stateKey === 'categories_unavailable') {
            $resultsMessage['title'] = 'Catégories temporairement indisponibles';
        } elseif ($stateKey === 'no_results') {
            $resultsMessage = [
                'title' => 'Aucune annonce ne correspond à vos critères',
                'body' => 'Modifiez un ou plusieurs critères pour élargir votre recherche.',
                'blocked_title' => '',
                'blocked_body' => '',
            ];
        }

        $filterSummaryParts = [];

        if ((string) $criteria['text'] !== '') {
            $filterSummaryParts[] = (string) $criteria['text'];
        }

        if ($criteria['category_id'] !== null && isset($categories[$criteria['category_id']])) {
            $filterSummaryParts[] = (string) $categories[$criteria['category_id']];
        }

        if ($criteria['item_state'] !== null) {
            $filterSummaryParts[] = ucfirst((string) $criteria['item_state']);
        }

        if ((string) $criteria['minimum_price'] !== '' || (string) $criteria['maximum_price'] !== '') {
            $minimumPriceLabel = '0,00 €';
            $maximumPriceLabel = 'sans limite';

            if ((string) $criteria['minimum_price'] !== '') {
                $minimumPriceLabel = (string) $criteria['minimum_price'] . ' €';
            }

            if ((string) $criteria['maximum_price'] !== '') {
                $maximumPriceLabel = (string) $criteria['maximum_price'] . ' €';
            }

            $filterSummaryParts[] = $minimumPriceLabel . ' à ' . $maximumPriceLabel;
        }

        if ((string) $criteria['sale_state'] !== 'active') {
            $saleStateLabel = 'Toutes';

            if ((string) $criteria['sale_state'] === 'ended') {
                $saleStateLabel = 'Terminées';
            }

            $filterSummaryParts[] = $saleStateLabel;
        }

        return [
            'field_error_messages' => $fieldErrorMessages,
            'first_search_error' => $firstSearchError,
            'has_price_range_error' => $hasPriceRangeError,
            'results_state_key' => $resultsStateKey,
            'results_message' => $resultsMessage,
            'filter_summary' => implode(' · ', $filterSummaryParts),
        ];
    }

    /**
     * Rôle : Construire les informations et liens de pagination en conservant les critères.
     * Paramètres : Critères normalisés et résultat de recherche.
     * Retour : Informations de pagination nécessaires à l'affichage.
     */
    private function buildPagination(array $criteria, array $searchResult): array
    {
        $currentPage = $searchResult['current_page'];
        $totalPages = $searchResult['total_pages'];
        $previousUrl = null;
        $nextUrl = null;

        if ($currentPage > 1) {
            $previousUrl = $this->buildSearchUrl($criteria, $currentPage - 1);
        }

        if ($currentPage < $totalPages) {
            $nextUrl = $this->buildSearchUrl($criteria, $currentPage + 1);
        }

        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'previous_url' => $previousUrl,
            'next_url' => $nextUrl,
        ];
    }

    /**
     * Rôle : Construire une adresse GET partageable pour une page de résultats.
     * Paramètres : Critères normalisés et numéro de page à intégrer.
     * Retour : Adresse interne de la recherche.
     */
    private function buildSearchUrl(array $criteria, int $page): string
    {
        $parameters = [];

        if ($criteria['text'] !== '') {
            $parameters['q'] = $criteria['text'];
        }

        if ($criteria['category_id'] !== null) {
            $parameters['category'] = $criteria['category_id'];
        }

        if ($criteria['item_state'] !== null) {
            $parameters['item_state'] = $criteria['item_state'];
        }

        if ($criteria['minimum_price_input'] !== '') {
            $parameters['minimum_price'] = $criteria['minimum_price_input'];
        }

        if ($criteria['maximum_price_input'] !== '') {
            $parameters['maximum_price'] = $criteria['maximum_price_input'];
        }

        if ($criteria['sale_state'] !== 'active') {
            $parameters['sale_state'] = $criteria['sale_state'];
        }

        if ($page > 1) {
            $parameters['page'] = $page;
        }

        return $this->buildRouteUrl('home', $parameters);
    }

    /**
     * Rôle : Limiter les critères renvoyés au navigateur aux valeurs utiles à l'interface.
     * Paramètres : Critères internes normalisés.
     * Retour : Critères publics utilisables par le template et JavaScript.
     */
    private function publicCriteria(array $criteria): array
    {
        return [
            'text' => $criteria['text'],
            'category_id' => $criteria['category_id'],
            'item_state' => $criteria['item_state'],
            'minimum_price' => $criteria['minimum_price_input'],
            'maximum_price' => $criteria['maximum_price_input'],
            'sale_state' => $criteria['sale_state'],
            'page' => $criteria['page'],
        ];
    }

    /**
     * Rôle : Fournir un résultat vide cohérent avant validation ou en cas d'erreur.
     * Paramètres : Aucun.
     * Retour : Structure vide de recherche et de pagination.
     */
    private function emptySearchResult(): array
    {
        return [
            'success' => true,
            'listings' => [],
            'total_items' => 0,
            'current_page' => 1,
            'total_pages' => 1,
            'items_per_page' => self::ITEMS_PER_PAGE,
        ];
    }

}
