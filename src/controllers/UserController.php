<?php

/**
 * Description générale : Contrôleur du tableau de bord de l'utilisateur connecté.
 * Rôle : Afficher et actualiser les ventes, les suivis et les enchères de l'utilisateur.
 * Tâches : Protéger les routes privées, préparer les cartes et limiter les réponses JSON aux données utiles.
 * Liens avec les autres fichiers : Étend Controller.php et utilise ListingModel.php et PhotoModel.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\models\ListingModel;
use App\models\PhotoModel;
use DateTimeImmutable;
use DateTimeZone;

class UserController extends Controller
{
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
            return 'public/assets/images/photos-objets/' . rawurlencode($photos[$listingId]);
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
}
