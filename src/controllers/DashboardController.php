<?php

/**
 * Description générale : Contrôleur du tableau de bord de l'utilisateur connecté.
 * Rôle : Afficher et actualiser les ventes, suivis et enchères de l'espace personnel.
 * Tâches : Protéger l'accès, charger les annonces, préparer les cartes et produire les réponses HTML ou JSON.
 * Liens avec les autres fichiers : Étend Controller.php et utilise les modèles du tableau de bord ainsi que PhotoStorage.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\core\Database;
use App\core\Session;
use App\services\PhotoStorage;
use App\models\CategoryModel;
use App\models\ListingModel;
use App\models\PhotoModel;
// NATIF PHP : DateTime est la classe native de gestion des dates et des heures ; elle permet ici de classer et de formater les échéances.
use DateTime;

class DashboardController extends Controller
{
    // ====================
    // ATTRIBUTS
    // ====================

    private PhotoStorage $photoStorage;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Conserver les dépendances communes et préparer le gestionnaire des fichiers photographiques.
     * Paramètres : Gestionnaires de base de données et de session partagés avec l'application.
     * Retour : Aucun.
     */
    public function __construct(Database $database, Session $session)
    {
        // Le stockage physique est séparé du modèle PhotoModel, qui ne conserve que les références en base.
        parent::__construct($database, $session);
        $this->photoStorage = new PhotoStorage();
    }

    /**
     * Rôle : Afficher les ventes, participations et enchères remportées de l'utilisateur connecté.
     * Paramètres : Aucun.
     * Retour : Aucun, le template du tableau de bord est affiché ou une redirection est envoyée.
     */
    public function showDashboard(): void
    {
        // Le tableau de bord est construit uniquement pour l'utilisateur actuellement connecté.
        $userId = $this->requireConnectedUser('dashboard');
        if ($userId === null) {
            return;
        }

        // Les trois zones du tableau sont préparées une fois avant le rendu du template.
        $dashboard = $this->buildDashboard($userId);
        $dashboard['csrf_token'] = $this->session->getCsrfToken();
        $dashboard['flash_success'] = $this->session->getFlashMessage('success');
        $dashboard['flash_notice'] = $this->session->getFlashMessage('notice');

        if ($dashboard['load_error']) {
            $dashboard['flash_notice'] = 'Le tableau de bord ne peut pas être actualisé pour le moment.';
        }

        unset($dashboard['load_error']);
        $dashboard = $this->addDashboardCardDisplay($dashboard);
        $dashboard['dashboard_message'] = $this->buildDashboardMessage($dashboard);
        $this->render('pages/dashboard.php', $dashboard);
    }

    /**
     * Rôle : Fournir l'état actualisé des ventes du vendeur connecté.
     * Paramètres : Aucun.
     * Retour : Aucun, une réponse JSON limitée aux cartes de vente est envoyée.
     */
    public function refreshSales(): void
    {
        // Cette route JSON ne transmet que les ventes de l'utilisateur de la session.
        $userId = $this->session->getConnectedUserId();
        if ($userId === null) {
            $this->respondDashboardUnavailable();
            return;
        }

        $currentTime = new DateTime();
        $model = new ListingModel($this->database);
        $rows = $model->getDashboardSales($userId, $currentTime);

        if ($rows === false) {
            $this->respondDashboardUnavailable();
            return;
        }

        $categories = $this->getDashboardCategories();
        $sales = $this->formatListings($rows, $categories, $currentTime);

        if ($sales === false) {
            $this->respondDashboardUnavailable();
            return;
        }

        $this->json(['success' => true, 'sales' => $sales]);
    }

    /**
     * Rôle : Fournir l'état actualisé des participations et gains de l'utilisateur connecté.
     * Paramètres : Aucun.
     * Retour : Aucun, une réponse JSON structurée est envoyée.
     */
    public function refreshParticipations(): void
    {
        // Cette route JSON ne transmet que les participations de l'utilisateur de la session.
        $userId = $this->session->getConnectedUserId();
        if ($userId === null) {
            $this->respondDashboardUnavailable();
            return;
        }

        $currentTime = new DateTime();
        $model = new ListingModel($this->database);
        $rows = $model->getDashboardParticipations($userId, $currentTime);

        if ($rows === false) {
            $this->respondDashboardUnavailable();
            return;
        }

        $categories = $this->getDashboardCategories();
        $listings = $this->formatListings($rows, $categories, $currentTime);

        if ($listings === false) {
            $this->respondDashboardUnavailable();
            return;
        }

        $data = $this->partitionParticipations(
            $listings,
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
        $currentTime = new DateTime();
        $model = new ListingModel($this->database);
        $participationRows = $model->getDashboardParticipations($userId, $currentTime);
        $salesRows = $model->getDashboardSales($userId, $currentTime);

        if ($participationRows === false || $salesRows === false) {
            return $this->failedDashboard();
        }

        $categories = $this->getDashboardCategories();
        $participationListings = $this->formatListings(
            $participationRows,
            $categories,
            $currentTime
        );
        $sales = $this->formatListings($salesRows, $categories, $currentTime);

        if ($participationListings === false || $sales === false) {
            return $this->failedDashboard();
        }

        $data = $this->partitionParticipations($participationListings, $userId);

        return [
            'sales' => $sales,
            'participations' => $data['participations'],
            'wins' => $data['wins'],
            'load_error' => false,
        ];
    }

    /**
     * Rôle : Charger les catégories du tableau de bord avec un repli simple en cas d'indisponibilité.
     * Paramètres : Aucun.
     * Retour : Catégories indexées ou tableau vide.
     */
    private function getDashboardCategories(): array
    {
        $categories = (new CategoryModel($this->database))->getAllCategories();

        if ($categories === null) {
            return [];
        }

        return $categories;
    }

    /**
     * Rôle : Construire l'état vide commun utilisé lorsque le tableau de bord ne peut pas être chargé.
     * Paramètres : Aucun.
     * Retour : Zones vides accompagnées de l'indicateur d'erreur.
     */
    private function failedDashboard(): array
    {
        return [
            'sales' => [],
            'participations' => [],
            'wins' => [],
            'load_error' => true,
        ];
    }

    /**
     * Rôle : Envoyer la réponse JSON commune lorsqu'une actualisation du tableau de bord échoue.
     * Paramètres : Aucun.
     * Retour : Aucun, la réponse JSON est envoyée.
     */
    private function respondDashboardUnavailable(): void
    {
        $this->json([
            'success' => false,
            'message' => 'Les dernières informations reçues restent affichées.',
        ]);
    }

    /**
     * Rôle : Enrichir les lignes de la base avec leurs photos et libellés d'affichage.
     * Paramètres : Lignes du tableau de bord, catégories de l'API et instant de référence.
     * Retour : Cartes limitées aux données utiles ou false en cas d'erreur SQL.
     */
    private function formatListings(
        array $rows,
        array $categories,
        DateTime $currentTime
    ): array|false
    {
        $ids = [];
        foreach ($rows as $row) {
            // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
            if (isset($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }

        $photos = (new PhotoModel($this->database))->getPrimaryPhotos($ids);

        if ($photos === false) {
            return false;
        }

        $listings = [];

        foreach ($rows as $row) {
            if (!isset(
                $row['id'],
                $row['titre'],
                $row['prix_depart'],
                $row['date_heure_fin'],
                $row['categorie_id']
            )) {
                continue;
            }
            $deadline = DateTime::createFromFormat('Y-m-d H:i:s', (string) $row['date_heure_fin']);
            if (!$deadline instanceof DateTime) {
                continue;
            }

            $id = (int) $row['id'];
            $currentAmountInEuros = (int) $row['prix_depart'];



            if (isset($row['best_bid'])) {
                $bestBidInEuros = (int) $row['best_bid'];



                $currentAmountInEuros = $bestBidInEuros;
            }

            $userBestBid = null;

            if (isset($row['user_best_bid'])) {
                $userBestBidInEuros = (int) $row['user_best_bid'];



                $userBestBid = $this->formatEuros($userBestBidInEuros);
            }

            $categoryId = (int) $row['categorie_id'];
            $categoryLabel = 'Catégorie indisponible';

            if (isset($categories[$categoryId])) {
                $categoryLabel = (string) $categories[$categoryId];
            }

            $listings[] = [
                'id' => $id,
                'title' => (string) $row['titre'],
                'category' => $categoryLabel,
                'current_price' => $this->formatEuros($currentAmountInEuros),
                'bid_count' => $this->readBidCount($row),
                'user_best_bid' => $userBestBid,
                'winner_id' => $this->readWinnerId($row),
                'is_active' => $deadline > $currentTime,
                'deadline' => $this->formatFrenchDateTime(
                    $deadline,
                    false,
                    false
                ),
                'deadline_date' => $this->formatFrenchDate(
                    $deadline,
                    false
                ),
                'photo_url' => $this->buildPhotoUrl($photos, $id),
                'detail_url' => $this->buildRouteUrl('listing_detail', ['id' => $id]),
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
        // NATIF PHP : is_numeric() vérifie qu’une valeur représente un nombre ; il protège ici la conversion ou le calcul qui suit.
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
            return $this->photoStorage->getPublicUrl($photos[$listingId]);
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
            $listing['is_current_winner'] = $listing['winner_id'] === $userId;

            if (!$listing['is_active'] && $listing['winner_id'] === $userId) {
                $wins[] = $listing;
            } else {
                $participations[] = $listing;
            }
        }
        return ['participations' => $participations, 'wins' => $wins];
    }

    /**
     * Rôle : Déterminer le message récapitulatif adapté aux données du tableau de bord.
     * Paramètres : Zones du tableau de bord déjà préparées par le contrôleur.
     * Retour : Variante, titre et contenu du message à afficher.
     */
    private function buildDashboardMessage(array $dashboard): array
    {
        $message = [
            'variant' => 'info',
            'title' => 'Votre activité en un coup d’œil',
            'body' => 'Les trois zones restent disponibles, même lorsqu’elles ne contiennent encore aucune annonce.',
        ];

        foreach ($dashboard['sales'] as $sale) {
            if ($sale['is_active']) {
                $message = [
                    'variant' => 'info',
                    'title' => 'Vente active',
                    'body' => 'Mes ventes actives sont actualisées automatiquement toutes les 10 secondes.',
                ];
            } else {
                $message = [
                    'variant' => 'success',
                    'title' => 'Ventes terminées',
                    'body' => 'Les résultats finaux sont conservés sans actualisation périodique.',
                ];
            }
        }

        if ($dashboard['participations'] !== []) {
            $participation = $dashboard['participations'][0];

            if (!$participation['is_active']) {
                $message = [
                    'variant' => 'error',
                    'title' => 'Vente terminée',
                    'body' => 'Cette enchère perdue reste visible dans votre historique, sans actualisation.',
                ];
            } elseif ($participation['user_best_bid'] === null) {
                $message = [
                    'variant' => 'info',
                    'title' => 'Annonce suivie',
                    'body' => 'Les annonces actives suivies sont actualisées automatiquement toutes les 2 secondes.',
                ];
            } elseif (!empty($participation['is_current_winner'])) {
                $message = [
                    'variant' => 'success',
                    'title' => 'Vous avez la meilleure enchère',
                    'body' => 'Cette annonce active est actualisée automatiquement toutes les 2 secondes.',
                ];
            } else {
                $message = [
                    'variant' => 'error',
                    'title' => 'Votre enchère a été dépassée',
                    'body' => 'Cette annonce active est actualisée automatiquement toutes les 2 secondes.',
                ];
            }
        }

        if ($dashboard['wins'] !== []) {
            return [
                'variant' => 'success',
                'title' => 'Enchère remportée',
                'body' => 'L’annonce apparaît uniquement dans la zone Enchères remportées.',
            ];
        }

        return $message;
    }

    /**
     * Rôle : Ajouter à chaque carte du tableau de bord les libellés liés à son état.
     * Paramètres : Zones de ventes, participations et enchères remportées déjà préparées.
     * Retour : Zones enrichies des données de présentation nécessaires au template.
     */
    private function addDashboardCardDisplay(array $dashboard): array
    {
        foreach (['sales', 'participations', 'wins'] as $zoneKey) {
            foreach ($dashboard[$zoneKey] as $index => $listing) {
                $dashboard[$zoneKey][$index]['display'] = $this->buildDashboardCardDisplay(
                    $listing,
                    $zoneKey
                );
            }
        }

        return $dashboard;
    }

    /**
     * Rôle : Déterminer les textes d'état d'une carte de tableau de bord.
     * Paramètres : Données d'une annonce et zone du tableau de bord qui la présente.
     * Retour : Libellés de date, d'état, d'actualisation et de lien de la carte.
     */
    private function buildDashboardCardDisplay(array $listing, string $zoneKey): array
    {
        $deadlinePrefix = 'Terminée le ';
        $deadlineSuffix = ' — Europe/Paris';
        $deadlineLabel = $listing['deadline'];
        $refreshLabel = '';
        $statusLabel = 'Vente terminée';
        $statusSymbol = '●';
        $detailLinkLabel = 'Voir l’annonce →';

        if ($listing['is_active']) {
            $deadlinePrefix = 'Se termine le ';
            $statusLabel = 'Vente active';
        }

        if ($zoneKey === 'sales' && !$listing['is_active']) {
            $deadlineSuffix = ' · Europe/Paris';
            $deadlineLabel = $listing['deadline_date'];
            $statusSymbol = '✓';
            $detailLinkLabel = 'Voir →';

            if ((int) $listing['bid_count'] > 0) {
                $statusLabel = 'Adjugée';
            } else {
                $statusLabel = 'Non adjugée';
            }
        }

        if ($zoneKey === 'participations') {
            $statusLabel = 'Enchère perdue — vente terminée';
            $statusSymbol = '×';

            if ($listing['is_active'] && $listing['user_best_bid'] === null) {
                $statusLabel = 'Annonce suivie';
                $statusSymbol = '○';
            } elseif ($listing['is_active'] && !empty($listing['is_current_winner'])) {
                $statusLabel = 'Meilleure enchère';
                $statusSymbol = '★';
            } elseif ($listing['is_active']) {
                $statusLabel = 'Enchère dépassée';
                $statusSymbol = '!';
            } else {
                $deadlinePrefix = 'Vente terminée le ';
            }
        }

        if ($zoneKey === 'wins') {
            $statusLabel = 'Enchère remportée';
            $statusSymbol = '✓';
            $deadlinePrefix = 'Vente terminée le ';
        }

        if ($listing['is_active']) {
            if ($zoneKey === 'sales') {
                $refreshLabel = ' · actualisation 10 s';
            } else {
                $refreshLabel = ' · actualisation 2 s';
            }
        }

        return [
            'deadline_prefix' => $deadlinePrefix,
            'deadline_suffix' => $deadlineSuffix,
            'deadline_label' => $deadlineLabel,
            'refresh_label' => $refreshLabel,
            'status_label' => $statusLabel,
            'status_symbol' => $statusSymbol,
            'detail_link_label' => $detailLinkLabel,
        ];
    }

}
