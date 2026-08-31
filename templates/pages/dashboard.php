<?php

/**
 * Description générale : Tableau de bord privé de l'utilisateur connecté.
 * Rôle : Présenter ses ventes, ses participations et les enchères qu'il a remportées.
 * Tâches : Afficher les états vides et fournir des zones actualisables sans rechargement complet.
 * Liens avec les autres fichiers : Est affiché par UserController.php et actualisé par dashboard.js.
 */

$zones = [
    ['key' => 'sales', 'title' => 'Mes ventes', 'empty' => 'Vous n’avez publié aucune annonce.'],
    ['key' => 'participations', 'title' => 'Mes participations', 'empty' => 'Vous ne suivez aucune vente active et n’avez aucune enchère à afficher.'],
    ['key' => 'wins', 'title' => 'Mes enchères remportées', 'empty' => 'Vous n’avez encore remporté aucune enchère.'],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Consultez vos ventes, participations et enchères remportées.">
    <title>Tableau de bord — QUIDITMIEUX</title>
    <link rel="icon" href="public/assets/images/favicon/favicon.ico" sizes="any">
    <link rel="icon" href="public/assets/images/favicon/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="public/assets/css/main.css">
    <script src="public/assets/js/main.js" defer></script>
    <script src="public/assets/js/dashboard.js" defer></script>
</head>
<body>
    <header class="site-header"><div class="site-header__inner">
        <a class="brand" href="index.php?route=home" aria-label="QUIDITMIEUX — Accueil"><img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="260" height="54"></a>
        <nav class="site-navigation" aria-label="Navigation principale"><ul class="site-navigation__list"><li><a href="index.php?route=home">Accueil</a></li><li><a href="index.php?route=dashboard" aria-current="page">Tableau de bord</a></li></ul></nav>
        <div class="site-header__actions"><a class="button button--secondary button--compact" href="index.php?route=account_form">Mon compte</a><a class="button button--primary button--compact" href="index.php?route=listing_create_form">Publier</a><form class="site-header__logout" action="index.php?route=logout" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($data['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><button class="button button--ghost button--compact" type="submit">Déconnexion</button></form></div>
    </div></header>

    <main class="dashboard container">
        <div class="dashboard__heading"><div><p class="eyebrow">Votre activité</p><h1>Tableau de bord</h1><p>Suivez vos ventes et vos enchères depuis un seul espace.</p></div><img src="public/assets/images/illustrations/growth-chart.png" alt="" width="180" height="160"></div>
        <?php if ($data['flash_success'] !== null): ?><div class="alert alert--success" role="status"><?= htmlspecialchars($data['flash_success'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($data['flash_notice'] !== null): ?><div class="alert alert--warning" role="status"><?= htmlspecialchars($data['flash_notice'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <p class="dashboard__status" data-dashboard-status role="status"></p>

        <?php foreach ($zones as $zone): ?>
            <section class="dashboard-zone glass-panel" aria-labelledby="<?= $zone['key'] ?>-title">
                <div class="section-heading"><h2 id="<?= $zone['key'] ?>-title"><?= htmlspecialchars($zone['title'], ENT_QUOTES, 'UTF-8') ?></h2></div>
                <div class="dashboard-grid" data-dashboard-zone="<?= $zone['key'] ?>">
                    <?php if ($data[$zone['key']] === []): ?>
                        <p class="dashboard-zone__empty" data-empty-message><?= htmlspecialchars($zone['empty'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php else: ?>
                        <?php foreach ($data[$zone['key']] as $listing): ?>
                            <?php $deadlinePrefix = 'Terminée le '; if ($listing['is_active']) { $deadlinePrefix = 'Fin le '; } ?>
                            <article class="dashboard-card" data-listing-id="<?= (int) $listing['id'] ?>">
                                <a class="dashboard-card__media" href="<?= htmlspecialchars($listing['detail_url'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if ($listing['photo_url'] !== null): ?><img src="<?= htmlspecialchars($listing['photo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie de <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>"><?php else: ?><img src="public/assets/images/illustrations/shopping-cart.png" alt="Aucune photographie disponible"><?php endif; ?>
                                </a>
                                <div class="dashboard-card__body"><p class="eyebrow"><?= htmlspecialchars($listing['category'], ENT_QUOTES, 'UTF-8') ?></p><h3><a href="<?= htmlspecialchars($listing['detail_url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></a></h3><p class="dashboard-card__price"><?= htmlspecialchars($listing['current_price'], ENT_QUOTES, 'UTF-8') ?></p><p><?= (int) $listing['bid_count'] ?> enchère(s)</p><?php if ($listing['user_best_bid'] !== null): ?><p>Votre meilleure enchère : <?= htmlspecialchars($listing['user_best_bid'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><p><?= $deadlinePrefix ?><?= htmlspecialchars($listing['deadline'], ENT_QUOTES, 'UTF-8') ?></p></div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
    <footer class="site-footer"><div class="site-footer__inner"><img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="180" height="38"><ul class="site-footer__links"><li><a href="index.php?route=home">Accueil</a></li><li><a href="index.php?route=privacy">Politique de confidentialité</a></li></ul><p>Ventes aux enchères entre particuliers.</p></div></footer>
</body>
</html>
