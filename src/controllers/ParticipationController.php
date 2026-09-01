<?php

/**
 * Description générale : Contrôleur des participations d'un utilisateur aux ventes.
 * Rôle : Coordonner le suivi volontaire et le dépôt transactionnel des enchères.
 * Tâches : Contrôler la requête, appeler les modèles et choisir une réponse HTML ou JSON.
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

        $amountInCents = $this->parseBidAmountInCents($amountText);

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

        $minimumBid = $decision['minimum_amount_in_cents'] / 100;
        $attemptedAmount = $amountInCents / 100;

        if (!$decision['accepted']) {
            $this->database->rollback();
            $message = 'Montant insuffisant : minimum '
                . number_format($minimumBid, 2, ',', ' ')
                . ' €.';
            $this->respondBid(false, $message, $listingId, $summary, $minimumBid, $attemptedAmount);
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

        $nextMinimum = $nextMinimumInCents / 100;
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
     * Rôle : Valider le format d'un montant reçu et le convertir en centimes.
     * Paramètres : Montant textuel issu du formulaire d'enchère.
     * Retour : Montant en centimes ou null lorsque le format est invalide.
     */
    private function parseBidAmountInCents(string $amount): ?int
    {
        if (preg_match('/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/D', $amount, $matches) !== 1) {
            return null;
        }

        $fraction = '00';

        if (isset($matches[2])) {
            $fraction = str_pad($matches[2], 2, '0');
        }

        return ((int) $matches[1] * 100) + (int) $fraction;
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

