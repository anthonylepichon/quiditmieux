<?php

/**
 * Description générale : Contrôleur de l'espace personnel de l'utilisateur connecté.
 * Rôle : Afficher et actualiser le tableau de bord, puis gérer les informations du compte.
 * Tâches : Protéger les routes privées, préparer les ventes et participations et limiter les réponses JSON.
 * Liens avec les autres fichiers : Étend Controller.php et utilise ListingModel.php, PhotoModel.php et UserModel.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\models\ListingModel;
use App\models\PhotoModel;
use App\models\UserModel;
use DateTimeImmutable;
use DateTimeZone;

class UserController extends Controller
{
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

        $userModel = new UserModel($this->database);
        $account = $userModel->getAccount($userId);

        if ($account === null) {
            $this->session->deconnecterUtilisateur();
            $this->session->enregistrerMessageTemporaire('notice', 'Le compte demandé est indisponible.');
            $this->redirect('home');
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
        $userModel = new UserModel($this->database);
        $account = $userModel->getAccount($userId);

        if ($account === null) {
            $errors['form'] = 'Le compte ne peut pas être modifié pour le moment.';
        } elseif (!isset($account['password_hash'])
            || !is_string($account['password_hash'])
            || !password_verify($currentPassword, $account['password_hash'])
        ) {
            $errors['current_password'] = 'Le mot de passe actuel est incorrect.';
        }

        if ($errors === []) {
            if ($userModel->pseudoExists($values['pseudo'], $userId)) {
                $errors['pseudo'] = 'Ce nom d’utilisateur est déjà utilisé.';
            }

            if ($userModel->emailExists($values['email'], $userId)) {
                $errors['email'] = 'Cette adresse électronique est déjà utilisée.';
            }
        }

        if ($errors !== []) {
            $this->renderAccountForm($values, $errors, null);
            return;
        }

        $passwordHash = null;

        if ($newPassword !== '') {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if (!$userModel->updateAccount($userId, $values['pseudo'], $values['email'], $passwordHash)) {
            $errors['form'] = 'Les modifications ne peuvent pas être enregistrées pour le moment.';
            $this->renderAccountForm($values, $errors, null);
            return;
        }

        $this->session->connecterUtilisateur($userId);
        $this->session->enregistrerMessageTemporaire('success', 'Les informations de votre compte sont à jour.');
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
            $this->json(['success' => false, 'message' => 'La session a expiré.']);
            return;
        }

        $listingModel = new ListingModel($this->database);
        $sales = $this->formatListings($listingModel->getDashboardSales($userId));
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
            $this->json(['success' => false, 'message' => 'La session a expiré.']);
            return;
        }

        $listingModel = new ListingModel($this->database);
        $participationData = $this->partitionParticipations(
            $this->formatListings($listingModel->getDashboardParticipations($userId)),
            $userId
        );
        $this->json([
            'success' => true,
            'participations' => $participationData['participations'],
            'wins' => $participationData['wins'],
        ]);
    }

    /**
     * Rôle : Construire toutes les zones du tableau de bord à partir de l'utilisateur de session.
     * Paramètres : Identifiant de l'utilisateur connecté.
     * Retour : Données prêtes à afficher dans le template.
     */
    private function buildDashboard(int $userId): array
    {
        $listingModel = new ListingModel($this->database);
        $sales = $this->formatListings($listingModel->getDashboardSales($userId));
        $participationData = $this->partitionParticipations(
            $this->formatListings($listingModel->getDashboardParticipations($userId)),
            $userId
        );

        return [
            'sales' => $sales,
            'participations' => $participationData['participations'],
            'wins' => $participationData['wins'],
        ];
    }

    /**
     * Rôle : Enrichir les lignes de la base avec leurs photos et libellés d'affichage.
     * Paramètres : Lignes d'annonces issues d'une requête du tableau de bord.
     * Retour : Cartes d'annonces limitées aux données utiles à l'interface.
     */
    private function formatListings(array $rows): array
    {
        $identifiers = [];

        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $identifiers[] = (int) $row['id'];
            }
        }

        $photoModel = new PhotoModel($this->database);
        $primaryPhotos = $photoModel->getPrimaryPhotos($identifiers);
        $utcTimezone = new DateTimeZone('UTC');
        $parisTimezone = new DateTimeZone('Europe/Paris');
        $now = new DateTimeImmutable('now', $utcTimezone);
        $listings = [];

        foreach ($rows as $row) {
            if (!isset($row['id'], $row['titre'], $row['prix_depart'], $row['date_heure_fin'])) {
                continue;
            }

            $deadline = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row['date_heure_fin'], $utcTimezone);

            if (!$deadline instanceof DateTimeImmutable) {
                continue;
            }

            $identifier = (int) $row['id'];
            $currentPrice = (float) $row['prix_depart'];

            if (isset($row['best_bid']) && is_numeric($row['best_bid'])) {
                $currentPrice = (float) $row['best_bid'];
            }

            $photoUrl = null;

            if (isset($primaryPhotos[$identifier])) {
                $photoUrl = 'public/assets/images/photos-objets/' . rawurlencode($primaryPhotos[$identifier]);
            }

            $userBestBid = null;

            if (isset($row['user_best_bid']) && is_numeric($row['user_best_bid'])) {
                $userBestBid = number_format((float) $row['user_best_bid'], 2, ',', ' ') . ' €';
            }

            $winnerId = null;

            if (isset($row['winner_id']) && is_numeric($row['winner_id'])) {
                $winnerId = (int) $row['winner_id'];
            }

            $bidCount = 0;

            if (isset($row['bid_count'])) {
                $bidCount = (int) $row['bid_count'];
            }

            $listings[] = [
                'id' => $identifier,
                'title' => (string) $row['titre'],
                'category' => (string) $row['categorie_libelle'],
                'item_state' => (string) $row['etat_objet'],
                'current_price' => number_format($currentPrice, 2, ',', ' ') . ' €',
                'bid_count' => $bidCount,
                'user_best_bid' => $userBestBid,
                'winner_id' => $winnerId,
                'is_following' => isset($row['is_following']) && (int) $row['is_following'] === 1,
                'is_active' => $deadline > $now,
                'deadline' => $deadline->setTimezone($parisTimezone)->format('d/m/Y à H:i'),
                'deadline_utc' => $deadline->format('Y-m-d\TH:i:s\Z'),
                'photo_url' => $photoUrl,
                'detail_url' => 'index.php?' . http_build_query(['route' => 'listing_detail', 'id' => $identifier]),
            ];
        }

        return $listings;
    }

    /**
     * Rôle : Séparer les participations ordinaires des enchères remportées.
     * Paramètres : Cartes préparées et identifiant de l'utilisateur connecté.
     * Retour : Deux listes destinées aux zones correspondantes du tableau de bord.
     */
    private function partitionParticipations(array $listings, int $userId): array
    {
        $participations = [];
        $wins = [];

        foreach ($listings as $listing) {
            if (!$listing['is_active'] && $listing['winner_id'] === $userId) {
                $wins[] = $listing;
            } else {
                $participations[] = $listing;
            }
        }

        return ['participations' => $participations, 'wins' => $wins];
    }

    /**
     * Rôle : Exiger une session connectée et mémoriser la destination interne en cas de redirection.
     * Paramètres : Destination interne demandée après authentification.
     * Retour : Identifiant connecté ou null lorsqu'une redirection est envoyée.
     */
    private function requireConnectedUser(string $destination): ?int
    {
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();

        if ($userId !== null) {
            return $userId;
        }

        $this->redirect('login_form', ['destination' => $destination]);
        return null;
    }

    /**
     * Rôle : Afficher le formulaire de compte sans jamais réafficher les mots de passe reçus.
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

        if (!$this->session->estJetonCsrfValide($this->readPostString('csrf_token'))) {
            $errors['form'] = 'Le formulaire a expiré. Rechargez la page puis recommencez.';
        }

        if (mb_strlen($values['pseudo']) < 3 || mb_strlen($values['pseudo']) > 30) {
            $errors['pseudo'] = 'Le nom d’utilisateur doit contenir entre 3 et 30 caractères.';
        } elseif (preg_match('/^[A-Za-z0-9_-]+$/D', $values['pseudo']) !== 1) {
            $errors['pseudo'] = 'Utilisez uniquement des lettres, chiffres, tirets ou tirets bas.';
        }

        if (mb_strlen($values['email']) > 254
            || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Saisissez une adresse électronique valide de 254 caractères au maximum.';
        }

        if ($currentPassword === '') {
            $errors['current_password'] = 'Saisissez votre mot de passe actuel pour confirmer les modifications.';
        }

        if ($newPassword !== '' || $confirmation !== '') {
            if (!$this->isStrongPassword($newPassword)) {
                $errors['new_password'] = 'Le nouveau mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
            }

            if ($confirmation === '' || !hash_equals($newPassword, $confirmation)) {
                $errors['new_password_confirmation'] = 'La confirmation doit être identique au nouveau mot de passe.';
            }
        }

        return $errors;
    }

    /**
     * Rôle : Vérifier la robustesse minimale du nouveau mot de passe.
     * Paramètres : Mot de passe à contrôler.
     * Retour : true lorsque toutes les règles sont respectées, sinon false.
     */
    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
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
}
