<?php

/**
 * Description générale : Page publique d'accueil et de recherche des annonces.
 * Rôle : Afficher le formulaire multicritère, les états de recherche, les cartes et la pagination.
 * Tâches : Présenter les données préparées par ListingSearchController et fournir une navigation sans JavaScript.
 * Liens avec les autres fichiers : Est affiché par ListingSearchController.php, inséré dans base.php et complété par home.js.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$criteria = $data['criteria'];
$errors = $data['errors'];
$categories = $data['categories'];
$listings = $data['listings'];
$pagination = $data['pagination'];
$stateKey = $data['state_key'];
$fieldErrorMessages = $data['field_error_messages'];
$hasPriceRangeError = $data['has_price_range_error'];
$resultsStateKey = $data['results_state_key'];
$resultsMessage = $data['results_message'];
$filterSummary = $data['filter_summary'];
$categoriesAvailable = $data['categories_available'];
$isConnected = $data['is_connected'];
$csrfToken = $data['csrf_token'];
$flashSuccess = $data['flash_success'];
$flashNotice = $data['flash_notice'];
$totalItems = (int) $data['total_items'];
$currentPage = 'home';
$pageTitle = 'Accueil — QUIDITMIEUX';
$pageDescription = 'Consultez et recherchez les ventes aux enchères QUIDITMIEUX.';
$pageScripts = ['public/assets/js/home.js'];
$featuredListing = null;
$paginationUrls = [];

if ($listings !== []) {
    $featuredListing = $listings[0];
}


for ($pageNumber = 1; $pageNumber <= (int) $pagination['total_pages']; $pageNumber++) {
    $pageParameters = ['route' => 'home'];

    if ((string) $criteria['text'] !== '') {
        $pageParameters['q'] = (string) $criteria['text'];
    }

    if ($criteria['category_id'] !== null) {
        $pageParameters['category'] = (string) $criteria['category_id'];
    }

    if ($criteria['item_state'] !== null) {
        $pageParameters['item_state'] = (string) $criteria['item_state'];
    }

    if ((string) $criteria['minimum_price'] !== '') {
        $pageParameters['minimum_price'] = (string) $criteria['minimum_price'];
    }

    if ((string) $criteria['maximum_price'] !== '') {
        $pageParameters['maximum_price'] = (string) $criteria['maximum_price'];
    }

    if ((string) $criteria['sale_state'] !== 'active') {
        $pageParameters['sale_state'] = (string) $criteria['sale_state'];
    }

    if ($pageNumber > 1) {
        $pageParameters['page'] = $pageNumber;
    }

    // NATIF PHP : http_build_query() transforme un tableau en paramètres d’URL ; il construit ici une adresse GET correctement encodée.
    $paginationUrls[$pageNumber] = 'index.php?' . http_build_query($pageParameters);
}
?>
<main>
    <!-- ==================== PRÉSENTATION ==================== -->
    <section class="home-hero container glass-panel" aria-labelledby="home-title">
        <div class="home-hero__content">
            <p class="eyebrow">Ventes aux enchères entre particuliers</p>
            <h1 id="home-title">Donnez une seconde vie aux objets qui comptent.</h1>
            <p>Découvrez des annonces, suivez les ventes et proposez le juste prix, simplement et en toute sécurité.</p>
            <a class="button button--primary" href="#annonces">Découvrir les enchères</a>
        </div>
        <div class="home-hero__visual">
            <img src="public/assets/images/illustrations/character-hero.png" alt="" width="343" height="314">
            <img class="home-hero__badge home-hero__badge--euro" src="public/assets/images/icons/decorative-badge-euro.svg" alt="" width="46" height="46">
            <img class="home-hero__badge home-hero__badge--binary" src="public/assets/images/icons/decorative-badge-binary.svg" alt="" width="46" height="46">
            <img class="home-hero__badge home-hero__badge--heart" src="public/assets/images/icons/decorative-badge-heart.svg" alt="" width="46" height="46">
            <img class="home-hero__badge home-hero__badge--trend" src="public/assets/images/icons/decorative-badge-trend.svg" alt="" width="46" height="46">
        </div>
    </section>

    <div class="container section-stack">
        <?php
        // Fragment flash-messages.php : messages temporaires de la recherche préparés par ListingSearchController.
        require __DIR__ . '/../fragments/flash-messages.php';
        ?>
        <!-- ==================== RECHERCHE ==================== -->
        <section class="search-panel glass-panel" aria-labelledby="search-title">
            <div class="section-heading">
                <h2 id="search-title">Rechercher une annonce</h2>
            </div>

            <form class="search-form" id="search-form" action="index.php" method="get" novalidate>
                <input type="hidden" name="route" value="home">
                <div class="search-form__grid">
                    <div class="form-field search-form__keywords">
                        <label class="form-field__label" for="search-text">Mots-clés</label>
                        <input
                            class="form-control"
                            id="search-text"
                            name="q"
                            type="search"
                            maxlength="120"
                            value="<?= htmlspecialchars((string) $criteria['text'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Titre ou description"
                            aria-describedby="error-q"
                            <?= isset($errors['q']) ? 'aria-invalid="true"' : '' ?>
                        >
                        <span class="form-field__error" id="error-q" data-error-for="q"><?= htmlspecialchars($fieldErrorMessages['q'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="form-field search-form__category">
                        <label class="form-field__label" for="search-category">Catégorie</label>
                        <select
                            class="form-control"
                            id="search-category"
                            name="category"
                            aria-describedby="error-category"
                            <?= !$categoriesAvailable ? 'disabled' : '' ?>
                            <?= isset($errors['category']) ? 'aria-invalid="true"' : '' ?>
                        >
                            <option value="">
                                <?php if ($categoriesAvailable): ?>
                                    Toutes les catégories
                                <?php else: ?>
                                    Indisponible
                                <?php endif; ?>
                            </option>
                            <?php foreach ($categories as $categoryId => $categoryLabel): ?>
                                <option
                                    value="<?= htmlspecialchars((string) $categoryId, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= (string) $criteria['category_id'] === (string) $categoryId ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars((string) $categoryLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-field__error" id="error-category" data-error-for="category"><?= htmlspecialchars($fieldErrorMessages['category'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="form-field search-form__item-state">
                        <label class="form-field__label" for="search-item-state">État de l’objet</label>
                        <select
                            class="form-control"
                            id="search-item-state"
                            name="item_state"
                            aria-describedby="error-item-state"
                            <?= isset($errors['item_state']) ? 'aria-invalid="true"' : '' ?>
                        >
                            <option value="">Tous les états</option>
                            <?php foreach (['neuf', 'très bon état', 'bon état', 'état correct'] as $itemState): ?>
                                <option value="<?= htmlspecialchars($itemState, ENT_QUOTES, 'UTF-8') ?>" <?= $criteria['item_state'] === $itemState ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($itemState), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-field__error" id="error-item-state" data-error-for="item_state"><?= htmlspecialchars($fieldErrorMessages['item_state'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="form-field search-form__sale-state">
                        <label class="form-field__label" for="search-sale-state">État de la vente</label>
                        <select
                            class="form-control"
                            id="search-sale-state"
                            name="sale_state"
                            aria-describedby="error-sale-state"
                            <?= isset($errors['sale_state']) ? 'aria-invalid="true"' : '' ?>
                        >
                            <option value="all" <?= $criteria['sale_state'] === 'all' ? 'selected' : '' ?>>
                                Toutes
                            </option>
                            <option value="active" <?= $criteria['sale_state'] === 'active' ? 'selected' : '' ?>>
                                En cours
                            </option>
                            <option value="ended" <?= $criteria['sale_state'] === 'ended' ? 'selected' : '' ?>>
                                Terminées
                            </option>
                        </select>
                        <span class="form-field__error" id="error-sale-state" data-error-for="sale_state"><?= htmlspecialchars($fieldErrorMessages['sale_state'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="form-field search-form__minimum-price">
                        <label class="form-field__label" for="search-minimum-price">Prix minimum</label>
                        <input
                            class="form-control"
                            id="search-minimum-price"
                            name="minimum_price"
                            type="number"
                            min="1"
                            max="99999999"
                            step="1"
                            inputmode="numeric"
                            value="<?= htmlspecialchars((string) $criteria['minimum_price'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="0 €"
                            aria-describedby="error-minimum-price"
                            <?= isset($errors['minimum_price']) ? 'aria-invalid="true"' : '' ?>
                        >
                        <span class="form-field__error" id="error-minimum-price" data-error-for="minimum_price"><?= htmlspecialchars($fieldErrorMessages['minimum_price'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="form-field search-form__maximum-price">
                        <label class="form-field__label" for="search-maximum-price">Prix maximum</label>
                        <input
                            class="form-control"
                            id="search-maximum-price"
                            name="maximum_price"
                            type="number"
                            min="1"
                            max="99999999"
                            step="1"
                            inputmode="numeric"
                            value="<?= htmlspecialchars((string) $criteria['maximum_price'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Sans limite"
                            aria-describedby="error-maximum-price"
                            <?= isset($errors['maximum_price']) ? 'aria-invalid="true"' : '' ?>
                        >
                        <span class="form-field__error" id="error-maximum-price" data-error-for="maximum_price"><?= htmlspecialchars($fieldErrorMessages['maximum_price'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <div class="search-form__actions">
                    <button class="button button--primary" type="submit">Rechercher</button>
                </div>
            </form>
        </section>

        <!-- ==================== RÉSULTATS ==================== -->
            <section id="annonces" class="results-section results-section--<?= htmlspecialchars($resultsStateKey, ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="results-title" data-results-region data-results-state="<?= htmlspecialchars($resultsStateKey, ENT_QUOTES, 'UTF-8') ?>" aria-busy="false">
                <div class="section-heading section-heading--row">
                    <h2 id="results-title" tabindex="-1">
                        <?php if ($stateKey === 'filtered_results'): ?>
                            <?= $totalItems ?> annonces correspondent à votre recherche
                        <?php elseif ($stateKey === 'no_results'): ?>
                            Résultats
                        <?php else: ?>
                            Les enchères qui se terminent bientôt
                        <?php endif; ?>
                    </h2>
                    <p class="results-summary" data-results-summary role="status" aria-live="polite">
                        <?php if ($stateKey === 'filtered_results'): ?>
                            <?= $totalItems ?> résultats
                        <?php elseif ($stateKey === 'categories_unavailable'): ?>
                            <?= $totalItems ?> ventes actives · échéance croissante
                        <?php elseif ($resultsStateKey === 'pagination'): ?>
                            <?= $totalItems ?> ventes · page <?= (int) $pagination['current_page'] ?>/<?= (int) $pagination['total_pages'] ?>
                        <?php elseif ($stateKey === 'initial'): ?>
                            <?= $totalItems ?> ventes actives · échéance croissante
                        <?php endif; ?>
                    </p>
            </div>

            <div data-results-message>
                <?php if ($stateKey === 'invalid_criteria'): ?>
                    <div class="alert alert--error home-state-alert" role="alert">
                        <strong><?= htmlspecialchars($resultsMessage['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($resultsMessage['body'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="search-blocked-state">
                        <h3><?= htmlspecialchars($resultsMessage['blocked_title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p><?= htmlspecialchars($resultsMessage['blocked_body'], ENT_QUOTES, 'UTF-8') ?></p>
                        <img src="public/assets/images/illustrations/shopping-cart.png" alt="" width="116" height="164">
                    </div>
                <?php elseif ($stateKey === 'search_error'): ?>
                    <div class="alert alert--error home-state-alert" role="alert">
                        <strong><?= htmlspecialchars($resultsMessage['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($resultsMessage['body'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php elseif ($stateKey === 'categories_unavailable'): ?>
                    <div class="alert alert--warning home-state-alert" role="status">
                        <strong><?= htmlspecialchars($resultsMessage['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($resultsMessage['body'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php elseif ($stateKey === 'no_results'): ?>
                    <div class="empty-state">
                        <img src="public/assets/images/illustrations/shopping-cart.png" alt="" width="168" height="238">
                        <div>
                            <h3><?= htmlspecialchars($resultsMessage['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars($resultsMessage['body'], ENT_QUOTES, 'UTF-8') ?></p>
                            <a class="button button--primary" href="#search-title">Modifier mes critères</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="auction-grid" data-results-list>
                <?php foreach ($listings as $listing): ?>
                    <?php
                    // Fragment auction-card.php : carte de l'annonce courante préparée par ListingSearchController.
                    require __DIR__ . '/../fragments/auction-card.php';
                    ?>
                <?php endforeach; ?>
            </div>

            <div class="filter-summary" data-filter-summary <?php if ($stateKey !== 'filtered_results'): ?>hidden<?php endif; ?>>
                <h3>Critères appliqués</h3>
                <?php // NATIF PHP : implode() assemble les éléments d’un tableau dans une chaîne ; il construit ici une liste ou une partie de requête. ?>
                <p data-filter-summary-values><?= htmlspecialchars($filterSummary, ENT_QUOTES, 'UTF-8') ?></p>
                <p>Les résultats sont classés par date et heure de fin.</p>
            </div>

            <nav class="pagination" data-pagination aria-label="Pagination des annonces">
                <?php if ($pagination['total_pages'] > 1): ?>
                    <?php if ($pagination['previous_url'] !== null): ?>
                        <a class="button button--secondary pagination__previous" href="<?= htmlspecialchars((string) $pagination['previous_url'], ENT_QUOTES, 'UTF-8') ?>" rel="prev">Précédent</a>
                    <?php else: ?>
                        <span class="button button--disabled pagination__previous" aria-disabled="true">Précédent</span>
                    <?php endif; ?>
                    <?php foreach ($paginationUrls as $pageNumber => $pageUrl): ?>
                        <a class="button pagination__page<?php if ($pageNumber === (int) $pagination['current_page']): ?> button--primary<?php else: ?> button--secondary<?php endif; ?>" href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>"<?php if ($pageNumber === (int) $pagination['current_page']): ?> aria-current="page"<?php endif; ?>><?= $pageNumber ?></a>
                    <?php endforeach; ?>
                    <?php if ($pagination['next_url'] !== null): ?>
                        <a class="button button--secondary pagination__next" href="<?= htmlspecialchars((string) $pagination['next_url'], ENT_QUOTES, 'UTF-8') ?>" rel="next">Suivant</a>
                    <?php else: ?>
                        <span class="button button--disabled pagination__next" aria-disabled="true">Suivant</span>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
        </section>
    </div>
</main>
