<?php

/**
 * Description générale : Page publique d'accueil et de recherche des annonces.
 * Rôle : Afficher le formulaire multicritère, les états de recherche, les cartes et la pagination.
 * Tâches : Présenter les données préparées par ListingController et fournir une navigation sans JavaScript.
 * Liens avec les autres fichiers : Est affiché par ListingController.php et complété par main.js, home.js et main.css.
 */

$criteria = $data['criteria'];
$errors = $data['errors'];
$categories = $data['categories'];
$listings = $data['listings'];
$pagination = $data['pagination'];
$stateKey = $data['state_key'];
$message = $data['message'];
$categoriesAvailable = $data['categories_available'];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Consultez et recherchez les ventes aux enchères QUIDITMIEUX.">
    <title>Accueil — QUIDITMIEUX</title>
    <link rel="icon" href="public/assets/images/favicon/favicon.ico" sizes="any">
    <link rel="icon" href="public/assets/images/favicon/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="public/assets/images/favicon/apple-touch-icon.png">
    <link rel="stylesheet" href="public/assets/css/main.css">
    <script src="public/assets/js/main.js" defer></script>
    <script src="public/assets/js/home.js" defer></script>
</head>
<body>
    <!-- ==================== EN-TÊTE ==================== -->
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="index.php?route=home" aria-label="QUIDITMIEUX — Accueil">
                <img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="260" height="54">
            </a>
            <nav class="site-navigation" aria-label="Navigation principale">
                <ul class="site-navigation__list">
                    <li><a href="index.php?route=home" aria-current="page">Accueil</a></li>
                    <li><a href="#annonces">Annonces</a></li>
                </ul>
            </nav>
            <div class="site-header__actions">
                <a class="button button--secondary button--compact" href="index.php?route=login_form">Connexion</a>
                <a class="button button--primary button--compact" href="index.php?route=register_form">Créer un compte</a>
            </div>
        </div>
    </header>

    <main>
        <!-- ==================== PRÉSENTATION ==================== -->
        <section class="home-hero container" aria-labelledby="home-title">
            <div class="home-hero__content">
                <p class="eyebrow">Ventes aux enchères accessibles à tous</p>
                <h1 id="home-title">Donnez une seconde vie aux objets qui comptent.</h1>
                <p>Découvrez des annonces, suivez les échéances et trouvez votre prochaine opportunité.</p>
                <a class="button button--primary" href="#annonces">Découvrir les annonces</a>
            </div>
            <div class="home-hero__visual" aria-hidden="true">
                <img src="public/assets/images/illustrations/character-hero.png" alt="" width="613" height="560">
            </div>
        </section>

        <div class="container section-stack">
            <!-- ==================== RECHERCHE ==================== -->
            <section class="search-panel glass-panel" aria-labelledby="search-title">
                <div class="section-heading">
                    <p class="eyebrow">Recherche multicritère</p>
                    <h2 id="search-title">Rechercher une annonce</h2>
                    <p>Combinez plusieurs critères pour affiner les résultats.</p>
                </div>

                <?php if (!$categoriesAvailable): ?>
                    <div class="alert alert--warning" role="status">
                        Les catégories sont temporairement indisponibles. Les autres critères restent utilisables.
                    </div>
                <?php endif; ?>

                <form class="search-form" id="search-form" action="index.php" method="get" novalidate>
                    <input type="hidden" name="route" value="home">
                    <div class="search-form__grid">
                        <div class="form-field search-form__text">
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

                        <div class="form-field">
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

                        <div class="form-field">
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

                        <div class="form-field">
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

                        <div class="form-field">
                            <label class="form-field__label" for="search-minimum-price">Prix courant minimum</label>
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

                        <div class="form-field">
                            <label class="form-field__label" for="search-maximum-price">Prix courant maximum</label>
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
                        <a class="button button--secondary" href="index.php?route=home">Réinitialiser</a>
                        <button class="button button--primary" type="submit">Rechercher</button>
                    </div>
                </form>
            </section>

            <!-- ==================== RÉSULTATS ==================== -->
            <section id="annonces" class="results-section" aria-labelledby="results-title" data-results-region aria-busy="false">
                <div class="section-heading section-heading--row">
                    <div>
                        <p class="eyebrow">Annonces disponibles</p>
                        <h2 id="results-title" tabindex="-1">Les dernières opportunités</h2>
                    </div>
                    <p class="results-summary" data-results-summary role="status" aria-live="polite"><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <div data-results-message>
                    <?php if ($stateKey === 'invalid_criteria' || $stateKey === 'search_error'): ?>
                        <div class="alert alert--error" role="alert"><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php elseif ($stateKey === 'no_results'): ?>
                        <div class="empty-state">
                            <img src="public/assets/images/illustrations/shopping-cart.png" alt="" width="168" height="238">
                            <h3>Aucun résultat</h3>
                            <p>Modifiez les critères ou revenez à la liste initiale des ventes.</p>
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
                                <p class="auction-card__category"><?= htmlspecialchars((string) $listing['category'], ENT_QUOTES, 'UTF-8') ?></p>
                                <h3 class="auction-card__title"><?= htmlspecialchars((string) $listing['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="auction-card__state"><?= htmlspecialchars(ucfirst((string) $listing['item_state']), ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="auction-card__meta">
                                    <div>
                                        <span>Prix courant</span>
                                        <strong class="auction-card__price"><?= htmlspecialchars((string) $listing['current_price_label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <?php if ($listing['sale_state'] === 'active'): ?>
                                        <time
                                            class="auction-card__deadline"
                                            datetime="<?= htmlspecialchars((string) $listing['deadline_utc'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-countdown
                                            data-deadline-utc="<?= htmlspecialchars((string) $listing['deadline_utc'], ENT_QUOTES, 'UTF-8') ?>"
                                        >Fin le <?= htmlspecialchars((string) $listing['deadline_label'], ENT_QUOTES, 'UTF-8') ?></time>
                                    <?php else: ?>
                                        <time class="auction-card__deadline" datetime="<?= htmlspecialchars((string) $listing['deadline_utc'], ENT_QUOTES, 'UTF-8') ?>">Vente terminée</time>
                                    <?php endif; ?>
                                </div>
                                <a class="auction-card__link" href="<?= htmlspecialchars((string) $listing['detail_url'], ENT_QUOTES, 'UTF-8') ?>">Voir l’annonce<span class="visually-hidden"> : <?= htmlspecialchars((string) $listing['title'], ENT_QUOTES, 'UTF-8') ?></span></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <nav class="pagination" data-pagination aria-label="Pagination des annonces">
                    <?php if ($pagination['previous_url'] !== null): ?>
                        <a class="button button--secondary button--compact" href="<?= htmlspecialchars((string) $pagination['previous_url'], ENT_QUOTES, 'UTF-8') ?>" rel="prev">Page précédente</a>
                    <?php endif; ?>
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <span class="pagination__status">Page <?= (int) $pagination['current_page'] ?> sur <?= (int) $pagination['total_pages'] ?></span>
                    <?php endif; ?>
                    <?php if ($pagination['next_url'] !== null): ?>
                        <a class="button button--secondary button--compact" href="<?= htmlspecialchars((string) $pagination['next_url'], ENT_QUOTES, 'UTF-8') ?>" rel="next">Page suivante</a>
                    <?php endif; ?>
                </nav>
            </section>
        </div>
    </main>

    <!-- ==================== PIED DE PAGE ==================== -->
    <footer class="site-footer">
        <div class="site-footer__inner">
            <img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="195" height="41">
            <ul class="site-footer__links">
                <li><a href="index.php?route=home">Accueil</a></li>
                <li><a href="index.php?route=privacy">Politique de confidentialité</a></li>
            </ul>
            <p class="site-footer__legal">Ventes aux enchères entre particuliers.</p>
        </div>
    </footer>
</body>
</html>
