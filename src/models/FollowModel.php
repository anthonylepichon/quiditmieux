<?php

/**
 * Description générale : Modèle des suivis volontaires d'annonces.
 * Rôle : Consulter et modifier les associations entre utilisateurs et annonces suivies.
 * Tâches : Déclarer ASSOC_UTILISATEUR_ANNONCE et fournir l'état de suivi nécessaire au détail.
 * Liens avec les autres fichiers : Étend Model.php et est utilisé par ListingController.php et ParticipationController.php.
 */

namespace App\models;

use App\core\Database;
use App\core\Model;

class FollowModel extends Model
{
    protected string $tableName = 'ASSOC_UTILISATEUR_ANNONCE';
    protected string $primaryKeyName = 'id';
    protected array $writableFields = ['utilisateur_id', 'annonce_id'];

    /**
     * Rôle : Initialiser le modèle avec le gestionnaire de base de données et des données éventuelles.
     * Paramètres : Gestionnaire de base de données et tableau facultatif de suivi.
     * Retour : Aucun.
     */
    public function __construct(Database $database, array $data = [])
    {
        parent::__construct($database, $data);
    }

    /**
     * Rôle : Indiquer si un utilisateur suit volontairement une annonce.
     * Paramètres : Identifiants de l'utilisateur et de l'annonce.
     * Retour : true lorsque le suivi existe, sinon false.
     */
    public function isFollowing(int $userId, int $listingId): bool
    {
        $sql = 'SELECT id FROM `ASSOC_UTILISATEUR_ANNONCE`'
            . ' WHERE utilisateur_id = :user_id AND annonce_id = :listing_id LIMIT 1';
        return $this->database->fetchOne($sql, [
            'user_id' => $userId,
            'listing_id' => $listingId,
        ]) !== null;
    }

    /**
     * Rôle : Créer un suivi volontaire lorsqu'il n'existe pas encore.
     * Paramètres : Identifiants de l'utilisateur et de l'annonce.
     * Retour : true si le suivi existe après l'opération, sinon false.
     */
    public function follow(int $userId, int $listingId): bool
    {
        if ($this->isFollowing($userId, $listingId)) {
            return true;
        }

        return $this->create(['utilisateur_id' => $userId, 'annonce_id' => $listingId]);
    }

    /**
     * Rôle : Retirer uniquement le suivi volontaire d'un utilisateur sur une annonce.
     * Paramètres : Identifiants de l'utilisateur et de l'annonce.
     * Retour : true lorsque la requête de retrait est exécutée, sinon false.
     */
    public function unfollow(int $userId, int $listingId): bool
    {
        return $this->database->execute(
            'DELETE FROM `ASSOC_UTILISATEUR_ANNONCE`'
            . ' WHERE utilisateur_id = :user_id AND annonce_id = :listing_id',
            ['user_id' => $userId, 'listing_id' => $listingId]
        );
    }
}

