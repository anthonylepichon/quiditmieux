<?php

/**
 * Description générale : En-tête partagé par toutes les pages de l'application.
 * Rôle : Afficher l'identité, la navigation et les actions adaptées à la session. Le template reste ainsi consacré à la présentation des données déjà préparées, sans décider des règles métier.
 * Tâches : Centraliser les liens publics, les accès privés et le formulaire de déconnexion protégé.
 * Liens avec les autres fichiers : Est inclus par base.php et utilise les informations de session préparées par les templates.
 */

/** @var bool $isConnected Indique si un utilisateur est connecté. */
/** @var string $csrfToken Jeton de protection du formulaire de déconnexion. */
/** @var string $currentPage Identifiant de la page courante. */

$layoutCurrentPage = '';

// NATIF PHP : is_string() vérifie qu’une valeur est une chaîne de caractères ; il évite ici de traiter un type inattendu comme du texte.
// NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
if (isset($currentPage) && is_string($currentPage)) {
    $layoutCurrentPage = $currentPage;
}
?>
<header class="site-header site-header--<?php if ($isConnected): ?>connected<?php else: ?>public<?php endif; ?>">
    <div class="site-header__inner">
        <a class="brand" href="index.php?route=home" aria-label="QUIDITMIEUX — Accueil">
            <img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="260" height="54">
        </a>
        <button class="site-header__menu-button button button--secondary button--compact" type="button" data-menu-button aria-expanded="false" aria-controls="main-navigation">
            Menu
        </button>
        <div class="site-header__navigation" id="main-navigation" data-menu-panel>
            <nav class="site-navigation" aria-label="Navigation principale">
                <ul class="site-navigation__list">
                    <li>
                        <a href="index.php?route=home" <?php if ($layoutCurrentPage === 'home'): ?>aria-current="page"<?php endif; ?>>
                            Accueil
                        </a>
                    </li>
                    <li>
                        <a href="index.php?route=home#annonces">Annonces</a>
                    </li>
                    <li>
                        <a href="index.php?route=listing_create_form">Publier</a>
                    </li>
                    <?php if ($isConnected): ?>
                        <li>
                            <a href="index.php?route=dashboard" <?php if ($layoutCurrentPage === 'dashboard'): ?>aria-current="page"<?php endif; ?>>
                                Tableau de bord
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="site-header__actions">
                <?php if ($isConnected): ?>
                    <a class="button button--secondary button--compact" href="index.php?route=account_form">
                        Mon compte
                    </a>
                    <form class="site-header__logout" action="index.php?route=logout" method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="button button--primary button--compact" type="submit">
                            Déconnexion
                        </button>
                    </form>
                <?php else: ?>
                    <a class="button button--secondary button--compact" href="index.php?route=login_form">
                        Connexion
                    </a>
                    <a class="button button--primary button--compact" href="index.php?route=register_form">
                        Créer un compte
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
