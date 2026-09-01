<?php

/**
 * Description générale : Modèle des catégories fournies par l'API externe de l'application.
 * Rôle : Interroger l'API et fournir aux contrôleurs des catégories validées.
 * Tâches : Charger les catégories, contrôler la réponse JSON et retrouver un libellé par identifiant.
 * Liens avec les autres fichiers : Est utilisé par ListingController.php sans étendre le modèle SQL générique.
 */

namespace App\models;

class CategoryModel
{
    // ====================
    // CONSTANTES
    // ====================

    private const API_URL = 'https://api.mywebecom.ovh/play/qdm/categ.php';

    // ====================
    // ATTRIBUTS
    // ====================

    private bool $categoriesLoaded = false;
    private ?array $categories = null;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Charger et valider toutes les catégories fournies par l'API publique.
     * Paramètres : Aucun.
     * Retour : Catégories indexées par identifiant ou null lorsque l'API est indisponible.
     */
    public function getAllCategories(): ?array
    {
        if ($this->categoriesLoaded) {
            return $this->categories;
        }

        $this->categoriesLoaded = true;
        $curl = curl_init(self::API_URL);

        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($response) || $statusCode !== 200) {
            return null;
        }

        $decodedCategories = json_decode($response, true);

        if (!is_array($decodedCategories)) {
            return null;
        }

        $categories = [];

        foreach ($decodedCategories as $identifier => $label) {
            $identifier = (string) $identifier;

            if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1
                || !is_string($label)
                || trim($label) === ''
            ) {
                return null;
            }

            $categories[(int) $identifier] = trim($label);
        }

        if ($categories === []) {
            return null;
        }

        $this->categories = $categories;
        return $this->categories;
    }

    /**
     * Rôle : Retrouver le libellé d'une catégorie externe à partir de son identifiant.
     * Paramètres : Identifiant de la catégorie demandée.
     * Retour : Libellé de la catégorie ou null si l'API est indisponible ou l'identifiant absent.
     */
    public function getCategoryLabel(int $categoryId): ?string
    {
        if ($categoryId < 1) {
            return null;
        }

        $categories = $this->getAllCategories();

        if ($categories === null || !isset($categories[$categoryId])) {
            return null;
        }

        return $categories[$categoryId];
    }
}
