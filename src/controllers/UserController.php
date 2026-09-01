<?php

/**
 * Description générale : Contrôleur de l'espace personnel de l'utilisateur connecté.
 * Rôle : Afficher le tableau de bord et gérer la modification sécurisée du compte.
 * Tâches : Protéger les routes privées, préparer les cartes, valider le compte et limiter les réponses JSON.
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
    // ====================
    // CONSTANTES
    // ====================

    private const PHOTO_PUBLIC_DIRECTORY = 'public/uploads/annonces/';

    // ====================
    // MÉTHODES
    // ====================

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
        if ($account === null) {
            $this->session->deconnecterUtilisateur();
            $this->session->enregistrerMessageTemporaire('notice', 'Le compte demandé est indisponible.');
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

        $values = ['pseudo' => trim($this->readPostString('pseudo')), 'email' => mb_strtolower(trim($this->readPostString('email')))];
        $currentPassword = $this->readPostString('current_password');
        $newPassword = $this->readPostString('new_password');
        $confirmation = $this->readPostString('new_password_confirmation');
        $errors = $this->validateAccountValues($values, $currentPassword, $newPassword, $confirmation);
        $model = new UserModel($this->database);
        $account = $model->getAccount($userId);

        if ($account === null) {
            $errors['form'] = 'Le compte ne peut pas être modifié pour le moment.';
        } elseif ($currentPassword !== '' && (!isset($account['password_hash'])
            || !is_string($account['password_hash'])
            || !password_verify($currentPassword, $account['password_hash']))) {
            $errors['current_password'] = 'Le mot de passe actuel est incorrect.';
        }

        if ($errors === []) {
            if ($model->pseudoExists($values['pseudo'], $userId)) {
                $errors['pseudo'] = 'Ce nom d’utilisateur est déjà utilisé.';
            }
            if ($model->emailExists($values['email'], $userId)) {
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
            if (!is_string($passwordHash)) {
                $this->renderAccountForm(
                    $values,
                    ['form' => 'Le nouveau mot de passe ne peut pas être sécurisé pour le moment.'],
                    null
                );
                return;
            }
        }
        if (!$model->updateAccount($userId, $values['pseudo'], $values['email'], $passwordHash)) {
            $this->renderAccountForm($values, ['form' => 'Les modifications ne peuvent pas être enregistrées pour le moment.'], null);
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

        $model = new ListingModel($this->database);
        $this->json(['success' => true, 'sales' => $this->formatListings($model->getDashboardSales($userId))]);
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

        $model = new ListingModel($this->database);
        $data = $this->partitionParticipations(
            $this->formatListings($model->getDashboardParticipations($userId)),
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
        $model = new ListingModel($this->database);
        $data = $this->partitionParticipations(
            $this->formatListings($model->getDashboardParticipations($userId)),
            $userId
        );
        return [
            'sales' => $this->formatListings($model->getDashboardSales($userId)),
            'participations' => $data['participations'],
            'wins' => $data['wins'],
        ];
    }

    /**
     * Rôle : Enrichir les lignes de la base avec leurs photos et libellés d'affichage.
     * Paramètres : Lignes d'annonces issues du tableau de bord.
     * Retour : Cartes limitées aux données utiles à l'interface.
     */
    private function formatListings(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }

        $photos = (new PhotoModel($this->database))->getPrimaryPhotos($ids);
        $utc = new DateTimeZone('UTC');
        $paris = new DateTimeZone('Europe/Paris');
        $now = new DateTimeImmutable('now', $utc);
        $listings = [];

        foreach ($rows as $row) {
            if (!isset($row['id'], $row['titre'], $row['prix_depart'], $row['date_heure_fin'])) {
                continue;
            }
            $deadline = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row['date_heure_fin'], $utc);
            if (!$deadline instanceof DateTimeImmutable) {
                continue;
            }

            $id = (int) $row['id'];
            $price = (float) $row['prix_depart'];
            if (isset($row['best_bid']) && is_numeric($row['best_bid'])) {
                $price = (float) $row['best_bid'];
            }
            $userBestBid = null;
            if (isset($row['user_best_bid']) && is_numeric($row['user_best_bid'])) {
                $userBestBid = number_format((float) $row['user_best_bid'], 2, ',', ' ') . ' €';
            }

            $listings[] = [
                'id' => $id,
                'title' => (string) $row['titre'],
                'category' => (string) $row['categorie_libelle'],
                'current_price' => number_format($price, 2, ',', ' ') . ' €',
                'bid_count' => $this->readBidCount($row),
                'user_best_bid' => $userBestBid,
                'winner_id' => $this->readWinnerId($row),
                'is_active' => $deadline > $now,
                'deadline' => $deadline->setTimezone($paris)->format('d/m/Y à H:i'),
                'photo_url' => $this->buildPhotoUrl($photos, $id),
                'detail_url' => 'index.php?' . http_build_query(['route' => 'listing_detail', 'id' => $id]),
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
            return self::PHOTO_PUBLIC_DIRECTORY . rawurlencode($photos[$listingId]);
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
            if (!$listing['is_active'] && $listing['winner_id'] === $userId) {
                $wins[] = $listing;
            } else {
                $participations[] = $listing;
            }
        }
        return ['participations' => $participations, 'wins' => $wins];
    }

    /**
     * Rôle : Exiger une session connectée et mémoriser la destination interne.
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
    private function validateAccountValues(array $values, string $currentPassword, string $newPassword, string $confirmation): array
    {
        $errors = [];
        if (!$this->session->estJetonCsrfValide($this->readPostString('csrf_token'))) {
            $errors['form'] = 'Le formulaire a expiré. Rechargez la page puis recommencez.';
        }
        if (mb_strlen($values['pseudo']) < 3 || mb_strlen($values['pseudo']) > 30) {
            $errors['pseudo'] = 'Le pseudo doit contenir entre 3 et 30 caractères.';
        } elseif (preg_match('/^[A-Za-z0-9_-]+$/D', $values['pseudo']) !== 1) {
            $errors['pseudo'] = 'Utilisez uniquement des lettres, chiffres, tirets ou tirets bas.';
        }
        if (mb_strlen($values['email']) > 254 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
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
