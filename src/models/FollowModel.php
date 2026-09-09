<?php

/**
 * Description générale : Modèle des suivis volontaires d'annonces.
 * Rôle : Consulter et modifier les associations entre utilisateurs et annonces suivies.
 * Tâches : Déclarer ASSOC_UTILISATEUR_ANNONCE et fournir l'état de suivi nécessaire au détail.
 * Liens avec les autres fichiers : Étend Model.php et est utilisé par ListingController.php et ParticipationController.php.
 */

namespace App\models;

use App\core\Model;

class FollowModel extends Model
{
    // Métadonnées utilisées par le modèle parent pour gérer les suivis autorisés.
    protected string $tableName = 'ASSOC_UTILISATEUR_ANNONCE';
    protected string $primaryKeyName = 'id';
    protected array $writableFields = ['utilisateur_id', 'annonce_id'];

    /**
     * Rôle : Indiquer si un utilisateur suit volontairement une annonce.
     * Paramètres : Identifiants de l'utilisateur et de l'annonce.
     * Retour : true si le suivi existe, false sinon, ou null en cas d'erreur SQL.
     */
    public function isFollowing(int $userId, int $listingId): ?bool
    {
        // La requête vérifie seulement l'existence d'une association de suivi.
        $sql = 'SELECT 1 AS found FROM `ASSOC_UTILISATEUR_ANNONCE`'
            . ' WHERE utilisateur_id = :user_id AND annonce_id = :listing_id LIMIT 1';

        // Les identifiants sont transmis séparément à la requête préparée.
        $follow = $this->database->fetchOne($sql, [
            'user_id' => $userId,
            'listing_id' => $listingId,
        ]);

        if ($follow === false) {
            // Une erreur SQL est distinguée de l'absence normale de suivi.
            return null;
        }

        // Une ligne trouvée confirme que l'utilisateur suit l'annonce.
        return $follow !== null;
    }

    /**
     * Rôle : Créer un suivi volontaire lorsqu'il n'existe pas encore.
     * Paramètres : Identifiants de l'utilisateur et de l'annonce.
     * Retour : true si le suivi existe après l'opération, sinon false.
     */
    public function follow(int $userId, int $listingId): bool
    {
        // L'état actuel évite de créer deux fois le même suivi.
        $isFollowing = $this->isFollowing($userId, $listingId);

        if ($isFollowing === null) {
            // L'opération s'arrête si la lecture de l'état a échoué.
            return false;
        }

        if ($isFollowing) {
            // Le suivi existe déjà : le résultat attendu est donc déjà atteint.
            return true;
        }

        // Le modèle parent crée l'association avec les deux identifiants autorisés.
        return $this->create(['utilisateur_id' => $userId, 'annonce_id' => $listingId]);
    }

    /**
     * Rôle : Retirer uniquement le suivi volontaire d'un utilisateur sur une annonce.
     * Paramètres : Identifiants de l'utilisateur et de l'annonce.
     * Retour : true lorsque la requête de retrait est exécutée, sinon false.
     */
    public function unfollow(int $userId, int $listingId): bool
    {
        // Seule l'association correspondant à l'utilisateur et à l'annonce est supprimée.
        return $this->database->execute(
            'DELETE FROM `ASSOC_UTILISATEUR_ANNONCE`'
            . ' WHERE utilisateur_id = :user_id AND annonce_id = :listing_id',
            ['user_id' => $userId, 'listing_id' => $listingId]
        );
    }
}

