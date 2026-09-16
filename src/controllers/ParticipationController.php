<?php

/**
 * Description générale : Contrôleur des participations d'un utilisateur aux ventes.
 * Rôle : Coordonner le suivi volontaire et le dépôt des enchères. Cela empêche d'enregistrer une participation sans vérifier la connexion, le jeton du formulaire, l'annonce et les droits de l'utilisateur.
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
     * Rôle : Enregistrer une enchère strictement supérieure au prix courant d'une vente active. Cette règle évite d'afficher ou d'enregistrer un montant incompatible avec l'état réel de la vente.
     * Paramètres : Aucun, l'annonce, le montant et le jeton sont lus dans la requête POST.
     * Retour : Aucun, une réponse JSON ou une redirection vers le détail est envoyée.
     */
    public function placeBid(): void
    {
        // L'identité provient exclusivement de la session et l'annonce du formulaire POST contrôlé.
        $userId = $this->session->getConnectedUserId();
        $listingId = $this->readPositivePostIdentifier('id');
        $amountText = $this->readPostString('amount');

        // Une enchère ne peut être déposée sans authentification.
        if ($userId === null) {
            $this->respondBid(false, 'Connectez-vous pour suivre cette annonce ou enchérir.', $listingId);
            return;
        }

        // Le jeton CSRF empêche qu'un autre site déclenche une enchère au nom de l'utilisateur.
        if ($listingId === null || !$this->isSubmittedCsrfTokenValid()) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $amountInEuros = null;

        // NATIF PHP : preg_match() vérifie un texte avec une expression régulière ; il contrôle ici que la valeur respecte le format attendu.
        if (preg_match('/^[0-9]+$/D', $amountText) === 1) {
            $amountInEuros = (int) $amountText;
        }

        if ($amountInEuros === null || $amountInEuros > 99_999) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        // NATIF PHP : DateTime est la classe native de gestion des dates et des heures ; elle fournit ici l’instant utilisé pour contrôler et enregistrer la participation.
        $currentTime = new \DateTime();
        $listingModel = new ListingModel($this->database);
        $canParticipate = $listingModel->canReceiveParticipationFrom(
            $listingId,
            $userId,
            $currentTime
        );

        if ($canParticipate === null) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $restriction = $listingModel->getLastParticipationRestriction();

        if (!$canParticipate) {
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
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $decision = $bidModel->evaluateBidAmountInEuros($listingId, $amountInEuros);

        if ($decision === null) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $minimumBidInEuros = $decision['minimum_amount_in_euros'];

        if (!$decision['accepted']) {
            $message = 'Montant insuffisant : minimum '
                . $this->formatEuros($minimumBidInEuros)
                . '.';
            $this->respondBid(false, $message, $listingId, $summary, $minimumBidInEuros, $amountInEuros);
            return;
        }

        if (!$bidModel->placeBid($userId, $listingId, $amountInEuros, $currentTime)) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $updatedSummary = $bidModel->getSummary($listingId);

        if ($updatedSummary === false) {
            $this->respondBid(false, 'Enchère refusée', $listingId);
            return;
        }

        $nextMinimumInEuros = $bidModel->getMinimumAmountInEuros($listingId);

        if ($nextMinimumInEuros === null) {
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
     * Rôle : Demander l'ajout du suivi d'une annonce après les contrôles communs de connexion, de jeton, d'échéance et de propriétaire.
     * Paramètres : Aucun, l'annonce et le jeton sont lus dans la requête POST.
     * Retour : Aucun, une réponse JSON ou une redirection vers le détail est envoyée.
     */
    public function follow(): void
    {
        // La logique commune évite de dupliquer les protections entre ajout et retrait du suivi.
        $this->changeFollowState(true);
    }

    /**
     * Rôle : Retirer le suivi volontaire sans modifier les enchères déjà déposées. L'utilisateur cesse ainsi de suivre l'annonce tout en conservant l'historique définitif de ses enchères.
     * Paramètres : Aucun, l'annonce et le jeton sont lus dans la requête POST.
     * Retour : Aucun, une réponse JSON ou une redirection vers le détail est envoyée.
     */
    public function unfollow(): void
    {
        // La logique commune évite de dupliquer les protections entre ajout et retrait du suivi.
        $this->changeFollowState(false);
    }

    /**
     * Rôle : Vérifier la demande de suivi puis appeler FollowModel pour l'utilisateur connecté et l'annonce reçue. L'identité ne provient jamais du formulaire.
     * Paramètres : État de suivi demandé.
     * Retour : Aucun.
     */
    private function changeFollowState(bool $shouldFollow): void
    {
        // L'identité provient de la session et l'annonce du formulaire POST contrôlé.
        $userId = $this->session->getConnectedUserId();
        $listingId = $this->readPositivePostIdentifier('id');

        if ($userId === null) {
            $this->respond(false, 'Connectez-vous pour suivre cette annonce ou enchérir.', $listingId, false);
            return;
        }

        if ($listingId === null || !$this->isSubmittedCsrfTokenValid()) {
            $this->respond(false, 'Le suivi ne peut pas être actualisé pour le moment.', $listingId, false);
            return;
        }

        $currentTime = new \DateTime();
        $listingModel = new ListingModel($this->database);
        $canParticipate = $listingModel->canReceiveParticipationFrom(
            $listingId,
            $userId,
            $currentTime
        );

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

        // Le modèle de suivi réalise uniquement l'ajout ou le retrait de la relation en base.
        $followModel = new FollowModel($this->database);
        $changed = false;
        $message = 'Vous ne suivez pas encore cette annonce. Suivez-la pour la retrouver dans votre tableau de bord.';

        // L'action demandée détermine l'opération de persistance et le message de retour.
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
     * Rôle : Envoyer un résultat JSON pour AJAX ou appliquer le repli POST-Redirect-GET. La même action reste ainsi utilisable avec JavaScript ou avec une redirection classique sans être exécutée deux fois.
     * Paramètres : Succès, message, annonce éventuelle et état de suivi obtenu.
     * Retour : Aucun.
     */
    private function respond(bool $success, string $message, ?int $listingId, bool $isFollowing): void
    {
        // Le JavaScript reçoit une structure JSON ; un formulaire classique suit le parcours POST-Redirect-GET.
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

        // Les messages d'échec sont conservés une seule fois dans la session pour la page suivante.
        if (!$success && $message !== '') {
            $this->session->setFlashMessage('notice', $message);
        }

        if ($listingId !== null) {
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $this->redirect('home');
    }

    /**
     * Rôle : Présenter le résultat déjà calculé d'une enchère en JSON pour JavaScript ou par redirection pour un formulaire classique. L'action n'est ainsi pas répétée lors du rechargement de la page.
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
        // Le format JSON fournit les informations nécessaires à la mise à jour immédiate de l'interface.
        if ($this->isJsonRequest()) {
            $currentPrice = null;
            $bidCount = null;

            // NATIF PHP : is_int() vérifie qu’une valeur est un entier ; il évite ici d’utiliser un autre type dans un traitement numérique.
            // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
            if (isset($summary['best_bid_in_euros']) && is_int($summary['best_bid_in_euros'])) {
                $currentPrice = $this->formatEuros($summary['best_bid_in_euros']);
            }

            // NATIF PHP : is_numeric() vérifie qu’une valeur représente un nombre ; il protège ici la conversion ou le calcul qui suit.
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

        // Une enchère refusée conserve le minimum et le montant saisi pour les afficher après la redirection.
        if (
            !$success
            && $listingId !== null
            && $minimumBidInEuros !== null
            && $attemptedAmountInEuros !== null
        ) {
            // NATIF PHP : json_encode() convertit une donnée PHP en JSON ; il prépare ici une réponse destinée au JavaScript ou un contenu à enregistrer.
            $rejectionData = json_encode([
                'listing_id' => $listingId,
                'minimum_in_euros' => $minimumBidInEuros,
                'amount_in_euros' => $attemptedAmountInEuros,
            ]);

            // NATIF PHP : is_string() vérifie qu’une valeur est une chaîne de caractères ; il évite ici de traiter un type inattendu comme du texte.
            if (is_string($rejectionData)) {
                $this->session->setFlashMessage('bid_rejection', $rejectionData);
            }
        } elseif (!$success && $message !== '') {
            $this->session->setFlashMessage('notice', $message);
        }

        if ($listingId !== null) {
            $this->redirect('listing_detail', ['id' => $listingId]);
        }

        $this->redirect('home');
    }

    /**
     * Rôle : Construire l'adresse interne du détail à partir d'un identifiant déjà contrôlé, ou revenir à l'accueil s'il est absent. Les réponses JSON disposent ainsi toujours d'une destination sûre.
     * Paramètres : Identifiant éventuel de l'annonce.
     * Retour : Adresse interne du détail ou de l'accueil.
     */
    private function detailUrl(?int $listingId): string
    {
        // Sans identifiant d'annonce exploitable, la seule destination sûre est l'accueil.
        if ($listingId === null) {
            return $this->buildRouteUrl('home');
        }

        return $this->buildRouteUrl('listing_detail', ['id' => $listingId]);
    }
}
