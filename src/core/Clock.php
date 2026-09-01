<?php

/**
 * Description générale : Horloge commune de l'application.
 * Rôle : Fournir l'instant UTC utilisé par les traitements PHP et les requêtes SQL.
 * Tâches : Créer l'heure UTC courante, la formater pour la base et fournir son horodatage Unix.
 * Liens avec les autres fichiers : Est utilisée par les contrôleurs, les modèles et Session.php pour éviter plusieurs sources d'heure.
 */

namespace App\core;

use DateTimeImmutable;
use DateTimeZone;

final class Clock
{
    // ====================
    // CONSTANTES
    // ====================

    private const DATABASE_FORMAT = 'Y-m-d H:i:s';

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Fournir l'instant courant dans le fuseau UTC de référence.
     * Paramètres : Aucun.
     * Retour : Date et heure UTC courantes.
     */
    public static function nowUtc(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * Rôle : Formater une date en UTC pour une colonne DATETIME de la base.
     * Paramètres : Date et heure à convertir.
     * Retour : Date UTC au format utilisé par MySQL.
     */
    public static function formatForDatabase(DateTimeImmutable $dateTime): string
    {
        return $dateTime
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(self::DATABASE_FORMAT);
    }

    /**
     * Rôle : Fournir l'horodatage Unix courant à partir de l'horloge commune.
     * Paramètres : Aucun.
     * Retour : Nombre de secondes écoulées depuis l'origine Unix.
     */
    public static function unixTimestamp(): int
    {
        return self::nowUtc()->getTimestamp();
    }
}
