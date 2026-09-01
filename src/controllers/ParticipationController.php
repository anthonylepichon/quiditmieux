<?php

/**
 * Description générale : Contrôleur des participations d'un utilisateur aux ventes.
 * Rôle : Coordonner le suivi volontaire et le dépôt transactionnel des enchères.
 * Tâches : Contrôler la requête, appeler les modèles et choisir une réponse HTML ou JSON.
 * Liens avec les autres fichiers : Étend Controller.php et utilise ListingModel.php, FollowModel.php et BidModel.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\core\Money;
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

        $amountInCents = Money::userInputToCents($amountText);

        if ($amountInCents === null) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        if (!$this->database->beginTransaction()) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $listingModel = new ListingModel($this->database);
        $canParticipate = $listingModel->canReceiveParticipationFrom($listingId, $userId);

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

        $decision = $bidModel->evaluateBidAmountInCents($listingId, $amountInCents);

        if ($decision === null) {
            $this->database->rollback();
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $minimumBidInCents = $decision['minimum_amount_in_cents'];

        if (!$decision['accepted']) {
            $this->database->rollback();
            $message = 'Montant insuffisant : minimum '
                . Money::formatCentsForDisplay($minimumBidInCents)
                . '.';
            $this->respondBid(false, $message, $listingId, $summary, $minimumBidInCents, $amountInCents);
            return;
        }

        if (!$bidModel->placeBid($userId, $listingId, $amountInCents)) {
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

        $nextMinimumInCents = $bidModel->getMinimumAmountInCents($listingId);

        if ($nextMinimumInCents === null) {
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
            $nextMinimumInCents
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
        $canParticipate = $listingModel->canReceiveParticipationFrom($listingId, $userId);

        if ($canParticipate === null) {
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, false);
            return;
        }

        $restriction = $listingModel->getLastParticipationRestriction();

        if (!$canParticipate) {
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
     * Paramètres : Succès, message, annonce, résumé, minimum et montant refusé en centimes éventuels.
     * Retour : Aucun.
     */
    private function respondBid(
        bool $success,
        string $message,
        ?int $listingId,
        array $summary = [],
        ?int $minimumBidInCents = null,
        ?int $attemptedAmountInCents = null
    ): void {
        if ($this->isJsonRequest()) {
            $currentPrice = null;
            $bidCount = null;

            if (isset($summary['best_bid_in_cents']) && is_int($summary['best_bid_in_cents'])) {
                $currentPrice = Money::formatCentsForDisplay($summary['best_bid_in_cents']);
            }

            if (isset($summary['bid_count']) && is_numeric($summary['bid_count'])) {
                $bidCount = (int) $summary['bid_count'];
            }

            $minimumBidValue = null;

            if ($minimumBidInCents !== null) {
                $minimumBidValue = Money::centsToDecimal($minimumBidInCents);
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
            && $minimumBidInCents !== null
            && $attemptedAmountInCents !== null
        ) {
            $rejectionData = json_encode([
                'listing_id' => $listingId,
                'minimum_in_cents' => $minimumBidInCents,
                'amount_in_cents' => $attemptedAmountInCents,
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
            return 'index.php?route=home';
        }

        return 'index.php?' . http_build_query(['route' => 'listing_detail', 'id' => $listingId]);
    }
}

