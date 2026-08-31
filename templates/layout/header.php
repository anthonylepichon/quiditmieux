<?php

/**
 * Description générale : En-tête partagé par toutes les pages de l'application.
 * Rôle : Afficher l'identité, la navigation et les actions adaptées à la session.
 * Tâches : Centraliser les liens publics, les accès privés et le formulaire de déconnexion protégé.
 * Liens avec les autres fichiers : Est inclus par les sept templates de pages et utilise main.css ainsi que main.js.
 */

$layoutCurrentPage = '';

if (isset($currentPage) && is_string($currentPage)) {
    $layoutCurrentPage = $currentPage;
}
?>
<header class="site-header">
    <div class="site-header__inner">
        <a class="brand" href="index.php?route=home" aria-label="QUIDITMIEUX — Accueil">
            <img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="260" height="54">
        </a>
        <button class="site-header__menu-button button button--secondary button--compact" type="button" data-menu-button aria-expanded="false" aria-controls="main-navigation">Menu</button>
        <div class="site-header__navigation" id="main-navigation" data-menu-panel>
            <nav class="site-navigation" aria-label="Navigation principale">
                <ul class="site-navigation__list">
                    <li><a href="index.php?route=home" <?php if ($layoutCurrentPage === 'home'): ?>aria-current="page"<?php endif; ?>>Accueil</a></li>
                    <li><a href="index.php?route=home#annonces">Annonces</a></li>
                    <li><a href="index.php?route=listing_create_form">Publier</a></li>
                </ul>
            </nav>
            <div class="site-header__actions">
                <?php if ($isConnected): ?>
                    <a class="button button--secondary button--compact" href="index.php?route=dashboard">Tableau de bord</a>
                    <a class="button button--secondary button--compact" href="index.php?route=account_form">Mon compte</a>
                    <form class="site-header__logout" action="index.php?route=logout" method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="button button--primary button--compact" type="submit">Déconnexion</button>
                    </form>
                <?php else: ?>
                    <a class="button button--secondary button--compact" href="index.php?route=login_form">Connexion</a>
                    <a class="button button--primary button--compact" href="index.php?route=register_form">Créer un compte</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
