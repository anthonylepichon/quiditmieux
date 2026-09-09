<?php

/**
 * Description générale : Page publique de connexion à un compte utilisateur.
 * Rôle : Afficher le formulaire, les erreurs génériques et la destination interne conservée.
 * Tâches : Transmettre les identifiants par POST avec un jeton CSRF sans réafficher le mot de passe.
 * Liens avec les autres fichiers : Est affiché par AuthController.php puis inséré dans base.php.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$values = $data['values'];
$errors = $data['errors'];
$successMessage = $data['success_message'];
$alert = $data['alert'];
$csrfToken = $data['csrf_token'];
$hasErrors = $errors !== [];
$isConnected = $successMessage !== null;
$currentPage = 'login';
$pageTitle = 'Connexion — QUIDITMIEUX';
$pageDescription = 'Connectez-vous à votre compte QUIDITMIEUX.';
$pageScripts = [];
$invalidAttribute = '';

if ($hasErrors) {
    $invalidAttribute = 'aria-invalid="true"';
}

if ($successMessage !== null) {
    $pageScripts = ['public/assets/js/login.js'];
}
?>
<main class="auth-page auth-page--login container">
    <section class="auth-card auth-card--login glass-panel" aria-labelledby="login-title">
        <div class="auth-card__form-panel auth-card__form-panel--login">
            <div class="section-heading">
                <p class="eyebrow">HEUREUX DE VOUS REVOIR</p>
                <h1 id="login-title"><?= htmlspecialchars($alert['heading'], ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <?php
            // Fragment alert.php : message de connexion déjà préparé par AuthController.
            $alertClasses = 'auth-card__introduction';
            require __DIR__ . '/../fragments/alert.php';
            ?>

            <?php if ($successMessage === null): ?>
                <form action="index.php?route=login" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="destination" value="<?= htmlspecialchars((string) ($values['destination'] ?? 'dashboard'), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-field">
                        <label class="form-field__label" for="login">Pseudo ou adresse électronique</label>
                        <input class="form-control" id="login" name="login" type="text" required autocomplete="username" placeholder="camille ou camille@exemple.fr" value="<?= htmlspecialchars((string) ($values['login'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-describedby="login-error" <?= $invalidAttribute ?>>
                        <span class="form-field__error" id="login-error">
                            <?= htmlspecialchars($alert['field_error'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div class="form-field">
                        <label class="form-field__label" for="password">Mot de passe</label>
                        <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" aria-describedby="password-error" <?= $invalidAttribute ?>>
                        <span class="form-field__error" id="password-error">
                            <?= htmlspecialchars($alert['field_error'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div class="auth-card__actions">
                        <button class="button button--primary" type="submit">Se connecter</button>
                        <a href="index.php?route=register_form">Pas encore de compte ?  Créer un compte →</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="auth-confirmation auth-confirmation--login" role="status" aria-labelledby="login-confirmation-title" data-login-redirect-url="index.php?route=<?= htmlspecialchars((string) ($values['destination'] ?? 'dashboard'), ENT_QUOTES, 'UTF-8') ?>">
                    <h2 id="login-confirmation-title">Vous êtes connecté.</h2>
                    <p class="auth-confirmation__body">Votre session est ouverte. La page demandée va s’afficher automatiquement.</p>
                    <p class="auth-confirmation__note">Redirection en cours…</p>
                </div>
            <?php endif; ?>
        </div>
        <aside class="auth-card__illustration auth-card__illustration--login" aria-label="Présentation des enchères">
            <img class="auth-card__character" src="public/assets/images/illustrations/character-planet.png" alt="" width="300" height="492">
            <div class="auth-card__illustration-copy">
                <h2>Entrez dans la vente.</h2>
                <p>Suivez les objets qui vous intéressent et participez aux enchères en cours.</p>
            </div>
        </aside>
    </section>
</main>
