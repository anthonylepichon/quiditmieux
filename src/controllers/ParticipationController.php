<?php

/**
 * Description générale : Contrôleur des participations d'un utilisateur aux ventes.
 * Rôle : Gérer le suivi volontaire et accueillir le dépôt transactionnel des enchères.
 * Tâches : Revalider l'authentification, le CSRF, la propriété et l'échéance avant toute modification.
 * Liens avec les autres fichiers : Étend Controller.php et utilise ListingModel.php, FollowModel.php et BidModel.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\models\BidModel;
use App\models\FollowModel;
use App\models\ListingModel;

class ParticipationController extends Controller
{
    /**
     * Rôle : Enregistrer une enchère strictement supérieure au prix courant d'une vente active.
     * Paramètres : Aucun, l'annonce, le montant et le jeton sont lus dans la requête POST.
     * Retour : Aucun, une réponse JSON ou une redirection vers le détail est envoyée.
     */
    public function placeBid(): void
    {
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();
        $listingId = $this->readPositivePostIdentifier('id');
        $amountText = $this->readPostString('amount');

        if ($userId === null) {
            $this->respondBid(false, 'Connectez-vous pour suivre cette annonce ou enchérir.', $listingId);
            return;
        }

        if ($listingId === null || !$this->session->estJetonCsrfValide($this->readPostString('csrf_token'))) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        if (preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/', $amountText) !== 1) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $amount = (float) $amountText;

        if (!$this->database->beginTransaction()) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $listingModel = new ListingModel($this->database);
        $bidModel = new BidModel($this->database);
        $listing = $listingModel->getForUpdate($listingId);

        if ($listing === false) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        if ($listing === null) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        if ((int) $listing['utilisateur_id'] === $userId) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        if ((string) $listing['date_heure_fin'] <= gmdate('Y-m-d H:i:s')) {
            $this->database->rollback();
            $this->respondBid(false, 'Vente terminée', $listingId);
            return;
        }

        $summary = $bidModel->getSummary($listingId);

        if ($summary === false) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $currentPrice = (float) $listing['prix_depart'];

        if ($summary['best_bid'] !== null) {
            $currentPrice = (float) $summary['best_bid'];
        }

        $minimumBid = round($currentPrice + 0.01, 2);

        if ($amount < $minimumBid) {
            $this->database->rollback();
            $message = 'Montant insuffisant : minimum '
                . number_format($minimumBid, 2, ',', ' ')
                . ' €.';
            $this->respondBid(false, $message, $listingId, $summary, $minimumBid, $amount);
            return;
        }

        if (!$bidModel->placeBid($userId, $listingId, $amount)) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $updatedSummary = $bidModel->getSummary($listingId);

        if ($updatedSummary === false) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        if (!$this->database->commit()) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $nextMinimum = round($amount + 0.01, 2);
        $this->respondBid(
            true,
            'Vous êtes actuellement le mieux-disant. Vous pouvez enchérir de nouveau si nécessaire.',
            $listingId,
            $updatedSummary,
            $nextMinimum
        );
    }

    /**
     * Rôle : Ajouter le suivi volontaire d'une annonce active appartenant à un autre utilisateur.
     * Paramètres : Aucun, l'annonce et le jeton sont lus dans la requête POST.
     * Retour : Aucun, une réponse JSON ou une redirection vers le détail est envoyée.
     */
    public function follow(): void
    {
        $this->changeFollowState(true);
    }

    /**
     * Rôle : Retirer le suivi volontaire sans modifier les enchères déjà déposées.
     * Paramètres : Aucun, l'annonce et le jeton sont lus dans la requête POST.
     * Retour : Aucun, une réponse JSON ou une redirection vers le détail est envoyée.
     */
    public function unfollow(): void
    {
        $this->changeFollowState(false);
    }

    /**
     * Rôle : Appliquer l'ajout ou le retrait du suivi après tous les contrôles serveur.
     * Paramètres : État de suivi demandé.
     * Retour : Aucun.
     */
    private function changeFollowState(bool $shouldFollow): void
    {
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();
        $listingId = $this->readPositivePostIdentifier('id');

        if ($userId === null) {
            $this->respond(false, 'Connectez-vous pour suivre cette annonce ou enchérir.', $listingId, false);
            return;
        }

        if ($listingId === null || !$this->session->estJetonCsrfValide($this->readPostString('csrf_token'))) {
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, false);
            return;
        }

        $listingModel = new ListingModel($this->database);
        $listing = $listingModel->getDetail($listingId);

        if ($listing === false) {
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, false);
            return;
        }

        if ($listing === null) {
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, false);
            return;
        }

        if ((int) $listing['utilisateur_id'] === $userId) {
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, false);
            return;
        }

        if ((string) $listing['date_heure_fin'] <= gmdate('Y-m-d H:i:s')) {
            $this->respond(false, 'Vente terminée', $listingId, false);
            return;
        }

        $followModel = new FollowModel($this->database);
        $changed = false;
        $message = 'Vous ne suivez pas encore cette annonce. Suivez-la pour la retrouver dans votre tableau de bord.';

        if ($shouldFollow) {
            $changed = $followModel->follow($userId, $listingId);
            $message = 'Vous pouvez continuer à suivre l’annonce et enchérir.';
        } else {
            $changed = $followModel->unfollow($userId, $listingId);
        }

        if (!$changed) {
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, !$shouldFollow);
            return;
        }

        $this->respond(true, $message, $listingId, $shouldFollow);
    }

    /**
     * Rôle : Envoyer un résultat JSON pour AJAX ou appliquer le repli POST-Redirect-GET.
     * Paramètres : Succès, message, annonce éventuelle et état de suivi obtenu.
     * Retour : Aucun.
     */
    private function respond(bool $success, string $message, ?int $listingId, bool $isFollowing): void
    {
        if ($this->isJsonRequest()) {
            $stateKey = 'not_following';

            if ($isFollowing) {
                $stateKey = 'following';
            }

            $this->json([
                'success' => $success,
                'message' => $message,
                'state_key' => $stateKey,
                'is_following' => $isFollowing,
                'canonical_url' => $this->detailUrl($listingId),
            ]);
            return;
        }

        if (!$success && $message !== '') {
            $this->session->enregistrerMessageTemporaire('notice', $message);
        }

        if ($listingId !== null) {
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $this->redirect('home');
    }

    /**
     * Rôle : Envoyer le résultat d'une enchère en JSON ou appliquer le repli POST-Redirect-GET.
     * Paramètres : Succès, message, annonce, résumé, minimum et montant refusé éventuels.
     * Retour : Aucun.
     */
    private function respondBid(
        bool $success,
        string $message,
        ?int $listingId,
        array $summary = [],
        ?float $minimumBid = null,
        ?float $attemptedAmount = null
    ): void {
        if ($this->isJsonRequest()) {
            $currentPrice = null;
            $bidCount = null;

            if (isset($summary['best_bid']) && is_numeric($summary['best_bid'])) {
                $currentPrice = number_format((float) $summary['best_bid'], 2, ',', ' ') . ' €';
            }

            if (isset($summary['bid_count']) && is_numeric($summary['bid_count'])) {
                $bidCount = (int) $summary['bid_count'];
            }

            $minimumBidValue = null;

            if ($minimumBid !== null) {
                $minimumBidValue = number_format($minimumBid, 2, '.', '');
            }

            $this->json([
                'success' => $success,
                'message' => $message,
                'current_price' => $currentPrice,
                'bid_count' => $bidCount,
                'minimum_bid' => $minimumBidValue,
                'canonical_url' => $this->detailUrl($listingId),
            ]);
            return;
        }

        if (!$success
            && $listingId !== null
            && $minimumBid !== null
            && $attemptedAmount !== null
        ) {
            $rejectionData = json_encode([
                'listing_id' => $listingId,
                'minimum' => $minimumBid,
                'amount' => $attemptedAmount,
            ]);

            if (is_string($rejectionData)) {
                $this->session->enregistrerMessageTemporaire('bid_rejection', $rejectionData);
            }
        } elseif (!$success && $message !== '') {
            $this->session->enregistrerMessageTemporaire('notice', $message);
        }

        if ($listingId !== null) {
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $this->redirect('home');
    }

    /**
     * Rôle : Lire une valeur POST simple sans accepter de tableau inattendu.
     * Paramètres : Nom du champ.
     * Retour : Valeur reçue ou chaîne vide.
     */
    private function readPostString(string $name): string
    {
        if (!isset($_POST[$name]) || !is_string($_POST[$name])) {
            return '';
        }

        return trim($_POST[$name]);
    }

    /**
     * Rôle : Lire un identifiant POST entier strictement positif.
     * Paramètres : Nom du champ.
     * Retour : Identifiant ou null lorsque la valeur est invalide.
     */
    private function readPositivePostIdentifier(string $name): ?int
    {
        $identifier = filter_var($this->readPostString($name), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($identifier === false) {
            return null;
        }

        return (int) $identifier;
    }

    /**
     * Rôle : Indiquer si le navigateur demande explicitement une réponse JSON.
     * Paramètres : Aucun.
     * Retour : true pour une demande AJAX JSON, sinon false.
     */
    private function isJsonRequest(): bool
    {
        return isset($_GET['format']) && is_string($_GET['format']) && $_GET['format'] === 'json';
    }

    /**
     * Rôle : Construire l'adresse canonique du détail sans accepter de donnée extérieure.
     * Paramètres : Identifiant éventuel de l'annonce.
     * Retour : Adresse interne du détail ou de l'accueil.
     */
    private function detailUrl(?int $listingId): string
    {
        if ($listingId === null) {
            return 'index.php?route=home';
        }

        return 'index.php?' . http_build_query(['route' => 'listing_detail', 'id' => $listingId]);
    }
}

