<?php

/**
 * Description générale : Modèle des catégories fournies par l'API externe de l'application.
 * Rôle : Centraliser l'accès aux catégories externes afin que les contrôleurs reçoivent une liste exploitable sans connaître le fonctionnement de l'API.
 * Tâches : Charger et valider les catégories puis retrouver un libellé par identifiant.
 * Liens avec les autres fichiers : Est utilisé par les contrôleurs, n'étend pas le modèle SQL générique.
 */

namespace App\models;

class CategoryModel
{
    // ====================
    // CONSTANTES
    // ====================

    private const API_URL = 'https://api.mywebecom.ovh/play/qdm/categ.php';

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Demander la liste complète des catégories à l'API et la transmettre aux contrôleurs. Ceux-ci peuvent ainsi remplir les formulaires, valider un choix et afficher les libellés disponibles.
     * Paramètres : Aucun.
     * Retour : Catégories indexées par identifiant ou null lorsque l'API est indisponible.
     */
    public function getAllCategories(): ?array
    {
        // Le modèle interroge directement l'API chaque fois qu'un contrôleur demande les catégories.
        return $this->requestApiCategories();
    }

    /**
     * Rôle : Retrouver le libellé correspondant à l'identifiant de catégorie enregistré avec une annonce. Cela permet d'afficher le nom de la catégorie sans le stocker dans la base de données.
     * Paramètres : Identifiant de la catégorie demandée.
     * Retour : Libellé de la catégorie ou null si les données sont indisponibles ou l'identifiant absent.
     */
    public function getCategoryLabel(int $categoryId): ?string
    {
        if ($categoryId < 1) {
            return null;
        }

        $categories = $this->getAllCategories();

        // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
        if ($categories === null || !isset($categories[$categoryId])) {
            return null;
        }

        return $categories[$categoryId];
    }

    /**
     * Rôle : Envoyer la demande HTTP à l'API puis convertir sa réponse JSON en tableau PHP. En cas d'échec de connexion, de statut HTTP incorrect ou de réponse inexploitable, la méthode retourne null au lieu de transmettre une donnée incorrecte.
     * Paramètres : Aucun.
     * Retour : Catégories validées ou null lorsque la requête échoue.
     */
    private function requestApiCategories(): ?array
    {
        // NATIF PHP : function_exists() vérifie qu’une fonction est disponible dans l’installation PHP ; il contrôle ici la présence de l’extension nécessaire.
        if (!function_exists('curl_init')) {
            return null;
        }

        // NATIF PHP : curl_init() initialise une requête HTTP avec l’extension cURL ; il prépare ici l’appel vers l’API des catégories.
        $curl = curl_init(self::API_URL);

        if ($curl === false) {
            return null;
        }

        // NATIF PHP : curl_setopt_array() applique plusieurs options à une requête cURL ; il configure ici les délais et le format de réponse attendus.
        curl_setopt_array($curl, [
            // NATIF PHP : CURLOPT_RETURNTRANSFER demande à cURL de retourner la réponse au lieu de l’afficher ; elle permet ici de traiter le JSON reçu.
            CURLOPT_RETURNTRANSFER => true,
            // NATIF PHP : CURLOPT_CONNECTTIMEOUT limite le temps de connexion de cURL ; elle évite ici de bloquer longtemps si l’API ne répond pas.
            CURLOPT_CONNECTTIMEOUT => 3,
            // NATIF PHP : CURLOPT_TIMEOUT limite la durée totale de la requête cURL ; elle protège ici l’application contre une attente excessive.
            CURLOPT_TIMEOUT => 5,
            // NATIF PHP : CURLOPT_FOLLOWLOCATION autorise cURL à suivre une redirection HTTP ; elle permet ici d’atteindre l’adresse finale de l’API.
            CURLOPT_FOLLOWLOCATION => false,
            // NATIF PHP : CURLOPT_HTTPHEADER fournit les en-têtes envoyés par cURL ; elle précise ici le format de réponse accepté.
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        // NATIF PHP : curl_exec() exécute la requête cURL ; il récupère ici la réponse envoyée par l’API.
        $response = curl_exec($curl);
        // NATIF PHP : curl_getinfo() retourne une information sur la requête cURL ; il contrôle ici le statut HTTP reçu.
        // NATIF PHP : CURLINFO_RESPONSE_CODE demande le statut HTTP reçu par cURL ; elle vérifie ici que l’API a répondu avec succès.
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        // NATIF PHP : curl_close() termine l’utilisation de la ressource cURL ; il libère ici la requête devenue inutile.
        curl_close($curl);

        // NATIF PHP : is_string() vérifie qu’une valeur est une chaîne de caractères ; il évite ici de traiter un type inattendu comme du texte.
        if (!is_string($response) || $statusCode !== 200) {
            return null;
        }

        // NATIF PHP : json_decode() convertit un texte JSON en donnée PHP ; il permet ici d’exploiter la réponse reçue.
        $decodedCategories = json_decode($response, true);

        // NATIF PHP : is_array() vérifie qu’une valeur est un tableau ; il évite ici de parcourir ou transmettre un type inattendu.
        if (!is_array($decodedCategories)) {
            return null;
        }

        return $this->normalizeCategories($decodedCategories);
    }

    /**
     * Rôle : Vérifier que chaque catégorie reçue possède un identifiant entier positif et un libellé non vide, puis l'indexer par son identifiant. Une entrée incorrecte est ainsi refusée avant d'être utilisée dans un formulaire ou une recherche.
     * Paramètres : Tableau candidat associant un identifiant à un libellé.
     * Retour : Catégories normalisées ou null si une information est invalide.
     */
    private function normalizeCategories(array $decodedCategories): ?array
    {
        // Chaque entrée est contrôlée avant de devenir une catégorie utilisable dans les formulaires et recherches.
        $categories = [];

        foreach ($decodedCategories as $identifier => $label) {
            $identifier = (string) $identifier;

            // NATIF PHP : preg_match() vérifie un texte avec une expression régulière ; il contrôle ici que la valeur respecte le format attendu.
            if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1
                || !is_string($label)
                // NATIF PHP : trim() retire les espaces placés au début et à la fin du texte ; il normalise ici une valeur reçue avant son contrôle.
                || trim($label) === ''
            ) {
                return null;
            }

            $categories[(int) $identifier] = trim($label);
        }

        if ($categories === []) {
            return null;
        }

        // NATIF PHP : ksort() trie un tableau selon ses clés ; il garantit ici un ordre stable des catégories.
        // NATIF PHP : SORT_NUMERIC demande un tri numérique ; elle ordonne ici les identifiants selon leur valeur et non comme du texte.
        ksort($categories, SORT_NUMERIC);

        return $categories;
    }
}
