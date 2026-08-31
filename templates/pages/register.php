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
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="index.php?route=home" aria-label="QUIDITMIEUX — Accueil">
                <img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="260" height="54">
            </a>
            <nav class="site-navigation" aria-label="Navigation principale">
                <ul class="site-navigation__list">
                    <li><a href="index.php?route=home">Accueil</a></li>
                    <li><a href="index.php?route=home#annonces">Annonces</a></li>
                </ul>
            </nav>
            <div class="site-header__actions">
                <a class="button button--secondary button--compact" href="index.php?route=login_form">Connexion</a>
                <a class="button button--primary button--compact" href="index.php?route=register_form" aria-current="page">Créer un compte</a>
            </div>
        </div>
    </header>

    <main class="auth-page container">
        <section class="auth-card glass-panel" aria-labelledby="register-title">
            <div class="section-heading">
                <p class="eyebrow">Bienvenue dans la communauté</p>
                <h1 id="register-title">Créer un compte</h1>
                <p>Inscrivez-vous pour publier, suivre et enchérir.</p>
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
                    <div class="form-field form-field--full">
                        <label class="form-field__label" for="pseudo">Nom d’utilisateur</label>
                        <input class="form-control" id="pseudo" name="pseudo" type="text" required minlength="3" maxlength="30" autocomplete="username" value="<?= htmlspecialchars((string) ($values['pseudo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-describedby="pseudo-help pseudo-error" <?= isset($errors['pseudo']) ? 'aria-invalid="true"' : '' ?>>
                        <span class="form-field__help" id="pseudo-help">3 à 30 caractères : lettres, chiffres, tirets et tirets bas.</span>
                        <span class="form-field__error" id="pseudo-error"><?= isset($errors['pseudo']) ? htmlspecialchars((string) $errors['pseudo'], ENT_QUOTES, 'UTF-8') : '' ?></span>
                    </div>
                    <div class="form-field form-field--full">
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
                    <a href="index.php?route=login_form">J’ai déjà un compte</a>
                </div>
            </form>
        </section>
    </main>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="180" height="38">
            <ul class="site-footer__links">
                <li><a href="index.php?route=home">Accueil</a></li>
                <li><a href="index.php?route=privacy">Politique de confidentialité</a></li>
            </ul>
            <p>Ventes aux enchères entre particuliers.</p>
        </div>
    </footer>
</body>
</html>
