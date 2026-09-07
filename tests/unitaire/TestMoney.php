<?php

/*
 * Description générale : Tests unitaires de la classe Money.
 * Rôle : Vérifier que les montants de la base restent des euros entiers.
 * Liens : Utilise src/core/Money.php et est execute depuis tests/Lancer.php.
 */

require_once __DIR__ . '/../../src/core/Money.php';

use App\core\Money;

$lanceurTests->verifierEgalite(
    42,
    Money::databaseValueToEuros('42'),
    "Un montant entier de la base doit être lu en euros"
);

$lanceurTests->verifierEgalite(
    null,
    Money::databaseValueToEuros('42.00'),
    "Un montant décimal ne doit plus être accepté"
);

$lanceurTests->verifierEgalite(
    null,
    Money::databaseValueToEuros('quarante-deux'),
    "Un montant non numérique ne doit pas être accepté"
);