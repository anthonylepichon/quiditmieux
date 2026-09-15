<?php

/**
 * Description générale : Gestionnaire du stockage physique des photographies d'annonces.
 * Rôle : Enregistrer, supprimer et rendre accessibles les fichiers photographiques téléversés.
 * Tâches : Préparer le dossier de stockage, créer des noms uniques, déplacer les fichiers et construire leurs URL publiques.
 * Liens avec les autres fichiers : Est utilisé par les contrôleurs d'annonce et DashboardController.php pour séparer les fichiers physiques des données de PhotoModel.php.
 */

namespace App\services;

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
        // Cette liste limite les formats que le gestionnaire peut enregistrer.
        $allowedExtensions = ['jpg', 'png', 'webp'];

        // L'identifiant, l'origine HTTP et l'extension sont contrôlés avant tout déplacement.
        if ($listingId < 1
            // NATIF PHP : is_uploaded_file() confirme que le fichier provient réellement d’un téléversement HTTP ; il sécurise ici le traitement de la photographie.
            || !is_uploaded_file($temporaryPath)
            // NATIF PHP : in_array() recherche une valeur dans un tableau ; il vérifie ici que la donnée appartient à la liste autorisée.
            || !in_array($extension, $allowedExtensions, true)
        ) {
            // Aucun fichier n'est enregistré lorsque les données reçues sont invalides.
            return false;
        }

        // Le dossier est obtenu par une méthode unique pour centraliser son chemin.
        $directory = $this->getStorageDirectory();

        // NATIF PHP : is_dir() vérifie qu’un chemin correspond à un dossier ; il permet ici de savoir si le répertoire doit être créé.
        // NATIF PHP : mkdir() crée un dossier ; il prépare ici l’emplacement nécessaire au cache ou aux photographies.
        if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
            // L'écriture s'arrête si le dossier permanent ne peut pas être préparé.
            return false;
        }

        // Le nom combine l'annonce et une partie aléatoire pour éviter les collisions.
        // NATIF PHP : bin2hex() convertit des octets en texte hexadécimal ; il produit ici une valeur sûre à stocker dans un nom ou un jeton.
        // NATIF PHP : random_bytes() génère des octets aléatoires sécurisés ; il crée ici un jeton ou un nom de fichier difficile à deviner.
        $filename = 'annonce-' . $listingId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
        $path = $directory . '/' . $filename;

        // Le déplacement réel confirme plus sûrement l’écriture que le précontrôle des droits du dossier.
        // NATIF PHP : move_uploaded_file() déplace de façon sécurisée un fichier téléversé ; il enregistre ici la photographie dans le dossier prévu.
        if (!move_uploaded_file($temporaryPath, $path)) {
            // Le détail technique reste dans le journal serveur, hors de l'affichage utilisateur.
            // NATIF PHP : error_log() écrit une information dans le journal du serveur ; il conserve ici la cause technique sans afficher de détail sensible.
            error_log('Impossible d’enregistrer la photographie : ' . $filename);
            return false;
        }

        // Seul le nom est renvoyé afin d'être enregistré dans la base de données.
        return $filename;
    }

    /**
     * Rôle : Supprimer une photographie du dossier permanent lorsqu'elle n'est plus utilisée.
     * Paramètres : Nom du fichier à supprimer, sans chemin de dossier.
     * Retour : true si le fichier est absent ou supprimé, sinon false.
     */
    public function deleteFile(string $filename): bool
    {
        // Le nom est isolé pour empêcher l'utilisation d'un chemin transmis par l'appelant.
        // NATIF PHP : basename() retourne uniquement le nom final d’un chemin ; il empêche ici l’utilisation de dossiers fournis dans le nom.
        $safeFilename = basename($filename);

        if ($safeFilename === '' || $safeFilename !== $filename) {
            // Un nom vide ou contenant un chemin n'est jamais utilisé pour une suppression.
            return false;
        }

        // La suppression est limitée au dossier permanent des photographies.
        $path = $this->getStorageDirectory() . '/' . $safeFilename;

        // NATIF PHP : file_exists() vérifie l’existence d’un chemin ; il permet ici de considérer comme déjà absent un fichier supprimé auparavant.
        if (!file_exists($path)) {
            // Un fichier déjà absent satisfait le résultat attendu de la suppression.
            return true;
        }

        // L'avertissement PHP est masqué car l'échec est contrôlé et inscrit dans le journal du serveur.
        // NATIF PHP : is_file() vérifie que le chemin désigne un fichier existant ; il évite ici de charger un fichier absent ou invalide.
        // NATIF PHP : unlink() supprime un fichier du disque ; il retire ici la photographie qui ne doit plus être conservée.
        if (!is_file($path) || !@unlink($path)) {
            // Un échec est consigné sans divulguer le chemin au visiteur.
            error_log('Impossible de supprimer la photographie : ' . $safeFilename);
            return false;
        }

        // Le fichier appartenant au stockage a été supprimé avec succès.
        return true;
    }

    /**
     * Rôle : Construire l'adresse publique d'une photographie enregistrée.
     * Paramètres : Nom du fichier photographique.
     * Retour : Adresse relative utilisable dans une page HTML.
     */
    public function getPublicUrl(string $filename): string
    {
        // Seul le nom final encodé est ajouté à l'adresse publique.
        // NATIF PHP : rawurlencode() encode une valeur pour son utilisation dans une URL ; il produit ici une adresse de photographie valide.
        return self::PUBLIC_DIRECTORY . rawurlencode(basename($filename));
    }

    /**
     * Rôle : Construire le chemin absolu du dossier permanent des photographies.
     * Paramètres : Aucun.
     * Retour : Chemin absolu du dossier de stockage.
     */
    private function getStorageDirectory(): string
    {
        // Le chemin absolu est reconstruit depuis le projet pour rester indépendant du poste.
        // NATIF PHP : dirname() retourne le dossier parent d’un chemin ; il permet ici de remonter dans l’arborescence du projet.
        // NATIF PHP : __DIR__ contient le chemin absolu du dossier du fichier courant ; elle permet ici de construire un chemin indépendant du poste utilisé.
        return dirname(__DIR__, 2) . self::STORAGE_DIRECTORY;
    }
}
