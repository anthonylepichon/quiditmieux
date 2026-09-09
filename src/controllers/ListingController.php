<?php

/**
 * Description générale : Contrôleur des annonces proposées aux enchères.
 * Rôle : Coordonner les demandes liées aux annonces et choisir leur réponse HTML ou JSON.
 * Tâches : Lire et valider les requêtes, interroger les modèles et préparer l'affichage.
 * Liens avec les autres fichiers : Étend Controller.php et utilise PhotoStorage.php ainsi que les modèles liés aux annonces.
 */

namespace App\controllers;

use App\core\Controller;
use App\core\Database;
use App\services\PhotoStorage;
use App\core\Session;
use App\models\BidModel;
use App\models\CategoryModel;
use App\models\FollowModel;
use App\models\ListingModel;
use App\models\PhotoModel;
// NATIF PHP : DateTimeImmutable est la classe native de gestion des dates sans modification de l’objet original ; elle fiabilise ici les comparaisons et les formats.
use DateTimeImmutable;

class ListingController extends Controller
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
        $currentTime = new DateTimeImmutable();
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
     * Rôle : Afficher le détail complet d'une annonce selon son état et les droits du visiteur.
     * Paramètres : Aucun, l'identifiant est lu dans la requête GET.
     * Retour : Aucun, le template de détail ou une redirection sûre est envoyé.
     */
    public function showDetail(): void
    {
        // L'instant de référence est partagé par tous les calculs d'état de l'annonce affichée.
        $currentTime = new DateTimeImmutable();
        // L'identifiant est contrôlé avant tout accès à l'annonce ou à ses informations associées.
        $listingId = $this->readPositiveGetIdentifier('id');

        if ($listingId === null) {
            $this->session->setFlashMessage('notice', 'L’annonce demandée est introuvable.');
            $this->redirect('home');
        }

        // Le modèle lit les données métier ; le contrôleur choisit seulement la réponse appropriée.
        $listingModel = new ListingModel($this->database);
        $listing = $listingModel->getDetail($listingId);

        if ($listing === false) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('home');
        }

        if ($listing === null) {
            $this->session->setFlashMessage('notice', 'L’annonce demandée est introuvable.');
            $this->redirect('home');
        }

        $categoryLabel = (new CategoryModel($this->database))->getCategoryLabel((int) $listing['categorie_id']);

        if ($categoryLabel === null) {
            $categoryLabel = 'Catégorie indisponible';
        }

        $bidModel = new BidModel($this->database);
        $photoModel = new PhotoModel($this->database);
        $followModel = new FollowModel($this->database);
        $summary = $bidModel->getSummary($listingId);

        if ($summary === false) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('home');
        }

        $viewerId = $this->session->getConnectedUserId();
        $isOwner = $viewerId !== null && $viewerId === (int) $listing['utilisateur_id'];
        $viewerHasBid = false;
        $isFollowing = false;

        if ($viewerId !== null && !$isOwner) {
            $viewerHasBid = $bidModel->userHasBid($listingId, $viewerId);
            $isFollowing = $followModel->isFollowing($viewerId, $listingId);

            if ($viewerHasBid === null || $isFollowing === null) {
                $this->session->setFlashMessage(
                    'notice',
                    'Les données de cette annonce sont momentanément indisponibles.'
                );
                $this->redirect('home');
            }
        }

        $deadline = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) $listing['date_heure_fin']
        );

        if (!$deadline instanceof DateTimeImmutable) {
            $this->session->setFlashMessage('notice', 'Cette annonce ne peut pas être affichée.');
            $this->redirect('home');
        }

        $isEnded = $deadline <= $currentTime;
        $currentAmountInEuros = (int) $listing['prix_depart'];



        if ($summary['best_bid_in_euros'] !== null) {
            $currentAmountInEuros = $summary['best_bid_in_euros'];
        }

        $history = [];

        if ($isOwner || $viewerHasBid) {
            $historyRows = $bidModel->getHistory($listingId);

            if ($historyRows === false) {
                $this->session->setFlashMessage(
                    'notice',
                    'Les données de cette annonce sont momentanément indisponibles.'
                );
                $this->redirect('home');
            }

            $history = $this->formatBidHistory($historyRows);
        }

        $photos = [];
        $photoRows = $photoModel->getListingPhotos($listingId);

        if ($photoRows === false) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('home');
        }

        foreach ($photoRows as $photo) {
            $photo['url'] = $this->photoStorage->getPublicUrl($photo['filename']);
            $photos[] = $photo;
        }

        $bidRejection = $this->recoverBidRejection($listingId);
        $canEdit = false;
        $canParticipate = false;

        if ($viewerId !== null) {
            $canEdit = $listingModel->canBeModifiedBy($listingId, $viewerId, $currentTime);
            $canParticipate = $listingModel->canReceiveParticipationFrom(
                $listingId,
                $viewerId,
                $currentTime
            );

            if ($canEdit === null || $canParticipate === null) {
                $this->session->setFlashMessage(
                    'notice',
                    'Les données de cette annonce sont momentanément indisponibles.'
                );
                $this->redirect('home');
            }
        }

        $this->render('pages/listing-detail.php', [
            'listing' => [
                'id' => $listingId,
                'title' => (string) $listing['titre'],
                'description' => (string) $listing['description'],
                'item_state' => (string) $listing['etat_objet'],
                'category' => $categoryLabel,
                'seller' => (string) $listing['seller_pseudo'],
                'seller_id' => (int) $listing['utilisateur_id'],
                'current_price_label' => $this->formatEuros($currentAmountInEuros),
                'minimum_bid' => $currentAmountInEuros + 1,
                'minimum_bid_label' => $this->formatEuros($currentAmountInEuros + 1),
                'bid_count' => (int) $summary['bid_count'],
                // NATIF PHP : DATE_ATOM désigne le format de date ISO 8601 ; elle prépare ici une date interprétable sans ambiguïté par JavaScript.
                'deadline' => $deadline->format(DATE_ATOM),
                'deadline_label' => $this->formatFrenchDateTime(
                    $deadline,
                    true,
                    false
                ),
                'is_ended' => $isEnded,
                'final_state' => $this->determineFinalState($isEnded, (int) $summary['bid_count']),
            ],
            'photos' => $photos,
            'history' => $history,
            'bid_rejection' => $bidRejection,
            'viewer' => [
                'is_connected' => $viewerId !== null,
                'is_owner' => $isOwner,
                'has_bid' => $viewerHasBid,
                'is_best_bidder' => $viewerId !== null && $viewerId === $summary['best_bidder_id'],
                'is_following' => $isFollowing,
                'can_edit' => $canEdit === true,
                'can_follow' => $canParticipate === true,
                'can_bid' => $canParticipate === true,
                'can_view_history' => $isOwner || $viewerHasBid,
            ],
            'display' => $this->buildDetailDisplay(
                $isEnded,
                (int) $summary['bid_count'],
                $this->determineFinalState($isEnded, (int) $summary['bid_count']),
                [
                    'is_connected' => $viewerId !== null,
                    'is_owner' => $isOwner,
                    'has_bid' => $viewerHasBid,
                    'is_best_bidder' => $viewerId !== null && $viewerId === $summary['best_bidder_id'],
                    'is_following' => $isFollowing,
                    'can_edit' => $canEdit === true,
                    'can_bid' => $canParticipate === true,
                ],
                $bidRejection,
                $this->formatEuros($currentAmountInEuros)
            ),
            'csrf_token' => $this->session->getCsrfToken(),
            'flash_success' => $this->session->getFlashMessage('success'),
            'flash_notice' => $this->session->getFlashMessage('notice'),
        ]);
    }

    /**
     * Rôle : Préparer le formulaire protégé de création d'une annonce.
     * Paramètres : Aucun.
     * Retour : Aucun, le formulaire ou une redirection vers la connexion est envoyé.
     */
    public function showCreateForm(): void
    {
        if ($this->requireConnectedUser('listing_create_form') === null) {
            return;
        }

        $categories = (new CategoryModel($this->database))->getAllCategories();
        $errors = [];

        if ($categories === null) {
            $categories = [];
            $errors['form'] = 'La création ou la modification de l’annonce est impossible pour le moment.';
        }

        $this->renderListingForm('create', $this->emptyListingFormValues(), $errors, $categories, []);
    }

    /**
     * Rôle : Valider les informations et photographies puis créer une annonce pour l'utilisateur connecté.
     * Paramètres : Aucun, les données sont lues dans la requête POST et les fichiers téléversés.
     * Retour : Aucun, le formulaire est réaffiché ou le détail créé est ouvert.
     */
    public function create(): void
    {
        $userId = $this->requireConnectedUser('listing_create_form');
        if ($userId === null) {
            return;
        }

        // Les valeurs et les fichiers sont contrôlés avant toute écriture en base ou sur le disque.
        $categories = (new CategoryModel($this->database))->getAllCategories();
        $values = $this->readListingFormValues();
        $errors = [];

        if (!$this->isSubmittedCsrfTokenValid()) {
            $errors['form'] = 'Le formulaire a expiré. Rechargez la page puis recommencez.';
        }

        if ($categories === null) {
            $categories = [];
            $errors['form'] = 'La création ou la modification de l’annonce est impossible pour le moment.';
        }

        // Les données validées sont transformées dans le format attendu par le modèle.
        $normalizedData = $this->validateListingValues($values, $categories, $errors, 'create');
        $uploadedPhotos = $this->validateUploadedPhotos($errors, 'create');

        if ($errors !== []) {
            $this->renderListingForm('create', $values, $errors, $categories, []);
            return;
        }

        $listingModel = new ListingModel($this->database);
        $listingId = $listingModel->createListing(
            $userId,
            $normalizedData['title'],
            $normalizedData['description'],
            $normalizedData['item_state'],
            $normalizedData['starting_price_in_euros'],
            $normalizedData['deadline'],
            $normalizedData['category_id']
        );
        $storedFiles = [];

        if ($listingId === null) {
            $errors['form'] = 'L’annonce ne peut pas être enregistrée pour le moment.';
            $this->renderListingForm('create', $values, $errors, $categories, []);
            return;
        }

        $photosStored = $this->storeUploadedPhotos($listingId, $uploadedPhotos, $storedFiles);

        if (!$photosStored) {
            $this->deleteStoredFiles($storedFiles);
            $errors['form'] = 'L’annonce et ses photographies n’ont pas pu être enregistrées.';
            $this->renderListingForm('create', $values, $errors, $categories, []);
            return;
        }

        $this->redirect('listing_detail', ['id' => $listingId]);
    }

    /**
     * Rôle : Vérifier les droits du vendeur puis afficher le formulaire prérempli d'une annonce modifiable.
     * Paramètres : Aucun, l'identifiant est lu dans la requête GET.
     * Retour : Aucun, le formulaire ou une redirection sûre est envoyé.
     */
    public function showEditForm(): void
    {
        $userId = $this->requireConnectedUser('dashboard');
        if ($userId === null) {
            return;
        }

        $listingId = $this->readPositiveGetIdentifier('id');
        $listingModel = new ListingModel($this->database);
        $listing = null;

        if ($listingId !== null) {
            $listing = $listingModel->getDetail($listingId);
        }

        if ($listing === false) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('dashboard');
        }

        if ($listing === null) {
            $this->session->setFlashMessage('notice', 'Cette annonce ne peut pas être modifiée.');
            $this->redirect('dashboard');
        }

        $currentTime = new DateTimeImmutable();
        $canModify = $listingModel->canBeModifiedBy($listingId, $userId, $currentTime);
        $lockedState = $listingModel->getLastManagementRestriction();

        if ($canModify === null) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('dashboard');
        }

        if ($lockedState === 'owner' || $lockedState === 'missing' || $lockedState === 'error') {
            $this->session->setFlashMessage('notice', 'Cette annonce ne peut pas être modifiée.');
            $this->redirect('dashboard');
        }

        $categories = (new CategoryModel($this->database))->getAllCategories();
        $errors = [];

        if ($categories === null) {
            if ($lockedState !== '') {
                $categories = [
                    (int) $listing['categorie_id'] => 'Catégorie indisponible',
                ];
            } else {
                $categories = [];
                $errors['form'] = 'La création ou la modification de l’annonce est impossible pour le moment.';
            }
        }

        $photoModel = new PhotoModel($this->database);
        $photos = $photoModel->getListingPhotos($listingId);

        if ($photos === false) {
            $photos = [];
            $errors['form'] = 'Les photographies de cette annonce sont momentanément indisponibles.';
        }

        $photos = $this->addPhotoUrls($photos);
        $values = $this->listingToFormValues($listing);
        $this->renderListingForm('edit', $values, $errors, $categories, $photos, $lockedState);
    }

    /**
     * Rôle : Revalider les droits, les données et les photographies puis modifier l'annonce.
     * Paramètres : Aucun, les données sont lues dans la requête POST.
     * Retour : Aucun, le formulaire est réaffiché ou le détail mis à jour est ouvert.
     */
    public function update(): void
    {
        $userId = $this->requireConnectedUser('dashboard');
        $listingId = $this->readPositivePostIdentifier('id');

        if ($userId === null) {
            return;
        }

        if ($listingId === null) {
            $this->session->setFlashMessage('notice', 'L’annonce à modifier est introuvable.');
            $this->redirect('dashboard');
        }

        $csrfIsValid = $this->isSubmittedCsrfTokenValid();
        $listingModel = $this->requireListingOwner($listingId, $userId, 'Modification verrouillée');
        $values = $this->readListingFormValues();
        $values['id'] = $listingId;
        $categories = (new CategoryModel($this->database))->getAllCategories();
        $errors = [];

        if (!$csrfIsValid) {
            $errors['form'] = 'Le formulaire a expiré. Rechargez la page puis recommencez.';
        }

        if ($categories === null) {
            $categories = [];
            $errors['form'] = 'La création ou la modification de l’annonce est impossible pour le moment.';
        }

        $normalizedData = $this->validateListingValues($values, $categories, $errors, 'edit');
        $uploadedPhotos = $this->validateUploadedPhotos($errors, 'edit');
        $photoModel = new PhotoModel($this->database);
        $existingPhotos = $photoModel->getListingPhotos($listingId);

        if ($existingPhotos === false) {
            $existingPhotos = [];
            $errors['form'] = 'Les photographies de cette annonce sont momentanément indisponibles.';
        }

        $removeIds = $this->readPhotoIdentifiersToRemove();
        $keptPhotos = [];
        $removedPhotos = [];

        foreach ($existingPhotos as $photo) {
            // NATIF PHP : in_array() recherche une valeur dans un tableau ; il vérifie ici que la donnée appartient à la liste autorisée.
            if (in_array($photo['id'], $removeIds, true)) {
                $removedPhotos[] = $photo;
            } else {
                $keptPhotos[] = $photo;
            }
        }

        // NATIF PHP : count() compte les éléments d’un tableau ; il permet ici de connaître la quantité avant le traitement.
        if (count($keptPhotos) + count($uploadedPhotos) > 3) {
            $errors['photos'] = 'Capacité atteinte. Supprimez une photo pour en ajouter une autre. La suivante devient principale si la première est supprimée.';
        }

        if ($errors !== []) {
            $this->renderListingForm(
                'edit',
                $values,
                $errors,
                $categories,
                $this->addPhotoUrls($existingPhotos)
            );
            return;
        }

        $currentTime = new DateTimeImmutable();
        $canModify = $listingModel->canBeModifiedBy($listingId, $userId, $currentTime);

        if ($canModify === null) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $restriction = $listingModel->getLastManagementRestriction();

        if (!$canModify) {
            $lockedMessage = 'Modification verrouillée';

            if ($restriction === 'ended') {
                $lockedMessage = 'L’échéance est atteinte. Cette annonce ne peut plus être modifiée ni supprimée.';
            } elseif ($restriction === 'bid') {
                $lockedMessage = 'Une enchère a été enregistrée. Cette annonce ne peut plus être modifiée ni supprimée.';
            }

            $this->session->setFlashMessage('notice', $lockedMessage);
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $updated = $listingModel->updateListing(
            $listingId,
            $normalizedData['title'],
            $normalizedData['description'],
            $normalizedData['item_state'],
            $normalizedData['starting_price_in_euros'],
            $normalizedData['deadline'],
            $normalizedData['category_id']
        );

        foreach ($removedPhotos as $photo) {
            if (!$photoModel->deleteFromListing((int) $photo['id'], $listingId)) {
                $updated = false;
            }
        }

        if ($updated && !$photoModel->reorder($listingId, $keptPhotos)) {
            $updated = false;
        }

        $storedFiles = [];

        if ($updated && !$this->storeUploadedPhotos(
            $listingId,
            $uploadedPhotos,
            $storedFiles,
            count($keptPhotos) + 1
        )) {
            $updated = false;
        }

        if (!$updated) {
            $this->deleteStoredFiles($storedFiles);
            $errors['form'] = 'Les modifications n’ont pas pu être enregistrées.';
            $this->renderListingForm('edit', $values, $errors, $categories, $this->addPhotoUrls($existingPhotos));
            return;
        }

        $this->deletePhotoFiles($removedPhotos);
        $this->redirect('listing_detail', ['id' => $listingId]);
    }

    /**
     * Rôle : Supprimer une annonce encore active, sans enchère et appartenant au vendeur connecté.
     * Paramètres : Aucun, l'identifiant et le jeton sont lus dans la requête POST.
     * Retour : Aucun, une redirection vers le tableau de bord ou le détail est envoyée.
     */
    public function delete(): void
    {
        $userId = $this->requireConnectedUser('dashboard');
        $listingId = $this->readPositivePostIdentifier('id');

        if ($userId === null) {
            return;
        }

        if ($listingId === null || !$this->isSubmittedCsrfTokenValid()) {
            $this->session->setFlashMessage('notice', 'La suppression ne peut pas être confirmée.');
            $this->redirect('dashboard');
        }

        $listingModel = $this->requireListingOwner(
            $listingId,
            $userId,
            'Cette annonce ne peut plus être supprimée.'
        );

        $currentTime = new DateTimeImmutable();
        $canDelete = $listingModel->canBeDeletedBy($listingId, $userId, $currentTime);

        if ($canDelete === null) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        if (!$canDelete) {
            $this->session->setFlashMessage(
                'notice',
                'Cette annonce ne peut plus être supprimée.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $photoModel = new PhotoModel($this->database);
        $photos = $photoModel->getListingPhotos($listingId);

        if ($photos === false) {
            $this->session->setFlashMessage(
                'notice',
                'Les photographies de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        if (!$listingModel->deleteListing($listingId)) {
            $this->session->setFlashMessage(
                'notice',
                'L’annonce n’a pas pu être supprimée.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $this->deletePhotoFiles($photos);
        $this->session->setFlashMessage('success', 'L’annonce a été supprimée.');
        $this->redirect('dashboard');
    }

    /**
     * Rôle : Refuser une gestion d'annonce avant les traitements coûteux si l'utilisateur n'en est pas propriétaire.
     * Paramètres : Identifiants de l'annonce et de l'utilisateur, puis message à afficher en cas de refus.
     * Retour : Modèle de l'annonce après confirmation de son propriétaire.
     */
    private function requireListingOwner(int $listingId, int $userId, string $deniedMessage): ListingModel
    {
        $listingModel = new ListingModel($this->database);
        $isOwner = $listingModel->isOwnedBy($listingId, $userId);

        if ($isOwner === null) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        if (!$isOwner) {
            $this->session->setFlashMessage('notice', $deniedMessage);
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        return $listingModel;
    }

    /**
     * Rôle : Rassembler les valeurs publiques du formulaire d'annonce.
     * Paramètres : Aucun.
     * Retour : Valeurs réaffichables indexées par champ.
     */
    private function readListingFormValues(): array
    {
        return [
            'title' => $this->readPostString('title'),
            'category' => $this->readPostString('category'),
            'description' => $this->readPostString('description'),
            'item_state' => $this->readPostString('item_state'),
            'starting_price' => $this->readPostString('starting_price'),
            'end_date' => $this->readPostString('end_date'),
            'end_time' => $this->readPostString('end_time'),
        ];
    }

    /**
     * Rôle : Fournir les valeurs vides nécessaires au formulaire initial.
     * Paramètres : Aucun.
     * Retour : Valeurs vides indexées par champ.
     */
    private function emptyListingFormValues(): array
    {
        return [
            'title' => '',
            'category' => '',
            'description' => '',
            'item_state' => '',
            'starting_price' => '',
            'end_date' => '',
            'end_time' => '',
        ];
    }

    /**
     * Rôle : Valider et normaliser toutes les données textuelles d'une annonce.
     * Paramètres : Valeurs reçues, catégories disponibles, erreurs à compléter et mode du formulaire.
     * Retour : Données normalisées destinées au modèle.
     */
    private function validateListingValues(array $values, array $categories, array &$errors, string $mode): array
    {
        // NATIF PHP : trim() retire les espaces placés au début et à la fin du texte ; il normalise ici une valeur reçue avant son contrôle.
        $title = trim($values['title']);
        $description = trim($values['description']);
        $categoryId = null;

        if ($title === '') {
            if ($mode === 'edit') {
                $errors['title'] = 'Le titre doit comporter au moins 3 caractères.';
            } else {
                $errors['title'] = 'Le titre est obligatoire.';
            }
        // NATIF PHP : mb_strlen() compte les caractères d’un texte UTF-8 ; il contrôle ici une longueur sans mal compter les caractères accentués.
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'Le titre doit comporter au moins 3 caractères.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'Le titre ne doit pas dépasser 255 caractères.';
        }

        if ($description === '') {
            $errors['description'] = 'La description est obligatoire.';
        } elseif (mb_strlen($description) < 10 || mb_strlen($description) > 5000) {
            $errors['description'] = 'La description doit contenir entre 10 et 5 000 caractères.';
        }

        if (
            // NATIF PHP : preg_match() vérifie un texte avec une expression régulière ; il contrôle ici que la valeur respecte le format attendu.
            preg_match('/^[1-9][0-9]*$/D', $values['category']) !== 1
            // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
            || !isset($categories[$values['category']])
        ) {
            $errors['category'] = 'Choisissez une catégorie proposée dans la liste.';
        } else {
            $categoryId = (int) $values['category'];
        }

        if (!in_array($values['item_state'], self::ITEM_STATES, true)) {
            $errors['item_state'] = 'Choisissez un état proposé dans la liste.';
        }

        $priceInEuros = $this->normalizePriceInEuros(
            $values['starting_price'],
            'starting_price',
            'Le prix de départ',
            $errors
        );

        if ($priceInEuros === null) {
            if ($mode === 'edit') {
                $errors['starting_price'] = 'Le prix de départ doit être strictement positif et saisi sans décimale.';
            } else {
                $errors['starting_price'] = 'Montant strictement positif, sans décimale.';
            }
        }

        $deadline = $this->normalizeDeadline($values['end_date'], $values['end_time'], $errors);

        return [
            'title' => $title,
            'description' => $description,
            'category_id' => $categoryId,
            'item_state' => $values['item_state'],
            'starting_price_in_euros' => $priceInEuros,
            'deadline' => $deadline,
        ];
    }

    /**
     * Rôle : Convertir une date et une heure françaises valides vers le format de la base de données.
     * Paramètres : Date, heure et erreurs à compléter.
     * Retour : Date formatée ou null lorsque la saisie est invalide.
     */
    private function normalizeDeadline(string $date, string $time, array &$errors): ?string
    {
        $deadline = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            !$deadline instanceof DateTimeImmutable
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $deadline->format('Y-m-d H:i') !== $date . ' ' . $time
        ) {
            $errors['end_date'] = 'La date doit être future.';
            $errors['end_time'] = 'L’heure doit être future.';
            return null;
        }

        if ($deadline <= new DateTimeImmutable()) {
            $errors['end_date'] = 'La date doit être future.';
            $errors['end_time'] = 'L’heure doit être future.';
            return null;
        }

        return $deadline->format('Y-m-d H:i:s');
    }

    /**
     * Rôle : Contrôler les fichiers reçus sans encore les déplacer dans le dossier public.
     * Paramètres : Erreurs à compléter et mode du formulaire.
     * Retour : Photographies validées avec leur fichier temporaire et leur extension sûre.
     */
    private function validateUploadedPhotos(array &$errors, string $mode): array
    {
        $validationMessage = 'Corrigez les champs signalés avant de publier l’annonce.';

        if ($mode === 'edit') {
            $validationMessage = 'Corrigez les champs signalés avant d’enregistrer les modifications.';
        }

        // NATIF PHP : $_FILES est un tableau superglobal décrivant les fichiers téléversés ; il fournit ici le nom, l’erreur et l’emplacement temporaire de chaque photographie.
        if (!isset($_FILES['photos'])) {
            return [];
        }

        $fileData = $_FILES['photos'];

        if (
            // NATIF PHP : is_array() vérifie qu’une valeur est un tableau ; il évite ici de parcourir ou transmettre un type inattendu.
            !is_array($fileData)
            || !isset($fileData['name'], $fileData['tmp_name'], $fileData['error'], $fileData['size'])
            || !is_array($fileData['name'])
            || !is_array($fileData['tmp_name'])
            || !is_array($fileData['error'])
            || !is_array($fileData['size'])
        ) {
            $errors['photos'] = $validationMessage;
            return [];
        }

        $photos = [];
        $allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        // NATIF PHP : finfo est la classe native qui analyse le contenu réel des fichiers ; elle contrôle ici le type MIME de la photographie.
        // NATIF PHP : FILEINFO_MIME_TYPE demande à Fileinfo de retourner le type MIME ; elle vérifie ici le contenu réel de la photographie.
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($fileData['error'] as $index => $uploadError) {
            // NATIF PHP : UPLOAD_ERR_NO_FILE signale qu’aucun fichier n’a été envoyé ; elle distingue ici une sélection vide des autres erreurs.
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (
                // NATIF PHP : UPLOAD_ERR_OK signale un téléversement réussi ; elle autorise ici la validation du fichier reçu.
                $uploadError !== UPLOAD_ERR_OK
                || !isset($fileData['tmp_name'][$index], $fileData['size'][$index])
                // NATIF PHP : is_string() vérifie qu’une valeur est une chaîne de caractères ; il évite ici de traiter un type inattendu comme du texte.
                || !is_string($fileData['tmp_name'][$index])
                // NATIF PHP : is_numeric() vérifie qu’une valeur représente un nombre ; il protège ici la conversion ou le calcul qui suit.
                || !is_numeric($fileData['size'][$index])
                // NATIF PHP : is_uploaded_file() confirme que le fichier provient réellement d’un téléversement HTTP ; il sécurise ici le traitement de la photographie.
                || !is_uploaded_file($fileData['tmp_name'][$index])
            ) {
                $errors['photos'] = $validationMessage;
                continue;
            }

            if ((int) $fileData['size'][$index] > 5 * 1024 * 1024) {
                $errors['photos'] = $validationMessage;
                continue;
            }

            $mimeType = $fileInfo->file($fileData['tmp_name'][$index]);

            if (
                !is_string($mimeType)
                || !isset($allowedMimeTypes[$mimeType])
                // NATIF PHP : getimagesize() lit les dimensions et le type d’une image ; il vérifie ici que le fichier reçu est une image exploitable.
                || getimagesize($fileData['tmp_name'][$index]) === false
            ) {
                $errors['photos'] = $validationMessage;
                continue;
            }

            $photos[] = [
                'temporary_path' => $fileData['tmp_name'][$index],
                'extension' => $allowedMimeTypes[$mimeType],
            ];
        }

        if (count($photos) > 3) {
            $errors['photos'] = 'Capacité atteinte. Supprimez une photo pour en ajouter une autre. La suivante devient principale si la première est supprimée.';
        }

        // NATIF PHP : array_slice() extrait une partie d’un tableau ; il limite ici les éléments conservés au nombre autorisé.
        return array_slice($photos, 0, 3);
    }

    /**
     * Rôle : Déplacer les photographies validées et enregistrer leurs références ordonnées.
     * Paramètres : Identifiant de l'annonce, photographies et noms de fichiers stockés à compléter.
     * Retour : true lorsque toutes les photographies sont enregistrées, sinon false.
     */
    private function storeUploadedPhotos(
        int $listingId,
        array $photos,
        array &$storedFiles,
        int $startingOrder = 1
    ): bool {
        if ($photos === []) {
            return true;
        }

        $photoModel = new PhotoModel($this->database);

        foreach ($photos as $index => $photo) {
            $filename = $this->photoStorage->storeUploadedFile(
                $listingId,
                $photo['temporary_path'],
                $photo['extension']
            );

            if ($filename === false) {
                return false;
            }

            $storedFiles[] = $filename;

            if (!$photoModel->addPhoto($listingId, $filename, $startingOrder + $index)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rôle : Supprimer les nouveaux fichiers déplacés lorsqu'une création échoue.
     * Paramètres : Liste des noms de fichiers créés pendant la demande.
     * Retour : Aucun.
     */
    private function deleteStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $filename) {
            if (is_string($filename)) {
                $this->photoStorage->deleteFile($filename);
            }
        }
    }

    /**
     * Rôle : Lire et normaliser les identifiants de photographies demandées en suppression.
     * Paramètres : Aucun.
     * Retour : Liste unique d'identifiants strictement positifs.
     */
    private function readPhotoIdentifiersToRemove(): array
    {
        // NATIF PHP : $_POST est un tableau superglobal contenant les champs envoyés en POST ; il récupère ici les données du formulaire à contrôler.
        if (!isset($_POST['remove_photos']) || !is_array($_POST['remove_photos'])) {
            return [];
        }

        $identifiers = [];

        foreach ($_POST['remove_photos'] as $value) {
            if (!is_string($value)) {
                continue;
            }

            // NATIF PHP : filter_var() valide ou filtre une valeur selon une règle native ; il refuse ici une donnée qui ne respecte pas le format attendu.
            // NATIF PHP : FILTER_VALIDATE_INT demande à filter_var() de valider un entier ; elle contrôle ici un identifiant ou un montant.
            $identifier = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($identifier !== false) {
                $identifiers[(int) $identifier] = (int) $identifier;
            }
        }

        // NATIF PHP : array_values() réindexe un tableau avec des clés numériques continues ; il prépare ici une liste propre pour la suite du traitement.
        return array_values($identifiers);
    }

    /**
     * Rôle : Transformer une annonce enregistrée en valeurs adaptées au formulaire de modification.
     * Paramètres : Annonce enregistrée.
     * Retour : Valeurs réaffichables du formulaire.
     */
    private function listingToFormValues(array $listing): array
    {
        $deadline = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) $listing['date_heure_fin']
        );
        $date = '';
        $time = '';

        if ($deadline instanceof DateTimeImmutable) {
            $date = $deadline->format('Y-m-d');
            $time = $deadline->format('H:i');
        }

        $category = (string) $listing['categorie_id'];
        $startingPrice = (int) $listing['prix_depart'];

        return [
            'id' => (int) $listing['id'],
            'title' => (string) $listing['titre'],
            'category' => $category,
            'description' => (string) $listing['description'],
            'item_state' => (string) $listing['etat_objet'],
            'starting_price' => $startingPrice,
            'end_date' => $date,
            'end_time' => $time,
        ];
    }

    /**
     * Rôle : Ajouter une URL publique sûre à chaque photographie préparée par le modèle.
     * Paramètres : Photographies ordonnées.
     * Retour : Photographies complétées avec leur URL.
     */
    private function addPhotoUrls(array $photos): array
    {
        foreach ($photos as &$photo) {
            $photo['url'] = $this->photoStorage->getPublicUrl($photo['filename']);
        }
        unset($photo);

        return $photos;
    }

    /**
     * Rôle : Récupérer l’état temporaire d’une enchère refusée pour la seule annonce concernée.
     * Paramètres : Identifiant de l’annonce affichée.
     * Retour : Montant saisi et minimum formatés, ou null si aucun refus ne correspond.
     */
    private function recoverBidRejection(int $listingId): ?array
    {
        $encodedData = $this->session->getFlashMessage('bid_rejection');

        if ($encodedData === null) {
            return null;
        }

        // NATIF PHP : json_decode() convertit un texte JSON en donnée PHP ; il permet ici d’exploiter la réponse reçue.
        $data = json_decode($encodedData, true);

        if (
            !is_array($data)
            || !isset($data['listing_id'], $data['minimum_in_euros'], $data['amount_in_euros'])
            || (int) $data['listing_id'] !== $listingId
            // NATIF PHP : is_int() vérifie qu’une valeur est un entier ; il évite ici d’utiliser un autre type dans un traitement numérique.
            || !is_int($data['minimum_in_euros'])
            || !is_int($data['amount_in_euros'])
        ) {
            return null;
        }

        return [
            'minimum' => $data['minimum_in_euros'],
            'minimum_label' => $this->formatEuros($data['minimum_in_euros']),
            'amount' => $data['amount_in_euros'],
            'amount_label' => $this->formatEuros($data['amount_in_euros']),
        ];
    }

    /**
     * Rôle : Supprimer du stockage les fichiers de photographies devenus inutiles après validation en base.
     * Paramètres : Photographies contenant des noms de fichiers sûrs.
     * Retour : Aucun.
     */
    private function deletePhotoFiles(array $photos): void
    {
        foreach ($photos as $photo) {
            if (!isset($photo['filename']) || !is_string($photo['filename'])) {
                continue;
            }

            $this->photoStorage->deleteFile($photo['filename']);
        }
    }

    /**
     * Rôle : Afficher le formulaire partagé de création ou de modification d'annonce.
     * Paramètres : Mode, valeurs, erreurs, catégories, photographies et verrouillage éventuel.
     * Retour : Aucun.
     */
    private function renderListingForm(
        string $mode,
        array $values,
        array $errors,
        array $categories,
        array $existingPhotos,
        string $lockedState = ''
    ): void {
        $this->render('pages/listing-form.php', [
            'mode' => $mode,
            'values' => $values,
            'errors' => $errors,
            'categories' => $categories,
            'existing_photos' => $existingPhotos,
            'locked_state' => $lockedState,
            'form_display' => $this->buildListingFormDisplay(
                $mode,
                $values,
                $errors,
                $categories,
                $existingPhotos,
                $lockedState
            ),
            'csrf_token' => $this->session->getCsrfToken(),
        ]);
    }

    /**
     * Rôle : Préparer les textes et états visuels du formulaire de création ou de modification d'une annonce.
     * Paramètres : Mode, valeurs réaffichables, erreurs, catégories disponibles et état de verrouillage.
     * Retour : Données de présentation prêtes à afficher sans décision métier dans le template.
     */
    private function buildListingFormDisplay(
        string $mode,
        array $values,
        array $errors,
        array $categories,
        array $existingPhotos,
        string $lockedState
    ): array {
        $isEditMode = $mode === 'edit';
        $isLocked = $lockedState === 'bid' || $lockedState === 'ended';
        $categoriesUnavailable = $categories === [];
        $globalErrorTitle = 'Vérifiez le formulaire';
        $globalErrorMessage = 'Corrigez les champs signalés avant de publier l’annonce.';
        $eyebrow = 'NOUVELLE VENTE';
        $lockedMessage = '';
        $lockedAttribute = '';
        $formTitle = 'Publier une annonce';
        $formRoute = 'listing_create';
        $submitLabel = 'Publier l’annonce';

        if ($isEditMode) {
            $globalErrorTitle = 'Vérifiez les modifications';
            $globalErrorMessage = 'Corrigez les champs signalés avant d’enregistrer les modifications.';
            $eyebrow = 'GESTION DE L’ANNONCE';
            $formTitle = (string) $values['title'];
            $formRoute = 'listing_update';
            $submitLabel = 'Enregistrer les modifications';
        }

        if ($lockedState === 'bid') {
            $lockedMessage = 'Une enchère a été enregistrée. Cette annonce ne peut plus être modifiée ni supprimée.';
            $lockedAttribute = 'disabled';
            $formTitle = 'Annonce verrouillée';
        } elseif ($lockedState === 'ended') {
            $lockedMessage = 'L’échéance est atteinte. Cette annonce ne peut plus être modifiée ni supprimée.';
            $lockedAttribute = 'disabled';
            $formTitle = 'Vente terminée';
        }

        if ($categoriesUnavailable) {
            $globalErrorTitle = 'Catégories indisponibles';
            $globalErrorMessage = 'La création ou la modification de l’annonce est impossible pour le moment.';
            $eyebrow = 'GESTION DE L’ANNONCE';
        }

        if (isset($errors['form']) && is_string($errors['form'])) {
            $globalErrorMessage = $errors['form'];
        }

        $alert = [
            'variant' => 'info',
            'title' => 'Préparez votre vente',
            'message' => 'Tous les champs marqués sont requis. Vous pouvez ajouter jusqu’à trois photographies.',
            'role' => 'note',
            'illustrated' => false,
        ];

        if ($isLocked) {
            $alert = [
                'variant' => 'warning',
                'title' => 'Modification verrouillée',
                'message' => $lockedMessage,
                'role' => 'status',
                'illustrated' => true,
            ];
        } elseif ($errors !== []) {
            $alert = [
                'variant' => 'error',
                'title' => $globalErrorTitle,
                'message' => $globalErrorMessage,
                'role' => 'alert',
                'illustrated' => true,
            ];
        } elseif ($isEditMode) {
            $alert = [
                'variant' => 'info',
                'title' => 'Modification autorisée',
                'message' => 'Aucune enchère enregistrée et échéance non atteinte.',
                'role' => 'note',
                'illustrated' => false,
            ];
        }

        $photoTitle = 'Aucune photographie ajoutée';
        $photoStatus = 'Vous pouvez publier sans photo ou en ajouter jusqu’à trois.';
        $actionInformation = 'Le prix de départ doit être strictement positif et saisi en euros entiers.';
        $buttonLabel = $submitLabel;
        $categoryPlaceholder = 'Choisir une catégorie';

        if ($isLocked || $isEditMode) {
            $photoTitle = 'Gestion des photographies';
        } elseif ($existingPhotos !== []) {
            $photoTitle = 'Photographies ajoutées';
        }

        if ($isLocked) {
            $photoStatus = 'Les informations restent consultables en lecture seule.';
        } elseif ($existingPhotos === []) {
            $photoStatus = 'Vous pouvez publier sans photo ou en ajouter jusqu’à trois.';
        } elseif (count($existingPhotos) >= 3) {
            $photoStatus = 'Capacité atteinte. Supprimez une photo pour en ajouter une autre. La suivante devient principale si la première est supprimée.';
        } elseif ($isEditMode) {
            $photoStatus = 'Ajoutez, remplacez ou supprimez les photographies dans la limite de trois.';
        } else {
            $photoStatus = 'La première photographie ajoutée est automatiquement l’image principale.';
        }

        if ($isEditMode && !$isLocked) {
            $actionInformation = 'Modification possible tant qu’aucune enchère n’est enregistrée et avant l’échéance.';
        }

        if ($categoriesUnavailable) {
            $buttonLabel = 'Publication indisponible';
            $actionInformation = 'Aucune catégorie locale de remplacement n’est proposée.';
            $categoryPlaceholder = 'Indisponible';
        }

        $bidCountLabel = $bidCount . ' enchère';
        $hasBidAttribute = 'false';
        $isBestBidderAttribute = 'false';
        $actionsClass = 'listing-summary__actions';
        $defaultParticipationLabel = $saleStatusLabel;

        if ($bidCount > 1) {
            $bidCountLabel .= 's';
        }

        if ($viewer['has_bid']) {
            $hasBidAttribute = 'true';
        }

        if ($viewer['is_best_bidder']) {
            $isBestBidderAttribute = 'true';
        }

        if ($isEnded) {
            $actionsClass .= ' listing-summary__actions--ended';
        }

        if ($viewer['is_connected'] && !$viewer['is_owner']) {
            $defaultParticipationLabel = 'Annonce non suivie';
        }

        return [
            'is_edit_mode' => $isEditMode,
            'is_locked' => $isLocked,
            'categories_unavailable' => $categoriesUnavailable,
            'eyebrow' => $eyebrow,
            'locked_attribute' => $lockedAttribute,
            'form_title' => $formTitle,
            'form_route' => $formRoute,
            'alert' => $alert,
            'photo_title' => $photoTitle,
            'photo_status' => $photoStatus,
            'show_photo_upload' => !$isLocked,
            'action_information' => $actionInformation,
            'button_label' => $buttonLabel,
            'category_placeholder' => $categoryPlaceholder,
        ];
    }

    /**
     * Rôle : Convertir l'historique brut en informations limitées et affichables en heure de Paris.
     * Paramètres : Lignes d'enchères à formater.
     * Retour : Historique formaté et sûr pour le template.
     */
    private function formatBidHistory(array $rows): array
    {
        $history = [];

        foreach ($rows as $row) {
            if (!isset($row['pseudo'], $row['montant'], $row['date_heure_enchere'])) {
                continue;
            }

            $date = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                (string) $row['date_heure_enchere']
            );

            if (!$date instanceof DateTimeImmutable) {
                continue;
            }

            $amountInEuros = (int) $row['montant'];



            $history[] = [
                'bidder' => (string) $row['pseudo'],
                'amount' => $this->formatEuros($amountInEuros),
                'date' => $this->formatFrenchDateTime(
                    $date,
                    true,
                    false
                ),
            ];
        }

        return $history;
    }

    /**
     * Rôle : Déterminer le libellé final public d'une vente terminée.
     * Paramètres : Indication de fin et nombre d'enchères.
     * Retour : Libellé final ou chaîne vide tant que la vente est active.
     */
    private function determineFinalState(bool $isEnded, int $bidCount): string
    {
        if (!$isEnded) {
            return '';
        }

        if ($bidCount > 0) {
            return 'Adjugée';
        }

        return 'Non adjugée';
    }

    /**
     * Rôle : Préparer les libellés et messages du détail d'une annonce selon son état et les droits du visiteur.
     * Paramètres : État de la vente, nombre d'enchères, résultat final, droits du visiteur, refus éventuel et prix affiché.
     * Retour : Données d'affichage prêtes à présenter dans le template de détail.
     */
    private function buildDetailDisplay(
        bool $isEnded,
        int $bidCount,
        string $finalState,
        array $viewer,
        ?array $bidRejection,
        string $currentPriceLabel
    ): array {
        $saleStatusLabel = 'Vente en cours';
        $finalResultTitle = '';
        $priceLabel = 'PRIX COURANT';
        $historyTitle = 'Historique des enchères';
        $historySubtitle = 'Les informations détaillées sont réservées aux utilisateurs autorisés.';
        $historyLockedTitle = 'Historique détaillé non accessible dans cette vue';
        $historyLockedBody = 'Les informations publiques restent disponibles : prix, nombre d’enchères et résultat final.';
        $summaryMessage = '';
        $endedAlertTitle = 'Vente terminée';
        $endedMessage = 'Aucune action de participation ou de modification n’est disponible.';
        $showEmptyHistory = false;
        $visitorInvitation = '';
        $ownerLockedMessage = 'Une enchère a été enregistrée : modification et suppression impossibles.';
        $bidMinimumHelp = 'Montant supérieur d’au moins 1 € au prix courant.';
        $bidStatusMessage = '';

        if ($isEnded) {
            $saleStatusLabel = 'Vente terminée';
            $finalResultTitle = 'Vente adjugée';
            $priceLabel = 'PRIX FINAL';

            if ($finalState === 'Non adjugée') {
                $finalResultTitle = 'Vente non adjugée';
            }

            if ($bidCount === 0) {
                $historySubtitle = 'La vente s’est terminée sans enchère.';
                $showEmptyHistory = true;
            }
        }

        $participationLabel = $saleStatusLabel;

        if ($isEnded && $bidCount === 0) {
            $participationLabel = 'Non adjugée';
        }

        if ($viewer['is_best_bidder']) {
            $participationLabel = 'Meilleure enchère';
        } elseif ($viewer['has_bid']) {
            $participationLabel = 'Enchère dépassée';
        } elseif ($viewer['is_following']) {
            $participationLabel = 'Annonce suivie';
        } elseif ($viewer['is_owner'] && !$isEnded) {
            $participationLabel = 'Votre vente active';
        } elseif ($viewer['is_connected'] && !$isEnded) {
            $participationLabel = 'Annonce non suivie';
        }

        $followRoute = 'follow_listing';
        $followLabel = 'Suivre';

        if ($viewer['is_following']) {
            $followRoute = 'unfollow_listing';
            $followLabel = 'Ne plus suivre';
        }

        if (!$isEnded) {
            if ($viewer['can_edit']) {
                $summaryMessage = 'Aucune enchère enregistrée : vos actions restent disponibles.';
                $historySubtitle = 'Aucune enchère n’a encore été enregistrée.';
                $showEmptyHistory = true;
            } elseif ($viewer['is_owner']) {
                $participationLabel = 'Actions verrouillées';
                $summaryMessage = 'Votre annonce reste visible jusqu’à l’échéance. Les actions d’édition sont définitivement bloquées.';
                $historyTitle = 'Historique détaillé des enchères';
                $historySubtitle = 'La première enchère verrouille modification et suppression.';
            } elseif ($viewer['is_best_bidder']) {
                $summaryMessage = 'Vous êtes actuellement le mieux-disant. Vous pouvez enchérir de nouveau si nécessaire.';
                $historyTitle = 'Historique détaillé des enchères';
                $historySubtitle = 'Pseudo, montant, date et heure — Europe/Paris.';
            } elseif ($viewer['has_bid']) {
                $summaryMessage = 'Votre meilleure offre n’est plus en tête. Le minimum actuel est indiqué dans le formulaire.';
                $historyTitle = 'Historique détaillé des enchères';
                $historySubtitle = 'Une offre supérieure a été enregistrée.';
            } elseif ($viewer['is_connected']) {
                $historySubtitle = 'Vous n’avez pas encore enchéri sur cette annonce.';
                $historyLockedTitle = 'Historique accessible après votre première enchère';

                if ($viewer['is_following']) {
                    $historyLockedBody = 'Vous pouvez continuer à suivre l’annonce et enchérir.';
                } else {
                    $historyLockedBody = 'Vous ne suivez pas encore cette annonce. Suivez-la pour la retrouver dans votre tableau de bord.';
                }
            }
        } elseif ($bidCount > 0) {
            $historyTitle = 'Historique final des enchères';

            if ($viewer['is_owner']) {
                $participationLabel = 'Vente adjugée';
                $finalResultTitle = 'Un gagnant a été désigné';
                $summaryMessage = 'La vente est terminée et adjugée. Aucune action transactionnelle n’est ajoutée ici.';
                $endedAlertTitle = 'Vente adjugée';
                $endedMessage = 'Le résultat final et l’historique restent consultables.';
                $historySubtitle = 'L’enchère gagnante est identifiée dans l’historique.';
            } elseif ($viewer['is_best_bidder']) {
                $participationLabel = 'Enchère remportée';
                $finalResultTitle = 'Vous remportez cette enchère';
                $summaryMessage = 'Votre offre de ' . $currentPriceLabel . ' est la meilleure. Le résultat et l’historique restent consultables.';
                $endedAlertTitle = 'Enchère remportée';
                $endedMessage = 'Votre offre est identifiée dans l’historique final.';
                $historySubtitle = 'Votre enchère gagnante est mise en évidence.';
            } elseif ($viewer['has_bid']) {
                $participationLabel = 'Enchère non remportée';
                $finalResultTitle = 'Votre enchère n’a pas gagné';
                $summaryMessage = 'Votre meilleure offre n’a pas remporté la vente. La vente est désormais terminée.';
                $endedMessage = 'Votre enchère n’a pas remporté cette vente.';
                $historySubtitle = 'Votre meilleure offre et l’enchère gagnante restent visibles.';
            } else {
                $participationLabel = 'Vente adjugée';
                $finalResultTitle = 'Vente terminée — adjugée';
                $endedAlertTitle = 'Vente adjugée';
                $endedMessage = 'Le prix final et le nombre d’enchères sont publics.';
                $historyTitle = 'Historique des enchères';
                $historySubtitle = 'Le détail de l’historique n’est pas accessible dans cette vue.';
            }
        }

        if ($bidRejection !== null && $viewer['can_bid']) {
            $participationLabel = 'Enchère refusée';
            $historySubtitle = 'L’offre de ' . $bidRejection['amount_label'] . ' n’a pas été enregistrée.';
            $historyLockedBody = 'Aucune enchère valide n’a été enregistrée. Corrigez le montant puis réessayez.';
            $bidMinimumHelp = 'Montant insuffisant : minimum ' . $bidRejection['minimum_label'] . '.';
            $bidStatusMessage = 'Enchère refusée : saisissez au minimum ' . $bidRejection['minimum_label'] . '.';
        }

        if (!$viewer['is_connected'] && !$isEnded) {
            $visitorInvitation = 'Connectez-vous pour suivre cette annonce ou enchérir.';
        }

        return [
            'sale_status_label' => $saleStatusLabel,
            'final_result_title' => $finalResultTitle,
            'price_label' => $priceLabel,
            'history_title' => $historyTitle,
            'history_subtitle' => $historySubtitle,
            'history_locked_title' => $historyLockedTitle,
            'history_locked_body' => $historyLockedBody,
            'summary_message' => $summaryMessage,
            'ended_alert_title' => $endedAlertTitle,
            'ended_message' => $endedMessage,
            'show_empty_history' => $showEmptyHistory,
            'bid_count_label' => $bidCountLabel,
            'participation_label' => $participationLabel,
            'follow_route' => $followRoute,
            'follow_label' => $followLabel,
            'has_bid_attribute' => $hasBidAttribute,
            'is_best_bidder_attribute' => $isBestBidderAttribute,
            'actions_class' => $actionsClass,
            'default_participation_label' => $defaultParticipationLabel,
            'visitor_invitation' => $visitorInvitation,
            'owner_locked_message' => $ownerLockedMessage,
            'bid_minimum_help' => $bidMinimumHelp,
            'bid_status_message' => $bidStatusMessage,
        ];
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
        DateTimeImmutable $currentTime
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
            $deadline = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                (string) $listing['date_heure_fin']
            );

            if (!$deadline instanceof DateTimeImmutable) {
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
