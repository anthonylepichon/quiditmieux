<?php

/**
 * Description générale : En-tête technique commun des documents HTML de l'application.
 * Rôle : Centraliser les métadonnées, favicons, styles et scripts de toutes les pages.
 * Tâches : Échapper les textes dynamiques, charger les ressources communes avec leur version et ajouter les scripts propres à la page.
 * Liens avec les autres fichiers : Est inclus par base.php et reçoit ses variables depuis Controller.php et le template courant.
 */

/** @var string $pageTitle Titre de la page courante. */
/** @var string $pageDescription Description de la page courante. */
/** @var array<int, string> $pageScripts Scripts propres à la page courante. */

$projectRoot = dirname(__DIR__, 2);
// NATIF PHP : filemtime() retourne la date de dernière modification d’un fichier ; il renouvelle ici l’adresse de la ressource après une mise à jour.
$mainStylesheetVersion = filemtime($projectRoot . '/public/assets/css/main.css');
$mainScriptVersion = filemtime($projectRoot . '/public/assets/js/main.js');

?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="public/assets/images/favicon/favicon.ico" sizes="any">
    <link rel="icon" href="public/assets/images/favicon/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="public/assets/images/favicon/apple-touch-icon.png">
    <link rel="stylesheet" href="public/assets/css/main.css?v=<?= (int) $mainStylesheetVersion ?>">
    <script src="public/assets/js/main.js?v=<?= (int) $mainScriptVersion ?>" defer></script>
    <?php foreach ($pageScripts as $pageScript): ?>
        <?php
            $pageScriptVersion = filemtime($projectRoot . '/' . $pageScript);
        ?>
        <script src="<?= htmlspecialchars($pageScript . '?v=' . (int) $pageScriptVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endforeach; ?>
</head>
