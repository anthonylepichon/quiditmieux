<?php

/**
 * Description générale : Contrôleur des participations d'un utilisateur aux ventes.
 * Rôle : Coordonner le suivi volontaire et le dépôt transactionnel des enchères.
 * Tâches : Contrôler la requête, appeler les modèles et choisir une réponse HTML ou JSON.
 * Liens avec les autres fichiers : Étend Controller.php et utilise Clock.php, ListingModel.php, FollowModel.php et BidModel.php.
 */

namespace App\controllers;

use App\core\Clock;
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

        if ($listingId === null || !$this->isSubmittedCsrfTokenValid()) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $amountInEuros = null;

        if (preg_match('/^[0-9]+$/D', $amountText) === 1) {
            $amountInEuros = (int) $amountText;
        }

        if ($amountInEuros === null || $amountInEuros > 99_999) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        if (!$this->database->beginTransaction()) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $currentTimeUtc = Clock::nowUtc();
        $listingModel = new ListingModel($this->database);
        $canParticipate = $listingModel->canReceiveParticipationFrom(
            $listingId,
            $userId,
            $currentTimeUtc
        );

        if ($canParticipate === null) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $restriction = $listingModel->getLastParticipationRestriction();

        if (!$canParticipate) {
            $this->database->rollback();

            if ($restriction === 'ended') {
                $this->respondBid(false, 'Vente terminée', $listingId);
            } else {
                $this->respondBid(false, 'Enchère refusée', $listingId);
            }

            return;
        }

        $bidModel = new BidModel($this->database);
        $summary = $bidModel->getSummary($listingId);

        if ($summary === false) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $decision = $bidModel->evaluateBidAmountInEuros($listingId, $amountInEuros);

        if ($decision === null) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $minimumBidInEuros = $decision['minimum_amount_in_euros'];

        if (!$decision['accepted']) {
            $this->database->rollback();
            $message = 'Montant insuffisant : minimum '
                . $this->formatEuros($minimumBidInEuros)
                . '.';
            $this->respondBid(false, $message, $listingId, $summary, $minimumBidInEuros, $amountInEuros);
            return;
        }

        if (!$bidModel->placeBid($userId, $listingId, $amountInEuros, $currentTimeUtc)) {
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

        $nextMinimumInEuros = $bidModel->getMinimumAmountInEuros($listingId);

        if ($nextMinimumInEuros === null) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        if (!$this->database->commit()) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $this->respondBid(
            true,
            'Vous êtes actuellement le mieux-disant. Vous pouvez enchérir de nouveau si nécessaire.',
            $listingId,
            $updatedSummary,
            $nextMinimumInEuros
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

        if ($listingId === null || !$this->isSubmittedCsrfTokenValid()) {
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, false);
            return;
        }

        if (!$this->database->beginTransaction()) {
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, false);
            return;
        }

        $currentTimeUtc = Clock::nowUtc();
        $listingModel = new ListingModel($this->database);
        $canParticipate = $listingModel->canReceiveParticipationFrom(
            $listingId,
            $userId,
            $currentTimeUtc
        );

        if ($canParticipate === null) {
            $this->database->rollback();
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, false);
            return;
        }

        $restriction = $listingModel->getLastParticipationRestriction();

        if (!$canParticipate) {
            $this->database->rollback();

            if ($restriction === 'ended') {
                $this->respond(false, 'Vente terminée', $listingId, false);
            } else {
                $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, false);
            }

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
            $this->database->rollback();
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, !$shouldFollow);
            return;
        }

        if (!$this->database->commit()) {
            $this->database->rollback();
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
     * Paramètres : Succès, message, annonce, résumé, minimum et montant refusé en euros éventuels.
     * Retour : Aucun.
     */
    private function respondBid(
        bool $success,
        string $message,
        ?int $listingId,
        array $summary = [],
        ?int $minimumBidInEuros = null,
        ?int $attemptedAmountInEuros = null
    ): void {
        if ($this->isJsonRequest()) {
            $currentPrice = null;
            $bidCount = null;

            if (isset($summary['best_bid_in_euros']) && is_int($summary['best_bid_in_euros'])) {
                $currentPrice = $this->formatEuros($summary['best_bid_in_euros']);
            }

            if (isset($summary['bid_count']) && is_numeric($summary['bid_count'])) {
                $bidCount = (int) $summary['bid_count'];
            }

            $minimumBidValue = null;

            if ($minimumBidInEuros !== null) {
                $minimumBidValue = $minimumBidInEuros;
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

        if (
            !$success
            && $listingId !== null
            && $minimumBidInEuros !== null
            && $attemptedAmountInEuros !== null
        ) {
            $rejectionData = json_encode([
                'listing_id' => $listingId,
                'minimum_in_euros' => $minimumBidInEuros,
                'amount_in_euros' => $attemptedAmountInEuros,
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
     * Rôle : Construire l'adresse canonique du détail sans accepter de donnée extérieure.
     * Paramètres : Identifiant éventuel de l'annonce.
     * Retour : Adresse interne du détail ou de l'accueil.
     */
    private function detailUrl(?int $listingId): string
    {
        if ($listingId === null) {
            return $this->buildRouteUrl('home');
        }

        return $this->buildRouteUrl('listing_detail', ['id' => $listingId]);
    }
}
