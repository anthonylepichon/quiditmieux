<?php

/**
 * Description générale : Page publique de création d'un compte utilisateur.
 * Rôle : Afficher le formulaire d'inscription, ses erreurs et son état de réussite. Le template reste ainsi consacré à la présentation des données déjà préparées, sans décider des règles métier.
 * Tâches : Présenter uniquement les données publiques préparées par AuthController et transmettre un POST protégé.
 * Liens avec les autres fichiers : Est affiché par AuthController.php puis inséré dans base.php.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$values = $data['values'];
$errors = $data['errors'];
$successMessage = $data['success_message'];
$alert = $data['alert'];
$csrfToken = $data['csrf_token'];
$hasErrors = $errors !== [];
$isConnected = false;
$currentPage = 'register';
$pageTitle = 'Créer un compte — QUIDITMIEUX';
$pageDescription = 'Créez votre compte QUIDITMIEUX.';
$pageScripts = ['public/assets/js/register.js'];
?>
<main class="auth-page auth-page--register container">
    <section class="auth-card auth-card--register glass-panel" aria-labelledby="register-title">
        <div class="auth-card__form-panel">
            <div class="section-heading">
                <p class="eyebrow">REJOINDRE QUIDITMIEUX</p>
                <h1 id="register-title"><?= htmlspecialchars($alert['heading'], ENT_QUOTES, 'UTF-8') ?></h1>
            </div>

            <?php
            // Fragment alert.php : message d'inscription déjà préparé par AuthController.
            $alertClasses = 'auth-card__introduction';
            require __DIR__ . '/../fragments/alert.php';
            ?>

            <?php if ($successMessage === null): ?>
                <form action="index.php?route=register" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="honeypot" aria-hidden="true">
                        <label for="website">Site internet</label>
                        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field__label" for="pseudo">Pseudo</label>
                            <input class="form-control" id="pseudo" name="pseudo" type="text" required minlength="3" maxlength="30" autocomplete="username" placeholder="Votre pseudo" value="<?= htmlspecialchars((string) ($values['pseudo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-describedby="pseudo-help pseudo-error" <?= isset($errors['pseudo']) ? 'aria-invalid="true"' : '' ?>>
                            <span class="form-field__help visually-hidden" id="pseudo-help">Pseudo : 3 à 30 caractères, lettres, chiffres, _ ou -, sans espace ni @.</span>
                        <span class="form-field__error" id="pseudo-error">
                            <?= isset($errors['pseudo']) ? htmlspecialchars((string) $errors['pseudo'], ENT_QUOTES, 'UTF-8') : '' ?>
                        </span>
                        </div>
                        <div class="form-field">
                            <label class="form-field__label" for="email">Adresse électronique</label>
                            <input class="form-control" id="email" name="email" type="email" required maxlength="255" autocomplete="email" placeholder="vous@exemple.fr" value="<?= htmlspecialchars((string) ($values['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-describedby="email-error" <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
                        <span class="form-field__error" id="email-error">
                            <?= isset($errors['email']) ? htmlspecialchars((string) $errors['email'], ENT_QUOTES, 'UTF-8') : '' ?>
                        </span>
                        </div>
                        <div class="form-field">
                            <label class="form-field__label" for="password">Mot de passe</label>
                            <input class="form-control" id="password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="••••••••" aria-describedby="password-help password-error" <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>>
                            <span class="form-field__help visually-hidden" id="password-help">Mot de passe : 8 caractères minimum avec majuscule, minuscule, chiffre et caractère spécial.</span>
                        <span class="form-field__error" id="password-error">
                            <?= isset($errors['password']) ? htmlspecialchars((string) $errors['password'], ENT_QUOTES, 'UTF-8') : '' ?>
                        </span>
                        </div>
                        <div class="form-field">
                            <label class="form-field__label" for="password-confirmation">Confirmation du mot de passe</label>
                            <input class="form-control" id="password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="••••••••" aria-describedby="confirmation-error" <?= isset($errors['password_confirmation']) ? 'aria-invalid="true"' : '' ?>>
                        <span class="form-field__error" id="confirmation-error">
                            <?= isset($errors['password_confirmation']) ? htmlspecialchars((string) $errors['password_confirmation'], ENT_QUOTES, 'UTF-8') : '' ?>
                        </span>
                        </div>
                    </div>
                    <div class="auth-card__rules">
                        <p>Pseudo : 3 à 30 caractères, lettres, chiffres, _ ou -, sans espace ni @.</p>
                        <p>Mot de passe : 8 caractères minimum avec majuscule, minuscule, chiffre et caractère spécial.</p>
                    </div>
                    <div class="privacy-consent form-field">
                        <label class="privacy-consent__label" for="privacy-policy">
                            <?php // NATIF PHP : empty() indique si une valeur est absente ou considérée comme vide ; il permet ici de détecter une donnée non renseignée. ?>
                            <input id="privacy-policy" name="privacy_policy" type="checkbox" value="1" required data-privacy-policy aria-describedby="privacy-policy-error" <?= !empty($values['privacy_policy']) ? 'checked' : '' ?>>
                            <span>J’ai lu et j’accepte la <a href="index.php?route=privacy">politique de confidentialité</a>.</span>
                        </label>
                    <span class="form-field__error" id="privacy-policy-error">
                        <?= isset($errors['privacy_policy']) ? htmlspecialchars((string) $errors['privacy_policy'], ENT_QUOTES, 'UTF-8') : '' ?>
                    </span>
                    </div>
                    <div class="auth-card__actions">
                        <button class="button button--primary" type="submit" data-register-button disabled aria-disabled="true">Créer mon compte</button>
                        <a href="index.php?route=login_form">Déjà inscrit ?  Se connecter →</a>
                    </div>
                <noscript>
                    <p class="auth-card__noscript">JavaScript doit être activé pour confirmer la politique de confidentialité et créer le compte.</p>
                </noscript>
                </form>
            <?php else: ?>
                <div class="auth-confirmation auth-confirmation--register" role="status" aria-labelledby="register-confirmation-title">
                    <h2 id="register-confirmation-title">Votre compte est prêt.</h2>
                    <p class="auth-confirmation__body">Connectez-vous avec votre pseudo ou votre adresse électronique pour suivre une annonce ou enchérir.</p>
                    <p class="auth-confirmation__note">Votre compte vous permet désormais de suivre les annonces et d’enchérir.</p>
                    <a class="button button--primary" href="index.php?route=login_form">Se connecter</a>
                </div>
            <?php endif; ?>
        </div>
        <aside class="auth-card__illustration" aria-label="Présentation de la communauté">
            <img class="auth-card__halo" src="public/assets/images/decorations/halo-register.svg" alt="" width="360" height="360">
            <img class="auth-card__character" src="public/assets/images/illustrations/character-planet.png" alt="" width="216" height="354">
            <div class="auth-card__illustration-copy">
                <img src="public/assets/images/icons/feature-icon-02.svg" alt="" width="70" height="70">
                <h2>Une communauté<br>qui donne une seconde vie.</h2>
                <p>Suivez des annonces et enchérissez sur des objets qui comptent.</p>
            </div>
        </aside>
    </section>
</main>
