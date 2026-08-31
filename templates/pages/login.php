<?php

/**
 * Description générale : Page publique de connexion à un compte utilisateur.
 * Rôle : Afficher le formulaire, les erreurs génériques et la destination interne conservée.
 * Tâches : Transmettre les identifiants par POST avec un jeton CSRF sans réafficher le mot de passe.
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
    <meta name="description" content="Connectez-vous à votre compte QUIDITMIEUX.">
    <title>Connexion — QUIDITMIEUX</title>
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
                <a class="button button--secondary button--compact" href="index.php?route=login_form" aria-current="page">Connexion</a>
                <a class="button button--primary button--compact" href="index.php?route=register_form">Créer un compte</a>
            </div>
        </div>
    </header>

    <main class="auth-page container">
        <section class="auth-card glass-panel" aria-labelledby="login-title">
            <div class="section-heading">
                <p class="eyebrow">Heureux de vous revoir</p>
                <h1 id="login-title">Se connecter</h1>
                <p>Retrouvez vos ventes, suivis et enchères.</p>
            </div>
            <?php if ($successMessage !== null): ?>
                <div class="alert alert--success" role="status"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (isset($errors['form'])): ?>
                <div class="alert alert--error" role="alert"><?= htmlspecialchars((string) $errors['form'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form action="index.php?route=login" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="destination" value="<?= htmlspecialchars((string) ($values['destination'] ?? 'dashboard'), ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-field">
                    <label class="form-field__label" for="login">Pseudo ou adresse électronique</label>
                    <input class="form-control" id="login" name="login" type="text" required autocomplete="username" value="<?= htmlspecialchars((string) ($values['login'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-field">
                    <label class="form-field__label" for="password">Mot de passe</label>
                    <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password">
                </div>
                <div class="auth-card__actions">
                    <button class="button button--primary" type="submit">Se connecter</button>
                    <a href="index.php?route=register_form">Créer un compte</a>
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
