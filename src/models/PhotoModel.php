<?php

/**
 * Description générale : Modèle des photographies associées aux annonces.
 * Rôle : Fournir la photographie principale nécessaire aux cartes d'annonce.
 * Tâches : Déclarer la table PHOTOGRAPHIE et sélectionner la première photographie dans l'ordre.
 * Liens avec les autres fichiers : Étend Model.php et complète les résultats affichés par home.php.
 */

namespace App\models;

use App\core\Model;

class PhotoModel extends Model
{
    // ====================
    // ATTRIBUTS
    // ====================

    // Métadonnées utilisées par le modèle parent pour les colonnes autorisées d'une photographie.
    protected string $tableName = 'PHOTOGRAPHIE';
    protected string $primaryKeyName = 'id';
    protected array $writableFields = [
        'annonce_id',
        'ref_fichier',
        'ordre',
    ];

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Associer une nouvelle photographie à une annonce dans son ordre d'affichage.
     * Paramètres : Identifiant de l'annonce, nom sécurisé du fichier et position d'affichage.
     * Retour : true lorsque la photographie est enregistrée, sinon false.
     */
    public function addPhoto(int $listingId, string $filename, int $order): bool
    {
        // Le modèle parent enregistre l'association, la référence sécurisée et sa position.
        return $this->create([
            'annonce_id' => $listingId,
            'ref_fichier' => $filename,
            'ordre' => $order,
        ]);
    }

    /**
     * Rôle : Obtenir la première photographie ordonnée de chaque annonce demandée.
     * Paramètres : Liste d'identifiants d'annonces.
     * Retour : Références indexées par annonce ou false en cas d'erreur SQL.
     */
    public function getPrimaryPhotos(array $listingIds): array|false
    {
        // Les identifiants sont nettoyés avant de construire les paramètres SQL.
        $identifiers = $this->normalizePositiveIdentifiers($listingIds);

        if ($identifiers === []) {
            // Aucune annonce valide ne nécessite de requête à la base de données.
            return [];
        }

        // Chaque identifiant possède son propre paramètre dans la clause IN.
        $parameters = [];
        $placeholders = [];

        foreach ($identifiers as $index => $identifier) {
            $parameterName = 'listing_' . $index;
            $placeholders[] = ':' . $parameterName;
            $parameters[$parameterName] = $identifier;
        }

        // Seules les photographies placées en première position sont recherchées.
        $sql = 'SELECT annonce_id, ref_fichier FROM `PHOTOGRAPHIE`'
            // NATIF PHP : implode() assemble les éléments d’un tableau dans une chaîne ; il construit ici une liste ou une partie de requête.
            . ' WHERE annonce_id IN (' . implode(', ', $placeholders) . ')'
            . ' AND ordre = 1 ORDER BY annonce_id ASC';
        $rows = $this->database->fetchAll($sql, $parameters);

        if ($rows === false) {
            // L'échec de lecture est renvoyé pour être traité par l'appelant.
            return false;
        }

        // Le résultat est indexé par identifiant d'annonce pour une lecture directe.
        $primaryPhotos = [];

        foreach ($rows as $row) {
            // NATIF PHP : is_string() vérifie qu’une valeur est une chaîne de caractères ; il évite ici de traiter un type inattendu comme du texte.
            // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
            if (!isset($row['annonce_id'], $row['ref_fichier']) || !is_string($row['ref_fichier'])) {
                continue;
            }

            $identifier = (int) $row['annonce_id'];

            if (!isset($primaryPhotos[$identifier])) {
                // Une seule référence principale est conservée pour chaque annonce.
                // NATIF PHP : basename() retourne uniquement le nom final d’un chemin ; il empêche ici l’utilisation de dossiers fournis dans le nom.
                $primaryPhotos[$identifier] = basename($row['ref_fichier']);
            }
        }

        return $primaryPhotos;
    }

    /**
     * Rôle : Récupérer toutes les photographies d'une annonce dans leur ordre d'affichage.
     * Paramètres : Identifiant de l'annonce.
     * Retour : Liste des photographies ou false en cas d'erreur SQL.
     */
    public function getListingPhotos(int $listingId): array|false
    {
        // Les photographies sont lues dans l'ordre prévu pour leur affichage.
        $rows = $this->database->fetchAll(
            'SELECT id, ref_fichier, ordre FROM `PHOTOGRAPHIE`'
            . ' WHERE annonce_id = :listing_id ORDER BY ordre ASC, id ASC',
            ['listing_id' => $listingId]
        );

        if ($rows === false) {
            // L'échec de lecture est renvoyé pour être traité par l'appelant.
            return false;
        }

        // Chaque ligne SQL est transformée dans le format attendu par le contrôleur.
        $photos = [];

        foreach ($rows as $row) {
            if (!isset($row['id'], $row['ref_fichier'], $row['ordre']) || !is_string($row['ref_fichier'])) {
                // Les lignes incomplètes sont ignorées pour ne pas exposer de donnée invalide.
                continue;
            }

            // basename() empêche qu'un chemin complet soit transmis à l'affichage.
            $photos[] = [
                'id' => (int) $row['id'],
                'filename' => basename($row['ref_fichier']),
                'order' => (int) $row['ordre'],
            ];
        }

        return $photos;
    }

    /**
     * Rôle : Supprimer une photographie uniquement lorsqu'elle appartient à l'annonce indiquée.
     * Paramètres : Identifiants de la photographie et de l'annonce.
     * Retour : true lorsque la requête est exécutée, sinon false.
     */
    public function deleteFromListing(int $photoId, int $listingId): bool
    {
        // La clause sur l'annonce évite de supprimer une photographie qui lui est étrangère.
        return $this->database->execute(
            'DELETE FROM `PHOTOGRAPHIE` WHERE id = :photo_id AND annonce_id = :listing_id',
            ['photo_id' => $photoId, 'listing_id' => $listingId]
        );
    }

    /**
     * Rôle : Refermer les éventuels écarts d'ordre après la suppression de photographies.
     * Paramètres : Identifiant de l'annonce et photographies ordonnées.
     * Retour : true lorsque tous les ordres sont enregistrés, sinon false.
     */
    public function reorder(int $listingId, array $photos): bool
    {
        // Le tableau reçu doit déjà représenter l'ordre souhaité des photographies.
        foreach ($photos as $index => $photo) {
            if (!isset($photo['id'])) {
                // Une photographie sans identifiant ne peut pas être mise à jour de manière sûre.
                return false;
            }

            if ((int) $photo['order'] === $index + 1) {
                // Aucune requête n'est nécessaire lorsque l'ordre est déjà correct.
                continue;
            }

            // La mise à jour reste limitée à la photographie de l'annonce concernée.
            if (!$this->database->execute(
                'UPDATE `PHOTOGRAPHIE` SET ordre = :photo_order'
                . ' WHERE id = :photo_id AND annonce_id = :listing_id',
                ['photo_order' => $index + 1, 'photo_id' => (int) $photo['id'], 'listing_id' => $listingId]
            )) {
                // Le premier échec interrompt le réordonnancement.
                return false;
            }
        }

        // Toutes les positions demandées ont été contrôlées ou mises à jour.
        return true;
    }

}

