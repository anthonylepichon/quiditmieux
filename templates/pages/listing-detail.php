<?php

/**
 * Description générale : Page publique de détail d'une annonce mise aux enchères.
 * Rôle : Afficher la vente, ses photographies, son prix et les actions adaptées aux droits du visiteur.
 * Tâches : Présenter les états public, vendeur, suiveur, enchérisseur et vente terminée sans exposer de donnée privée.
 * Liens avec les autres fichiers : Est affiché par ListingDetailController.php, inséré dans base.php et complété par listing-detail.js.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$listing = $data['listing'];
$photos = $data['photos'];
$history = $data['history'];
$viewer = $data['viewer'];
$bidRejection = $data['bid_rejection'];
$display = $data['display'];
$csrfToken = $data['csrf_token'];
$flashSuccess = $data['flash_success'];
$flashNotice = $data['flash_notice'];
$isConnected = $viewer['is_connected'];
$currentPage = 'listing_detail';
$pageTitle = $listing['title'] . ' — QUIDITMIEUX';
$pageDescription = 'Consultez le détail de l’annonce ' . $listing['title'] . '.';
$pageScripts = ['public/assets/js/listing-detail.js'];
$saleStatusLabel = $display['sale_status_label'];
$finalResultTitle = $display['final_result_title'];
$priceLabel = $display['price_label'];
$historyTitle = $display['history_title'];
$historySubtitle = $display['history_subtitle'];
$historyLockedTitle = $display['history_locked_title'];
$historyLockedBody = $display['history_locked_body'];
$summaryMessage = $display['summary_message'];
$endedAlertTitle = $display['ended_alert_title'];
$endedMessage = $display['ended_message'];
$showEmptyHistory = $display['show_empty_history'];
$bidCountLabel = $display['bid_count_label'];
$participationLabel = $display['participation_label'];
$followRoute = $display['follow_route'];
$followLabel = $display['follow_label'];
$hasBidAttribute = $display['has_bid_attribute'];
$isBestBidderAttribute = $display['is_best_bidder_attribute'];
$actionsClass = $display['actions_class'];
?>
<main class="listing-detail container">
    <a class="back-link visually-hidden" href="index.php?route=home#annonces">Retour aux annonces</a>
    <?php
    // Fragment flash-messages.php : messages temporaires de l'annonce préparés par ListingDetailController.
    require __DIR__ . '/../fragments/flash-messages.php';
    ?>

    <header class="listing-detail__heading">
        <p class="eyebrow"><?= htmlspecialchars($listing['category'], ENT_QUOTES, 'UTF-8') ?> · <?= $saleStatusLabel ?></p>
        <h1 id="listing-title"><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p>Vendu par <?= htmlspecialchars($listing['seller'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($listing['item_state'], ENT_QUOTES, 'UTF-8') ?> · <?= $bidCountLabel ?> · Échéance : <?= htmlspecialchars($listing['deadline_label'], ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <section class="listing-detail__hero" aria-labelledby="listing-title">
        <div class="listing-gallery glass-panel" data-carousel data-carousel-interval="5000" role="region" aria-label="Photographies de l’annonce" aria-roledescription="carrousel">
            <?php if ($photos !== []): ?>
                <div class="listing-gallery__viewport">
                    <img class="listing-gallery__main" data-carousel-image src="<?= htmlspecialchars($photos[0]['url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie 1 de <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php // NATIF PHP : count() compte les éléments d’un tableau ; il permet ici de connaître la quantité avant le traitement. ?>
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
                <div class="listing-gallery__empty">
                    <img src="public/assets/images/illustrations/shopping-cart.png" alt="Aucune photographie disponible">
                </div>
            <?php endif; ?>
            <div class="listing-gallery__description">
                <h2 id="description-title">Description</h2>
                <?php // NATIF PHP : nl2br() convertit les retours à la ligne en balises HTML ; il conserve ici la mise en forme de la description. ?>
                <p><?= nl2br(htmlspecialchars($listing['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                <p class="listing-gallery__metadata">État : <?= htmlspecialchars($listing['item_state'], ENT_QUOTES, 'UTF-8') ?> · Catégorie : <?= htmlspecialchars($listing['category'], ENT_QUOTES, 'UTF-8') ?> · Vendeur : <?= htmlspecialchars($listing['seller'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <div class="listing-summary glass-panel">
            <?php if ($listing['is_ended']): ?>
                <p class="status-badge status-badge--ended"><?= htmlspecialchars($participationLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <p class="status-badge" data-participation-badge data-default-label="<?= htmlspecialchars($display['default_participation_label'], ENT_QUOTES, 'UTF-8') ?>" data-has-bid="<?= $hasBidAttribute ?>" data-is-best-bidder="<?= $isBestBidderAttribute ?>"><?= htmlspecialchars($participationLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <div class="listing-summary__price-copy">
                <span><?= $priceLabel ?></span>
                <strong class="listing-summary__price" data-current-price>
                    <?= htmlspecialchars($listing['current_price_label'], ENT_QUOTES, 'UTF-8') ?>
                </strong>
                <p>
                    <span data-bid-count><?= (int) $listing['bid_count'] ?></span>
                    <span data-bid-count-label>
                        <?php if ((int) $listing['bid_count'] > 1): ?>
                            enchères
                        <?php else: ?>
                            enchère
                        <?php endif; ?>
                    </span>
                </p>
            </div>
            <img class="listing-summary__asset" src="public/assets/images/illustrations/coin-vault.png" alt="" width="88" height="123">

            <?php if ($listing['is_ended']): ?>
                <div class="listing-summary__final-result">
                    <strong><?= htmlspecialchars($finalResultTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                    <p>Vente terminée le <?= htmlspecialchars($listing['deadline_label'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php else: ?>
                <div class="hero-countdown listing-summary__countdown" data-countdown data-deadline="<?= htmlspecialchars($listing['deadline'], ENT_QUOTES, 'UTF-8') ?>">
                    <p>Cette vente se termine dans</p>
                    <div class="hero-countdown__values" data-countdown-values>
                        <span>--<small>JOURS</small></span>
                        <span>--<small>HEURES</small></span>
                        <span>--<small>MINUTES</small></span>
                        <span>--<small>SECONDES</small></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$viewer['is_connected'] && !$listing['is_ended']): ?>
                <p class="listing-summary__invitation"><?= htmlspecialchars($display['visitor_invitation'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if ($summaryMessage !== ''): ?>
                <p class="listing-summary__owner-copy"><?= htmlspecialchars($summaryMessage, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <div class="<?= $actionsClass ?>" data-listing-actions>
                <?php if ($listing['is_ended']): ?>
                    <p class="alert alert--warning alert--illustrated">
                        <strong><?= htmlspecialchars($endedAlertTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($endedMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                <?php elseif ($viewer['can_edit']): ?>
                    <a class="button button--secondary" href="index.php?route=listing_edit_form&id=<?= (int) $listing['id'] ?>">Modifier l’annonce</a>
                    <form action="index.php?route=listing_delete" method="post">
                        <input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="button button--danger" type="submit">Supprimer l’annonce</button>
                    </form>
                <?php elseif ($viewer['is_owner']): ?>
                    <p class="alert alert--warning"><?= htmlspecialchars($display['owner_locked_message'], ENT_QUOTES, 'UTF-8') ?></p>
                    <button class="button button--secondary" type="button" disabled>Modifier</button>
                    <button class="button button--danger" type="button" disabled>Supprimer</button>
                <?php elseif (!$viewer['is_connected'] && !$listing['is_ended']): ?>
                    <a class="button button--secondary" href="index.php?route=login_form">Se connecter</a>
                    <a class="button button--primary" href="index.php?route=register_form">Créer un compte</a>
                <?php elseif ($viewer['can_follow']): ?>
                    <form action="index.php?route=<?= $followRoute ?>" method="post" data-follow-form>
                        <input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="button button--secondary" type="submit"><?= $followLabel ?></button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="form-field__help" data-participation-status role="status"></p>

            <?php if ($viewer['can_bid']): ?>
                <form class="bid-form" action="index.php?route=place_bid" method="post" data-bid-form>
                    <input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <label class="form-field__label" for="bid-amount">Votre enchère</label>
                    <div class="bid-form__row">
                        <input class="form-control" id="bid-amount" name="amount" type="number" min="<?= htmlspecialchars($listing['minimum_bid'], ENT_QUOTES, 'UTF-8') ?>" max="99999999" step="1" inputmode="numeric" placeholder="<?= htmlspecialchars($listing['minimum_bid_label'], ENT_QUOTES, 'UTF-8') ?> minimum"<?php if ($bidRejection !== null): ?> value="<?= htmlspecialchars($bidRejection['amount'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?> required>
                        <button class="button button--primary" type="submit">Enchérir</button>
                    </div>
                    <p class="form-field__help" data-minimum-bid>
                        <?= htmlspecialchars($display['bid_minimum_help'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="form-field__help">Une enchère est définitive.</p>
                    <p class="form-field__help" data-bid-status role="status">
                        <?= htmlspecialchars($display['bid_status_message'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="bid-history glass-panel" aria-labelledby="history-title">
        <h2 id="history-title"><?= htmlspecialchars($historyTitle, ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($historySubtitle, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($showEmptyHistory): ?>
            <div class="bid-history__empty">
                <img src="public/assets/images/illustrations/shopping-cart.png" alt="" width="110" height="110">
                <strong>Aucune enchère enregistrée</strong>
                <p>L’historique apparaîtra ici dès qu’une première enchère valide aura été enregistrée.</p>
            </div>
        <?php elseif ($viewer['can_view_history']): ?>
            <div class="bid-history__content">
                <?php if ($history === []): ?>
                    <p>Aucune enchère enregistrée</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($history as $bid): ?>
                            <li>
                                <strong><?= htmlspecialchars($bid['amount'], ENT_QUOTES, 'UTF-8') ?></strong>
                                par <?= htmlspecialchars($bid['bidder'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($bid['date'], ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bid-history__locked">
                <img src="public/assets/images/icons/feature-icon-03.svg" alt="" width="70" height="70">
                <div>
                    <h3><?= htmlspecialchars($historyLockedTitle, ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars($historyLockedBody, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>
