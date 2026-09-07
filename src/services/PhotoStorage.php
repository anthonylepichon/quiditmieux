<?php

/**
 * Description générale : Gestionnaire du stockage physique des photographies d'annonces.
 * Rôle : Enregistrer, supprimer et rendre accessibles les fichiers photographiques téléversés.
 * Tâches : Préparer le dossier de stockage, créer des noms uniques, déplacer les fichiers et construire leurs URL publiques.
 * Liens avec les autres fichiers : Est utilisé par ListingController.php pour séparer les fichiers physiques des données de PhotoModel.php.
 */

namespace App\core;

class PhotoStorage
{
    // ====================
    // CONSTANTES
    // ====================

    private const PUBLIC_DIRECTORY = 'public/uploads/annonces/';
    private const STORAGE_DIRECTORY = '/public/uploads/annonces';

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Déplacer une photographie téléversée vers le dossier permanent avec un nom unique.
     * Paramètres : Identifiant de l'annonce, chemin temporaire et extension validée du fichier.
     * Retour : Nom du fichier enregistré ou false lorsque le stockage échoue.
     */
    public function storeUploadedFile(int $listingId, string $temporaryPath, string $extension): string|false
    {
        $allowedExtensions = ['jpg', 'png', 'webp'];

        if ($listingId < 1
            || !is_uploaded_file($temporaryPath)
            || !in_array($extension, $allowedExtensions, true)
        ) {
            return false;
        }

        $directory = $this->getStorageDirectory();

        if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
            return false;
        }

        $filename = 'annonce-' . $listingId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
        $path = $directory . '/' . $filename;

        // Le déplacement réel confirme plus sûrement l’écriture que le précontrôle des droits du dossier.
        if (!move_uploaded_file($temporaryPath, $path)) {
            error_log('Impossible d’enregistrer la photographie : ' . $filename);
            return false;
        }

        return $filename;
    }

    /**
     * Rôle : Supprimer une photographie du dossier permanent lorsqu'elle n'est plus utilisée.
     * Paramètres : Nom du fichier à supprimer, sans chemin de dossier.
     * Retour : true si le fichier est absent ou supprimé, sinon false.
     */
    public function deleteFile(string $filename): bool
    {
        $safeFilename = basename($filename);

        if ($safeFilename === '' || $safeFilename !== $filename) {
            return false;
        }

        $path = $this->getStorageDirectory() . '/' . $safeFilename;

        if (!file_exists($path)) {
            return true;
        }

        // L'avertissement PHP est masqué car l'échec est contrôlé et inscrit dans le journal du serveur.
        if (!is_file($path) || !@unlink($path)) {
            error_log('Impossible de supprimer la photographie : ' . $safeFilename);
            return false;
        }

        return true;
    }

    /**
     * Rôle : Construire l'adresse publique d'une photographie enregistrée.
     * Paramètres : Nom du fichier photographique.
     * Retour : Adresse relative utilisable dans une page HTML.
     */
    public function getPublicUrl(string $filename): string
    {
        return self::PUBLIC_DIRECTORY . rawurlencode(basename($filename));
    }

    /**
     * Rôle : Construire le chemin absolu du dossier permanent des photographies.
     * Paramètres : Aucun.
     * Retour : Chemin absolu du dossier de stockage.
     */
    private function getStorageDirectory(): string
    {
        return dirname(__DIR__, 2) . self::STORAGE_DIRECTORY;
    }
}
