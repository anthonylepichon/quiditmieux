<?php

/**
 * Description générale : Outil commun de conversion des montants monétaires de l'application.
 * Rôle : Convertir les montants entre saisie, euros entiers, base de données et affichage.
 * Tâches : Valider les euros entiers et produire des formats cohérents sans valeur décimale.
 * Liens avec les autres fichiers : Est utilisé par les contrôleurs et modèles qui manipulent des prix ou des enchères.
 */

namespace App\core;

final class Money
{
    // ====================
    // CONSTANTES
    // ====================

    private const MAXIMUM_EUROS = 99_999_999;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Convertir un montant saisi sans décimale en euros entiers.
     * Paramètres : Montant textuel provenant d'un formulaire.
     * Retour : Montant en euros ou null lorsque le format ou la limite autorisée sont invalides.
     */
    public static function userInputToEuros(string $amount): ?int
    {
        $normalizedAmount = trim($amount);

        if (preg_match('/^[0-9]+$/D', $normalizedAmount) !== 1) {
            return null;
        }

        return self::normalizedDigitsToEuros($normalizedAmount);
    }

    /**
     * Rôle : Convertir une valeur entière issue de PDO en euros.
     * Paramètres : Montant entier provenant de la base de données.
     * Retour : Montant en euros ou null lorsque la valeur est inexploitable.
     */
    public static function databaseValueToEuros(string $amount): ?int
    {
        if (preg_match('/^[0-9]+$/D', $amount) !== 1) {
            return null;
        }

        return self::normalizedDigitsToEuros($amount);
    }

    /**
     * Rôle : Convertir des euros entiers en valeur compatible avec SQL et les champs HTML.
     * Paramètres : Montant positif ou nul exprimé en euros.
     * Retour : Montant sans décimale.
     */
    public static function eurosToDatabaseValue(int $amountInEuros): string
    {
        return (string) $amountInEuros;
    }

    /**
     * Rôle : Formater des euros entiers pour un affichage monétaire français.
     * Paramètres : Montant positif ou nul exprimé en euros.
     * Retour : Libellé avec séparateurs de milliers et symbole euro.
     */
    public static function formatEurosForDisplay(int $amountInEuros): string
    {
        $euros = (string) $amountInEuros;
        $groupedEuros = preg_replace('/\B(?=(?:[0-9]{3})+(?![0-9]))/', ' ', $euros);

        if (!is_string($groupedEuros)) {
            $groupedEuros = $euros;
        }

        return $groupedEuros . ' €';
    }

    /**
     * Rôle : Convertir une suite de chiffres normalisée en euros dans la limite fonctionnelle.
     * Paramètres : Chiffres représentant un montant positif ou nul.
     * Retour : Montant en euros ou null lorsqu'il dépasse la limite autorisée.
     */
    private static function normalizedDigitsToEuros(string $digits): ?int
    {
        $euros = ltrim($digits, '0');

        if ($euros === '') {
            $euros = '0';
        }

        if (strlen($euros) > 8) {
            return null;
        }

        $amountInEuros = (int) $euros;

        if ($amountInEuros > self::MAXIMUM_EUROS) {
            return null;
        }

        return $amountInEuros;
    }
}
