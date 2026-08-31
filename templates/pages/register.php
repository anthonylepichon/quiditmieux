<?php

/**
 * Description générale : Page publique de création d'un compte utilisateur.
 * Rôle : Afficher le formulaire d'inscription, ses erreurs et son état de réussite.
 * Tâches : Présenter uniquement les données publiques préparées par AuthController et transmettre un POST protégé.
 * Liens avec les autres fichiers : Est affiché par AuthController.php et utilise main.css.
 */

$values = $data['values'];
$errors = $data['errors'];
$successMessage = $data['success_message'];
$csrfToken = $data['csrf_token'];
$isConnected = false;
$currentPage = 'register';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Créez votre compte QUIDITMIEUX.">
    <title>Créer un compte — QUIDITMIEUX</title>
    <link rel="icon" href="public/assets/images/favicon/favicon.ico" sizes="any">
    <link rel="icon" href="public/assets/images/favicon/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="public/assets/css/main.css">
    <script src="public/assets/js/main.js" defer></script>
</head>
<body>
    <?php require dirname(__DIR__) . '/layout/header.php'; ?>

    <main class="auth-page container">
        <section class="auth-card glass-panel" aria-labelledby="register-title">
            <div class="auth-card__form-panel">
                <div class="section-heading">
                    <p class="eyebrow">Rejoindre QUIDITMIEUX</p>
                    <h1 id="register-title">Créer votre compte</h1>
                </div>

                <div class="alert alert--info auth-card__introduction" role="note">
                    <strong>Un compte, simplement</strong>
                    <span>Renseignez les quatre champs puis vérifiez les règles indiquées.</span>
                </div>

            <?php if ($successMessage !== null): ?>
                <div class="alert alert--success" role="status"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (isset($errors['form'])): ?>
                <div class="alert alert--error" role="alert"><?= htmlspecialchars((string) $errors['form'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form action="index.php?route=register" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="honeypot" aria-hidden="true">
                    <label for="website">Site internet</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-field__label" for="pseudo">Pseudo</label>
                        <input class="form-control" id="pseudo" name="pseudo" type="text" required minlength="3" maxlength="30" autocomplete="username" value="<?= htmlspecialchars((string) ($values['pseudo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-describedby="pseudo-help pseudo-error" <?= isset($errors['pseudo']) ? 'aria-invalid="true"' : '' ?>>
                        <span class="form-field__help" id="pseudo-help">3 à 30 caractères : lettres, chiffres, tirets et tirets bas.</span>
                        <span class="form-field__error" id="pseudo-error"><?= isset($errors['pseudo']) ? htmlspecialchars((string) $errors['pseudo'], ENT_QUOTES, 'UTF-8') : '' ?></span>
                    </div>
                    <div class="form-field">
                        <label class="form-field__label" for="email">Adresse électronique</label>
                        <input class="form-control" id="email" name="email" type="email" required maxlength="254" autocomplete="email" value="<?= htmlspecialchars((string) ($values['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-describedby="email-error" <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
                        <span class="form-field__error" id="email-error"><?= isset($errors['email']) ? htmlspecialchars((string) $errors['email'], ENT_QUOTES, 'UTF-8') : '' ?></span>
                    </div>
                    <div class="form-field">
                        <label class="form-field__label" for="password">Mot de passe</label>
                        <input class="form-control" id="password" name="password" type="password" required minlength="8" autocomplete="new-password" aria-describedby="password-help password-error" <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>>
                        <span class="form-field__help" id="password-help">8 caractères minimum avec majuscule, minuscule, chiffre et caractère spécial.</span>
                        <span class="form-field__error" id="password-error"><?= isset($errors['password']) ? htmlspecialchars((string) $errors['password'], ENT_QUOTES, 'UTF-8') : '' ?></span>
                    </div>
                    <div class="form-field">
                        <label class="form-field__label" for="password-confirmation">Confirmer le mot de passe</label>
                        <input class="form-control" id="password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password" aria-describedby="confirmation-error" <?= isset($errors['password_confirmation']) ? 'aria-invalid="true"' : '' ?>>
                        <span class="form-field__error" id="confirmation-error"><?= isset($errors['password_confirmation']) ? htmlspecialchars((string) $errors['password_confirmation'], ENT_QUOTES, 'UTF-8') : '' ?></span>
                    </div>
                </div>
                <div class="auth-card__actions">
                    <button class="button button--primary" type="submit">Créer mon compte</button>
                    <a href="index.php?route=login_form">Déjà inscrit ? Se connecter →</a>
                </div>
            </form>
            </div>
            <aside class="auth-card__illustration" aria-label="Présentation de la communauté">
                <img class="auth-card__character" src="public/assets/images/illustrations/character-planet.png" alt="" width="216" height="354">
                <div class="auth-card__illustration-copy">
                    <img src="public/assets/images/icons/feature-icon-02.svg" alt="" width="70" height="70">
                    <h2>Une communauté<br>qui donne une seconde vie.</h2>
                    <p>Suivez des annonces et enchérissez sur des objets qui comptent.</p>
                </div>
            </aside>
        </section>
    </main>

    <?php require dirname(__DIR__) . '/layout/footer.php'; ?>
</body>
</html>
