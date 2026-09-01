<?php

/**
 * Description générale : Contrôleur des annonces proposées aux enchères.
 * Rôle : Coordonner les demandes liées aux annonces et choisir leur réponse HTML ou JSON.
 * Tâches : Lire et valider les requêtes, interroger les modèles et préparer l'affichage.
 * Liens avec les autres fichiers : Étend Controller.php et utilise Clock.php ainsi que les modèles liés aux annonces.
 */

namespace App\controllers;

use App\core\Clock;
use App\core\Controller;
use App\core\Money;
use App\models\BidModel;
use App\models\CategoryModel;
use App\models\FollowModel;
use App\models\ListingModel;
use App\models\PhotoModel;
use DateTimeImmutable;
use DateTimeZone;

class ListingController extends Controller
{
    // ====================
    // CONSTANTES
    // ====================

    private const ITEMS_PER_PAGE = 12;
    private const ITEM_STATES = ['neuf', 'très bon état', 'bon état', 'état correct'];
    private const SALE_STATES = ['all', 'active', 'ended'];
    private const PHOTO_PUBLIC_DIRECTORY = 'public/uploads/annonces/';
    private const PHOTO_STORAGE_DIRECTORY = '/public/uploads/annonces';

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Afficher l'accueil ou renvoyer les résultats actualisés d'une recherche paginée.
     * Paramètres : Aucun, les critères sont lus dans la requête GET.
     * Retour : Aucun, une réponse HTML ou JSON est envoyée au navigateur.
     */
    public function search(): void
    {
        $currentTimeUtc = Clock::nowUtc();
        $categories = (new CategoryModel())->getAllCategories();
        $categoriesAvailable = $categories !== null;

        if ($categories === null) {
            $categories = [];
        }

        $validation = $this->validateCriteria($categories, $categoriesAvailable);
        $criteria = $validation['criteria'];
        $errors = $validation['errors'];
        $hasCustomCriteria = $validation['has_custom_criteria'];
        $searchResult = $this->emptySearchResult();
        $stateKey = 'invalid_criteria';
        $message = 'Corrigez les champs signalés, puis relancez la recherche. Vos autres critères sont conservés.';
        $success = $errors === [];

        if ($success) {
            $listingModel = new ListingModel($this->database);
            $searchResult = $listingModel->searchListings(
                $criteria,
                $criteria['page'],
                self::ITEMS_PER_PAGE,
                $currentTimeUtc
            );
            $success = $searchResult['success'];

            if ($success) {
                $enrichedListings = $this->enrichListings(
                    $searchResult['listings'],
                    $categories,
                    $currentTimeUtc
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
        $response = [
            'success' => $success,
            'message' => $message,
            'state_key' => $stateKey,
            'criteria' => $this->publicCriteria($criteria),
            'errors' => $errors,
            'categories_available' => $categoriesAvailable,
            'categories' => $categories,
            'listings' => $searchResult['listings'],
            'pagination' => $pagination,
            'total_items' => $searchResult['total_items'],
            'is_connected' => $this->session->estUtilisateurConnecte(),
            'csrf_token' => $this->session->obtenirJetonCsrf(),
            'flash_success' => $this->session->recupererMessageTemporaire('success'),
            'flash_notice' => $this->session->recupererMessageTemporaire('notice'),
        ];

        if ($this->isJsonRequest()) {
            if ($errors !== []) {
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
        $currentTimeUtc = Clock::nowUtc();
        $listingId = $this->readPositiveGetIdentifier('id');

        if ($listingId === null) {
            $this->session->enregistrerMessageTemporaire('notice', 'L’annonce demandée est introuvable.');
            $this->redirect('home');
        }

        $listingModel = new ListingModel($this->database);
        $listing = $listingModel->getDetail($listingId);

        if ($listing === false) {
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('home');
        }

        if ($listing === null) {
            $this->session->enregistrerMessageTemporaire('notice', 'L’annonce demandée est introuvable.');
            $this->redirect('home');
        }

        $categoryLabel = (new CategoryModel())->getCategoryLabel((int) $listing['categorie_id']);

        if ($categoryLabel === null) {
            $categoryLabel = 'Catégorie indisponible';
        }

        $bidModel = new BidModel($this->database);
        $photoModel = new PhotoModel($this->database);
        $followModel = new FollowModel($this->database);
        $summary = $bidModel->getSummary($listingId);

        if ($summary === false) {
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('home');
        }

        $viewerId = $this->session->obtenirIdentifiantUtilisateurConnecte();
        $isOwner = $viewerId !== null && $viewerId === (int) $listing['utilisateur_id'];
        $viewerHasBid = false;
        $isFollowing = false;

        if ($viewerId !== null && !$isOwner) {
            $viewerHasBid = $bidModel->userHasBid($listingId, $viewerId);
            $isFollowing = $followModel->isFollowing($viewerId, $listingId);

            if ($viewerHasBid === null || $isFollowing === null) {
                $this->session->enregistrerMessageTemporaire(
                    'notice',
                    'Les données de cette annonce sont momentanément indisponibles.'
                );
                $this->redirect('home');
            }
        }

        $utcTimezone = new DateTimeZone('UTC');
        $parisTimezone = new DateTimeZone('Europe/Paris');
        $deadline = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) $listing['date_heure_fin'],
            $utcTimezone
        );

        if (!$deadline instanceof DateTimeImmutable) {
            $this->session->enregistrerMessageTemporaire('notice', 'Cette annonce ne peut pas être affichée.');
            $this->redirect('home');
        }

        $isEnded = $deadline <= $currentTimeUtc;
        $currentAmountInEuros = Money::databaseValueToEuros((string) $listing['prix_depart']);

        if ($currentAmountInEuros === null) {
            $this->session->enregistrerMessageTemporaire('notice', 'Cette annonce ne peut pas être affichée.');
            $this->redirect('home');
        }

        if ($summary['best_bid_in_euros'] !== null) {
            $currentAmountInEuros = $summary['best_bid_in_euros'];
        }

        $history = [];

        if ($isOwner || $viewerHasBid) {
            $historyRows = $bidModel->getHistory($listingId);

            if ($historyRows === false) {
                $this->session->enregistrerMessageTemporaire(
                    'notice',
                    'Les données de cette annonce sont momentanément indisponibles.'
                );
                $this->redirect('home');
            }

            $history = $this->formatBidHistory($historyRows, $parisTimezone);
        }

        $photos = [];
        $photoRows = $photoModel->getListingPhotos($listingId);

        if ($photoRows === false) {
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('home');
        }

        foreach ($photoRows as $photo) {
            $photo['url'] = self::PHOTO_PUBLIC_DIRECTORY . rawurlencode($photo['filename']);
            $photos[] = $photo;
        }

        $bidRejection = $this->recoverBidRejection($listingId);
        $canEdit = false;
        $canParticipate = false;

        if ($viewerId !== null) {
            $canEdit = $listingModel->canBeModifiedBy($listingId, $viewerId, $currentTimeUtc);
            $canParticipate = $listingModel->canReceiveParticipationFrom(
                $listingId,
                $viewerId,
                $currentTimeUtc
            );

            if ($canEdit === null || $canParticipate === null) {
                $this->session->enregistrerMessageTemporaire(
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
                'current_price_label' => Money::formatEurosForDisplay($currentAmountInEuros),
                'minimum_bid' => Money::eurosToDatabaseValue($currentAmountInEuros + 1),
                'minimum_bid_label' => Money::formatEurosForDisplay($currentAmountInEuros + 1),
                'bid_count' => (int) $summary['bid_count'],
                'deadline_utc' => $deadline->format('Y-m-d\TH:i:s\Z'),
                'deadline_label' => $this->formatFrenchDateTime(
                    $deadline->setTimezone($parisTimezone),
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
            'csrf_token' => $this->session->obtenirJetonCsrf(),
            'flash_success' => $this->session->recupererMessageTemporaire('success'),
            'flash_notice' => $this->session->recupererMessageTemporaire('notice'),
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

        $categories = (new CategoryModel())->getAllCategories();
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

        $categories = (new CategoryModel())->getAllCategories();
        $values = $this->readListingFormValues();
        $errors = [];

        if (!$this->isSubmittedCsrfTokenValid()) {
            $errors['form'] = 'Le formulaire a expiré. Rechargez la page puis recommencez.';
        }

        if ($categories === null) {
            $categories = [];
            $errors['form'] = 'La création ou la modification de l’annonce est impossible pour le moment.';
        }

        $normalizedData = $this->validateListingValues($values, $categories, $errors, 'create');
        $uploadedPhotos = $this->validateUploadedPhotos($errors, 'create');

        if ($errors !== []) {
            $this->renderListingForm('create', $values, $errors, $categories, []);
            return;
        }

        if (!$this->database->beginTransaction()) {
            $errors['form'] = 'La publication ne peut pas démarrer pour le moment.';
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
            $normalizedData['deadline_utc'],
            $normalizedData['category_id']
        );
        $storedFiles = [];

        if ($listingId === null) {
            $this->database->rollback();
            $errors['form'] = 'L’annonce ne peut pas être enregistrée pour le moment.';
            $this->renderListingForm('create', $values, $errors, $categories, []);
            return;
        }

        $photosStored = $this->storeUploadedPhotos($listingId, $uploadedPhotos, $storedFiles);

        if (!$photosStored || !$this->database->commit()) {
            $this->database->rollback();
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
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('dashboard');
        }

        if ($listing === null) {
            $this->session->enregistrerMessageTemporaire('notice', 'Cette annonce ne peut pas être modifiée.');
            $this->redirect('dashboard');
        }

        $currentTimeUtc = Clock::nowUtc();
        $canModify = $listingModel->canBeModifiedBy($listingId, $userId, $currentTimeUtc);
        $lockedState = $listingModel->getLastManagementRestriction();

        if ($canModify === null) {
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('dashboard');
        }

        if ($lockedState === 'owner' || $lockedState === 'missing' || $lockedState === 'error') {
            $this->session->enregistrerMessageTemporaire('notice', 'Cette annonce ne peut pas être modifiée.');
            $this->redirect('dashboard');
        }

        $categories = (new CategoryModel())->getAllCategories();
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
     * Rôle : Revalider les droits, les données et les photographies puis modifier l'annonce en transaction.
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
            $this->session->enregistrerMessageTemporaire('notice', 'L’annonce à modifier est introuvable.');
            $this->redirect('dashboard');
        }

        $csrfIsValid = $this->isSubmittedCsrfTokenValid();
        $listingModel = $this->requireListingOwner($listingId, $userId, 'Modification verrouillée');
        $values = $this->readListingFormValues();
        $values['id'] = $listingId;
        $categories = (new CategoryModel())->getAllCategories();
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
            if (in_array($photo['id'], $removeIds, true)) {
                $removedPhotos[] = $photo;
            } else {
                $keptPhotos[] = $photo;
            }
        }

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

        if (!$this->database->beginTransaction()) {
            $errors['form'] = 'La modification ne peut pas démarrer pour le moment.';
            $this->renderListingForm('edit', $values, $errors, $categories, $this->addPhotoUrls($existingPhotos));
            return;
        }

        $currentTimeUtc = Clock::nowUtc();
        $canModify = $listingModel->canBeModifiedBy($listingId, $userId, $currentTimeUtc);

        if ($canModify === null) {
            $this->database->rollback();
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $restriction = $listingModel->getLastManagementRestriction();

        if (!$canModify) {
            $this->database->rollback();
            $lockedMessage = 'Modification verrouillée';

            if ($restriction === 'ended') {
                $lockedMessage = 'L’échéance est atteinte. Cette annonce ne peut plus être modifiée ni supprimée.';
            } elseif ($restriction === 'bid') {
                $lockedMessage = 'Une enchère a été enregistrée. Cette annonce ne peut plus être modifiée ni supprimée.';
            }

            $this->session->enregistrerMessageTemporaire('notice', $lockedMessage);
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $updated = $listingModel->updateListing(
            $listingId,
            $normalizedData['title'],
            $normalizedData['description'],
            $normalizedData['item_state'],
            $normalizedData['starting_price_in_euros'],
            $normalizedData['deadline_utc'],
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

        if (!$updated || !$this->database->commit()) {
            $this->database->rollback();
            $this->deleteStoredFiles($storedFiles);
            $errors['form'] = 'Les modifications n’ont pas pu être enregistrées.';
            $this->renderListingForm('edit', $values, $errors, $categories, $this->addPhotoUrls($existingPhotos));
            return;
        }

        $this->deletePhotoFiles($removedPhotos);
        $this->redirect('listing_detail', ['id' => $listingId]);
    }

    /**
     * Rôle : Supprimer en transaction une annonce encore active, sans enchère et appartenant au vendeur connecté.
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
            $this->session->enregistrerMessageTemporaire('notice', 'La suppression ne peut pas être confirmée.');
            $this->redirect('dashboard');
        }

        $listingModel = $this->requireListingOwner(
            $listingId,
            $userId,
            'Cette annonce ne peut plus être supprimée.'
        );

        if (!$this->database->beginTransaction()) {
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'La suppression est temporairement indisponible.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $currentTimeUtc = Clock::nowUtc();
        $canDelete = $listingModel->canBeDeletedBy($listingId, $userId, $currentTimeUtc);

        if ($canDelete === null) {
            $this->database->rollback();
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        if (!$canDelete) {
            $this->database->rollback();
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'Cette annonce ne peut plus être supprimée.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $photoModel = new PhotoModel($this->database);
        $photos = $photoModel->getListingPhotos($listingId);

        if ($photos === false) {
            $this->database->rollback();
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'Les photographies de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        if (!$listingModel->deleteListing($listingId) || !$this->database->commit()) {
            $this->database->rollback();
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'L’annonce n’a pas pu être supprimée.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $this->deletePhotoFiles($photos);
        $this->session->enregistrerMessageTemporaire('success', 'L’annonce a été supprimée.');
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
            $this->session->enregistrerMessageTemporaire(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        if (!$isOwner) {
            $this->session->enregistrerMessageTemporaire('notice', $deniedMessage);
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
            'title' => '', 'category' => '', 'description' => '', 'item_state' => '',
            'starting_price' => '', 'end_date' => '', 'end_time' => '',
        ];
    }

    /**
     * Rôle : Valider et normaliser toutes les données textuelles d'une annonce.
     * Paramètres : Valeurs reçues, catégories disponibles, erreurs à compléter et mode du formulaire.
     * Retour : Données normalisées destinées au modèle.
     */
    private function validateListingValues(array $values, array $categories, array &$errors, string $mode): array
    {
        $title = trim($values['title']);
        $description = trim($values['description']);
        $categoryId = null;

        if ($title === '') {
            if ($mode === 'edit') {
                $errors['title'] = 'Le titre doit comporter au moins 3 caractères.';
            } else {
                $errors['title'] = 'Le titre est obligatoire.';
            }
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

        if (preg_match('/^[1-9][0-9]*$/D', $values['category']) !== 1
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

        $deadlineUtc = $this->normalizeParisDeadline($values['end_date'], $values['end_time'], $errors);

        return [
            'title' => $title,
            'description' => $description,
            'category_id' => $categoryId,
            'item_state' => $values['item_state'],
            'starting_price_in_euros' => $priceInEuros,
            'deadline_utc' => $deadlineUtc,
        ];
    }

    /**
     * Rôle : Convertir une date et une heure de Paris valides vers une date UTC de base de données.
     * Paramètres : Date, heure et erreurs à compléter.
     * Retour : Date UTC ou null lorsque la saisie est invalide.
     */
    private function normalizeParisDeadline(string $date, string $time, array &$errors): ?string
    {
        $parisTimezone = new DateTimeZone('Europe/Paris');
        $utcTimezone = new DateTimeZone('UTC');
        $deadline = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $parisTimezone);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (!$deadline instanceof DateTimeImmutable
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $deadline->format('Y-m-d H:i') !== $date . ' ' . $time
        ) {
            $errors['end_date'] = 'La date doit être future.';
            $errors['end_time'] = 'L’heure doit être future.';
            return null;
        }

        if ($deadline->setTimezone($utcTimezone) <= Clock::nowUtc()) {
            $errors['end_date'] = 'La date doit être future.';
            $errors['end_time'] = 'L’heure doit être future.';
            return null;
        }

        return Clock::formatForDatabase($deadline);
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

        if (!isset($_FILES['photos'])) {
            return [];
        }

        $fileData = $_FILES['photos'];

        if (!is_array($fileData)
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
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($fileData['error'] as $index => $uploadError) {
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($uploadError !== UPLOAD_ERR_OK
                || !isset($fileData['tmp_name'][$index], $fileData['size'][$index])
                || !is_string($fileData['tmp_name'][$index])
                || !is_numeric($fileData['size'][$index])
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

            if (!is_string($mimeType)
                || !isset($allowedMimeTypes[$mimeType])
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

        return array_slice($photos, 0, 3);
    }

    /**
     * Rôle : Déplacer les photographies validées et enregistrer leurs références ordonnées.
     * Paramètres : Identifiant de l'annonce, photographies et chemins stockés à compléter.
     * Retour : true lorsque toutes les photographies sont enregistrées, sinon false.
     */
    private function storeUploadedPhotos(
        int $listingId,
        array $photos,
        array &$storedFiles,
        int $startingOrder = 1
    ): bool
    {
        $directory = dirname(__DIR__, 2) . self::PHOTO_STORAGE_DIRECTORY;

        if ($photos === []) {
            return true;
        }

        if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
            return false;
        }

        if (!is_writable($directory)) {
            return false;
        }

        $photoModel = new PhotoModel($this->database);

        foreach ($photos as $index => $photo) {
            $filename = 'annonce-' . $listingId . '-' . bin2hex(random_bytes(12)) . '.' . $photo['extension'];
            $path = $directory . '/' . $filename;

            if (!move_uploaded_file($photo['temporary_path'], $path)) {
                return false;
            }

            $storedFiles[] = $path;

            if (!$photoModel->addPhoto($listingId, $filename, $startingOrder + $index)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rôle : Supprimer les nouveaux fichiers déplacés lorsqu'une création échoue.
     * Paramètres : Liste de chemins absolus créés pendant la demande.
     * Retour : Aucun.
     */
    private function deleteStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $path) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
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
        if (!isset($_POST['remove_photos']) || !is_array($_POST['remove_photos'])) {
            return [];
        }

        $identifiers = [];

        foreach ($_POST['remove_photos'] as $value) {
            if (!is_string($value)) {
                continue;
            }

            $identifier = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($identifier !== false) {
                $identifiers[(int) $identifier] = (int) $identifier;
            }
        }

        return array_values($identifiers);
    }

    /**
     * Rôle : Transformer une annonce enregistrée en valeurs adaptées au formulaire de modification.
     * Paramètres : Annonce enregistrée.
     * Retour : Valeurs réaffichables du formulaire.
     */
    private function listingToFormValues(array $listing): array
    {
        $utcTimezone = new DateTimeZone('UTC');
        $parisTimezone = new DateTimeZone('Europe/Paris');
        $deadline = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) $listing['date_heure_fin'],
            $utcTimezone
        );
        $date = '';
        $time = '';

        if ($deadline instanceof DateTimeImmutable) {
            $parisDeadline = $deadline->setTimezone($parisTimezone);
            $date = $parisDeadline->format('Y-m-d');
            $time = $parisDeadline->format('H:i');
        }

        $category = (string) $listing['categorie_id'];
        $startingPriceInEuros = Money::databaseValueToEuros((string) $listing['prix_depart']);
        $startingPrice = '';

        if ($startingPriceInEuros !== null) {
            $startingPrice = Money::eurosToDatabaseValue($startingPriceInEuros);
        }

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
            $photo['url'] = self::PHOTO_PUBLIC_DIRECTORY . rawurlencode($photo['filename']);
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
        $encodedData = $this->session->recupererMessageTemporaire('bid_rejection');

        if ($encodedData === null) {
            return null;
        }

        $data = json_decode($encodedData, true);

        if (!is_array($data)
            || !isset($data['listing_id'], $data['minimum_in_euros'], $data['amount_in_euros'])
            || (int) $data['listing_id'] !== $listingId
            || !is_int($data['minimum_in_euros'])
            || !is_int($data['amount_in_euros'])
        ) {
            return null;
        }

        return [
            'minimum' => Money::eurosToDatabaseValue($data['minimum_in_euros']),
            'minimum_label' => Money::formatEurosForDisplay($data['minimum_in_euros']),
            'amount' => Money::eurosToDatabaseValue($data['amount_in_euros']),
            'amount_label' => Money::formatEurosForDisplay($data['amount_in_euros']),
        ];
    }

    /**
     * Rôle : Supprimer du stockage les fichiers de photographies devenus inutiles après validation en base.
     * Paramètres : Photographies contenant des noms de fichiers sûrs.
     * Retour : Aucun.
     */
    private function deletePhotoFiles(array $photos): void
    {
        $directory = dirname(__DIR__, 2) . self::PHOTO_STORAGE_DIRECTORY . '/';

        foreach ($photos as $photo) {
            if (!isset($photo['filename']) || !is_string($photo['filename'])) {
                continue;
            }

            $path = $directory . basename($photo['filename']);

            if (is_file($path)) {
                unlink($path);
            }
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
            'csrf_token' => $this->session->obtenirJetonCsrf(),
        ]);
    }

    /**
     * Rôle : Convertir l'historique brut en informations limitées et affichables en heure de Paris.
     * Paramètres : Lignes d'enchères et fuseau horaire d'affichage.
     * Retour : Historique formaté et sûr pour le template.
     */
    private function formatBidHistory(array $rows, DateTimeZone $parisTimezone): array
    {
        $history = [];
        $utcTimezone = new DateTimeZone('UTC');

        foreach ($rows as $row) {
            if (!isset($row['pseudo'], $row['montant'], $row['date_heure_enchere'])) {
                continue;
            }

            $date = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                (string) $row['date_heure_enchere'],
                $utcTimezone
            );

            if (!$date instanceof DateTimeImmutable) {
                continue;
            }

            $amountInEuros = Money::databaseValueToEuros((string) $row['montant']);

            if ($amountInEuros === null) {
                continue;
            }

            $history[] = [
                'bidder' => (string) $row['pseudo'],
                'amount' => Money::formatEurosForDisplay($amountInEuros),
                'date' => $this->formatFrenchDateTime(
                    $date->setTimezone($parisTimezone),
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
            $splitWords = preg_split('/\s+/u', $text);

            if (is_array($splitWords)) {
                $words = array_values(array_filter($splitWords, 'is_string'));
            }
        }

        $categoryId = null;

        if ($categoriesAvailable && $categoryInput !== '') {
            if (preg_match('/^[1-9][0-9]*$/D', $categoryInput) !== 1
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
            $minimumPriceInput = Money::eurosToDatabaseValue($minimumPriceInEuros);
        }

        if ($maximumPriceInEuros !== null) {
            $maximumPriceInput = Money::eurosToDatabaseValue($maximumPriceInEuros);
        }

        if ($minimumPriceInEuros !== null
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

        $priceInEuros = Money::userInputToEuros($value);

        if ($priceInEuros === null || $priceInEuros <= 0) {
            $errors[$field] = $label . ' doit être strictement positif et rester dans la limite autorisée.';
            return null;
        }

        return $priceInEuros;
    }

    /**
     * Rôle : Ajouter le prix courant, la photographie principale et les informations d'affichage aux annonces.
     * Paramètres : Annonces brutes, catégories fournies par l'API et instant UTC de référence.
     * Retour : Annonces prêtes à afficher ou false en cas d'erreur SQL complémentaire.
     */
    private function enrichListings(
        array $listings,
        array $categories,
        DateTimeImmutable $currentTimeUtc
    ): array|false
    {
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
        $utcTimezone = new DateTimeZone('UTC');
        $parisTimezone = new DateTimeZone('Europe/Paris');

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
                (string) $listing['date_heure_fin'],
                $utcTimezone
            );

            if (!$deadline instanceof DateTimeImmutable) {
                continue;
            }

            $photoUrl = null;

            if (isset($primaryPhotos[$identifier])) {
                $photoUrl = self::PHOTO_PUBLIC_DIRECTORY
                    . rawurlencode($primaryPhotos[$identifier]);
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

            if ($deadline > $currentTimeUtc) {
                $saleState = 'active';
            }

            $displayListings[] = [
                'id' => $identifier,
                'title' => (string) $listing['titre'],
                'category' => $categoryLabel,
                'item_state' => (string) $listing['etat_objet'],
                'current_price' => Money::eurosToDatabaseValue($currentAmountInEuros),
                'current_price_label' => Money::formatEurosForDisplay($currentAmountInEuros),
                'deadline_utc' => $deadline->format('Y-m-d\TH:i:s\Z'),
                'deadline_label' => $this->formatFrenchDateTime(
                    $deadline->setTimezone($parisTimezone),
                    false,
                    true
                ),
                'sale_state' => $saleState,
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

