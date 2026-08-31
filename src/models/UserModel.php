<?php

/**
 * Description générale : Modèle des comptes utilisateurs de l'application.
 * Rôle : Créer les comptes et vérifier l'unicité de leurs informations publiques.
 * Tâches : Déclarer la table UTILISATEUR et rechercher un pseudo ou une adresse électronique normalisés.
 * Liens avec les autres fichiers : Étend Model.php et est utilisé par AuthController.php.
 */

namespace App\models;

use App\core\Database;
use App\core\Model;

class UserModel extends Model
{
    // ====================
    // ATTRIBUTS
    // ====================

    protected string $tableName = 'UTILISATEUR';
    protected string $primaryKeyName = 'id';
    protected array $writableFields = ['pseudo', 'email', 'password_hash'];

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Initialiser le modèle avec le gestionnaire de base de données et des données éventuelles.
     * Paramètres : Gestionnaire de base de données et tableau facultatif de données utilisateur.
     * Retour : Aucun.
     */
    public function __construct(Database $database, array $data = [])
    {
        parent::__construct($database, $data);
    }

    /**
     * Rôle : Rechercher un compte à partir du pseudo ou de l'adresse électronique.
     * Paramètres : Identifiant saisi et indication précisant s'il s'agit d'une adresse électronique.
     * Retour : Compte avec son empreinte de mot de passe ou null lorsqu'il est absent.
     */
    public function findByLogin(string $login, bool $isEmail): ?array
    {
        $column = 'pseudo';

        if ($isEmail) {
            $column = 'email';
        }

        return $this->database->fetchOne(
            'SELECT id, pseudo, email, password_hash FROM UTILISATEUR '
            . 'WHERE LOWER(' . $column . ') = LOWER(:login) LIMIT 1',
            ['login' => $login]
        );
    }

    /**
     * Rôle : Vérifier si un pseudo est déjà enregistré sans tenir compte de la casse.
     * Paramètres : Pseudo recherché et éventuel identifiant de compte à exclure.
     * Retour : true si le pseudo existe, sinon false.
     */
    public function pseudoExists(string $pseudo, ?int $excludedUserId = null): bool
    {
        return $this->normalizedValueExists('pseudo', $pseudo, $excludedUserId);
    }

    /**
     * Rôle : Vérifier si une adresse électronique est déjà enregistrée sans tenir compte de la casse.
     * Paramètres : Adresse recherchée et éventuel identifiant de compte à exclure.
     * Retour : true si l'adresse existe, sinon false.
     */
    public function emailExists(string $email, ?int $excludedUserId = null): bool
    {
        return $this->normalizedValueExists('email', $email, $excludedUserId);
    }

    /**
     * Rôle : Vérifier l'existence normalisée d'une valeur dans une colonne autorisée.
     * Paramètres : Colonne contrôlée, valeur recherchée et éventuel identifiant à exclure.
     * Retour : true lorsqu'un compte correspondant existe, sinon false.
     */
    private function normalizedValueExists(string $column, string $value, ?int $excludedUserId): bool
    {
        if ($column !== 'pseudo' && $column !== 'email') {
            return false;
        }

        $sql = 'SELECT id FROM UTILISATEUR WHERE LOWER(' . $column . ') = LOWER(:value)';
        $parameters = ['value' => $value];

        if ($excludedUserId !== null) {
            $sql .= ' AND id <> :excluded_user_id';
            $parameters['excluded_user_id'] = $excludedUserId;
        }

        $sql .= ' LIMIT 1';

        return $this->database->fetchOne($sql, $parameters) !== null;
    }
}
