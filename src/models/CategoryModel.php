<?php

/**
 * Description générale : Modèle des catégories fournies par l'API externe de l'application.
 * Rôle : Interroger l'API et fournir aux contrôleurs des catégories validées.
 * Tâches : Charger, valider et mettre en cache les catégories puis retrouver un libellé par identifiant.
 * Liens avec les autres fichiers : Est utilisé par les contrôleurs, s'appuie sur Clock.php et n'étend pas le modèle SQL générique.
 */

namespace App\models;

use App\core\Clock;

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
        if ($this->categoriesLoaded) {
            return $this->categories;
        }

        $this->categoriesLoaded = true;
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
        if (!function_exists('curl_init')) {
            return null;
        }

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

        return $this->normalizeCategories($decodedCategories);
    }

    /**
     * Rôle : Lire et contrôler le dernier cache local des catégories.
     * Paramètres : Aucun.
     * Retour : Date d'enregistrement et catégories validées, ou null si le cache est inexploitable.
     */
    private function readCachedData(): ?array
    {
        $cachePath = $this->getCachePath();

        if (!is_file($cachePath) || !is_readable($cachePath)) {
            return null;
        }

        $encodedData = file_get_contents($cachePath);

        if (!is_string($encodedData) || $encodedData === '') {
            return null;
        }

        $cachedData = json_decode($encodedData, true);

        if (!is_array($cachedData)
            || !isset($cachedData['saved_at'], $cachedData['categories'])
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
        return $savedAt >= Clock::unixTimestamp() - self::CACHE_LIFETIME_SECONDS;
    }

    /**
     * Rôle : Enregistrer un résultat valide afin de limiter les futurs appels à l'API.
     * Paramètres : Catégories validées à conserver.
     * Retour : true lorsque le cache est enregistré, sinon false sans bloquer l'affichage.
     */
    private function writeCache(array $categories): bool
    {
        $cachePath = $this->getCachePath();
        $cacheDirectory = dirname($cachePath);

        if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0775, true) && !is_dir($cacheDirectory)) {
            return false;
        }

        $encodedData = json_encode([
            'saved_at' => Clock::unixTimestamp(),
            'categories' => $categories,
        ], JSON_UNESCAPED_UNICODE);

        if (!is_string($encodedData)) {
            return false;
        }

        return file_put_contents($cachePath, $encodedData, LOCK_EX) !== false;
    }

    /**
     * Rôle : Contrôler et indexer une liste de catégories provenant de l'API ou du cache.
     * Paramètres : Tableau candidat associant un identifiant à un libellé.
     * Retour : Catégories normalisées ou null si une information est invalide.
     */
    private function normalizeCategories(array $decodedCategories): ?array
    {
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
        return dirname(__DIR__, 2) . self::CACHE_RELATIVE_PATH;
    }
}
