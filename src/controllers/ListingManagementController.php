<?php

/**
 * Description générale : Contrôleur de gestion des annonces du vendeur.
 * Rôle : Permettre au vendeur de créer, modifier et supprimer ses annonces.
 * Tâches : Valider les formulaires, vérifier le propriétaire et coordonner les données avec le stockage des photographies.
 * Liens avec les autres fichiers : Étend Controller.php et utilise ListingModel.php, PhotoModel.php, CategoryModel.php et PhotoStorage.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\core\Database;
use App\core\Session;
use App\services\PhotoStorage;
use App\models\CategoryModel;
use App\models\ListingModel;
use App\models\PhotoModel;
// NATIF PHP : DateTime est la classe native de gestion des dates et des heures ; elle permet ici de contrôler et de formater les échéances.
use DateTime;

class ListingManagementController extends Controller
{
    // ====================
    // CONSTANTES
    // ====================

    private const ITEM_STATES = ['neuf', 'très bon état', 'bon état', 'état correct'];

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

        $currentTime = new DateTime();
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

        $currentTime = new DateTime();
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

        $currentTime = new DateTime();
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
        $deadline = DateTime::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);
        $dateErrors = DateTime::getLastErrors();

        if (
            !$deadline instanceof DateTime
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $deadline->format('Y-m-d H:i') !== $date . ' ' . $time
        ) {
            $errors['end_date'] = 'La date doit être future.';
            $errors['end_time'] = 'L’heure doit être future.';
            return null;
        }

        if ($deadline <= new DateTime()) {
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
        $deadline = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            (string) $listing['date_heure_fin']
        );
        $date = '';
        $time = '';

        if ($deadline instanceof DateTime) {
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

}
