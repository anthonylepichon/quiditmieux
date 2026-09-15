<?php

/**
 * Description générale : Bloc commun des messages temporaires stockés en session.
 * Rôle : Afficher un éventuel succès puis un éventuel avertissement déjà préparés par le contrôleur. Le template reste ainsi consacré à la présentation des données déjà préparées, sans décider des règles métier.
 * Tâches : Échapper les messages et conserver leur rôle d'accessibilité.
 * Liens avec les autres fichiers : Est inclus par home.php, dashboard.php et listing-detail.php.
 */

/** @var string|null $flashSuccess Message temporaire de réussite. */
/** @var string|null $flashNotice Message temporaire d'information ou d'avertissement. */
?>
<?php if ($flashSuccess !== null): ?>
    <div class="alert alert--success" role="status">
        <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($flashNotice !== null): ?>
    <div class="alert alert--warning" role="status">
        <?= htmlspecialchars($flashNotice, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>
