<?php

/**
 * Description générale : Outil commun de conversion des montants monétaires de l'application.
 * Rôle : Convertir les montants entre saisie, centimes entiers, base de données et affichage.
 * Tâches : Valider les décimales, éviter les calculs flottants et produire des formats cohérents.
 * Liens avec les autres fichiers : Est utilisé par les contrôleurs et modèles qui manipulent des prix ou des enchères.
 */

namespace App\core;

final class Money
{
    // ====================
    // CONSTANTES
    // ====================

    private const MAXIMUM_CENTS = 9_999_999_999;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Convertir un montant saisi avec un point ou une virgule en centimes entiers.
     * Paramètres : Montant textuel provenant d'un formulaire.
     * Retour : Montant en centimes ou null lorsque le format ou la limite SQL sont invalides.
     */
    public static function userInputToCents(string $amount): ?int
    {
        $normalizedAmount = str_replace(',', '.', trim($amount));
        return self::decimalToCents($normalizedAmount);
    }

    /**
     * Rôle : Convertir une valeur décimale positive issue de PDO en centimes entiers.
     * Paramètres : Montant décimal utilisant un point.
     * Retour : Montant en centimes ou null lorsque la valeur est inexploitable.
     */
    public static function decimalToCents(string $amount): ?int
    {
        if (preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/D', $amount, $matches) !== 1) {
            return null;
        }

        $euros = ltrim($matches[1], '0');

        if ($euros === '') {
            $euros = '0';
        }

        if (strlen($euros) > 8) {
            return null;
        }

        $fraction = '00';

        if (isset($matches[2])) {
            $fraction = str_pad($matches[2], 2, '0');
        }

        $amountInCents = ((int) $euros * 100) + (int) $fraction;

        if ($amountInCents > self::MAXIMUM_CENTS) {
            return null;
        }

        return $amountInCents;
    }

    /**
     * Rôle : Convertir des centimes entiers en valeur décimale compatible avec SQL et les champs HTML.
     * Paramètres : Montant positif ou nul exprimé en centimes.
     * Retour : Montant avec exactement deux décimales.
     */
    public static function centsToDecimal(int $amountInCents): string
    {
        $euros = intdiv($amountInCents, 100);
        $cents = $amountInCents % 100;
        return $euros . '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Rôle : Formater des centimes entiers pour un affichage monétaire français.
     * Paramètres : Montant positif ou nul exprimé en centimes.
     * Retour : Libellé avec séparateurs de milliers, virgule et symbole euro.
     */
    public static function formatCentsForDisplay(int $amountInCents): string
    {
        $euros = (string) intdiv($amountInCents, 100);
        $cents = $amountInCents % 100;
        $groupedEuros = preg_replace('/\B(?=(?:[0-9]{3})+(?![0-9]))/', ' ', $euros);

        if (!is_string($groupedEuros)) {
            $groupedEuros = $euros;
        }

        return $groupedEuros . ',' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT) . ' €';
    }
}
