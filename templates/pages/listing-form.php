<?php

/**
 * Description générale : Formulaire protégé de création et de modification d'une annonce.
 * Rôle : Afficher les informations de vente, les catégories externes et la gestion de zéro à trois photographies.
 * Tâches : Réafficher les valeurs sûres, les erreurs et transmettre les données en multipart par POST.
 * Liens avec les autres fichiers : Est affiché par ListingController.php et complété par listing-form.js et main.css.
 */

$mode = $data['mode'];
$values = $data['values'];
$errors = $data['errors'];
$categories = $data['categories'];
$existingPhotos = $data['existing_photos'];
$csrfToken = $data['csrf_token'];
$isConnected = true;
$currentPage = 'listing_form';
$isEditMode = $mode === 'edit';
$pageTitle = 'Publier une annonce';
$formRoute = 'listing_create';
$submitLabel = 'Publier l’annonce';

if ($isEditMode) {
    $pageTitle = 'Modifier l’annonce';
    $formRoute = 'listing_update';
    $submitLabel = 'Enregistrer les modifications';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> sur QUIDITMIEUX.">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — QUIDITMIEUX</title>
    <link rel="icon" href="public/assets/images/favicon/favicon.ico" sizes="any"><link rel="icon" href="public/assets/images/favicon/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="public/assets/css/main.css"><script src="public/assets/js/main.js" defer></script><script src="public/assets/js/listing-form.js" defer></script>
</head>
<body>
    <?php require dirname(__DIR__) . '/layout/header.php'; ?>

    <main class="listing-form-page container">
        <section class="listing-form-panel glass-panel" aria-labelledby="listing-form-title">
            <div class="listing-form-panel__introduction"><div class="section-heading"><p class="eyebrow">Nouvelle vente</p><h1 id="listing-form-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1><p>Décrivez l’objet, fixez un prix de départ et une échéance en heure de Paris.</p></div><img src="public/assets/images/illustrations/shopping-cart.png" alt="" width="178" height="160"></div>
            <div class="alert alert--info" role="note"><strong>Préparez votre vente</strong><br>Tous les champs marqués sont requis. Vous pouvez ajouter jusqu’à trois photographies.</div>
            <?php if (isset($errors['form'])): ?><div class="alert alert--error" role="alert"><?= htmlspecialchars((string) $errors['form'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <form action="index.php?route=<?= $formRoute ?>" method="post" enctype="multipart/form-data" novalidate data-listing-form>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($isEditMode && isset($values['id'])): ?><input type="hidden" name="id" value="<?= (int) $values['id'] ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="form-field"><label class="form-field__label" for="title">Titre *</label><input class="form-control" id="title" name="title" type="text" required minlength="3" maxlength="255" value="<?= htmlspecialchars((string) $values['title'], ENT_QUOTES, 'UTF-8') ?>" <?= isset($errors['title']) ? 'aria-invalid="true"' : '' ?>><span class="form-field__error"><?= isset($errors['title']) ? htmlspecialchars((string) $errors['title'], ENT_QUOTES, 'UTF-8') : '' ?></span></div>
                    <div class="form-field"><label class="form-field__label" for="category">Catégorie</label><select class="form-control" id="category" name="category" required <?= $categories === [] ? 'disabled' : '' ?>><option value="">Choisir une catégorie</option><?php foreach ($categories as $identifier => $label): ?><option value="<?= (int) $identifier ?>" <?= (string) $values['category'] === (string) $identifier ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><span class="form-field__error"><?= isset($errors['category']) ? htmlspecialchars((string) $errors['category'], ENT_QUOTES, 'UTF-8') : '' ?></span></div>
                    <div class="form-field"><label class="form-field__label" for="item-state">État de l’objet</label><select class="form-control" id="item-state" name="item_state" required><option value="">Choisir un état</option><?php foreach (['neuf', 'très bon état', 'bon état', 'état correct'] as $state): ?><option value="<?= htmlspecialchars($state, ENT_QUOTES, 'UTF-8') ?>" <?= $values['item_state'] === $state ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($state), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><span class="form-field__error"><?= isset($errors['item_state']) ? htmlspecialchars((string) $errors['item_state'], ENT_QUOTES, 'UTF-8') : '' ?></span></div>
                    <div class="form-field form-field--full"><label class="form-field__label" for="description">Description</label><textarea class="form-control" id="description" name="description" required minlength="10" maxlength="5000" <?= isset($errors['description']) ? 'aria-invalid="true"' : '' ?>><?= htmlspecialchars((string) $values['description'], ENT_QUOTES, 'UTF-8') ?></textarea><span class="form-field__error"><?= isset($errors['description']) ? htmlspecialchars((string) $errors['description'], ENT_QUOTES, 'UTF-8') : '' ?></span></div>
                    <div class="form-field"><label class="form-field__label" for="starting-price">Prix de départ</label><input class="form-control" id="starting-price" name="starting_price" type="number" required min="0.01" step="0.01" value="<?= htmlspecialchars((string) $values['starting_price'], ENT_QUOTES, 'UTF-8') ?>"><span class="form-field__error"><?= isset($errors['starting_price']) ? htmlspecialchars((string) $errors['starting_price'], ENT_QUOTES, 'UTF-8') : '' ?></span></div>
                    <div></div>
                    <div class="form-field"><label class="form-field__label" for="end-date">Date de fin</label><input class="form-control" id="end-date" name="end_date" type="date" required value="<?= htmlspecialchars((string) $values['end_date'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="form-field"><label class="form-field__label" for="end-time">Heure de fin</label><input class="form-control" id="end-time" name="end_time" type="time" required value="<?= htmlspecialchars((string) $values['end_time'], ENT_QUOTES, 'UTF-8') ?>"><span class="form-field__error"><?= isset($errors['deadline']) ? htmlspecialchars((string) $errors['deadline'], ENT_QUOTES, 'UTF-8') : '' ?></span></div>
                </div>

                <fieldset class="photo-manager"><legend>Photographies</legend><p class="form-field__help">3 photographies maximum · la première ajoutée est l’image principale · toute nouvelle photo est ajoutée à la fin.</p>
                    <?php if (isset($errors['photos'])): ?><p class="form-field__error" role="alert"><?= htmlspecialchars((string) $errors['photos'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    <?php if ($existingPhotos !== []): ?><div class="photo-preview-grid" data-existing-photos><?php foreach ($existingPhotos as $index => $photo): ?><article class="photo-preview"><img src="<?= htmlspecialchars($photo['url'], ENT_QUOTES, 'UTF-8') ?>" alt="Photographie existante <?= $index + 1 ?>"><span>Image <?= $index + 1 ?><?= $index === 0 ? ' — principale' : '' ?></span><label><input type="checkbox" name="remove_photos[]" value="<?= (int) $photo['id'] ?>"> Retirer</label></article><?php endforeach; ?></div><?php endif; ?>
                    <label class="button button--secondary" for="photos">Choisir des images</label><input class="visually-hidden" id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-photo-input>
                    <div class="photo-preview-grid" data-photo-previews></div><p class="form-field__help" data-photo-status role="status">Aucune nouvelle photographie sélectionnée.</p>
                </fieldset>
                <div class="listing-form-actions"><a class="button button--secondary" href="index.php?route=dashboard">Annuler</a><button class="button button--primary" type="submit" <?= $categories === [] ? 'disabled' : '' ?>><?= htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8') ?></button></div>
            </form>
        </section>
    </main>
    <?php require dirname(__DIR__) . '/layout/footer.php'; ?>
</body></html>
