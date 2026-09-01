<?php

/**
 * Description générale : Page publique d'accueil et de recherche des annonces.
 * Rôle : Afficher le formulaire multicritère, les états de recherche, les cartes et la pagination.
 * Tâches : Présenter les données préparées par ListingController et fournir une navigation sans JavaScript.
 * Liens avec les autres fichiers : Est affiché par ListingController.php, inséré dans base.php et complété par home.js.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$criteria = $data['criteria'];
$errors = $data['errors'];
$categories = $data['categories'];
$listings = $data['listings'];
$pagination = $data['pagination'];
$stateKey = $data['state_key'];
$message = $data['message'];
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
$resultsStateKey = $stateKey;
$firstSearchError = (string) $message;
$filterSummaryParts = [];
$paginationUrls = [];

if ($listings !== []) {
    $featuredListing = $listings[0];
}

if ($stateKey === 'initial' && (int) $pagination['total_pages'] > 1) {
    $resultsStateKey = 'pagination';
}

foreach ($errors as $errorMessage) {
    $firstSearchError = (string) $errorMessage;
    break;
}

if ((string) $criteria['text'] !== '') {
    $filterSummaryParts[] = (string) $criteria['text'];
}

if ($criteria['category_id'] !== null && isset($categories[$criteria['category_id']])) {
    $filterSummaryParts[] = (string) $categories[$criteria['category_id']];
}

if ($criteria['item_state'] !== null) {
    $filterSummaryParts[] = ucfirst((string) $criteria['item_state']);
}

if ((string) $criteria['minimum_price'] !== '' || (string) $criteria['maximum_price'] !== '') {
    $minimumPriceLabel = '0,00 €';
    $maximumPriceLabel = 'sans limite';

    if ((string) $criteria['minimum_price'] !== '') {
        $minimumPriceLabel = (string) $criteria['minimum_price'] . ' €';
    }

    if ((string) $criteria['maximum_price'] !== '') {
        $maximumPriceLabel = (string) $criteria['maximum_price'] . ' €';
    }

    $filterSummaryParts[] = $minimumPriceLabel . ' à ' . $maximumPriceLabel;
}

if ((string) $criteria['sale_state'] !== 'active') {
    $saleStateLabel = 'Toutes';

    if ((string) $criteria['sale_state'] === 'ended') {
        $saleStateLabel = 'Terminées';
    }

    $filterSummaryParts[] = $saleStateLabel;
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
            <?php if ($flashSuccess !== null): ?>
                <div class="alert alert--success" role="status"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($flashNotice !== null): ?>
                <div class="alert alert--warning" role="status"><?= htmlspecialchars($flashNotice, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
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
                            <span class="form-field__error" id="error-q" data-error-for="q"><?= isset($errors['q']) ? htmlspecialchars((string) $errors['q'], ENT_QUOTES, 'UTF-8') : '' ?></span>
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
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($categories as $categoryId => $categoryLabel): ?>
                                    <option
                                        value="<?= htmlspecialchars((string) $categoryId, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= (string) $criteria['category_id'] === (string) $categoryId ? 'selected' : '' ?>
                                    ><?= htmlspecialchars((string) $categoryLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-field__error" id="error-category" data-error-for="category"><?= isset($errors['category']) ? htmlspecialchars((string) $errors['category'], ENT_QUOTES, 'UTF-8') : '' ?></span>
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
                                    <option value="<?= htmlspecialchars($itemState, ENT_QUOTES, 'UTF-8') ?>" <?= $criteria['item_state'] === $itemState ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($itemState), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-field__error" id="error-item-state" data-error-for="item_state"><?= isset($errors['item_state']) ? htmlspecialchars((string) $errors['item_state'], ENT_QUOTES, 'UTF-8') : '' ?></span>
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
                                <option value="all" <?= $criteria['sale_state'] === 'all' ? 'selected' : '' ?>>Toutes</option>
                                <option value="active" <?= $criteria['sale_state'] === 'active' ? 'selected' : '' ?>>En cours</option>
                                <option value="ended" <?= $criteria['sale_state'] === 'ended' ? 'selected' : '' ?>>Terminées</option>
                            </select>
                            <span class="form-field__error" id="error-sale-state" data-error-for="sale_state"><?= isset($errors['sale_state']) ? htmlspecialchars((string) $errors['sale_state'], ENT_QUOTES, 'UTF-8') : '' ?></span>
                        </div>

                        <div class="form-field search-form__minimum-price">
                            <label class="form-field__label" for="search-minimum-price">Prix minimum</label>
                            <input
                                class="form-control"
                                id="search-minimum-price"
                                name="minimum_price"
                                type="number"
                                min="0.01"
                                max="99999999.99"
                                step="0.01"
                                inputmode="decimal"
                                value="<?= htmlspecialchars((string) $criteria['minimum_price'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="0,00 €"
                                aria-describedby="error-minimum-price"
                                <?= isset($errors['minimum_price']) ? 'aria-invalid="true"' : '' ?>
                            >
                            <span class="form-field__error" id="error-minimum-price" data-error-for="minimum_price"><?= isset($errors['minimum_price']) ? htmlspecialchars((string) $errors['minimum_price'], ENT_QUOTES, 'UTF-8') : '' ?></span>
                        </div>

                        <div class="form-field search-form__maximum-price">
                            <label class="form-field__label" for="search-maximum-price">Prix maximum</label>
                            <input
                                class="form-control"
                                id="search-maximum-price"
                                name="maximum_price"
                                type="number"
                                min="0.01"
                                max="99999999.99"
                                step="0.01"
                                inputmode="decimal"
                                value="<?= htmlspecialchars((string) $criteria['maximum_price'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Sans limite"
                                aria-describedby="error-maximum-price"
                                <?= isset($errors['maximum_price']) ? 'aria-invalid="true"' : '' ?>
                            >
                            <span class="form-field__error" id="error-maximum-price" data-error-for="maximum_price"><?= isset($errors['maximum_price']) ? htmlspecialchars((string) $errors['maximum_price'], ENT_QUOTES, 'UTF-8') : '' ?></span>
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
                    <h2 id="results-title" tabindex="-1"><?php if ($stateKey === 'filtered_results'): ?><?= $totalItems ?> annonces correspondent à votre recherche<?php elseif ($stateKey === 'no_results'): ?>Résultats<?php else: ?>Les enchères qui se terminent bientôt<?php endif; ?></h2>
                    <p class="results-summary" data-results-summary role="status" aria-live="polite"><?php if ($stateKey === 'filtered_results'): ?><?= $totalItems ?> résultats<?php elseif ($stateKey === 'categories_unavailable'): ?><?= $totalItems ?> ventes actives · échéance croissante<?php elseif ($resultsStateKey === 'pagination'): ?><?= $totalItems ?> ventes · page <?= (int) $pagination['current_page'] ?>/<?= (int) $pagination['total_pages'] ?><?php elseif ($stateKey === 'initial'): ?><?= $totalItems ?> ventes actives · échéance croissante<?php endif; ?></p>
                </div>

                <div data-results-message>
                    <?php if ($stateKey === 'invalid_criteria' || $stateKey === 'search_error'): ?>
                        <div class="alert alert--error home-state-alert" role="alert">
                            <strong><?php if ($stateKey === 'invalid_criteria'): ?>Corrigez les critères indiqués<?php else: ?>Recherche temporairement indisponible<?php endif; ?></strong>
                            <span><?= htmlspecialchars($firstSearchError, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="search-blocked-state">
                            <h3>La recherche n’a pas été exécutée.</h3>
                            <p>Corrigez les champs signalés, puis relancez la recherche. Vos autres critères sont conservés.</p>
                            <img src="public/assets/images/illustrations/shopping-cart.png" alt="" width="116" height="164">
                        </div>
                    <?php elseif ($stateKey === 'categories_unavailable'): ?>
                        <div class="alert alert--warning home-state-alert" role="status">
                            <strong>Catégories temporairement indisponibles</strong>
                            <span>Les autres critères restent utilisables et les annonces existantes conservent leur catégorie enregistrée.</span>
                        </div>
                    <?php elseif ($stateKey === 'no_results'): ?>
                        <div class="empty-state">
                            <img src="public/assets/images/illustrations/shopping-cart.png" alt="" width="168" height="238">
                            <div>
                                <h3>Aucune annonce ne correspond à vos critères</h3>
                                <p>Modifiez un ou plusieurs critères pour élargir votre recherche.</p>
                                <a class="button button--primary" href="#search-title">Modifier mes critères</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="auction-grid" data-results-list>
                    <?php foreach ($listings as $listing): ?>
                        <article class="auction-card">
                            <div class="auction-card__media">
                                <?php if ($listing['photo_url'] !== null): ?>
                                    <img src="<?= htmlspecialchars((string) $listing['photo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie de <?= htmlspecialchars((string) $listing['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                <?php else: ?>
                                    <img class="auction-card__placeholder" src="public/assets/images/illustrations/shopping-cart.png" alt="Aucune photographie disponible" loading="lazy">
                                <?php endif; ?>
                            </div>
                            <div class="auction-card__body">
                                <p class="auction-card__category"><?php if ($listing['sale_state'] === 'active'): ?>Vente en cours<?php else: ?>Vente terminée<?php endif; ?></p>
                                <h3 class="auction-card__title"><?= htmlspecialchars((string) $listing['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <div class="auction-card__meta">
                                    <div>
                                        <span>Prix courant</span>
                                        <strong class="auction-card__price"><?= htmlspecialchars((string) $listing['current_price_label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <?php if ($listing['sale_state'] === 'active'): ?>
                                        <time
                                            class="auction-card__deadline"
                                            datetime="<?= htmlspecialchars((string) $listing['deadline_utc'], ENT_QUOTES, 'UTF-8') ?>"
                                        ><?= htmlspecialchars((string) $listing['deadline_label'], ENT_QUOTES, 'UTF-8') ?></time>
                                    <?php else: ?>
                                        <time class="auction-card__deadline" datetime="<?= htmlspecialchars((string) $listing['deadline_utc'], ENT_QUOTES, 'UTF-8') ?>">Vente terminée</time>
                                    <?php endif; ?>
                                </div>
                                <a class="auction-card__link" href="<?= htmlspecialchars((string) $listing['detail_url'], ENT_QUOTES, 'UTF-8') ?>">Voir l’annonce<span class="visually-hidden"> : <?= htmlspecialchars((string) $listing['title'], ENT_QUOTES, 'UTF-8') ?></span></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="filter-summary" data-filter-summary <?php if ($stateKey !== 'filtered_results'): ?>hidden<?php endif; ?>>
                    <h3>Critères appliqués</h3>
                    <p data-filter-summary-values><?= htmlspecialchars(implode(' · ', $filterSummaryParts), ENT_QUOTES, 'UTF-8') ?></p>
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
