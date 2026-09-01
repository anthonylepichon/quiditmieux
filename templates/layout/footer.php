<?php

/**
 * Description générale : Pied de page partagé par toutes les pages de l'application.
 * Rôle : Regrouper l'identité et les liens secondaires de QUIDITMIEUX.
 * Tâches : Centraliser la navigation de fin de page et le lien de confidentialité demandé.
 * Liens avec les autres fichiers : Est inclus par base.php après le contenu propre à chaque page.
 */
?>
<footer class="site-footer">
    <div class="site-footer__inner">
        <a class="brand" href="index.php?route=home" aria-label="QUIDITMIEUX — Accueil">
            <img src="public/assets/images/svg/logo-quiditmieux.svg" alt="QUIDITMIEUX" width="220" height="46">
        </a>
        <ul class="site-footer__links">
            <li><a href="index.php?route=home">Accueil</a></li>
            <li><a href="index.php?route=home#annonces">Annonces</a></li>
            <li><a href="index.php?route=listing_create_form">Publier une annonce</a></li>
        </ul>
        <a class="site-footer__privacy" href="index.php?route=privacy">Politique de confidentialité</a>
    </div>
</footer>
