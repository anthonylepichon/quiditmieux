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
     * Rôle : Initialiser le modèle avec le gestionnaire de base de données.
     * Paramètres : Gestionnaire de base de données et données éventuelles.
     * Retour : Aucun.
     */
    public function __construct(Database $database, array $data = [])
    {
        parent::__construct($database, $data);
    }

    /**
     * Rôle : Vérifier si un utilisateur suit une annonce.
     * Paramètres : Identifiant utilisateur et identifiant annonce.
     * Retour : true lorsque l'association existe, sinon false.
     */
    public function isFollowing(int $userId, int $listingId): bool
    {
        return $this->database->fetchOne(
            'SELECT id FROM ASSOC_UTILISATEUR_ANNONCE '
            . 'WHERE utilisateur_id = :user_id AND annonce_id = :listing_id LIMIT 1',
            ['user_id' => $userId, 'listing_id' => $listingId]
        ) !== null;
    }
}

