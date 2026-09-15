<?php

/**
 * Description générale : Contrôleur du détail d'une annonce proposée aux enchères.
 * Rôle : Afficher une annonce et préparer les informations utiles à sa consultation. Les données transmises à l'étape suivante ont ainsi une forme cohérente et cette préparation n'est pas répétée ailleurs.
 * Tâches : Charger l'annonce, ses photographies, ses enchères et son état de suivi, puis préparer la vue de détail.
 * Liens avec les autres fichiers : Étend Controller.php et utilise les modèles liés au détail ainsi que PhotoStorage.php.
 */

namespace App\controllers;

use App\core\Controller;
use App\core\Database;
use App\core\Session;
use App\services\PhotoStorage;
use App\models\BidModel;
use App\models\CategoryModel;
use App\models\FollowModel;
use App\models\ListingModel;
use App\models\PhotoModel;
// NATIF PHP : DateTime est la classe native de gestion des dates et des heures ; elle permet ici de comparer l'échéance et de la formater.
use DateTime;

class ListingDetailController extends Controller
{
    // ====================
    // ATTRIBUTS
    // ====================

    private PhotoStorage $photoStorage;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Conserver les dépendances communes et préparer le gestionnaire des fichiers photographiques. Cela évite une référence sans fichier, un fichier orphelin ou l'association d'une photographie à la mauvaise annonce.
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
     * Rôle : Afficher le détail complet d'une annonce selon son état et les droits du visiteur. Le traitement est ainsi interrompu avant qu'un utilisateur non autorisé puisse consulter ou modifier la ressource.
     * Paramètres : Aucun, l'identifiant est lu dans la requête GET.
     * Retour : Aucun, le template de détail ou une redirection sûre est envoyé.
     */
    public function showDetail(): void
    {
        // L'instant de référence est partagé par tous les calculs d'état de l'annonce affichée.
        $currentTime = new DateTime();
        // L'identifiant est contrôlé avant tout accès à l'annonce ou à ses informations associées.
        $listingId = $this->readPositiveGetIdentifier('id');

        if ($listingId === null) {
            $this->session->setFlashMessage('notice', 'L’annonce demandée est introuvable.');
            $this->redirect('home');
        }

        // Le modèle lit les données métier ; le contrôleur choisit seulement la réponse appropriée.
        $listingModel = new ListingModel($this->database);
        $listing = $listingModel->getDetail($listingId);

        if ($listing === false) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('home');
        }

        if ($listing === null) {
            $this->session->setFlashMessage('notice', 'L’annonce demandée est introuvable.');
            $this->redirect('home');
        }

        $categoryLabel = (new CategoryModel())->getCategoryLabel((int) $listing['categorie_id']);

        if ($categoryLabel === null) {
            $categoryLabel = 'Catégorie indisponible';
        }

        $bidModel = new BidModel($this->database);
        $photoModel = new PhotoModel($this->database);
        $followModel = new FollowModel($this->database);
        $summary = $bidModel->getSummary($listingId);

