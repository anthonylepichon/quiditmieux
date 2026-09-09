<?php

/**
 * Description générale : Formulaire privé de modification du compte utilisateur.
 * Rôle : Modifier le pseudo, l'adresse électronique et éventuellement le mot de passe.
 * Tâches : Afficher les erreurs par champ sans jamais réafficher un mot de passe.
 * Liens avec les autres fichiers : Est affiché par UserController.php puis inséré dans base.php.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$values = $data['values'];
$errors = $data['errors'];
$successMessage = $data['success_message'];
$alert = $data['alert'];
$csrfToken = $data['csrf_token'];
$hasErrors = $errors !== [];
$isConnected = true;
$currentPage = 'account';
$pageTitle = 'Mon compte — QUIDITMIEUX';
$pageDescription = 'Modifiez les informations de votre compte QUIDITMIEUX.';
$invalid = ['pseudo' => '', 'email' => '', 'current_password' => '', 'new_password' => '', 'new_password_confirmation' => ''];
foreach ($invalid as $field => $attribute) {
    // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
    if (isset($errors[$field])) {
        $invalid[$field] = 'aria-invalid="true"';
    }
}
?>
<main class="account-page container">
    <section class="account-card glass-panel" aria-labelledby="account-title">
        <div class="account-card__form-panel">
            <div class="section-heading">
                <p class="eyebrow">VOS INFORMATIONS</p>
                <h1 id="account-title"><?= htmlspecialchars($alert['heading'], ENT_QUOTES, 'UTF-8') ?></h1>
            </div>

            <?php
            // Fragment alert.php : message du compte déjà préparé par UserController.
            $alertClasses = '';
            require __DIR__ . '/../fragments/alert.php';
            ?>

            <form action="index.php?route=account_update" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-field__label" for="pseudo">Pseudo</label>
                        <input class="form-control" id="pseudo" name="pseudo" type="text" required minlength="3" maxlength="30" autocomplete="username" value="<?= htmlspecialchars((string) $values['pseudo'], ENT_QUOTES, 'UTF-8') ?>" aria-describedby="pseudo-help pseudo-error" <?= $invalid['pseudo'] ?>>
                        <span class="form-field__help" id="pseudo-help">
                            <?php if (!isset($errors['pseudo'])): ?>
                                3 à 30 caractères : lettres, chiffres, _ ou -
                            <?php endif; ?>
                        </span>
                        <span class="form-field__error" id="pseudo-error">
                            <?php if (isset($errors['pseudo'])): ?>
                                <?= htmlspecialchars((string) $errors['pseudo'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="form-field">
                        <label class="form-field__label" for="email">Adresse électronique</label>
                        <input class="form-control" id="email" name="email" type="email" required maxlength="255" autocomplete="email" value="<?= htmlspecialchars((string) $values['email'], ENT_QUOTES, 'UTF-8') ?>" aria-describedby="email-error" <?= $invalid['email'] ?>>
                        <span class="form-field__error" id="email-error">
                            <?php if (isset($errors['email'])): ?>
                                <?= htmlspecialchars((string) $errors['email'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="form-field form-field--full">
                        <label class="form-field__label" for="current-password">Mot de passe actuel</label>
                        <input class="form-control" id="current-password" name="current_password" type="password" required autocomplete="current-password" aria-describedby="current-password-help current-password-error" <?= $invalid['current_password'] ?>>
                        <span class="form-field__help" id="current-password-help">
                            <?php if ($successMessage !== null): ?>
                                À saisir lors d’une prochaine modification.
                            <?php elseif (!isset($errors['current_password'])): ?>
                                Requis pour toute modification.
                            <?php endif; ?>
                        </span>
                        <span class="form-field__error" id="current-password-error">
                            <?php if (isset($errors['current_password'])): ?>
                                <?= htmlspecialchars((string) $errors['current_password'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="form-field">
                        <label class="form-field__label" for="new-password">Nouveau mot de passe</label>
                        <input class="form-control" id="new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" aria-describedby="new-password-help new-password-error" <?= $invalid['new_password'] ?>>
                        <span class="form-field__help" id="new-password-help">
                            <?php if ($successMessage !== null): ?>
                                8 caractères minimum avec majuscule, minuscule, chiffre et caractère spécial.
                            <?php elseif (!isset($errors['new_password'])): ?>
                                Laisser vide pour conserver le mot de passe actuel.
                            <?php endif; ?>
                        </span>
                        <span class="form-field__error" id="new-password-error">
                            <?php if (isset($errors['new_password'])): ?>
                                <?= htmlspecialchars((string) $errors['new_password'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="form-field">
                        <label class="form-field__label" for="new-password-confirmation">Confirmation du nouveau mot de passe</label>
                        <input class="form-control" id="new-password-confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" aria-describedby="confirmation-error" <?= $invalid['new_password_confirmation'] ?>>
                        <span class="form-field__error" id="confirmation-error">
                            <?php if (isset($errors['new_password_confirmation'])): ?>
                                <?= htmlspecialchars((string) $errors['new_password_confirmation'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <p class="account-card__password-rule">Nouveau mot de passe : 8 caractères minimum, avec une majuscule, une minuscule, un chiffre et un caractère spécial.</p>
                <div class="account-card__actions">
                    <button class="button button--primary" type="submit">Enregistrer les modifications</button>
                    <p>Le nouveau mot de passe peut rester vide si vous ne souhaitez pas le modifier.</p>
                </div>
            </form>
        </div>
        <aside class="account-card__illustration">
            <img src="public/assets/images/illustrations/character-planet.png" alt="" width="300" height="492">
            <h2>Votre profil, vos accès.</h2>
            <p>Mettez à jour vos informations en confirmant toujours avec votre mot de passe actuel.</p>
            <p class="account-card__security"><span aria-hidden="true">✓</span> Contrôle requis avant chaque enregistrement.</p>
        </aside>
    </section>
</main>
