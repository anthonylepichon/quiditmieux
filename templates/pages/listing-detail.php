<?php

/**
 * Description générale : Page publique de détail d'une annonce mise aux enchères.
 * Rôle : Afficher la vente, ses photographies, son prix et les actions adaptées aux droits du visiteur.
 * Tâches : Présenter les états public, vendeur, suiveur, enchérisseur et vente terminée sans exposer de donnée privée.
 * Liens avec les autres fichiers : Est affiché par ListingController.php et complété par main.js et listing-detail.js.
 */

$listing = $data['listing'];
$photos = $data['photos'];
$history = $data['history'];
$viewer = $data['viewer'];
$csrfToken = $data['csrf_token'];
$followRoute = 'follow_listing';
$followLabel = 'Suivre';

if ($viewer['is_following']) {
    $followRoute = 'unfollow_listing';
    $followLabel = 'Ne plus suivre';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Consultez le détail de l'annonce <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>.">
    <title><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?> — QUIDITMIEUX</title>
    <link rel="icon" href="public/assets/images/favicon/favicon.ico" sizes="any">
    <link rel="icon" href="public/assets/images/favicon/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="public/assets/css/main.css">
    <script src="public/assets/js/main.js" defer></script>
    <script src="public/assets/js/listing-detail.js" defer></script>
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="index.php?route=home" aria-label="QUIDITMIEUX — Accueil"><img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="260" height="54"></a>
            <nav class="site-navigation" aria-label="Navigation principale"><ul class="site-navigation__list"><li><a href="index.php?route=home">Accueil</a></li><li><a href="index.php?route=home#annonces">Annonces</a></li></ul></nav>
            <div class="site-header__actions">
                <?php if ($viewer['is_connected']): ?>
                    <a class="button button--secondary button--compact" href="index.php?route=dashboard">Tableau de bord</a>
                    <a class="button button--primary button--compact" href="index.php?route=listing_create_form">Publier</a>
                    <form class="site-header__logout" action="index.php?route=logout" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button class="button button--ghost button--compact" type="submit">Déconnexion</button></form>
                <?php else: ?>
                    <a class="button button--secondary button--compact" href="index.php?route=login_form">Connexion</a>
                    <a class="button button--primary button--compact" href="index.php?route=register_form">Créer un compte</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="listing-detail container">
        <a class="back-link" href="index.php?route=home#annonces">← Retour aux annonces</a>
        <?php if ($data['flash_success'] !== null): ?><div class="alert alert--success" role="status"><?= htmlspecialchars($data['flash_success'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($data['flash_notice'] !== null): ?><div class="alert alert--warning" role="status"><?= htmlspecialchars($data['flash_notice'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <section class="listing-detail__hero" aria-labelledby="listing-title">
            <div class="listing-gallery">
                <?php if ($photos !== []): ?>
                    <img class="listing-gallery__main" src="<?= htmlspecialchars($photos[0]['url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie principale de <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php if (count($photos) > 1): ?>
                        <div class="listing-gallery__thumbnails">
                            <?php foreach ($photos as $index => $photo): ?>
                                <img src="<?= htmlspecialchars($photo['url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie <?= $index + 1 ?> de <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="listing-gallery__empty"><img src="public/assets/images/illustrations/shopping-cart.png" alt="Aucune photographie disponible"></div>
                <?php endif; ?>
            </div>

            <div class="listing-summary glass-panel">
                <p class="eyebrow"><?= htmlspecialchars($listing['category'], ENT_QUOTES, 'UTF-8') ?></p>
                <h1 id="listing-title"><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Vendu par <strong><?= htmlspecialchars($listing['seller'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                <dl class="listing-summary__facts">
                    <div><dt>État de l’objet</dt><dd><?= htmlspecialchars($listing['item_state'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                    <div><dt>Prix courant</dt><dd class="listing-summary__price" data-current-price><?= htmlspecialchars($listing['current_price_label'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                    <div><dt>Enchères</dt><dd data-bid-count><?= (int) $listing['bid_count'] ?></dd></div>
                </dl>

                <?php if ($listing['is_ended']): ?>
                    <p class="status-badge status-badge--ended"><?= htmlspecialchars($listing['final_state'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <p>Fin prévue le <?= htmlspecialchars($listing['deadline_label'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="countdown" data-countdown data-deadline-utc="<?= htmlspecialchars($listing['deadline_utc'], ENT_QUOTES, 'UTF-8') ?>">Calcul du temps restant…</p>
                <?php endif; ?>

                <div class="listing-summary__actions" data-listing-actions>
                    <?php if ($viewer['can_edit']): ?>
                        <a class="button button--secondary" href="index.php?route=listing_edit_form&id=<?= (int) $listing['id'] ?>">Modifier</a>
                        <form action="index.php?route=listing_delete" method="post"><input type="hidden" name="id" value="<?= (int) $listing['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button class="button button--danger" type="submit">Supprimer</button></form>
                    <?php elseif ($viewer['is_owner']): ?>
                        <p class="alert alert--warning">Cette annonce ne peut plus être modifiée ni supprimée.</p>
                    <?php elseif (!$viewer['is_connected'] && !$listing['is_ended']): ?>
                        <a class="button button--secondary" href="index.php?route=login_form">Se connecter pour participer</a>
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

        <section class="listing-description glass-panel" aria-labelledby="description-title"><h2 id="description-title">Description</h2><p><?= nl2br(htmlspecialchars($listing['description'], ENT_QUOTES, 'UTF-8')) ?></p></section>

        <?php if ($viewer['can_view_history']): ?>
            <section class="bid-history glass-panel" aria-labelledby="history-title"><h2 id="history-title">Historique des enchères</h2>
                <?php if ($history === []): ?><p>Aucune enchère enregistrée.</p><?php else: ?><ul><?php foreach ($history as $bid): ?><li><strong><?= htmlspecialchars($bid['amount'], ENT_QUOTES, 'UTF-8') ?></strong> par <?= htmlspecialchars($bid['bidder'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($bid['date'], ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul><?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    <footer class="site-footer"><div class="site-footer__inner"><img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="180" height="38"><ul class="site-footer__links"><li><a href="index.php?route=home">Accueil</a></li><li><a href="index.php?route=privacy">Politique de confidentialité</a></li></ul><p>Ventes aux enchères entre particuliers.</p></div></footer>
</body>
</html>
