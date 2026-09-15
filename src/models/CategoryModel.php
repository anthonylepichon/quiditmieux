<?php

/**
 * Description générale : Modèle des catégories fournies par l'API externe.
 * Rôle : Récupérer les identifiants et les libellés des catégories afin de les utiliser dans les annonces.
 * Tâches : Lire la réponse JSON de l'API et retrouver un libellé à partir d'un identifiant.
 * Liens avec les autres fichiers : Est utilisé par les contrôleurs qui affichent ou valident une catégorie.
 */

namespace App\models;

class CategoryModel
{
    private const API_URL = 'https://api.mywebecom.ovh/play/qdm/categ.php';

    /**
     * Rôle : Lire la réponse de l'API et la convertir en tableau pour fournir les catégories aux contrôleurs.
     * Paramètres : Aucun.
     * Retour : Catégories indexées par identifiant ou null si la réponse ne peut pas être utilisée.
     */
    public function getAllCategories(): ?array
    {
        // NATIF PHP : file_get_contents() lit ici le contenu renvoyé par l'adresse de l'API.
        $response = file_get_contents(self::API_URL);

        if ($response === false) {
            return null;
        }

        // NATIF PHP : json_decode() transforme le texte JSON reçu en tableau PHP grâce au paramètre true.
        $categories = json_decode($response, true);

        // NATIF PHP : is_array() vérifie que la conversion a bien produit un tableau utilisable.
        if (!is_array($categories)) {
            return null;
        }

        return $categories;
    }

    /**
     * Rôle : Retrouver le libellé correspondant à l'identifiant enregistré avec une annonce afin de l'afficher.
     * Paramètres : Identifiant de la catégorie recherchée.
     * Retour : Libellé trouvé ou null si l'identifiant est absent.
     */
    public function getCategoryLabel(int $categoryId): ?string
    {
        $categories = $this->getAllCategories();

        // NATIF PHP : isset() vérifie que la catégorie demandée existe dans le tableau reçu.
        if ($categories === null || !isset($categories[$categoryId])) {
            return null;
        }

        return $categories[$categoryId];
    }
}
