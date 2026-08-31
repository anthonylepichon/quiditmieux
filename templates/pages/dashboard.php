<?php

/**
 * Description générale : Tableau de bord privé de l'utilisateur connecté.
 * Rôle : Présenter ses ventes, ses annonces suivies ou enchéries et ses enchères remportées.
 * Tâches : Afficher trois zones autonomes et leurs états vides conformément à la maquette.
 * Liens avec les autres fichiers : Est affiché par UserController.php et actualisé par dashboard.js.
 */

$zones = [
    ['key' => 'sales', 'title' => 'Mes ventes', 'description' => 'Annonces dont vous êtes le vendeur.', 'empty_title' => 'Aucune vente', 'empty' => 'Vos annonces publiées apparaîtront ici.', 'class' => 'dashboard-zone--sales'],
    ['key' => 'participations', 'title' => 'Annonces suivies ou enchéries', 'description' => 'Suivis actifs et historique de vos enchères.', 'empty_title' => 'Aucune annonce suivie ou enchérie', 'empty' => 'Suivez une annonce ou enchérissez pour la retrouver ici.', 'class' => 'dashboard-zone--participations'],
    ['key' => 'wins', 'title' => 'Enchères remportées', 'description' => 'Résultats finaux de vos ventes gagnées.', 'empty_title' => 'Aucune enchère remportée', 'empty' => 'Les ventes que vous remportez apparaîtront ici.', 'class' => 'dashboard-zone--wins'],
];
$isConnected = true;
$csrfToken = $data['csrf_token'];
$currentPage = 'dashboard';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Consultez vos ventes, suivis et enchères remportées.">
    <title>Tableau de bord — QUIDITMIEUX</title>
    <link rel="icon" href="public/assets/images/favicon/favicon.ico" sizes="any">
    <link rel="icon" href="public/assets/images/favicon/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="public/assets/css/main.css">
    <script src="public/assets/js/main.js" defer></script>
    <script src="public/assets/js/dashboard.js" defer></script>
</head>
<body>
    <?php require __DIR__ . '/../layout/header.php'; ?>
    <main class="dashboard container">
        <section class="dashboard__heading">
            <div>
                <p class="eyebrow">Votre activité</p>
                <h1>Tableau de bord</h1>
                <p>Vos ventes, vos annonces suivies et vos enchères réunies au même endroit.</p>
                <ul class="dashboard__metrics" aria-label="Rubriques du tableau de bord">
                    <li>Mes ventes</li><li>Suivis &amp; enchères</li><li>Remportées</li>
                </ul>
            </div>
            <img src="public/assets/images/illustrations/growth-chart.png" alt="" width="180" height="160">
        </section>

        <?php if ($data['flash_success'] !== null): ?><div class="alert alert--success" role="status"><?= htmlspecialchars($data['flash_success'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($data['flash_notice'] !== null): ?><div class="alert alert--warning" role="status"><?= htmlspecialchars($data['flash_notice'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <div class="alert alert--info"><strong>Votre activité en un coup d’œil</strong><span>Les trois zones restent disponibles, même lorsqu’elles ne contiennent encore aucune annonce.</span></div>
        <p class="dashboard__status" data-dashboard-status role="status"></p>

        <?php foreach ($zones as $zone): ?>
            <section class="dashboard-zone <?= $zone['class'] ?>" aria-labelledby="<?= $zone['key'] ?>-title">
                <div class="dashboard-zone__heading">
                    <h2 id="<?= $zone['key'] ?>-title"><?= htmlspecialchars($zone['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= htmlspecialchars($zone['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="dashboard-grid" data-dashboard-zone="<?= $zone['key'] ?>">
                    <?php if ($data[$zone['key']] === []): ?>
                        <div class="dashboard-zone__empty" data-empty-message><span aria-hidden="true">◇</span><div><strong><?= htmlspecialchars($zone['empty_title'], ENT_QUOTES, 'UTF-8') ?></strong><p><?= htmlspecialchars($zone['empty'], ENT_QUOTES, 'UTF-8') ?></p></div></div>
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
    <?php require __DIR__ . '/../layout/footer.php'; ?>
</body>
</html>
