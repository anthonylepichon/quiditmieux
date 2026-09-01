<?php

/**
 * Description générale : Page publique de détail d'une annonce mise aux enchères.
 * Rôle : Afficher la vente, ses photographies, son prix et les actions adaptées aux droits du visiteur.
 * Tâches : Présenter les états public, vendeur, suiveur, enchérisseur et vente terminée sans exposer de donnée privée.
 * Liens avec les autres fichiers : Est affiché par ListingController.php, inséré dans base.php et complété par listing-detail.js.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$listing = $data['listing'];
$photos = $data['photos'];
$history = $data['history'];
$viewer = $data['viewer'];
$bidRejection = $data['bid_rejection'];
$csrfToken = $data['csrf_token'];
$isConnected = $viewer['is_connected'];
$currentPage = 'listing_detail';
$pageTitle = $listing['title'] . ' — QUIDITMIEUX';
$pageDescription = 'Consultez le détail de l’annonce ' . $listing['title'] . '.';
$pageScripts = ['public/assets/js/listing-detail.js'];
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
$bidCountLabel = (int) $listing['bid_count'] . ' enchère';

if ((int) $listing['bid_count'] > 1) {
    $bidCountLabel .= 's';
}

if ($listing['is_ended']) {
    $saleStatusLabel = 'Vente terminée';
    $finalResultTitle = 'Vente adjugée';
    $priceLabel = 'PRIX FINAL';

    if ($listing['final_state'] === 'Non adjugée') {
        $finalResultTitle = 'Vente non adjugée';
    }

    if ((int) $listing['bid_count'] === 0) {
        $historySubtitle = 'La vente s’est terminée sans enchère.';
        $showEmptyHistory = true;
    }
}

$participationLabel = $saleStatusLabel;

if ($listing['is_ended'] && (int) $listing['bid_count'] === 0) {
    $participationLabel = 'Non adjugée';
}

if ($viewer['is_best_bidder']) {
    $participationLabel = 'Meilleure enchère';
} elseif ($viewer['has_bid']) {
    $participationLabel = 'Enchère dépassée';
} elseif ($viewer['is_following']) {
    $participationLabel = 'Annonce suivie';
} elseif ($viewer['is_owner'] && !$listing['is_ended']) {
    $participationLabel = 'Votre vente active';
} elseif ($viewer['is_connected'] && !$listing['is_ended']) {
    $participationLabel = 'Annonce non suivie';
}
$followRoute = 'follow_listing';
$followLabel = 'Suivre';

if ($viewer['is_following']) {
    $followRoute = 'unfollow_listing';
    $followLabel = 'Ne plus suivre';
}

$hasBidAttribute = 'false';
$isBestBidderAttribute = 'false';

if ($viewer['has_bid']) {
    $hasBidAttribute = 'true';
}

if ($viewer['is_best_bidder']) {
    $isBestBidderAttribute = 'true';
}

if (!$listing['is_ended']) {
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
} elseif ((int) $listing['bid_count'] > 0) {
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
        $summaryMessage = 'Votre offre de ' . $listing['current_price_label'] . ' est la meilleure. Le résultat et l’historique restent consultables.';
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
}

$actionsClass = 'listing-summary__actions';

if ($listing['is_ended']) {
    $actionsClass .= ' listing-summary__actions--ended';
}
?>
<main class="listing-detail container">
        <a class="back-link visually-hidden" href="index.php?route=home#annonces">Retour aux annonces</a>
        <?php if ($data['flash_success'] !== null): ?><div class="alert alert--success" role="status"><?= htmlspecialchars($data['flash_success'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($data['flash_notice'] !== null): ?><div class="alert alert--warning" role="status"><?= htmlspecialchars($data['flash_notice'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <header class="listing-detail__heading">
            <p class="eyebrow"><?= htmlspecialchars($listing['category'], ENT_QUOTES, 'UTF-8') ?> · <?= $saleStatusLabel ?></p>
            <h1 id="listing-title"><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Vendu par <?= htmlspecialchars($listing['seller'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($listing['item_state'], ENT_QUOTES, 'UTF-8') ?> · <?= $bidCountLabel ?> · Échéance : <?= htmlspecialchars($listing['deadline_label'], ENT_QUOTES, 'UTF-8') ?> — Europe/Paris</p>
        </header>

        <section class="listing-detail__hero" aria-labelledby="listing-title">
            <div class="listing-gallery glass-panel" data-carousel data-carousel-interval="5000" role="region" aria-label="Photographies de l’annonce" aria-roledescription="carrousel">
                <?php if ($photos !== []): ?>
                    <div class="listing-gallery__viewport">
                        <img class="listing-gallery__main" data-carousel-image src="<?= htmlspecialchars($photos[0]['url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie 1 de <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (count($photos) > 1): ?>
                            <button class="listing-gallery__arrow listing-gallery__arrow--previous" type="button" data-carousel-previous aria-label="Afficher la photographie précédente"><span aria-hidden="true">←</span></button>
                            <button class="listing-gallery__toggle" type="button" data-carousel-toggle>Pause</button>
                            <button class="listing-gallery__arrow listing-gallery__arrow--next" type="button" data-carousel-next aria-label="Afficher la photographie suivante"><span aria-hidden="true">→</span></button>
                        <?php endif; ?>
                    </div>
                    <?php if (count($photos) > 1): ?>
                        <p class="visually-hidden" data-carousel-status aria-live="off">Photographie 1 sur <?= count($photos) ?></p>
                        <div class="listing-gallery__thumbnails" aria-label="Choisir une photographie">
                            <?php foreach ($photos as $index => $photo): ?>
                                <button class="listing-gallery__thumbnail<?php if ($index === 0): ?> is-active<?php endif; ?>" type="button" data-carousel-thumbnail data-carousel-index="<?= $index ?>" data-carousel-src="<?= htmlspecialchars($photo['url'], ENT_QUOTES, 'UTF-8') ?>" data-carousel-alt="Photographie <?= $index + 1 ?> de <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Afficher la photographie <?= $index + 1 ?> sur <?= count($photos) ?>"<?php if ($index === 0): ?> aria-current="true"<?php endif; ?>>
                                    <img src="<?= htmlspecialchars($photo['url'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="listing-gallery__empty"><img src="public/assets/images/illustrations/shopping-cart.png" alt="Aucune photographie disponible"></div>
                <?php endif; ?>
                <div class="listing-gallery__description">
                    <h2 id="description-title">Description</h2>
                    <p><?= nl2br(htmlspecialchars($listing['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <p class="listing-gallery__metadata">État : <?= htmlspecialchars($listing['item_state'], ENT_QUOTES, 'UTF-8') ?> · Catégorie : <?= htmlspecialchars($listing['category'], ENT_QUOTES, 'UTF-8') ?> · Vendeur : <?= htmlspecialchars($listing['seller'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <div class="listing-summary glass-panel">
                <?php if ($listing['is_ended']): ?>
                    <p class="status-badge status-badge--ended"><?= htmlspecialchars($participationLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <p class="status-badge" data-participation-badge data-default-label="<?php if ($viewer['is_connected'] && !$viewer['is_owner']): ?>Annonce non suivie<?php else: ?><?= htmlspecialchars($saleStatusLabel, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>" data-has-bid="<?= $hasBidAttribute ?>" data-is-best-bidder="<?= $isBestBidderAttribute ?>"><?= htmlspecialchars($participationLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <div class="listing-summary__price-copy"><span><?= $priceLabel ?></span><strong class="listing-summary__price" data-current-price><?= htmlspecialchars($listing['current_price_label'], ENT_QUOTES, 'UTF-8') ?></strong><p><span data-bid-count><?= (int) $listing['bid_count'] ?></span> <span data-bid-count-label><?php if ((int) $listing['bid_count'] > 1): ?>enchères<?php else: ?>enchère<?php endif; ?></span></p></div>
                <img class="listing-summary__asset" src="public/assets/images/illustrations/coin-vault.png" alt="" width="88" height="123">

                <?php if ($listing['is_ended']): ?>
                    <div class="listing-summary__final-result">
                        <strong><?= htmlspecialchars($finalResultTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                        <p>Vente terminée le <?= htmlspecialchars($listing['deadline_label'], ENT_QUOTES, 'UTF-8') ?> — Europe/Paris</p>
                    </div>
                <?php else: ?>
                    <div class="hero-countdown listing-summary__countdown" data-countdown data-deadline-utc="<?= htmlspecialchars($listing['deadline_utc'], ENT_QUOTES, 'UTF-8') ?>">
                        <p>Cette vente se termine dans</p>
                        <div class="hero-countdown__values" data-countdown-values><span>--<small>JOURS</small></span><span>--<small>HEURES</small></span><span>--<small>MINUTES</small></span><span>--<small>SECONDES</small></span></div>
                    </div>
                <?php endif; ?>

                <?php if (!$viewer['is_connected'] && !$listing['is_ended']): ?><p class="listing-summary__invitation">Connectez-vous pour suivre cette annonce ou enchérir.</p><?php endif; ?>
                <?php if ($summaryMessage !== ''): ?><p class="listing-summary__owner-copy"><?= htmlspecialchars($summaryMessage, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                <div class="<?= $actionsClass ?>" data-listing-actions>
                    <?php if ($listing['is_ended']): ?>
                        <p class="alert alert--warning alert--illustrated"><strong><?= htmlspecialchars($endedAlertTitle, ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars($endedMessage, ENT_QUOTES, 'UTF-8') ?></span></p>
                    <?php elseif ($viewer['can_edit']): ?>
                        <a class="button button--secondary" href="index.php?route=listing_edit_form&id=<?= (int) $listing['id'] ?>">Modifier l’annonce</a>
                        <form action="index.php?route=listing_delete" method="post"><input type="hidden" name="id" value="<?= (int) $listing['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button class="button button--danger" type="submit">Supprimer l’annonce</button></form>
                    <?php elseif ($viewer['is_owner']): ?>
                        <p class="alert alert--warning">Une enchère a été enregistrée : modification et suppression impossibles.</p>
                        <button class="button button--secondary" type="button" disabled>Modifier</button>
                        <button class="button button--danger" type="button" disabled>Supprimer</button>
                    <?php elseif (!$viewer['is_connected'] && !$listing['is_ended']): ?>
                        <a class="button button--secondary" href="index.php?route=login_form">Se connecter</a>
                        <a class="button button--primary" href="index.php?route=register_form">Créer un compte</a>
                    <?php elseif ($viewer['can_follow']): ?>
                        <form action="index.php?route=<?= $followRoute ?>" method="post" data-follow-form><input type="hidden" name="id" value="<?= (int) $listing['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button class="button button--secondary" type="submit"><?= $followLabel ?></button></form>
                    <?php endif; ?>
                </div>
                <p class="form-field__help" data-participation-status role="status"></p>

                <?php if ($viewer['can_bid']): ?>
                    <form class="bid-form" action="index.php?route=place_bid" method="post" data-bid-form>
                        <input type="hidden" name="id" value="<?= (int) $listing['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <label class="form-field__label" for="bid-amount">Votre enchère</label>
                        <div class="bid-form__row"><input class="form-control" id="bid-amount" name="amount" type="number" min="<?= htmlspecialchars($listing['minimum_bid'], ENT_QUOTES, 'UTF-8') ?>" max="99999999" step="1" inputmode="numeric" placeholder="<?= htmlspecialchars($listing['minimum_bid_label'], ENT_QUOTES, 'UTF-8') ?> minimum"<?php if ($bidRejection !== null): ?> value="<?= htmlspecialchars($bidRejection['amount'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?> required><button class="button button--primary" type="submit">Enchérir</button></div>
                        <p class="form-field__help" data-minimum-bid><?php if ($bidRejection !== null): ?>Montant insuffisant : minimum <?= htmlspecialchars($bidRejection['minimum_label'], ENT_QUOTES, 'UTF-8') ?>.<?php else: ?>Montant supérieur d’au moins 1 € au prix courant.<?php endif; ?></p>
                        <p class="form-field__help">Une enchère est définitive.</p>
                        <p class="form-field__help" data-bid-status role="status"><?php if ($bidRejection !== null): ?>Enchère refusée : saisissez au minimum <?= htmlspecialchars($bidRejection['minimum_label'], ENT_QUOTES, 'UTF-8') ?>.<?php endif; ?></p>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="bid-history glass-panel" aria-labelledby="history-title"><h2 id="history-title"><?= htmlspecialchars($historyTitle, ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($historySubtitle, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($showEmptyHistory): ?>
                <div class="bid-history__empty"><img src="public/assets/images/illustrations/shopping-cart.png" alt="" width="110" height="110"><strong>Aucune enchère enregistrée</strong><p>L’historique apparaîtra ici dès qu’une première enchère valide aura été enregistrée.</p></div>
            <?php elseif ($viewer['can_view_history']): ?>
                <div class="bid-history__content"><?php if ($history === []): ?><p>Aucune enchère enregistrée</p><?php else: ?><ul><?php foreach ($history as $bid): ?><li><strong><?= htmlspecialchars($bid['amount'], ENT_QUOTES, 'UTF-8') ?></strong> par <?= htmlspecialchars($bid['bidder'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($bid['date'], ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul><?php endif; ?></div>
            <?php else: ?>
                <div class="bid-history__locked"><img src="public/assets/images/icons/feature-icon-03.svg" alt="" width="70" height="70"><div><h3><?= htmlspecialchars($historyLockedTitle, ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($historyLockedBody, ENT_QUOTES, 'UTF-8') ?></p></div></div>
            <?php endif; ?>
        </section>
</main>
