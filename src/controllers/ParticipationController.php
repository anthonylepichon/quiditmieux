<?php

/**
 * Description générale : Contrôleur des participations d'un utilisateur aux ventes.
 * Rôle : Gérer le suivi volontaire des annonces par les utilisateurs connectés.
 * Tâches : Revalider l'authentification, le CSRF, la propriété et l'échéance avant toute modification.
 * Liens avec les autres fichiers : Étend Controller.php et utilise ListingModel.php et FollowModel.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\models\FollowModel;
use App\models\ListingModel;

class ParticipationController extends Controller
{
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
            $this->respond(false, 'Connectez-vous pour suivre cette annonce.', $listingId, false);
            return;
        }

        if ($listingId === null || !$this->session->estJetonCsrfValide($this->readPostString('csrf_token'))) {
            $this->respond(false, 'La demande de suivi ne peut pas être confirmée.', $listingId, false);
            return;
        }

        $listingModel = new ListingModel($this->database);
        $listing = $listingModel->getDetail($listingId);

        if ($listing === null) {
            $this->respond(false, 'L’annonce demandée est introuvable.', $listingId, false);
            return;
        }

        if ((int) $listing['utilisateur_id'] === $userId) {
            $this->respond(false, 'Vous ne pouvez pas suivre votre propre annonce.', $listingId, false);
            return;
        }

        if ((string) $listing['date_heure_fin'] <= gmdate('Y-m-d H:i:s')) {
            $this->respond(false, 'Cette vente est terminée.', $listingId, false);
            return;
        }

        $followModel = new FollowModel($this->database);
        $changed = false;
        $message = 'Annonce retirée de vos suivis.';

        if ($shouldFollow) {
            $changed = $followModel->follow($userId, $listingId);
            $message = 'Annonce ajoutée à vos suivis.';
        } else {
            $changed = $followModel->unfollow($userId, $listingId);
        }

        if (!$changed) {
            $this->respond(false, 'Le suivi ne peut pas être modifié pour le moment.', $listingId, !$shouldFollow);
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

        $messageType = 'notice';

        if ($success) {
            $messageType = 'success';
        }

        $this->session->enregistrerMessageTemporaire($messageType, $message);

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
