<?php

/**
 * Description générale : Tableau de bord privé de l'utilisateur connecté.
 * Rôle : Présenter ses ventes, ses annonces suivies ou enchéries et ses enchères remportées.
 * Tâches : Afficher trois zones autonomes et leurs états vides conformément à la maquette.
 * Liens avec les autres fichiers : Est affiché par DashboardController.php, inséré dans base.php et actualisé par dashboard.js.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$zones = [
    ['key' => 'sales', 'title' => 'Mes ventes', 'description' => 'Annonces dont vous êtes le vendeur.', 'empty_title' => 'Aucune vente', 'empty' => 'Vos annonces publiées apparaîtront ici.', 'class' => 'dashboard-zone--sales', 'asset' => 'shopping-cart.png'],
    ['key' => 'participations', 'title' => 'Annonces suivies ou enchéries', 'description' => 'Suivis actifs et historique de vos enchères.', 'empty_title' => 'Aucune annonce suivie ou enchérie', 'empty' => 'Suivez une annonce ou enchérissez pour la retrouver ici.', 'class' => 'dashboard-zone--participations', 'asset' => 'growth-chart.png'],
    ['key' => 'wins', 'title' => 'Enchères remportées', 'description' => 'Résultats finaux de vos ventes gagnées.', 'empty_title' => 'Aucune enchère remportée', 'empty' => 'Les ventes que vous remportez apparaîtront ici.', 'class' => 'dashboard-zone--wins', 'asset' => 'coin-vault.png'],
];
$isConnected = true;
$csrfToken = $data['csrf_token'];
$flashSuccess = $data['flash_success'];
$flashNotice = $data['flash_notice'];
$currentPage = 'dashboard';
$pageTitle = 'Tableau de bord — QUIDITMIEUX';
$pageDescription = 'Consultez vos ventes, suivis et enchères remportées.';
$pageScripts = ['public/assets/js/dashboard.js'];
$dashboardMessage = $data['dashboard_message'];
?>
<main class="dashboard container">
    <section class="dashboard__heading">
        <div>
            <p class="eyebrow">Votre activité</p>
            <h1>Tableau de bord</h1>
            <p>Vos ventes, vos annonces suivies et vos enchères réunies au même endroit.</p>
            <ul class="dashboard__metrics" aria-label="Rubriques du tableau de bord">
                <li><a href="#dashboard-sales">Mes ventes</a></li>
                <li><a href="#dashboard-participations">Suivis &amp; enchères</a></li>
                <li><a href="#dashboard-wins">Remportées</a></li>
            </ul>
        </div>
        <img src="public/assets/images/illustrations/growth-chart.png" alt="" width="180" height="160">
    </section>

    <?php
    // Fragment flash-messages.php : messages temporaires du tableau de bord préparés par DashboardController.
    require __DIR__ . '/../fragments/flash-messages.php';
    ?>

    <div class="alert alert--<?= htmlspecialchars($dashboardMessage['variant'], ENT_QUOTES, 'UTF-8') ?>" data-dashboard-message>
        <strong><?= htmlspecialchars($dashboardMessage['title'], ENT_QUOTES, 'UTF-8') ?></strong>
        <span><?= htmlspecialchars($dashboardMessage['body'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <p class="dashboard__status" data-dashboard-status role="status"></p>

    <?php foreach ($zones as $zone): ?>
        <section class="dashboard-zone <?= $zone['class'] ?><?php if ($data[$zone['key']] !== []): ?> dashboard-zone--populated<?php endif; ?>" id="dashboard-<?= $zone['key'] ?>" aria-labelledby="<?= $zone['key'] ?>-title">
            <img class="dashboard-zone__asset" src="public/assets/images/illustrations/<?= htmlspecialchars($zone['asset'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="54" height="54">
            <div class="dashboard-zone__heading">
                <h2 id="<?= $zone['key'] ?>-title"><?= htmlspecialchars($zone['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($zone['description'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="dashboard-grid" data-dashboard-zone="<?= $zone['key'] ?>">
                <?php if ($data[$zone['key']] === []): ?>
                    <div class="dashboard-zone__empty" data-empty-message>
                        <span aria-hidden="true">◇</span>
                        <div>
                            <strong><?= htmlspecialchars($zone['empty_title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <p><?= htmlspecialchars($zone['empty'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($data[$zone['key']] as $listing): ?>
                        <?php $cardDisplay = $listing['display']; ?>
                        <?php
                        // Fragment dashboard-card.php : carte et statut préparés par DashboardController.
                        require __DIR__ . '/../fragments/dashboard-card.php';
                        ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</main>