        if ($summary === false) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('home');
        }

        $viewerId = $this->session->getConnectedUserId();
        $isOwner = $viewerId !== null && $viewerId === (int) $listing['utilisateur_id'];
        $viewerHasBid = false;
        $isFollowing = false;

        if ($viewerId !== null && !$isOwner) {
            $viewerHasBid = $bidModel->userHasBid($listingId, $viewerId);
            $isFollowing = $followModel->isFollowing($viewerId, $listingId);

            if ($viewerHasBid === null || $isFollowing === null) {
                $this->session->setFlashMessage(
                    'notice',
                    'Les données de cette annonce sont momentanément indisponibles.'
                );
                $this->redirect('home');
            }
        }

        $deadline = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            (string) $listing['date_heure_fin']
        );

        if (!$deadline instanceof DateTime) {
            $this->session->setFlashMessage('notice', 'Cette annonce ne peut pas être affichée.');
            $this->redirect('home');
        }

        $isEnded = $deadline <= $currentTime;
        $currentAmountInEuros = (int) $listing['prix_depart'];



        if ($summary['best_bid_in_euros'] !== null) {
            $currentAmountInEuros = $summary['best_bid_in_euros'];
        }

        $history = [];

        if ($isOwner || $viewerHasBid) {
            $historyRows = $bidModel->getHistory($listingId);

            if ($historyRows === false) {
                $this->session->setFlashMessage(
                    'notice',
                    'Les données de cette annonce sont momentanément indisponibles.'
                );
                $this->redirect('home');
            }

            $history = $this->formatBidHistory($historyRows);
        }

        $photos = [];
        $photoRows = $photoModel->getListingPhotos($listingId);

        if ($photoRows === false) {
            $this->session->setFlashMessage(
                'notice',
                'Les données de cette annonce sont momentanément indisponibles.'
            );
            $this->redirect('home');
        }

        foreach ($photoRows as $photo) {
            $photo['url'] = $this->photoStorage->getPublicUrl($photo['filename']);
            $photos[] = $photo;
        }

        $bidRejection = $this->recoverBidRejection($listingId);
        $canEdit = false;
        $canParticipate = false;

        if ($viewerId !== null) {
            $canEdit = $listingModel->canBeModifiedBy($listingId, $viewerId, $currentTime);
            $canParticipate = $listingModel->canReceiveParticipationFrom(
                $listingId,
                $viewerId,
                $currentTime
            );

            if ($canEdit === null || $canParticipate === null) {
                $this->session->setFlashMessage(
                    'notice',
                    'Les données de cette annonce sont momentanément indisponibles.'
                );
                $this->redirect('home');
            }
        }

        $this->render('pages/listing-detail.php', [
            'listing' => [
                'id' => $listingId,
                'title' => (string) $listing['titre'],
                'description' => (string) $listing['description'],
                'item_state' => (string) $listing['etat_objet'],
                'category' => $categoryLabel,
                'seller' => (string) $listing['seller_pseudo'],
                'seller_id' => (int) $listing['utilisateur_id'],
                'current_price_label' => $this->formatEuros($currentAmountInEuros),
                'minimum_bid' => $currentAmountInEuros + 1,
                'minimum_bid_label' => $this->formatEuros($currentAmountInEuros + 1),
                'bid_count' => (int) $summary['bid_count'],
                // NATIF PHP : DATE_ATOM désigne le format de date ISO 8601 ; elle prépare ici une date interprétable sans ambiguïté par JavaScript.
                'deadline' => $deadline->format(DATE_ATOM),
                'deadline_label' => $this->formatFrenchDateTime(
                    $deadline,
                    true,
                    false
                ),
                'is_ended' => $isEnded,
                'final_state' => $this->determineFinalState($isEnded, (int) $summary['bid_count']),
            ],
            'photos' => $photos,
            'history' => $history,
            'bid_rejection' => $bidRejection,
            'viewer' => [
                'is_connected' => $viewerId !== null,
                'is_owner' => $isOwner,
                'has_bid' => $viewerHasBid,
                'is_best_bidder' => $viewerId !== null && $viewerId === $summary['best_bidder_id'],
                'is_following' => $isFollowing,
                'can_edit' => $canEdit === true,
                'can_follow' => $canParticipate === true,
                'can_bid' => $canParticipate === true,
                'can_view_history' => $isOwner || $viewerHasBid,
            ],
            'display' => $this->buildDetailDisplay(
                $isEnded,
                (int) $summary['bid_count'],
                $this->determineFinalState($isEnded, (int) $summary['bid_count']),
                [
                    'is_connected' => $viewerId !== null,
                    'is_owner' => $isOwner,
                    'has_bid' => $viewerHasBid,
                    'is_best_bidder' => $viewerId !== null && $viewerId === $summary['best_bidder_id'],
                    'is_following' => $isFollowing,
                    'can_edit' => $canEdit === true,
                    'can_bid' => $canParticipate === true,
                ],
                $bidRejection,
                $this->formatEuros($currentAmountInEuros)
            ),
            'csrf_token' => $this->session->getCsrfToken(),
            'flash_success' => $this->session->getFlashMessage('success'),
            'flash_notice' => $this->session->getFlashMessage('notice'),
        ]);
    }

    /**
     * Rôle : Récupérer l’état temporaire d’une enchère refusée pour la seule annonce concernée. La saisie et son explication sont ainsi réaffichées uniquement sur le bon détail, puis supprimées de la session.
     * Paramètres : Identifiant de l’annonce affichée.
     * Retour : Montant saisi et minimum formatés, ou null si aucun refus ne correspond.
     */
    private function recoverBidRejection(int $listingId): ?array
    {
        $encodedData = $this->session->getFlashMessage('bid_rejection');

        if ($encodedData === null) {
            return null;
        }

        // NATIF PHP : json_decode() convertit un texte JSON en donnée PHP ; il permet ici d’exploiter la réponse reçue.
        $data = json_decode($encodedData, true);

        if (
            !is_array($data)
            || !isset($data['listing_id'], $data['minimum_in_euros'], $data['amount_in_euros'])
            || (int) $data['listing_id'] !== $listingId
            // NATIF PHP : is_int() vérifie qu’une valeur est un entier ; il évite ici d’utiliser un autre type dans un traitement numérique.
            || !is_int($data['minimum_in_euros'])
            || !is_int($data['amount_in_euros'])
        ) {
            return null;
        }

        return [
            'minimum' => $data['minimum_in_euros'],
            'minimum_label' => $this->formatEuros($data['minimum_in_euros']),
            'amount' => $data['amount_in_euros'],
            'amount_label' => $this->formatEuros($data['amount_in_euros']),
        ];
    }

    /**
     * Rôle : Convertir l'historique brut en informations limitées et affichables en heure de Paris. La comparaison et l'enregistrement utilisent ainsi une valeur temporelle cohérente, sans décaler la fin d'une vente.
     * Paramètres : Lignes d'enchères à formater.
     * Retour : Historique formaté et sûr pour le template.
     */
    private function formatBidHistory(array $rows): array
    {
        $history = [];

        foreach ($rows as $row) {
            if (!isset($row['pseudo'], $row['montant'], $row['date_heure_enchere'])) {
                continue;
            }

            $date = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                (string) $row['date_heure_enchere']
            );

            if (!$date instanceof DateTime) {
                continue;
            }

            $amountInEuros = (int) $row['montant'];



            $history[] = [
                'bidder' => (string) $row['pseudo'],
                'amount' => $this->formatEuros($amountInEuros),
                'date' => $this->formatFrenchDateTime(
                    $date,
                    true,
                    false
                ),
            ];
        }

        return $history;
    }

    /**
     * Rôle : Déterminer le libellé final public d'une vente terminée. Les données transmises à l'étape suivante ont ainsi une forme cohérente et cette préparation n'est pas répétée ailleurs.
     * Paramètres : Indication de fin et nombre d'enchères.
     * Retour : Libellé final ou chaîne vide tant que la vente est active.
     */
    private function determineFinalState(bool $isEnded, int $bidCount): string
    {
        if (!$isEnded) {
            return '';
        }

        if ($bidCount > 0) {
            return 'Adjugée';
        }

        return 'Non adjugée';
    }

    /**
     * Rôle : Préparer les libellés et messages du détail d'une annonce selon son état et les droits du visiteur. Le traitement est ainsi interrompu avant qu'un utilisateur non autorisé puisse consulter ou modifier la ressource.
     * Paramètres : État de la vente, nombre d'enchères, résultat final, droits du visiteur, refus éventuel et prix affiché.
     * Retour : Données d'affichage prêtes à présenter dans le template de détail.
     */
    private function buildDetailDisplay(
        bool $isEnded,
        int $bidCount,
        string $finalState,
        array $viewer,
        ?array $bidRejection,
        string $currentPriceLabel
    ): array {
        $saleStatusLabel = 'Vente en cours';
        $finalResultTitle = '';
        $priceLabel = 'PRIX COURANT';
        $historyTitle = 'Historique des enchères';
        $historySubtitle = 'Les informations détaillées sont réservées aux utilisateurs autorisés.';
        $historyLockedTitle = 'Historique détaillé non accessible dans cette vue';
        $historyLockedBody = 'Les informations publiques restent disponibles : prix, nombre d’enchères et résultat final.';
        $summaryMessage = '';
        $endedAlertTitle = 'Vente terminée';
        $endedMessage = 'Aucune action de participation ou de modification n’est disponible.';
        $showEmptyHistory = false;
        $visitorInvitation = '';
        $ownerLockedMessage = 'Une enchère a été enregistrée : modification et suppression impossibles.';
        $bidMinimumHelp = 'Montant supérieur d’au moins 1 € au prix courant.';
        $bidStatusMessage = '';

        if ($isEnded) {
            $saleStatusLabel = 'Vente terminée';
            $finalResultTitle = 'Vente adjugée';
            $priceLabel = 'PRIX FINAL';

            if ($finalState === 'Non adjugée') {
                $finalResultTitle = 'Vente non adjugée';
            }

            if ($bidCount === 0) {
                $historySubtitle = 'La vente s’est terminée sans enchère.';
                $showEmptyHistory = true;
            }
        }

        $participationLabel = $saleStatusLabel;

        if ($isEnded && $bidCount === 0) {
            $participationLabel = 'Non adjugée';
        }

        if ($viewer['is_best_bidder']) {
            $participationLabel = 'Meilleure enchère';
        } elseif ($viewer['has_bid']) {
            $participationLabel = 'Enchère dépassée';
        } elseif ($viewer['is_following']) {
            $participationLabel = 'Annonce suivie';
        } elseif ($viewer['is_owner'] && !$isEnded) {
            $participationLabel = 'Votre vente active';
        } elseif ($viewer['is_connected'] && !$isEnded) {
            $participationLabel = 'Annonce non suivie';
        }

        $followRoute = 'follow_listing';
        $followLabel = 'Suivre';

        if ($viewer['is_following']) {
            $followRoute = 'unfollow_listing';
            $followLabel = 'Ne plus suivre';
        }

        if (!$isEnded) {
            if ($viewer['can_edit']) {
                $summaryMessage = 'Aucune enchère enregistrée : vos actions restent disponibles.';
                $historySubtitle = 'Aucune enchère n’a encore été enregistrée.';
                $showEmptyHistory = true;
            } elseif ($viewer['is_owner']) {
                $participationLabel = 'Actions verrouillées';
                $summaryMessage = 'Votre annonce reste visible jusqu’à l’échéance. Les actions d’édition sont définitivement bloquées.';
                $historyTitle = 'Historique détaillé des enchères';
                $historySubtitle = 'La première enchère verrouille modification et suppression.';
            } elseif ($viewer['is_best_bidder']) {
                $summaryMessage = 'Vous êtes actuellement le mieux-disant. Vous pouvez enchérir de nouveau si nécessaire.';
                $historyTitle = 'Historique détaillé des enchères';
                $historySubtitle = 'Pseudo, montant, date et heure — Europe/Paris.';
            } elseif ($viewer['has_bid']) {
                $summaryMessage = 'Votre meilleure offre n’est plus en tête. Le minimum actuel est indiqué dans le formulaire.';
                $historyTitle = 'Historique détaillé des enchères';
                $historySubtitle = 'Une offre supérieure a été enregistrée.';
            } elseif ($viewer['is_connected']) {
                $historySubtitle = 'Vous n’avez pas encore enchéri sur cette annonce.';
                $historyLockedTitle = 'Historique accessible après votre première enchère';

                if ($viewer['is_following']) {
                    $historyLockedBody = 'Vous pouvez continuer à suivre l’annonce et enchérir.';
                } else {
                    $historyLockedBody = 'Vous ne suivez pas encore cette annonce. Suivez-la pour la retrouver dans votre tableau de bord.';
                }
            }
        } elseif ($bidCount > 0) {
            $historyTitle = 'Historique final des enchères';

            if ($viewer['is_owner']) {
                $participationLabel = 'Vente adjugée';
                $finalResultTitle = 'Un gagnant a été désigné';
                $summaryMessage = 'La vente est terminée et adjugée. Aucune action transactionnelle n’est ajoutée ici.';
                $endedAlertTitle = 'Vente adjugée';
                $endedMessage = 'Le résultat final et l’historique restent consultables.';
                $historySubtitle = 'L’enchère gagnante est identifiée dans l’historique.';
            } elseif ($viewer['is_best_bidder']) {
                $participationLabel = 'Enchère remportée';
                $finalResultTitle = 'Vous remportez cette enchère';
                $summaryMessage = 'Votre offre de ' . $currentPriceLabel . ' est la meilleure. Le résultat et l’historique restent consultables.';
                $endedAlertTitle = 'Enchère remportée';
                $endedMessage = 'Votre offre est identifiée dans l’historique final.';
                $historySubtitle = 'Votre enchère gagnante est mise en évidence.';
            } elseif ($viewer['has_bid']) {
                $participationLabel = 'Enchère non remportée';
                $finalResultTitle = 'Votre enchère n’a pas gagné';
                $summaryMessage = 'Votre meilleure offre n’a pas remporté la vente. La vente est désormais terminée.';
                $endedMessage = 'Votre enchère n’a pas remporté cette vente.';
                $historySubtitle = 'Votre meilleure offre et l’enchère gagnante restent visibles.';
            } else {
                $participationLabel = 'Vente adjugée';
                $finalResultTitle = 'Vente terminée — adjugée';
                $endedAlertTitle = 'Vente adjugée';
                $endedMessage = 'Le prix final et le nombre d’enchères sont publics.';
                $historyTitle = 'Historique des enchères';
                $historySubtitle = 'Le détail de l’historique n’est pas accessible dans cette vue.';
            }
        }

        if ($bidRejection !== null && $viewer['can_bid']) {
            $participationLabel = 'Enchère refusée';
            $historySubtitle = 'L’offre de ' . $bidRejection['amount_label'] . ' n’a pas été enregistrée.';
            $historyLockedBody = 'Aucune enchère valide n’a été enregistrée. Corrigez le montant puis réessayez.';
            $bidMinimumHelp = 'Montant insuffisant : minimum ' . $bidRejection['minimum_label'] . '.';
            $bidStatusMessage = 'Enchère refusée : saisissez au minimum ' . $bidRejection['minimum_label'] . '.';
        }

        if (!$viewer['is_connected'] && !$isEnded) {
            $visitorInvitation = 'Connectez-vous pour suivre cette annonce ou enchérir.';
        }

        return [
            'sale_status_label' => $saleStatusLabel,
            'final_result_title' => $finalResultTitle,
            'price_label' => $priceLabel,
            'history_title' => $historyTitle,
            'history_subtitle' => $historySubtitle,
            'history_locked_title' => $historyLockedTitle,
            'history_locked_body' => $historyLockedBody,
            'summary_message' => $summaryMessage,
            'ended_alert_title' => $endedAlertTitle,
            'ended_message' => $endedMessage,
            'show_empty_history' => $showEmptyHistory,
            'bid_count_label' => $bidCountLabel,
            'participation_label' => $participationLabel,
            'follow_route' => $followRoute,
            'follow_label' => $followLabel,
            'has_bid_attribute' => $hasBidAttribute,
            'is_best_bidder_attribute' => $isBestBidderAttribute,
            'actions_class' => $actionsClass,
            'default_participation_label' => $defaultParticipationLabel,
            'visitor_invitation' => $visitorInvitation,
            'owner_locked_message' => $ownerLockedMessage,
            'bid_minimum_help' => $bidMinimumHelp,
            'bid_status_message' => $bidStatusMessage,
        ];
    }

}
