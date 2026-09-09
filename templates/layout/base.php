<?php

/**
 * Description générale : Structure HTML principale partagée par toutes les pages.
 * Rôle : Assembler l'en-tête technique, la navigation, le contenu de la page et le pied de page.
 * Tâches : Éviter la répétition de la structure du document et conserver un ordre d'affichage uniforme.
 * Liens avec les autres fichiers : Est affiché par Controller.php et inclut head.php, header.php et footer.php.
 */

/** @var string $content Contenu HTML construit par le template de la page. */
?>
<!doctype html>
<html lang="fr">
    <?php // Fragment head.php : métadonnées, feuilles de style et scripts propres à la page. ?>
    <?php require __DIR__ . '/head.php'; ?>
    <body>
        <?php // Fragment header.php : identité du site, navigation et actions liées à la session. ?>
        <?php require __DIR__ . '/header.php'; ?>
        <?php // Contenu de la page : il peut assembler ses propres fragments de présentation. ?>
        <?= $content ?>
        <?php // Fragment footer.php : pied de page commun et liens institutionnels. ?>
        <?php require __DIR__ . '/footer.php'; ?>
    </body>
</html>
