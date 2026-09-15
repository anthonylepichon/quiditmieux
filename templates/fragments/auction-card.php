<?php

/**
 * Description générale : Carte réutilisable d'une annonce dans une liste de résultats.
 * Rôle : Présenter les informations publiques d'une annonce préparées par ListingSearchController.
 * Tâches : Afficher la photographie, l'état, le prix, l'échéance et le lien de détail.
 * Liens avec les autres fichiers : Est inclus par home.php pour chaque annonce issue de ListingSearchController.php.
 */

/** @var array<string, mixed> $listing Annonce prête à afficher. */
?>
<article class="auction-card">
    <div class="auction-card__media">
        <?php if ($listing['photo_url'] !== null): ?>
            <img src="<?= htmlspecialchars((string) $listing['photo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie de <?= htmlspecialchars((string) $listing['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
        <?php else: ?>
            <img class="auction-card__placeholder" src="public/assets/images/illustrations/shopping-cart.png" alt="Aucune photographie disponible" loading="lazy">
        <?php endif; ?>
    </div>
    <div class="auction-card__body">
        <p class="auction-card__category"><?= htmlspecialchars((string) $listing['sale_state_label'], ENT_QUOTES, 'UTF-8') ?></p>
        <h3 class="auction-card__title"><?= htmlspecialchars((string) $listing['title'], ENT_QUOTES, 'UTF-8') ?></h3>
        <div class="auction-card__meta">
            <div>
                <span>Prix courant</span>
                <strong class="auction-card__price"><?= htmlspecialchars((string) $listing['current_price_label'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <time class="auction-card__deadline" datetime="<?= htmlspecialchars((string) $listing['deadline'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) $listing['deadline_display_label'], ENT_QUOTES, 'UTF-8') ?>
            </time>
        </div>
        <a class="auction-card__link" href="<?= htmlspecialchars((string) $listing['detail_url'], ENT_QUOTES, 'UTF-8') ?>">
            Voir l’annonce
            <span class="visually-hidden">: <?= htmlspecialchars((string) $listing['title'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
    </div>
</article>
