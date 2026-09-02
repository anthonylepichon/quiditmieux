<?php

/**
 * Description générale : Contrôleur de l'espace personnel de l'utilisateur connecté.
 * Rôle : Afficher le tableau de bord et coordonner la modification sécurisée du compte.
 * Tâches : Protéger les routes privées, préparer les cartes, appeler les modèles et limiter les réponses JSON.
 * Liens avec les autres fichiers : Étend Controller.php et utilise Clock.php, PhotoStorage.php ainsi que les modèles de l'espace personnel.
 */

namespace App\controllers;

use App\core\Clock;
use App\core\Controller;
use App\core\Database;
use App\core\Money;
use App\core\PhotoStorage;
use App\core\Session;
use App\models\CategoryModel;
use App\models\ListingModel;
use App\models\PhotoModel;
use App\models\UserModel;
use DateTimeImmutable;
use DateTimeZone;

class UserController extends Controller
{
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
        parent::__construct($database, $session);
        $this->photoStorage = new PhotoStorage();
    }

    /**
     * Rôle : Afficher le formulaire privé avec les informations actuelles du compte.
     * Paramètres : Aucun.
     * Retour : Aucun, le formulaire est affiché ou une redirection est envoyée.
     */
    public function showAccountForm(): void
    {
        $userId = $this->requireConnectedUser('account_form');
        if ($userId === null) {
            return;
        }
        $account = (new UserModel($this->database))->getAccount($userId);

        if ($account === false) {
            $this->renderAccountForm(
                ['pseudo' => '', 'email' => ''],
                ['form' => 'Les informations du compte sont momentanément indisponibles.'],
                null
            );
            return;
        }

        if ($account === null) {
            $this->session->deconnecterUtilisateur();
            $this->redirect('home');
            return;
        }
        $this->renderAccountForm(
            ['pseudo' => (string) $account['pseudo'], 'email' => (string) $account['email']],
            [],
            $this->session->recupererMessageTemporaire('success')
        );
    }

    /**
     * Rôle : Valider puis modifier l'identité et éventuellement le mot de passe du compte connecté.
     * Paramètres : Aucun, les informations sont lues dans la requête POST.
     * Retour : Aucun, le formulaire est réaffiché ou une redirection est envoyée.
     */
    public function updateAccount(): void
    {
        $userId = $this->requireConnectedUser('account_form');
        if ($userId === null) {
            return;
        }

        $values = [
            'pseudo' => trim($this->readPostString('pseudo')),
            'email' => mb_strtolower(trim($this->readPostString('email'))),
        ];
        $currentPassword = $this->readPostString('current_password');
        $newPassword = $this->readPostString('new_password');
        $confirmation = $this->readPostString('new_password_confirmation');
        $errors = $this->validateAccountValues($values, $currentPassword, $newPassword, $confirmation);
        $model = new UserModel($this->database);
        $account = $model->getAccount($userId);

        if ($account === false) {
            $this->renderAccountForm(
                $values,
                ['form' => 'Les informations du compte sont momentanément indisponibles.'],
                null
            );
            return;
        }

        if ($account === null) {
            $this->session->deconnecterUtilisateur();
            $this->redirect('home');
            return;
        }

        if ($currentPassword !== '') {
            $passwordIsValid = $model->verifyPassword($userId, $currentPassword);

            if ($passwordIsValid === null) {
                $errors['form'] = 'Les informations du compte sont momentanément indisponibles.';
            } elseif (!$passwordIsValid) {
                $errors['current_password'] = 'Le mot de passe actuel est incorrect.';
            }
        }

        if ($errors === []) {
            $pseudoExists = $model->pseudoExists($values['pseudo'], $userId);
            $emailExists = $model->emailExists($values['email'], $userId);

            if ($pseudoExists === null || $emailExists === null) {
                $errors['form'] = 'Les informations du compte sont momentanément indisponibles.';
            }

            if ($pseudoExists === true) {
                $errors['pseudo'] = 'Ce pseudo est déjà utilisé.';
            }

            if ($emailExists === true) {
                $errors['email'] = 'Cette adresse électronique est déjà utilisée.';
            }
        }

        if ($errors !== []) {
            $this->renderAccountForm($values, $errors, null);
            return;
        }

        $passwordToUpdate = null;

        if ($newPassword !== '') {
            $passwordToUpdate = $newPassword;
        }

        $accountUpdated = $model->updateAccount(
            $userId,
            $values['pseudo'],
            $values['email'],
            $passwordToUpdate
        );

        if ($accountUpdated === null) {
            $this->renderAccountForm(
                $values,
                ['form' => 'Le nouveau mot de passe ne peut pas être sécurisé pour le moment.'],
                null
            );
            return;
        }

        if (!$accountUpdated) {
            $this->renderAccountForm(
                $values,
                ['form' => 'Les modifications ne peuvent pas être enregistrées pour le moment.'],
                null
            );
            return;
        }

        $this->session->connecterUtilisateur($userId);
        $this->session->enregistrerMessageTemporaire('success', 'Les champs de mot de passe ont été vidés après l’enregistrement.');
        $this->redirect('account_form');
    }

    /**
     * Rôle : Afficher les ventes, participations et enchères remportées de l'utilisateur connecté.
     * Paramètres : Aucun.
     * Retour : Aucun, le template du tableau de bord est affiché ou une redirection est envoyée.
     */
    public function showDashboard(): void
    {
        $userId = $this->requireConnectedUser('dashboard');
        if ($userId === null) {
            return;
        }

        $dashboard = $this->buildDashboard($userId);
        $dashboard['csrf_token'] = $this->session->obtenirJetonCsrf();
        $dashboard['flash_success'] = $this->session->recupererMessageTemporaire('success');
        $dashboard['flash_notice'] = $this->session->recupererMessageTemporaire('notice');

        if ($dashboard['load_error']) {
            $dashboard['flash_notice'] = 'Le tableau de bord ne peut pas être actualisé pour le moment.';
        }

        unset($dashboard['load_error']);
        $this->render('pages/dashboard.php', $dashboard);
    }

    /**
     * Rôle : Fournir l'état actualisé des ventes du vendeur connecté.
     * Paramètres : Aucun.
     * Retour : Aucun, une réponse JSON limitée aux cartes de vente est envoyée.
     */
    public function refreshSales(): void
    {
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();
        if ($userId === null) {
            $this->respondDashboardUnavailable();
            return;
        }

        $currentTimeUtc = Clock::nowUtc();
        $model = new ListingModel($this->database);
        $rows = $model->getDashboardSales($userId, $currentTimeUtc);

        if ($rows === false) {
            $this->respondDashboardUnavailable();
            return;
        }

        $categories = $this->getDashboardCategories();
        $sales = $this->formatListings($rows, $categories, $currentTimeUtc);

        if ($sales === false) {
            $this->respondDashboardUnavailable();
            return;
        }

        $this->json(['success' => true, 'sales' => $sales]);
    }

    /**
     * Rôle : Fournir l'état actualisé des participations et gains de l'utilisateur connecté.
     * Paramètres : Aucun.
     * Retour : Aucun, une réponse JSON structurée est envoyée.
     */
    public function refreshParticipations(): void
    {
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();
        if ($userId === null) {
            $this->respondDashboardUnavailable();
            return;
        }

        $currentTimeUtc = Clock::nowUtc();
        $model = new ListingModel($this->database);
        $rows = $model->getDashboardParticipations($userId, $currentTimeUtc);

        if ($rows === false) {
            $this->respondDashboardUnavailable();
            return;
        }

        $categories = $this->getDashboardCategories();
        $listings = $this->formatListings($rows, $categories, $currentTimeUtc);

        if ($listings === false) {
            $this->respondDashboardUnavailable();
            return;
        }

        $data = $this->partitionParticipations(
            $listings,
            $userId
        );
        $this->json(['success' => true, 'participations' => $data['participations'], 'wins' => $data['wins']]);
    }

    /**
     * Rôle : Construire toutes les zones du tableau de bord.
     * Paramètres : Identifiant de l'utilisateur connecté.
     * Retour : Données prêtes à afficher.
     */
    private function buildDashboard(int $userId): array
    {
        $currentTimeUtc = Clock::nowUtc();
        $model = new ListingModel($this->database);
        $participationRows = $model->getDashboardParticipations($userId, $currentTimeUtc);
        $salesRows = $model->getDashboardSales($userId, $currentTimeUtc);

        if ($participationRows === false || $salesRows === false) {
            return $this->failedDashboard();
        }

        $categories = $this->getDashboardCategories();
        $participationListings = $this->formatListings(
            $participationRows,
            $categories,
            $currentTimeUtc
        );
        $sales = $this->formatListings($salesRows, $categories, $currentTimeUtc);

        if ($participationListings === false || $sales === false) {
            return $this->failedDashboard();
        }

        $data = $this->partitionParticipations($participationListings, $userId);

        return [
            'sales' => $sales,
            'participations' => $data['participations'],
            'wins' => $data['wins'],
            'load_error' => false,
        ];
    }

    /**
     * Rôle : Charger les catégories du tableau de bord avec un repli simple en cas d'indisponibilité.
     * Paramètres : Aucun.
     * Retour : Catégories indexées ou tableau vide.
     */
    private function getDashboardCategories(): array
    {
        $categories = (new CategoryModel())->getAllCategories();

        if ($categories === null) {
            return [];
        }

        return $categories;
    }

    /**
     * Rôle : Construire l'état vide commun utilisé lorsque le tableau de bord ne peut pas être chargé.
     * Paramètres : Aucun.
     * Retour : Zones vides accompagnées de l'indicateur d'erreur.
     */
    private function failedDashboard(): array
    {
        return [
            'sales' => [],
            'participations' => [],
            'wins' => [],
            'load_error' => true,
        ];
    }

    /**
     * Rôle : Envoyer la réponse JSON commune lorsqu'une actualisation du tableau de bord échoue.
     * Paramètres : Aucun.
     * Retour : Aucun, la réponse JSON est envoyée.
     */
    private function respondDashboardUnavailable(): void
    {
        $this->json([
            'success' => false,
            'message' => 'Les dernières informations reçues restent affichées.',
        ]);
    }

    /**
     * Rôle : Enrichir les lignes de la base avec leurs photos et libellés d'affichage.
     * Paramètres : Lignes du tableau de bord, catégories de l'API et instant UTC de référence.
     * Retour : Cartes limitées aux données utiles ou false en cas d'erreur SQL.
     */
    private function formatListings(
        array $rows,
        array $categories,
        DateTimeImmutable $currentTimeUtc
    ): array|false
    {
        $ids = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }

        $photos = (new PhotoModel($this->database))->getPrimaryPhotos($ids);

        if ($photos === false) {
            return false;
        }

        $utc = new DateTimeZone('UTC');
        $paris = new DateTimeZone('Europe/Paris');
        $listings = [];

        foreach ($rows as $row) {
            if (!isset(
                $row['id'],
                $row['titre'],
                $row['prix_depart'],
                $row['date_heure_fin'],
                $row['categorie_id']
            )) {
                continue;
            }
            $deadline = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row['date_heure_fin'], $utc);
            if (!$deadline instanceof DateTimeImmutable) {
                continue;
            }

            $id = (int) $row['id'];
            $currentAmountInEuros = Money::databaseValueToEuros((string) $row['prix_depart']);

            if ($currentAmountInEuros === null) {
                return false;
            }

            if (isset($row['best_bid'])) {
                $bestBidInEuros = Money::databaseValueToEuros((string) $row['best_bid']);

                if ($bestBidInEuros === null) {
                    return false;
                }

                $currentAmountInEuros = $bestBidInEuros;
            }

            $userBestBid = null;

            if (isset($row['user_best_bid'])) {
                $userBestBidInEuros = Money::databaseValueToEuros((string) $row['user_best_bid']);

                if ($userBestBidInEuros === null) {
                    return false;
                }

                $userBestBid = Money::formatEurosForDisplay($userBestBidInEuros);
            }

            $categoryId = (int) $row['categorie_id'];
            $categoryLabel = 'Catégorie indisponible';

            if (isset($categories[$categoryId])) {
                $categoryLabel = (string) $categories[$categoryId];
            }

            $listings[] = [
                'id' => $id,
                'title' => (string) $row['titre'],
                'category' => $categoryLabel,
                'current_price' => Money::formatEurosForDisplay($currentAmountInEuros),
                'bid_count' => $this->readBidCount($row),
                'user_best_bid' => $userBestBid,
                'winner_id' => $this->readWinnerId($row),
                'is_active' => $deadline > $currentTimeUtc,
                'deadline' => $this->formatFrenchDateTime(
                    $deadline->setTimezone($paris),
                    false,
                    false
                ),
                'deadline_date' => $this->formatFrenchDate(
                    $deadline->setTimezone($paris),
                    false
                ),
                'photo_url' => $this->buildPhotoUrl($photos, $id),
                'detail_url' => $this->buildRouteUrl('listing_detail', ['id' => $id]),
            ];
        }
        return $listings;
    }

    /**
     * Rôle : Lire l'identifiant du gagnant sans accepter une valeur invalide.
     * Paramètres : Ligne d'annonce issue de la base.
     * Retour : Identifiant du gagnant ou null.
     */
    private function readWinnerId(array $row): ?int
    {
        if (isset($row['winner_id']) && is_numeric($row['winner_id'])) {
            return (int) $row['winner_id'];
        }
        return null;
    }

    /**
     * Rôle : Lire le nombre d'enchères d'une ligne du tableau de bord.
     * Paramètres : Ligne d'annonce issue de la base.
     * Retour : Nombre d'enchères, égal à zéro lorsqu'il est absent.
     */
    private function readBidCount(array $row): int
    {
        if (isset($row['bid_count'])) {
            return (int) $row['bid_count'];
        }
        return 0;
    }

    /**
     * Rôle : Construire l'adresse de la photographie principale.
     * Paramètres : Photographies indexées et identifiant d'annonce.
     * Retour : Adresse publique de l'image ou null lorsqu'elle est absente.
     */
    private function buildPhotoUrl(array $photos, int $listingId): ?string
    {
        if (isset($photos[$listingId])) {
            return $this->photoStorage->getPublicUrl($photos[$listingId]);
        }
        return null;
    }

    /**
     * Rôle : Séparer les participations ordinaires des enchères remportées.
     * Paramètres : Cartes préparées et identifiant de l'utilisateur connecté.
     * Retour : Deux listes destinées aux zones correspondantes.
     */
    private function partitionParticipations(array $listings, int $userId): array
    {
        $participations = [];
        $wins = [];
        foreach ($listings as $listing) {
            $listing['is_current_winner'] = $listing['winner_id'] === $userId;

            if (!$listing['is_active'] && $listing['winner_id'] === $userId) {
                $wins[] = $listing;
            } else {
                $participations[] = $listing;
            }
        }
        return ['participations' => $participations, 'wins' => $wins];
    }

    /**
     * Rôle : Afficher le formulaire de compte sans réafficher les mots de passe reçus.
     * Paramètres : Valeurs publiques, erreurs et message de réussite éventuel.
     * Retour : Aucun.
     */
    private function renderAccountForm(array $values, array $errors, ?string $successMessage): void
    {
        $this->render('pages/account.php', [
            'values' => $values,
            'errors' => $errors,
            'success_message' => $successMessage,
            'csrf_token' => $this->session->obtenirJetonCsrf(),
        ]);
    }

    /**
     * Rôle : Appliquer les règles de validation des informations modifiables du compte.
     * Paramètres : Valeurs publiques, mot de passe actuel, nouveau mot de passe et confirmation.
     * Retour : Erreurs indexées par champ, éventuellement vides.
     */
    private function validateAccountValues(
        array $values,
        string $currentPassword,
        string $newPassword,
        string $confirmation
    ): array {
        $errors = [];

        if (!$this->isSubmittedCsrfTokenValid()) {
            $errors['form'] = 'Plusieurs champs doivent être corrigés avant l’enregistrement.';
        }

        if (!$this->isValidPseudo($values['pseudo'])) {
            $errors['pseudo'] = 'Format du pseudo invalide.';
        }

        if (!$this->isValidEmail($values['email'])) {
            $errors['email'] = 'Adresse électronique invalide.';
        }

        if ($currentPassword === '') {
            $errors['current_password'] = 'Le mot de passe actuel est requis.';
        }

        if ($newPassword !== '' || $confirmation !== '') {
            if (!$this->isStrongPassword($newPassword)) {
                $errors['new_password'] = 'Le nouveau mot de passe ne respecte pas les règles requises.';
            }

            if ($confirmation === '' || !hash_equals($newPassword, $confirmation)) {
                $errors['new_password_confirmation'] = 'La confirmation ne correspond pas au nouveau mot de passe.';
            }
        }

        return $errors;
    }

}
