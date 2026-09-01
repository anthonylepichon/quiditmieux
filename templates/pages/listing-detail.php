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
$csrfToken = $data['csrf_token'];
$isConnected = $viewer['is_connected'];
$currentPage = 'listing_detail';
$pageTitle = $listing['title'] . ' — QUIDITMIEUX';
$pageDescription = 'Consultez le détail de l’annonce ' . $listing['title'] . '.';
$pageScripts = ['public/assets/js/listing-detail.js'];
$saleStatusLabel = 'Vente en cours';
$finalResultTitle = '';
$historySubtitle = 'Suivez l’évolution du prix et les participations enregistrées sur cette vente.';
$showEmptyEndedHistory = false;

if ($listing['is_ended']) {
    $saleStatusLabel = 'Vente terminée';
    $finalResultTitle = 'Vente adjugée';

    if ($listing['final_state'] === 'Non adjugée') {
        $finalResultTitle = 'Vente non adjugée';
    }

    if ((int) $listing['bid_count'] === 0) {
        $historySubtitle = 'Cette vente s’est terminée sans aucune enchère.';
        $showEmptyEndedHistory = true;
    }
}

$participationLabel = $saleStatusLabel;

if ($viewer['is_best_bidder']) {
    $participationLabel = 'Meilleure enchère';
} elseif ($viewer['has_bid']) {
    $participationLabel = 'Enchère dépassée';
} elseif ($viewer['is_following']) {
    $participationLabel = 'Annonce suivie';
} elseif ($viewer['is_owner'] && !$listing['is_ended']) {
    $participationLabel = 'Votre vente active';
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

$ownerMessage = '';

if ($viewer['can_edit']) {
    $ownerMessage = 'Vous pouvez modifier ou supprimer cette annonce tant qu’aucune enchère n’a été enregistrée.';
} elseif ($viewer['is_owner'] && !$listing['is_ended']) {
    $ownerMessage = 'Une enchère est enregistrée : cette annonce est désormais verrouillée.';
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
            <p>Vendu par <?= htmlspecialchars($listing['seller'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($listing['item_state'], ENT_QUOTES, 'UTF-8') ?> · <?= (int) $listing['bid_count'] ?> enchère(s) · Échéance : <?= htmlspecialchars($listing['deadline_label'], ENT_QUOTES, 'UTF-8') ?> — Europe/Paris</p>
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
                    <p class="status-badge status-badge--ended"><?= htmlspecialchars($listing['final_state'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <p class="status-badge" data-participation-badge data-default-label="<?= htmlspecialchars($saleStatusLabel, ENT_QUOTES, 'UTF-8') ?>" data-has-bid="<?= $hasBidAttribute ?>" data-is-best-bidder="<?= $isBestBidderAttribute ?>"><?= htmlspecialchars($participationLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <div class="listing-summary__price-copy"><span>Prix courant</span><strong class="listing-summary__price" data-current-price><?= htmlspecialchars($listing['current_price_label'], ENT_QUOTES, 'UTF-8') ?></strong><p><span data-bid-count><?= (int) $listing['bid_count'] ?></span> enchère(s) enregistrée(s)</p></div>
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

                <?php if (!$viewer['is_connected'] && !$listing['is_ended']): ?><p class="listing-summary__invitation">Connectez-vous ou créez un compte pour enchérir et suivre cette annonce.</p><?php endif; ?>
                <?php if ($ownerMessage !== ''): ?><p class="listing-summary__owner-copy"><?= htmlspecialchars($ownerMessage, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                <div class="<?= $actionsClass ?>" data-listing-actions>
                    <?php if ($listing['is_ended']): ?>
                        <p class="alert alert--warning alert--illustrated"><strong>Vente terminée</strong><span>Aucune action de participation ou de modification n’est disponible.</span></p>
                    <?php elseif ($viewer['can_edit']): ?>
                        <a class="button button--secondary" href="index.php?route=listing_edit_form&id=<?= (int) $listing['id'] ?>">Modifier</a>
                        <form action="index.php?route=listing_delete" method="post"><input type="hidden" name="id" value="<?= (int) $listing['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button class="button button--danger" type="submit">Supprimer</button></form>
                    <?php elseif ($viewer['is_owner']): ?>
                        <p class="alert alert--warning">Cette annonce ne peut plus être modifiée ni supprimée.</p>
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
                        <div class="bid-form__row"><input class="form-control" id="bid-amount" name="amount" type="number" min="<?= htmlspecialchars($listing['minimum_bid'], ENT_QUOTES, 'UTF-8') ?>" step="0.01" required><button class="button button--primary" type="submit">Enchérir</button></div>
                        <p class="form-field__help" data-minimum-bid>Montant minimum : <?= htmlspecialchars($listing['minimum_bid'], ENT_QUOTES, 'UTF-8') ?> €</p>
                        <p class="form-field__help" data-bid-status role="status"></p>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="bid-history glass-panel" aria-labelledby="history-title"><h2 id="history-title">Historique des enchères</h2><p><?= htmlspecialchars($historySubtitle, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($showEmptyEndedHistory): ?>
                <div class="bid-history__empty"><img src="public/assets/images/illustrations/shopping-cart.png" alt="" width="110" height="110"><strong>Aucune enchère enregistrée</strong><p>Cette annonce n’a reçu aucune enchère avant la fin de la vente.</p></div>
            <?php elseif ($viewer['can_view_history']): ?>
                <div class="bid-history__content"><?php if ($history === []): ?><p>Aucune enchère enregistrée.</p><?php else: ?><ul><?php foreach ($history as $bid): ?><li><strong><?= htmlspecialchars($bid['amount'], ENT_QUOTES, 'UTF-8') ?></strong> par <?= htmlspecialchars($bid['bidder'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($bid['date'], ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul><?php endif; ?></div>
            <?php else: ?>
                <div class="bid-history__locked"><img src="public/assets/images/icons/feature-icon-03.svg" alt="" width="70" height="70"><div><h3>Historique détaillé non accessible</h3><p>Connectez-vous et participez à la vente pour consulter le détail des enchères enregistrées.</p></div></div>
            <?php endif; ?>
        </section>
</main>
