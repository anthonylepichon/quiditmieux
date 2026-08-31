<?php

/**
 * Description générale : Formulaire privé de modification du compte utilisateur.
 * Rôle : Modifier le pseudo, l'adresse électronique et éventuellement le mot de passe.
 * Tâches : Afficher les erreurs par champ sans jamais réafficher un mot de passe.
 * Liens avec les autres fichiers : Est affiché par UserController.php et utilise les composants de formulaire communs.
 */

$values = $data['values'];
$errors = $data['errors'];
$successMessage = $data['success_message'];
$csrfToken = $data['csrf_token'];
$pseudoInvalid = '';
$emailInvalid = '';
$currentPasswordInvalid = '';
$newPasswordInvalid = '';
$confirmationInvalid = '';

if (isset($errors['pseudo'])) { $pseudoInvalid = 'aria-invalid="true"'; }
if (isset($errors['email'])) { $emailInvalid = 'aria-invalid="true"'; }
if (isset($errors['current_password'])) { $currentPasswordInvalid = 'aria-invalid="true"'; }
if (isset($errors['new_password'])) { $newPasswordInvalid = 'aria-invalid="true"'; }
if (isset($errors['new_password_confirmation'])) { $confirmationInvalid = 'aria-invalid="true"'; }
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Modifiez les informations de votre compte QUIDITMIEUX.">
    <title>Mon compte — QUIDITMIEUX</title>
    <link rel="icon" href="public/assets/images/favicon/favicon.ico" sizes="any">
    <link rel="icon" href="public/assets/images/favicon/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="public/assets/css/main.css">
</head>
<body>
    <header class="site-header"><div class="site-header__inner">
        <a class="brand" href="index.php?route=home" aria-label="QUIDITMIEUX — Accueil"><img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="260" height="54"></a>
        <nav class="site-navigation" aria-label="Navigation principale"><ul class="site-navigation__list"><li><a href="index.php?route=home">Accueil</a></li><li><a href="index.php?route=dashboard">Tableau de bord</a></li></ul></nav>
        <div class="site-header__actions"><a class="button button--secondary button--compact" href="index.php?route=dashboard">Tableau de bord</a><form class="site-header__logout" action="index.php?route=logout" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button class="button button--ghost button--compact" type="submit">Déconnexion</button></form></div>
    </div></header>

    <main class="auth-page container">
        <section class="auth-card glass-panel" aria-labelledby="account-title">
            <div class="section-heading"><p class="eyebrow">Espace personnel</p><h1 id="account-title">Mon compte</h1><p>Votre mot de passe actuel est obligatoire pour confirmer toute modification.</p></div>
            <?php if ($successMessage !== null): ?><div class="alert alert--success" role="status"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if (isset($errors['form'])): ?><div class="alert alert--error" role="alert"><?= htmlspecialchars((string) $errors['form'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

            <form action="index.php?route=account_update" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-grid">
                    <div class="form-field form-field--full"><label class="form-field__label" for="pseudo">Nom d’utilisateur</label><input class="form-control" id="pseudo" name="pseudo" type="text" required minlength="3" maxlength="30" autocomplete="username" value="<?= htmlspecialchars((string) $values['pseudo'], ENT_QUOTES, 'UTF-8') ?>" aria-describedby="pseudo-help pseudo-error" <?= $pseudoInvalid ?>><span class="form-field__help" id="pseudo-help">3 à 30 caractères : lettres, chiffres, tirets et tirets bas.</span><span class="form-field__error" id="pseudo-error"><?php if (isset($errors['pseudo'])): ?><?= htmlspecialchars((string) $errors['pseudo'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span></div>
                    <div class="form-field form-field--full"><label class="form-field__label" for="email">Adresse électronique</label><input class="form-control" id="email" name="email" type="email" required maxlength="254" autocomplete="email" value="<?= htmlspecialchars((string) $values['email'], ENT_QUOTES, 'UTF-8') ?>" aria-describedby="email-error" <?= $emailInvalid ?>><span class="form-field__error" id="email-error"><?php if (isset($errors['email'])): ?><?= htmlspecialchars((string) $errors['email'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span></div>
                    <div class="form-field form-field--full"><label class="form-field__label" for="current-password">Mot de passe actuel</label><input class="form-control" id="current-password" name="current_password" type="password" required autocomplete="current-password" aria-describedby="current-password-error" <?= $currentPasswordInvalid ?>><span class="form-field__error" id="current-password-error"><?php if (isset($errors['current_password'])): ?><?= htmlspecialchars((string) $errors['current_password'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span></div>
                    <div class="form-field"><label class="form-field__label" for="new-password">Nouveau mot de passe</label><input class="form-control" id="new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" aria-describedby="new-password-help new-password-error" <?= $newPasswordInvalid ?>><span class="form-field__help" id="new-password-help">Laissez vide pour conserver le mot de passe actuel.</span><span class="form-field__error" id="new-password-error"><?php if (isset($errors['new_password'])): ?><?= htmlspecialchars((string) $errors['new_password'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span></div>
                    <div class="form-field"><label class="form-field__label" for="new-password-confirmation">Confirmer le nouveau mot de passe</label><input class="form-control" id="new-password-confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" aria-describedby="confirmation-error" <?= $confirmationInvalid ?>><span class="form-field__error" id="confirmation-error"><?php if (isset($errors['new_password_confirmation'])): ?><?= htmlspecialchars((string) $errors['new_password_confirmation'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span></div>
                </div>
                <div class="auth-card__actions"><button class="button button--primary" type="submit">Enregistrer les modifications</button><a href="index.php?route=dashboard">Annuler</a></div>
            </form>
        </section>
    </main>
    <footer class="site-footer"><div class="site-footer__inner"><img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="180" height="38"><ul class="site-footer__links"><li><a href="index.php?route=home">Accueil</a></li><li><a href="index.php?route=privacy">Politique de confidentialité</a></li></ul><p>Ventes aux enchères entre particuliers.</p></div></footer>
</body>
</html>
