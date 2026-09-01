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
$csrfToken = $data['csrf_token'];
$hasErrors = $errors !== [];
$globalErrorMessage = 'Vérifiez les informations saisies puis recommencez.';

if (isset($errors['form'])) {
    $globalErrorMessage = (string) $errors['form'];
}
$isConnected = false;
$currentPage = 'login';
$pageTitle = 'Connexion — QUIDITMIEUX';
$pageDescription = 'Connectez-vous à votre compte QUIDITMIEUX.';
?>
<main class="auth-page auth-page--login container">
        <section class="auth-card auth-card--login glass-panel" aria-labelledby="login-title">
            <div class="auth-card__form-panel auth-card__form-panel--login">
                <div class="section-heading">
                    <p class="eyebrow">Heureux de vous revoir</p>
                    <h1 id="login-title">Se connecter</h1>
                </div>
            <?php if ($hasErrors): ?>
                <div class="alert alert--error alert--illustrated auth-card__introduction" role="alert">
                    <strong>Connexion impossible</strong>
                    <span><?= htmlspecialchars($globalErrorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php elseif ($successMessage !== null): ?>
                <div class="alert alert--success alert--illustrated auth-card__introduction" role="status">
                    <strong>Connexion confirmée</strong>
                    <span><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php else: ?>
                <div class="alert alert--info auth-card__introduction" role="note">
                    <strong>Connexion sécurisée</strong>
                    <span>Utilisez votre pseudo ou votre adresse électronique.</span>
                </div>
            <?php endif; ?>
            <form action="index.php?route=login" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="destination" value="<?= htmlspecialchars((string) ($values['destination'] ?? 'dashboard'), ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-field">
                    <label class="form-field__label" for="login">Pseudo ou adresse électronique</label>
                    <input class="form-control" id="login" name="login" type="text" required autocomplete="username" placeholder="camille ou camille@exemple.fr" value="<?= htmlspecialchars((string) ($values['login'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-field">
                    <label class="form-field__label" for="password">Mot de passe</label>
                    <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••">
                </div>
                <div class="auth-card__actions">
                    <button class="button button--primary" type="submit">Se connecter</button>
                    <a href="index.php?route=register_form">Pas encore de compte ? Créer un compte →</a>
                </div>
            </form>
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
