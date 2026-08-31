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
}
