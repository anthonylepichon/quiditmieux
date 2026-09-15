<?php

/**
 * Description générale : Formulaire protégé de création et de modification d'une annonce.
 * Rôle : Afficher les informations de vente, les catégories externes et la gestion de zéro à trois photographies. Le template reste ainsi consacré à la présentation des données déjà préparées, sans décider des règles métier.
 * Tâches : Réafficher les valeurs sûres, les erreurs et transmettre les données en multipart par POST.
 * Liens avec les autres fichiers : Est affiché par ListingManagementController.php, inséré dans base.php et complété par listing-form.js.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$values = $data['values'];
$errors = $data['errors'];
$categories = $data['categories'];
$existingPhotos = $data['existing_photos'];
$formDisplay = $data['form_display'];
$csrfToken = $data['csrf_token'];
$isConnected = true;
$currentPage = 'listing_form';
$isEditMode = $formDisplay['is_edit_mode'];
$isLocked = $formDisplay['is_locked'];
$categoriesUnavailable = $formDisplay['categories_unavailable'];
$eyebrow = $formDisplay['eyebrow'];
$lockedAttribute = $formDisplay['locked_attribute'];
$formTitle = $formDisplay['form_title'];
$formRoute = $formDisplay['form_route'];
$alert = $formDisplay['alert'];
$photoTitle = $formDisplay['photo_title'];
$photoStatus = $formDisplay['photo_status'];
$showPhotoUpload = $formDisplay['show_photo_upload'];
$actionInformation = $formDisplay['action_information'];
$buttonLabel = $formDisplay['button_label'];
$categoryPlaceholder = $formDisplay['category_placeholder'];

$pageTitle = $formTitle . ' — QUIDITMIEUX';
$pageDescription = $formTitle . ' sur QUIDITMIEUX.';
$pageScripts = ['public/assets/js/listing-form.js'];

if ($isLocked) {
    $pageScripts = [];
}
?>
<main class="listing-form-page container">
    <section class="listing-form-panel glass-panel" aria-labelledby="listing-form-title">
        <div class="listing-form-panel__introduction">
            <div class="section-heading">
                <p class="eyebrow"><?= $eyebrow ?></p>
                <h1 id="listing-form-title"><?= htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Décrivez l’objet, fixez un prix de départ et une échéance en heure de Paris.</p>
            </div>
            <img src="public/assets/images/illustrations/shopping-cart.png" alt="" width="178" height="160">
        </div>
        <?php
        // Fragment alert.php : état du formulaire déjà préparé par ListingManagementController.
        $alertClasses = '';
        require __DIR__ . '/../fragments/alert.php';
        ?>
        <form action="index.php?route=<?= $formRoute ?>" method="post" enctype="multipart/form-data" novalidate data-listing-form>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($isEditMode && isset($values['id'])): ?>
                <input type="hidden" name="id" value="<?= (int) $values['id'] ?>">
            <?php endif; ?>
            <div class="form-grid listing-form-grid">
                <div class="form-field listing-field--title">
                    <label class="form-field__label" for="title">Titre *</label>
                    <input class="form-control" id="title" name="title" type="text" required minlength="3" maxlength="255" placeholder="Titre de l’objet" value="<?= htmlspecialchars((string) $values['title'], ENT_QUOTES, 'UTF-8') ?>" aria-describedby="error-title" <?php if (isset($errors['title'])): ?>aria-invalid="true"<?php endif; ?> <?= $lockedAttribute ?>>
                    <span class="form-field__error" id="error-title">
                        <?php if (isset($errors['title']) && is_string($errors['title'])): ?>
                            <?= htmlspecialchars($errors['title'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="form-field listing-field--category">
                    <label class="form-field__label" for="category">Catégorie *</label>
                    <select class="form-control" id="category" name="category" required aria-describedby="error-category" <?= $categoriesUnavailable ? 'disabled' : '' ?> <?php if (isset($errors['category'])): ?>aria-invalid="true"<?php endif; ?> <?= $lockedAttribute ?>>
                        <option value=""><?= htmlspecialchars($categoryPlaceholder, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($categories as $identifier => $label): ?>
                            <option value="<?= (int) $identifier ?>" <?= (string) $values['category'] === (string) $identifier ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-field__error" id="error-category">
                        <?php if ($categoriesUnavailable): ?>
                            Catégories temporairement indisponibles.
                        <?php elseif (isset($errors['category']) && is_string($errors['category'])): ?>
                            <?= htmlspecialchars($errors['category'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="form-field listing-field--description">
                    <label class="form-field__label" for="description">Description *</label>
                    <textarea class="form-control" id="description" name="description" required minlength="10" maxlength="5000" placeholder="Décrivez précisément l’objet proposé." aria-describedby="error-description" <?php if (isset($errors['description'])): ?>aria-invalid="true"<?php endif; ?> <?= $lockedAttribute ?>><?= htmlspecialchars((string) $values['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    <span class="form-field__error" id="error-description">
                        <?php if (isset($errors['description']) && is_string($errors['description'])): ?>
                            <?= htmlspecialchars($errors['description'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php // NATIF PHP : ucfirst() met le premier caractère en majuscule ; il harmonise ici le libellé affiché. ?>
                <div class="form-field listing-field--state">
                    <label class="form-field__label" for="item-state">État de l’objet *</label>
                    <select class="form-control" id="item-state" name="item_state" required aria-describedby="error-item-state" <?php if (isset($errors['item_state'])): ?>aria-invalid="true"<?php endif; ?> <?= $lockedAttribute ?>>
                        <option value="">Choisir un état</option>
                        <?php foreach (['neuf', 'très bon état', 'bon état', 'état correct'] as $state): ?>
                            <option value="<?= htmlspecialchars($state, ENT_QUOTES, 'UTF-8') ?>" <?= $values['item_state'] === $state ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($state), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-field__error" id="error-item-state">
                        <?php if (isset($errors['item_state']) && is_string($errors['item_state'])): ?>
                            <?= htmlspecialchars($errors['item_state'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="form-field listing-field--price">
                    <label class="form-field__label" for="starting-price">Prix de départ *</label>
                    <input class="form-control" id="starting-price" name="starting_price" type="number" required min="1" max="99999999" step="1" inputmode="numeric" placeholder="0 €" value="<?= htmlspecialchars((string) $values['starting_price'], ENT_QUOTES, 'UTF-8') ?>" aria-describedby="error-starting-price" <?php if (isset($errors['starting_price'])): ?>aria-invalid="true"<?php endif; ?> <?= $lockedAttribute ?>>
                    <span class="form-field__error" id="error-starting-price">
                        <?php if (isset($errors['starting_price']) && is_string($errors['starting_price'])): ?>
                            <?= htmlspecialchars($errors['starting_price'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="form-field listing-field--date">
                    <label class="form-field__label" for="end-date">Date de fin *</label>
                    <input class="form-control" id="end-date" name="end_date" type="date" required placeholder="JJ/MM/AAAA" value="<?= htmlspecialchars((string) $values['end_date'], ENT_QUOTES, 'UTF-8') ?>" aria-describedby="error-end-date" <?php if (isset($errors['end_date'])): ?>aria-invalid="true"<?php endif; ?> <?= $lockedAttribute ?>>
                    <span class="form-field__error" id="error-end-date">
                        <?php if (isset($errors['end_date']) && is_string($errors['end_date'])): ?>
                            <?= htmlspecialchars($errors['end_date'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="form-field listing-field--time">
                    <label class="form-field__label" for="end-time">Heure de fin *</label>
                    <input class="form-control" id="end-time" name="end_time" type="time" required placeholder="HH:MM" value="<?= htmlspecialchars((string) $values['end_time'], ENT_QUOTES, 'UTF-8') ?>" aria-describedby="help-end-time error-end-time" <?php if (isset($errors['end_time']) || isset($errors['deadline'])): ?>aria-invalid="true"<?php endif; ?> <?= $lockedAttribute ?>>
                    <span class="form-field__help" id="help-end-time">Heure de Paris — Europe/Paris</span>
                    <span class="form-field__error" id="error-end-time">
                        <?php if (isset($errors['end_time']) && is_string($errors['end_time'])): ?>
                            <?= htmlspecialchars($errors['end_time'], ENT_QUOTES, 'UTF-8') ?>
                        <?php elseif (isset($errors['deadline']) && is_string($errors['deadline'])): ?>
                            <?= htmlspecialchars($errors['deadline'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <?php // NATIF PHP : count() compte les éléments d’un tableau ; il permet ici de connaître la quantité avant le traitement. ?>
            <fieldset class="photo-manager" data-existing-photo-count="<?= count($existingPhotos) ?>" <?php if (isset($errors['photos'])): ?>aria-invalid="true" aria-describedby="error-photos"<?php endif; ?> <?= $lockedAttribute ?>><legend>Photographies</legend><p class="photo-manager__help">3 photographies maximum · la première ajoutée est l’image principale · toute nouvelle photo est ajoutée à la fin.</p><span class="photo-manager__counter" data-photo-counter><?= count($existingPhotos) ?> / 3</span>
                <?php if (isset($errors['photos']) && is_string($errors['photos'])): ?><p class="form-field__error" id="error-photos" role="alert"><?= htmlspecialchars($errors['photos'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <div class="photo-preview-grid" data-photo-preview-grid><?php foreach ($existingPhotos as $index => $photo): ?><article class="photo-preview" data-existing-photo><img src="<?= htmlspecialchars($photo['url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie existante <?= $index + 1 ?>"><span>Photo <?= $index + 1 ?><?= $index === 0 ? ' — principale' : '' ?></span><?php if ($isLocked): ?><small>Lecture seule</small><?php else: ?><input type="checkbox" name="remove_photos[]" value="<?= (int) $photo['id'] ?>" hidden><button class="button button--secondary button--compact" type="button" data-remove-existing-photo aria-label="Supprimer la photographie <?= $index + 1 ?>">Supprimer</button><?php endif; ?></article><?php endforeach; ?><div class="photo-preview-grid__new" data-photo-previews></div></div>
                <?php if ($showPhotoUpload): ?><label class="photo-manager__upload" for="photos"><span aria-hidden="true">＋</span><strong>Ajouter une photographie</strong><small data-photo-tile-status><?= count($existingPhotos) ?> / 3 — la première ajoutée sera principale</small></label><input class="visually-hidden" id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-photo-input><?php endif; ?>
                <div class="photo-manager__copy"><strong data-photo-title><?= htmlspecialchars($photoTitle, ENT_QUOTES, 'UTF-8') ?></strong><p data-photo-status role="status"><?= htmlspecialchars($photoStatus, ENT_QUOTES, 'UTF-8') ?></p></div>
            </fieldset>
            <div class="listing-form-actions">
                <?php if ($isLocked): ?>
                    <button class="button button--secondary" type="button" disabled>Modification verrouillée</button>
                    <p><strong>Lecture seule</strong><br>Les informations restent consultables en lecture seule.</p>
                <?php else: ?>
                    <button class="button button--primary" type="submit" <?= $categoriesUnavailable ? 'disabled' : '' ?>><?= htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') ?></button>
                    <?php if ($isEditMode && isset($values['id'])): ?><button class="button button--danger" type="submit" form="listing-delete-form">Supprimer l’annonce</button><?php endif; ?>
                    <p><?= htmlspecialchars($actionInformation, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($isEditMode && !$isLocked && isset($values['id'])): ?>
            <form id="listing-delete-form" action="index.php?route=listing_delete" method="post">
                <input type="hidden" name="id" value="<?= (int) $values['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            </form>
        <?php endif; ?>
    </section>
</main>
