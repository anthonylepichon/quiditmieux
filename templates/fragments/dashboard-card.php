<?php

/**
 * Description générale : Carte réutilisable d'une annonce dans une zone du tableau de bord.
 * Rôle : Afficher une annonce et son statut déjà préparé par UserController.
 * Tâches : Présenter la photographie, les libellés de date, le statut, le prix et le lien de détail.
 * Liens avec les autres fichiers : Est inclus par dashboard.php pour les ventes, participations et enchères remportées.
 */

/** @var array<string, mixed> $listing Annonce prête à afficher. */
/** @var array<string, string> $cardDisplay Libellés visuels préparés par UserController. */
?>
<article class="dashboard-card" data-listing-id="<?= (int) $listing['id'] ?>">
    <a class="dashboard-card__media" href="<?= htmlspecialchars($listing['detail_url'], ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($listing['photo_url'] !== null): ?>
            <img src="<?= htmlspecialchars($listing['photo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie de <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>">
        <?php else: ?>
            <img src="public/assets/images/illustrations/shopping-cart.png" alt="Aucune photographie disponible">
        <?php endif; ?>
    </a>
    <div class="dashboard-card__body">
        <h3><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p>
            <?= htmlspecialchars($cardDisplay['deadline_prefix'], ENT_QUOTES, 'UTF-8') ?>
            <?= htmlspecialchars($cardDisplay['deadline_label'], ENT_QUOTES, 'UTF-8') ?>
            <?= htmlspecialchars($cardDisplay['deadline_suffix'], ENT_QUOTES, 'UTF-8') ?>
            <?= htmlspecialchars($cardDisplay['refresh_label'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p class="dashboard-card__status">
            <?= htmlspecialchars($cardDisplay['status_symbol'], ENT_QUOTES, 'UTF-8') ?>
            <?= htmlspecialchars($cardDisplay['status_label'], ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <strong class="dashboard-card__price"><?= htmlspecialchars($listing['current_price'], ENT_QUOTES, 'UTF-8') ?></strong>
    <a class="dashboard-card__link" href="<?= htmlspecialchars($listing['detail_url'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($cardDisplay['detail_link_label'], ENT_QUOTES, 'UTF-8') ?>
    </a>
</article>
