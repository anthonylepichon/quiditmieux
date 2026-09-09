<?php

/**
 * Description générale : Alerte générique affichée dans les formulaires et pages de l'application.
 * Rôle : Présenter un message déjà préparé par le contrôleur, sans en déterminer le contenu.
 * Tâches : Afficher la variante, le titre, le texte et le rôle ARIA transmis par la page appelante.
 * Liens avec les autres fichiers : Est inclus par les templates de page qui reçoivent un tableau alert depuis leur contrôleur.
 */

/** @var array<string, mixed> $alert Données textuelles et sémantiques préparées par le contrôleur. */
/** @var string $alertClasses Classes CSS complémentaires préparées par la page appelante. */

$fragmentAlertClasses = '';
$fragmentAlertIllustrated = false;

if (isset($alertClasses) && is_string($alertClasses)) {
    $fragmentAlertClasses = ' ' . $alertClasses;
}

if (isset($alert['illustrated']) && $alert['illustrated'] === true) {
    $fragmentAlertIllustrated = true;
}
?>
<div class="alert alert--<?= htmlspecialchars((string) $alert['variant'], ENT_QUOTES, 'UTF-8') ?><?php if ($fragmentAlertIllustrated): ?> alert--illustrated<?php endif; ?><?= htmlspecialchars($fragmentAlertClasses, ENT_QUOTES, 'UTF-8') ?>" role="<?= htmlspecialchars((string) $alert['role'], ENT_QUOTES, 'UTF-8') ?>">
    <strong><?= htmlspecialchars((string) $alert['title'], ENT_QUOTES, 'UTF-8') ?></strong>
    <span><?= htmlspecialchars((string) $alert['message'], ENT_QUOTES, 'UTF-8') ?></span>
</div>
