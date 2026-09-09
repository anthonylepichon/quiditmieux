<?php

/**
 * Description générale : Modèle des catégories fournies par l'API externe de l'application.
 * Rôle : Interroger l'API et fournir aux contrôleurs des catégories validées.
 * Tâches : Charger, valider et mettre en cache les catégories puis retrouver un libellé par identifiant.
 * Liens avec les autres fichiers : Est utilisé par les contrôleurs, n'étend pas le modèle SQL générique.
 */

namespace App\models;


class CategoryModel
{
    // ====================
    // CONSTANTES
    // ====================

    private const API_URL = 'https://api.mywebecom.ovh/play/qdm/categ.php';
    private const CACHE_LIFETIME_SECONDS = 3600;
    private const CACHE_RELATIVE_PATH = '/cache/categories.json';

    // ====================
    // ATTRIBUTS
    // ====================

    private bool $categoriesLoaded = false;
    private ?array $categories = null;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Obtenir les catégories validées depuis le cache ou l'API publique.
     * Paramètres : Aucun.
     * Retour : Catégories indexées par identifiant ou null lorsqu'aucune source n'est disponible.
     */
    public function getAllCategories(): ?array
    {
        // Les catégories déjà chargées pendant la demande sont réutilisées sans nouvel accès au cache ou à l'API.
        if ($this->categoriesLoaded) {
            return $this->categories;
        }

        $this->categoriesLoaded = true;
        // Le cache local est consulté avant l'API afin de réduire les appels externes.
        $cachedData = $this->readCachedData();

        if ($cachedData !== null && $this->cacheIsFresh($cachedData['saved_at'])) {
            $this->categories = $cachedData['categories'];
            return $this->categories;
        }

        $apiCategories = $this->requestApiCategories();

        if ($apiCategories !== null) {
            $this->categories = $apiCategories;
            $this->writeCache($apiCategories);
            return $this->categories;
        }

        if ($cachedData !== null) {
            $this->categories = $cachedData['categories'];
            return $this->categories;
        }

        return null;
    }

    /**
     * Rôle : Retrouver le libellé d'une catégorie externe à partir de son identifiant.
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
     * Rôle : Interroger l'API externe avec des délais d'attente limités.
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
     * Rôle : Lire et contrôler le dernier cache local des catégories.
     * Paramètres : Aucun.
     * Retour : Date d'enregistrement et catégories validées, ou null si le cache est inexploitable.
     */
    private function readCachedData(): ?array
    {
        // Le cache est facultatif : son absence ne doit pas empêcher la tentative d'accès à l'API.
        $cachePath = $this->getCachePath();

        // NATIF PHP : is_file() vérifie que le chemin désigne un fichier existant ; il évite ici de charger un fichier absent ou invalide.
        // NATIF PHP : is_readable() vérifie qu’un fichier peut être lu ; il évite ici une tentative de lecture impossible.
        if (!is_file($cachePath) || !is_readable($cachePath)) {
            return null;
        }

        // NATIF PHP : file_get_contents() lit l’intégralité d’un fichier dans une chaîne ; il récupère ici le contenu du cache.
        $encodedData = file_get_contents($cachePath);

        if (!is_string($encodedData) || $encodedData === '') {
            return null;
        }

        $cachedData = json_decode($encodedData, true);

        if (!is_array($cachedData)
            || !isset($cachedData['saved_at'], $cachedData['categories'])
            // NATIF PHP : is_int() vérifie qu’une valeur est un entier ; il évite ici d’utiliser un autre type dans un traitement numérique.
            || !is_int($cachedData['saved_at'])
            || !is_array($cachedData['categories'])
        ) {
            return null;
        }

        $categories = $this->normalizeCategories($cachedData['categories']);

        if ($categories === null) {
            return null;
        }

        return [
            'saved_at' => $cachedData['saved_at'],
            'categories' => $categories,
        ];
    }

    /**
     * Rôle : Indiquer si un cache peut être utilisé sans nouvel appel à l'API.
     * Paramètres : Horodatage Unix de l'enregistrement du cache.
     * Retour : true pendant l'heure suivant l'enregistrement, sinon false.
     */
    private function cacheIsFresh(int $savedAt): bool
    {
        // NATIF PHP : time() retourne l’heure Unix actuelle en secondes ; il sert ici à calculer une expiration ou la validité du cache.
        return $savedAt >= time() - self::CACHE_LIFETIME_SECONDS;
    }

    /**
     * Rôle : Enregistrer un résultat valide afin de limiter les futurs appels à l'API.
     * Paramètres : Catégories validées à conserver.
     * Retour : true lorsque le cache est enregistré, sinon false sans bloquer l'affichage.
     */
    private function writeCache(array $categories): bool
    {
        $cachePath = $this->getCachePath();
        // NATIF PHP : dirname() retourne le dossier parent d’un chemin ; il permet ici de remonter dans l’arborescence du projet.
        $cacheDirectory = dirname($cachePath);

        // NATIF PHP : is_dir() vérifie qu’un chemin correspond à un dossier ; il permet ici de savoir si le répertoire doit être créé.
        // NATIF PHP : mkdir() crée un dossier ; il prépare ici l’emplacement nécessaire au cache ou aux photographies.
        if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0775, true) && !is_dir($cacheDirectory)) {
            return false;
        }

        // NATIF PHP : json_encode() convertit une donnée PHP en JSON ; il prépare ici une réponse destinée au JavaScript ou un contenu à enregistrer.
        $encodedData = json_encode([
            'saved_at' => time(),
            'categories' => $categories,
        // NATIF PHP : JSON_UNESCAPED_UNICODE conserve les caractères Unicode lisibles dans le JSON ; elle évite ici de transformer les accents en codes.
        ], JSON_UNESCAPED_UNICODE);

        if (!is_string($encodedData)) {
            return false;
        }

        // NATIF PHP : file_put_contents() écrit une chaîne dans un fichier ; il enregistre ici le contenu du cache.
        // NATIF PHP : LOCK_EX demande un verrou exclusif pendant l’écriture du fichier ; elle évite ici deux écritures simultanées du cache.
        return file_put_contents($cachePath, $encodedData, LOCK_EX) !== false;
    }

    /**
     * Rôle : Contrôler et indexer une liste de catégories provenant de l'API ou du cache.
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

    /**
     * Rôle : Construire le chemin interne du fichier de cache ignoré par Git.
     * Paramètres : Aucun.
     * Retour : Chemin absolu du cache des catégories.
     */
    private function getCachePath(): string
    {
        // NATIF PHP : __DIR__ contient le chemin absolu du dossier du fichier courant ; elle permet ici de construire un chemin indépendant du poste utilisé.
        return dirname(__DIR__, 2) . self::CACHE_RELATIVE_PATH;
    }
}
