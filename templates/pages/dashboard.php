<?php

/**
 * Description générale : Tableau de bord privé de l'utilisateur connecté.
 * Rôle : Présenter ses ventes, ses annonces suivies ou enchéries et ses enchères remportées.
 * Tâches : Afficher trois zones autonomes et leurs états vides conformément à la maquette.
 * Liens avec les autres fichiers : Est affiché par UserController.php, inséré dans base.php et actualisé par dashboard.js.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$zones = [
    ['key' => 'sales', 'title' => 'Mes ventes', 'description' => 'Annonces dont vous êtes le vendeur.', 'empty_title' => 'Aucune vente', 'empty' => 'Vos annonces publiées apparaîtront ici.', 'class' => 'dashboard-zone--sales', 'asset' => 'shopping-cart.png'],
    ['key' => 'participations', 'title' => 'Annonces suivies ou enchéries', 'description' => 'Suivis actifs et historique de vos enchères.', 'empty_title' => 'Aucune annonce suivie ou enchérie', 'empty' => 'Suivez une annonce ou enchérissez pour la retrouver ici.', 'class' => 'dashboard-zone--participations', 'asset' => 'growth-chart.png'],
    ['key' => 'wins', 'title' => 'Enchères remportées', 'description' => 'Résultats finaux de vos ventes gagnées.', 'empty_title' => 'Aucune enchère remportée', 'empty' => 'Les ventes que vous remportez apparaîtront ici.', 'class' => 'dashboard-zone--wins', 'asset' => 'coin-vault.png'],
];
$isConnected = true;
$csrfToken = $data['csrf_token'];
$currentPage = 'dashboard';
$pageTitle = 'Tableau de bord — QUIDITMIEUX';
$pageDescription = 'Consultez vos ventes, suivis et enchères remportées.';
$pageScripts = ['public/assets/js/dashboard.js'];
?>
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
            <section class="dashboard-zone <?= $zone['class'] ?><?php if ($data[$zone['key']] !== []): ?> dashboard-zone--populated<?php endif; ?>" aria-labelledby="<?= $zone['key'] ?>-title">
                <img class="dashboard-zone__asset" src="public/assets/images/illustrations/<?= htmlspecialchars($zone['asset'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="54" height="54">
                <div class="dashboard-zone__heading">
                    <h2 id="<?= $zone['key'] ?>-title"><?= htmlspecialchars($zone['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= htmlspecialchars($zone['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="dashboard-grid" data-dashboard-zone="<?= $zone['key'] ?>">
                    <?php if ($data[$zone['key']] === []): ?>
                        <div class="dashboard-zone__empty" data-empty-message><span aria-hidden="true">◇</span><div><strong><?= htmlspecialchars($zone['empty_title'], ENT_QUOTES, 'UTF-8') ?></strong><p><?= htmlspecialchars($zone['empty'], ENT_QUOTES, 'UTF-8') ?></p></div></div>
                    <?php else: ?>
                        <?php foreach ($data[$zone['key']] as $listing): ?>
                            <?php
                            $deadlinePrefix = 'Terminée le ';
                            $refreshLabel = '';
                            $statusLabel = 'Vente terminée';

                            if ($listing['is_active']) {
                                $deadlinePrefix = 'Se termine le ';
                                $statusLabel = 'Vente active';
                            }

                            if ($zone['key'] === 'sales' && !$listing['is_active']) {
                                $statusLabel = (int) $listing['bid_count'] > 0 ? 'Vente adjugée' : 'Non adjugée';
                            }

                            if ($zone['key'] === 'participations') {
                                $statusLabel = 'Enchère perdue';
                                if ($listing['is_active'] && $listing['user_best_bid'] === null) {
                                    $statusLabel = 'Annonce suivie';
                                } elseif ($listing['is_active'] && !empty($listing['is_current_winner'])) {
                                    $statusLabel = 'Meilleure enchère';
                                } elseif ($listing['is_active']) {
                                    $statusLabel = 'Enchère dépassée';
                                }
                            }

                            if ($zone['key'] === 'wins') {
                                $statusLabel = 'Enchère remportée';
                            }

                            if ($listing['is_active']) {
                                $refreshLabel = $zone['key'] === 'sales' ? ' · actualisation 10 s' : ' · actualisation 2 s';
                            }
                            ?>
                            <article class="dashboard-card" data-listing-id="<?= (int) $listing['id'] ?>">
                                <a class="dashboard-card__media" href="<?= htmlspecialchars($listing['detail_url'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if ($listing['photo_url'] !== null): ?><img src="<?= htmlspecialchars($listing['photo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie de <?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>"><?php else: ?><img src="public/assets/images/illustrations/shopping-cart.png" alt="Aucune photographie disponible"><?php endif; ?>
                                </a>
                                <div class="dashboard-card__body"><h3><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= $deadlinePrefix ?><?= htmlspecialchars($listing['deadline'], ENT_QUOTES, 'UTF-8') ?> — Europe/Paris<?= $refreshLabel ?></p><p class="dashboard-card__status">●&nbsp; <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></p></div>
                                <strong class="dashboard-card__price"><?= htmlspecialchars($listing['current_price'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <a class="dashboard-card__link" href="<?= htmlspecialchars($listing['detail_url'], ENT_QUOTES, 'UTF-8') ?>">Voir l’annonce&nbsp; →</a>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
</main>
