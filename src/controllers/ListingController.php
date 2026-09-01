<?php

/**
 * Description générale : Contrôleur des annonces proposées aux enchères.
 * Rôle : Coordonner l'affichage initial, la recherche multicritère et la pagination de l'accueil.
 * Tâches : Valider les critères, interroger les modèles, consulter l'API de catégories et répondre en HTML ou JSON.
 * Liens avec les autres fichiers : Étend Controller.php, utilise ListingModel.php, BidModel.php, PhotoModel.php et affiche home.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\models\BidModel;
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

    private const CATEGORIES_API_URL = 'https://api.mywebecom.ovh/play/qdm/categ.php';
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
        $categories = $this->fetchCategories();
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
        $message = 'Certains critères doivent être corrigés.';
        $success = $errors === [];

        if ($success) {
            $listingModel = new ListingModel($this->database);
            $searchResult = $listingModel->searchListings(
                $criteria,
                $criteria['page'],
                self::ITEMS_PER_PAGE
            );
            $success = $searchResult['success'];

            if ($success) {
                $searchResult['listings'] = $this->enrichListings($searchResult['listings']);
                $criteria['page'] = $searchResult['current_page'];
                $stateKey = $this->determineStateKey(
                    $searchResult,
                    $hasCustomCriteria,
                    $categoriesAvailable
                );
                $message = $this->buildStateMessage($stateKey, $searchResult);
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
        $listingId = $this->readPositiveIdentifier('id');

        if ($listingId === null) {
            $this->session->enregistrerMessageTemporaire('notice', 'L’annonce demandée est introuvable.');
            $this->redirect('home');
        }

        $listingModel = new ListingModel($this->database);
        $listing = $listingModel->getDetail($listingId);

        if ($listing === null) {
            $this->session->enregistrerMessageTemporaire('notice', 'L’annonce demandée est introuvable.');
            $this->redirect('home');
        }

        $bidModel = new BidModel($this->database);
        $photoModel = new PhotoModel($this->database);
        $followModel = new FollowModel($this->database);
        $summary = $bidModel->getSummary($listingId);
        $viewerId = $this->session->obtenirIdentifiantUtilisateurConnecte();
        $isOwner = $viewerId !== null && $viewerId === (int) $listing['utilisateur_id'];
        $viewerHasBid = false;
        $isFollowing = false;

        if ($viewerId !== null && !$isOwner) {
            $viewerHasBid = $bidModel->userHasBid($listingId, $viewerId);
            $isFollowing = $followModel->isFollowing($viewerId, $listingId);
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

        $isEnded = $deadline <= new DateTimeImmutable('now', $utcTimezone);
        $currentPrice = (float) $listing['prix_depart'];

        if ($summary['best_bid'] !== null) {
            $currentPrice = (float) $summary['best_bid'];
        }

        $history = [];

        if ($isOwner || $viewerHasBid) {
            $history = $this->formatBidHistory($bidModel->getHistory($listingId), $parisTimezone);
        }

        $photos = [];

        foreach ($photoModel->getListingPhotos($listingId) as $photo) {
            $photo['url'] = self::PHOTO_PUBLIC_DIRECTORY . rawurlencode($photo['filename']);
            $photos[] = $photo;
        }

        $this->render('pages/listing-detail.php', [
            'listing' => [
                'id' => $listingId,
                'title' => (string) $listing['titre'],
                'description' => (string) $listing['description'],
                'item_state' => (string) $listing['etat_objet'],
                'category' => (string) $listing['categorie_libelle'],
                'seller' => (string) $listing['seller_pseudo'],
                'seller_id' => (int) $listing['utilisateur_id'],
                'current_price_label' => number_format($currentPrice, 2, ',', ' ') . ' €',
                'minimum_bid' => number_format($currentPrice + 0.01, 2, '.', ''),
                'bid_count' => (int) $summary['bid_count'],
                'deadline_utc' => $deadline->format('Y-m-d\TH:i:s\Z'),
                'deadline_label' => $deadline->setTimezone($parisTimezone)->format('d/m/Y à H:i'),
                'is_ended' => $isEnded,
                'final_state' => $this->determineFinalState($isEnded, (int) $summary['bid_count']),
            ],
            'photos' => $photos,
            'history' => $history,
            'viewer' => [
                'is_connected' => $viewerId !== null,
                'is_owner' => $isOwner,
                'has_bid' => $viewerHasBid,
                'is_best_bidder' => $viewerId !== null && $viewerId === $summary['best_bidder_id'],
                'is_following' => $isFollowing,
                'can_edit' => $isOwner && !$isEnded && (int) $summary['bid_count'] === 0,
                'can_follow' => $viewerId !== null && !$isOwner && !$isEnded,
                'can_bid' => $viewerId !== null && !$isOwner && !$isEnded,
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
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();

        if ($userId === null) {
            $this->redirect('login_form', ['destination' => 'listing_create_form']);
        }

        $categories = $this->fetchCategories();
        $errors = [];

        if ($categories === null) {
            $categories = [];
            $errors['form'] = 'Les catégories sont indisponibles. La publication est temporairement bloquée.';
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
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();

        if ($userId === null) {
            $this->redirect('login_form', ['destination' => 'listing_create_form']);
        }

        $categories = $this->fetchCategories();
        $values = $this->readListingFormValues();
        $errors = [];

        if (!$this->session->estJetonCsrfValide($this->readPostString('csrf_token'))) {
            $errors['form'] = 'Le formulaire a expiré. Rechargez la page puis recommencez.';
        }

        if ($categories === null) {
            $categories = [];
            $errors['form'] = 'Les catégories sont indisponibles. Aucune annonce ne peut être publiée.';
        }

        $normalizedData = $this->validateListingValues($values, $categories, $errors);
        $uploadedPhotos = $this->validateUploadedPhotos($errors);

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
        $created = $listingModel->create([
            'utilisateur_id' => $userId,
            'titre' => $normalizedData['title'],
            'description' => $normalizedData['description'],
            'etat_objet' => $normalizedData['item_state'],
            'prix_depart' => $normalizedData['starting_price'],
            'date_heure_fin' => $normalizedData['deadline_utc'],
            'categorie_id_externe' => $normalizedData['category_id'],
            'categorie_libelle' => $normalizedData['category_label'],
        ]);
        $listingId = $listingModel->getValue('id');
        $storedFiles = [];

        if (!$created || !is_int($listingId)) {
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

        $this->session->enregistrerMessageTemporaire('success', 'Votre annonce a été publiée.');
        $this->redirect('listing_detail', ['id' => $listingId]);
    }

    /**
     * Rôle : Vérifier les droits du vendeur puis afficher le formulaire prérempli d'une annonce modifiable.
     * Paramètres : Aucun, l'identifiant est lu dans la requête GET.
     * Retour : Aucun, le formulaire ou une redirection sûre est envoyé.
     */
    public function showEditForm(): void
    {
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();

        if ($userId === null) {
            $this->redirect('login_form', ['destination' => 'dashboard']);
        }

        $listingId = $this->readPositiveIdentifier('id');
        $listingModel = new ListingModel($this->database);
        $listing = null;

        if ($listingId !== null) {
            $listing = $listingModel->getDetail($listingId);
        }

        if ($listing === null || (int) $listing['utilisateur_id'] !== $userId) {
            $this->session->enregistrerMessageTemporaire('notice', 'Cette annonce ne peut pas être modifiée.');
            $this->redirect('dashboard');
        }

        $bidModel = new BidModel($this->database);

        if ($bidModel->listingHasBid($listingId)
            || (string) $listing['date_heure_fin'] <= gmdate('Y-m-d H:i:s')
        ) {
            $this->session->enregistrerMessageTemporaire('notice', 'La modification de cette annonce est verrouillée.');
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $categories = $this->fetchCategories();
        $errors = [];

        if ($categories === null) {
            $categories = [];
            $errors['form'] = 'Les catégories sont indisponibles. La modification est temporairement bloquée.';
        }

        $photoModel = new PhotoModel($this->database);
        $photos = $this->addPhotoUrls($photoModel->getListingPhotos($listingId));
        $values = $this->listingToFormValues($listing, $categories);
        $this->renderListingForm('edit', $values, $errors, $categories, $photos);
    }

    /**
     * Rôle : Revalider les droits, les données et les photographies puis modifier l'annonce en transaction.
     * Paramètres : Aucun, les données sont lues dans la requête POST.
     * Retour : Aucun, le formulaire est réaffiché ou le détail mis à jour est ouvert.
     */
    public function update(): void
    {
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();
        $listingId = $this->readPositivePostIdentifier('id');

        if ($userId === null) {
            $this->redirect('login_form', ['destination' => 'dashboard']);
        }

        if ($listingId === null) {
            $this->session->enregistrerMessageTemporaire('notice', 'L’annonce à modifier est introuvable.');
            $this->redirect('dashboard');
        }

        $values = $this->readListingFormValues();
        $values['id'] = $listingId;
        $categories = $this->fetchCategories();
        $errors = [];

        if (!$this->session->estJetonCsrfValide($this->readPostString('csrf_token'))) {
            $errors['form'] = 'Le formulaire a expiré. Rechargez la page puis recommencez.';
        }

        if ($categories === null) {
            $categories = [];
            $errors['form'] = 'Les catégories sont indisponibles. La modification est temporairement bloquée.';
        }

        $normalizedData = $this->validateListingValues($values, $categories, $errors);
        $uploadedPhotos = $this->validateUploadedPhotos($errors);
        $photoModel = new PhotoModel($this->database);
        $existingPhotos = $photoModel->getListingPhotos($listingId);
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
            $errors['photos'] = 'Trois photographies sont autorisées au maximum après modification.';
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

        $listingModel = new ListingModel($this->database);
        $lockedListing = $listingModel->getForUpdate($listingId);
        $bidModel = new BidModel($this->database);

        if ($lockedListing === null
            || (int) $lockedListing['utilisateur_id'] !== $userId
            || (string) $lockedListing['date_heure_fin'] <= gmdate('Y-m-d H:i:s')
            || $bidModel->listingHasBid($listingId)
        ) {
            $this->database->rollback();
            $this->session->enregistrerMessageTemporaire('notice', 'La modification est désormais verrouillée.');
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $updated = $listingModel->update($listingId, [
            'titre' => $normalizedData['title'],
            'description' => $normalizedData['description'],
            'etat_objet' => $normalizedData['item_state'],
            'prix_depart' => $normalizedData['starting_price'],
            'date_heure_fin' => $normalizedData['deadline_utc'],
            'categorie_id_externe' => $normalizedData['category_id'],
            'categorie_libelle' => $normalizedData['category_label'],
        ]);

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
        $this->session->enregistrerMessageTemporaire('success', 'L’annonce a été mise à jour.');
        $this->redirect('listing_detail', ['id' => $listingId]);
    }

    /**
     * Rôle : Supprimer en transaction une annonce encore active, sans enchère et appartenant au vendeur connecté.
     * Paramètres : Aucun, l'identifiant et le jeton sont lus dans la requête POST.
     * Retour : Aucun, une redirection vers le tableau de bord ou le détail est envoyée.
     */
    public function delete(): void
    {
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();
        $listingId = $this->readPositivePostIdentifier('id');

        if ($userId === null) {
            $this->redirect('login_form', ['destination' => 'dashboard']);
        }

        if ($listingId === null || !$this->session->estJetonCsrfValide($this->readPostString('csrf_token'))) {
            $this->session->enregistrerMessageTemporaire('notice', 'La suppression ne peut pas être confirmée.');
            $this->redirect('dashboard');
        }

        if (!$this->database->beginTransaction()) {
            $this->session->enregistrerMessageTemporaire('notice', 'La suppression est temporairement indisponible.');
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $listingModel = new ListingModel($this->database);
        $lockedListing = $listingModel->getForUpdate($listingId);
        $bidModel = new BidModel($this->database);

        if ($lockedListing === null
            || (int) $lockedListing['utilisateur_id'] !== $userId
            || (string) $lockedListing['date_heure_fin'] <= gmdate('Y-m-d H:i:s')
            || $bidModel->listingHasBid($listingId)
        ) {
            $this->database->rollback();
            $this->session->enregistrerMessageTemporaire('notice', 'Cette annonce ne peut plus être supprimée.');
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $photoModel = new PhotoModel($this->database);
        $photos = $photoModel->getListingPhotos($listingId);

        if (!$listingModel->delete($listingId) || !$this->database->commit()) {
            $this->database->rollback();
            $this->session->enregistrerMessageTemporaire('notice', 'L’annonce n’a pas pu être supprimée.');
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $this->deletePhotoFiles($photos);
        $this->session->enregistrerMessageTemporaire('success', 'L’annonce a été supprimée.');
        $this->redirect('dashboard');
    }

    /**
     * Rôle : Lire une valeur POST simple sans accepter de tableau inattendu.
     * Paramètres : Nom du champ demandé.
     * Retour : Valeur reçue ou chaîne vide lorsqu'elle est absente ou invalide.
     */
    private function readPostString(string $name): string
    {
        if (!isset($_POST[$name]) || !is_string($_POST[$name])) {
            return '';
        }

        return trim($_POST[$name]);
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
     * Paramètres : Valeurs reçues, catégories disponibles et erreurs à compléter.
     * Retour : Données normalisées destinées au modèle.
     */
    private function validateListingValues(array $values, array $categories, array &$errors): array
    {
        $title = trim($values['title']);
        $description = trim($values['description']);
        $categoryId = null;
        $categoryLabel = '';

        if (mb_strlen($title) < 3 || mb_strlen($title) > 255) {
            $errors['title'] = 'Le titre doit contenir entre 3 et 255 caractères.';
        }

        if (mb_strlen($description) < 10 || mb_strlen($description) > 5000) {
            $errors['description'] = 'La description doit contenir entre 10 et 5 000 caractères.';
        }

        if (preg_match('/^[1-9][0-9]*$/D', $values['category']) !== 1
            || !isset($categories[$values['category']])
        ) {
            $errors['category'] = 'Choisissez une catégorie proposée dans la liste.';
        } else {
            $categoryId = (int) $values['category'];
            $categoryLabel = $categories[$values['category']];
        }

        if (!in_array($values['item_state'], self::ITEM_STATES, true)) {
            $errors['item_state'] = 'Choisissez un état proposé dans la liste.';
        }

        $price = $this->normalizePrice($values['starting_price'], 'starting_price', 'Le prix de départ', $errors);
        $deadlineUtc = $this->normalizeParisDeadline($values['end_date'], $values['end_time'], $errors);

        return [
            'title' => $title,
            'description' => $description,
            'category_id' => $categoryId,
            'category_label' => $categoryLabel,
            'item_state' => $values['item_state'],
            'starting_price' => $price,
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
            $errors['deadline'] = 'Saisissez une date et une heure de fin valides.';
            return null;
        }

        if ($deadline <= new DateTimeImmutable('now', $parisTimezone)) {
            $errors['deadline'] = 'La date et l’heure de fin doivent être situées dans le futur.';
            return null;
        }

        return $deadline->setTimezone($utcTimezone)->format('Y-m-d H:i:s');
    }

    /**
     * Rôle : Contrôler les fichiers reçus sans encore les déplacer dans le dossier public.
     * Paramètres : Erreurs à compléter.
     * Retour : Photographies validées avec leur fichier temporaire et leur extension sûre.
     */
    private function validateUploadedPhotos(array &$errors): array
    {
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
            $errors['photos'] = 'Les photographies reçues ne sont pas utilisables.';
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
                $errors['photos'] = 'Une photographie n’a pas été reçue correctement.';
                continue;
            }

            if ((int) $fileData['size'][$index] > 5 * 1024 * 1024) {
                $errors['photos'] = 'Chaque photographie doit peser 5 Mo au maximum.';
                continue;
            }

            $mimeType = $fileInfo->file($fileData['tmp_name'][$index]);

            if (!is_string($mimeType)
                || !isset($allowedMimeTypes[$mimeType])
                || getimagesize($fileData['tmp_name'][$index]) === false
            ) {
                $errors['photos'] = 'Utilisez uniquement des images JPEG, PNG ou WebP valides.';
                continue;
            }

            $photos[] = [
                'temporary_path' => $fileData['tmp_name'][$index],
                'extension' => $allowedMimeTypes[$mimeType],
            ];
        }

        if (count($photos) > 3) {
            $errors['photos'] = 'Trois photographies sont autorisées au maximum.';
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

            if (!$photoModel->create([
                'annonce_id' => $listingId,
                'ref_fichier' => $filename,
                'ordre' => $startingOrder + $index,
            ])) {
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
     * Rôle : Lire un identifiant entier strictement positif dans la requête POST.
     * Paramètres : Nom du champ.
     * Retour : Identifiant validé ou null.
     */
    private function readPositivePostIdentifier(string $name): ?int
    {
        $value = $this->readPostString($name);
        $identifier = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($identifier === false) {
            return null;
        }

        return (int) $identifier;
    }

    /**
     * Rôle : Transformer une annonce enregistrée en valeurs adaptées au formulaire de modification.
     * Paramètres : Annonce et catégories actuellement disponibles.
     * Retour : Valeurs réaffichables du formulaire.
     */
    private function listingToFormValues(array $listing, array $categories): array
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

        $category = (string) $listing['categorie_id_externe'];

        if (!isset($categories[$category])) {
            foreach ($categories as $identifier => $label) {
                if ($label === $listing['categorie_libelle']) {
                    $category = (string) $identifier;
                    break;
                }
            }
        }

        return [
            'id' => (int) $listing['id'],
            'title' => (string) $listing['titre'],
            'category' => $category,
            'description' => (string) $listing['description'],
            'item_state' => (string) $listing['etat_objet'],
            'starting_price' => number_format((float) $listing['prix_depart'], 2, '.', ''),
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
     * Paramètres : Mode, valeurs, erreurs, catégories et photographies existantes.
     * Retour : Aucun.
     */
    private function renderListingForm(
        string $mode,
        array $values,
        array $errors,
        array $categories,
        array $existingPhotos
    ): void {
        $this->render('pages/listing-form.php', [
            'mode' => $mode,
            'values' => $values,
            'errors' => $errors,
            'categories' => $categories,
            'existing_photos' => $existingPhotos,
            'csrf_token' => $this->session->obtenirJetonCsrf(),
        ]);
    }

    /**
     * Rôle : Lire un identifiant entier strictement positif dans la requête GET.
     * Paramètres : Nom du paramètre.
     * Retour : Identifiant validé ou null lorsque la valeur est absente ou invalide.
     */
    private function readPositiveIdentifier(string $name): ?int
    {
        if (!isset($_GET[$name]) || !is_string($_GET[$name])) {
            return null;
        }

        $identifier = filter_var($_GET[$name], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($identifier === false) {
            return null;
        }

        return (int) $identifier;
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

            $history[] = [
                'bidder' => (string) $row['pseudo'],
                'amount' => number_format((float) $row['montant'], 2, ',', ' ') . ' €',
                'date' => $date->setTimezone($parisTimezone)->format('d/m/Y à H:i'),
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
        $categoryLabel = null;

        if ($categoriesAvailable && $categoryInput !== '') {
            if (preg_match('/^[1-9][0-9]*$/D', $categoryInput) !== 1
                || !isset($categories[$categoryInput])
            ) {
                $errors['category'] = 'Choisissez une catégorie proposée dans la liste.';
            } else {
                $categoryId = (int) $categoryInput;
                $categoryLabel = $categories[$categoryInput];
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

        $minimumPrice = $this->normalizePrice(
            $minimumPriceInput,
            'minimum_price',
            'Le prix minimum',
            $errors
        );
        $maximumPrice = $this->normalizePrice(
            $maximumPriceInput,
            'maximum_price',
            'Le prix maximum',
            $errors
        );

        if ($minimumPrice !== null) {
            $minimumPriceInput = str_replace(',', '.', $minimumPriceInput);
        }

        if ($maximumPrice !== null) {
            $maximumPriceInput = str_replace(',', '.', $maximumPriceInput);
        }

        if ($minimumPrice !== null && $maximumPrice !== null && $minimumPrice > $maximumPrice) {
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
                'category_label' => $categoryLabel,
                'item_state' => $itemState,
                'minimum_price' => $minimumPrice,
                'maximum_price' => $maximumPrice,
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
     * Rôle : Valider et convertir une limite de prix facultative.
     * Paramètres : Valeur reçue, nom du champ, libellé compréhensible et erreurs à compléter.
     * Retour : Prix normalisé ou null lorsque le champ est vide ou invalide.
     */
    private function normalizePrice(
        string $value,
        string $field,
        string $label,
        array &$errors
    ): ?float {
        if ($value === '') {
            return null;
        }

        $normalizedValue = str_replace(',', '.', $value);

        if (preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', $normalizedValue) !== 1) {
            $errors[$field] = $label . ' doit être un montant positif avec deux décimales au maximum.';
            return null;
        }

        $price = (float) $normalizedValue;

        if ($price <= 0 || $price > 99999999.99) {
            $errors[$field] = $label . ' doit être strictement positif et rester dans la limite autorisée.';
            return null;
        }

        return $price;
    }

    /**
     * Rôle : Charger et valider la liste des catégories fournie par l'API publique.
     * Paramètres : Aucun.
     * Retour : Catégories indexées par identifiant externe ou null si l'API est indisponible.
     */
    private function fetchCategories(): ?array
    {
        $curl = curl_init(self::CATEGORIES_API_URL);

        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($response) || $statusCode !== 200) {
            return null;
        }

        $decodedCategories = json_decode($response, true);

        if (!is_array($decodedCategories)) {
            return null;
        }

        $categories = [];

        foreach ($decodedCategories as $identifier => $label) {
            $identifier = (string) $identifier;

            if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1
                || !is_string($label)
                || trim($label) === ''
            ) {
                return null;
            }

            $categories[$identifier] = trim($label);
        }

        if ($categories === []) {
            return null;
        }

        return $categories;
    }

    /**
     * Rôle : Ajouter le prix courant, la photographie principale et les informations d'affichage aux annonces.
     * Paramètres : Annonces brutes retournées par ListingModel.
     * Retour : Annonces limitées aux informations nécessaires à la carte d'accueil.
     */
    private function enrichListings(array $listings): array
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
        $currentPrices = $bidModel->getCurrentPrices($listingIds, $startingPrices);
        $primaryPhotos = $photoModel->getPrimaryPhotos($listingIds);
        $displayListings = [];
        $utcTimezone = new DateTimeZone('UTC');
        $parisTimezone = new DateTimeZone('Europe/Paris');
        $now = new DateTimeImmutable('now', $utcTimezone);

        foreach ($listings as $listing) {
            if (!isset(
                $listing['id'],
                $listing['titre'],
                $listing['categorie_libelle'],
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

            $currentPrice = $currentPrices[$identifier] ?? (float) $listing['prix_depart'];
            $displayListings[] = [
                'id' => $identifier,
                'title' => (string) $listing['titre'],
                'category' => (string) $listing['categorie_libelle'],
                'item_state' => (string) $listing['etat_objet'],
                'current_price' => round((float) $currentPrice, 2),
                'current_price_label' => number_format((float) $currentPrice, 2, ',', ' ') . ' €',
                'deadline_utc' => $deadline->format('Y-m-d\TH:i:s\Z'),
                'deadline_label' => $deadline->setTimezone($parisTimezone)->format('d/m/Y à H:i'),
                'sale_state' => $deadline > $now ? 'active' : 'ended',
                'photo_url' => $photoUrl,
                'detail_url' => 'index.php?' . http_build_query([
                    'route' => 'listing_detail',
                    'id' => $identifier,
                ]),
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
            return 'Les catégories sont temporairement indisponibles. Les autres critères restent utilisables.';
        }

        if ($stateKey === 'no_results') {
            return 'Aucune annonce ne correspond à votre recherche.';
        }

        if ($stateKey === 'pagination') {
            return 'Page ' . $searchResult['current_page'] . ' sur ' . $searchResult['total_pages'] . '.';
        }

        if ($stateKey === 'filtered_results') {
            return $searchResult['total_items'] . ' annonce(s) correspondent à votre recherche.';
        }

        return 'Découvrez les ventes actives dont l’échéance est la plus proche.';
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
        $parameters = ['route' => 'home'];

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

        return 'index.php?' . http_build_query($parameters);
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
     * Rôle : Indiquer si le navigateur demande le contrat JSON de la recherche.
     * Paramètres : Aucun.
     * Retour : true pour une demande JSON explicite, sinon false.
     */
    private function isJsonRequest(): bool
    {
        return isset($_GET['format'])
            && is_string($_GET['format'])
            && $_GET['format'] === 'json';
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

