<?php

/**
 * Description générale : Modèle des comptes utilisateurs de l'application.
 * Rôle : Rechercher, créer et mettre à jour les données privées d'un utilisateur.
 * Tâches : Déclarer la table UTILISATEUR et fournir les recherches normalisées nécessaires à l'authentification.
 * Liens avec les autres fichiers : Étend Model.php et est utilisé par AuthController.php et UserController.php.
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
     * Rôle : Rechercher un compte par pseudo ou par adresse électronique sans tenir compte de la casse.
     * Paramètres : Identifiant de connexion normalisé et indication précisant s'il s'agit d'une adresse.
     * Retour : Données privées du compte ou null lorsqu'aucun compte ne correspond.
     */
    public function findByLogin(string $login, bool $isEmail): ?array
    {
        $column = 'pseudo';

        if ($isEmail) {
            $column = 'email';
        }

        $sql = 'SELECT id, pseudo, email, password_hash FROM `UTILISATEUR`'
            . ' WHERE LOWER(`' . $column . '`) = LOWER(:login) LIMIT 1';
        return $this->database->fetchOne($sql, ['login' => $login]);
    }

    /**
     * Rôle : Indiquer si un pseudo est déjà utilisé par un autre compte.
     * Paramètres : Pseudo normalisé et identifiant éventuellement exclu de la recherche.
     * Retour : true lorsqu'un doublon existe, sinon false.
     */
    public function pseudoExists(string $pseudo, ?int $excludedUserId = null): bool
    {
        return $this->normalizedValueExists('pseudo', $pseudo, $excludedUserId);
    }

    /**
     * Rôle : Indiquer si une adresse électronique est déjà utilisée par un autre compte.
     * Paramètres : Adresse normalisée et identifiant éventuellement exclu de la recherche.
     * Retour : true lorsqu'un doublon existe, sinon false.
     */
    public function emailExists(string $email, ?int $excludedUserId = null): bool
    {
        return $this->normalizedValueExists('email', $email, $excludedUserId);
    }

    /**
     * Rôle : Récupérer les informations privées nécessaires au formulaire du compte.
     * Paramètres : Identifiant de l'utilisateur connecté.
     * Retour : Identité et empreinte du mot de passe, ou null lorsque le compte est absent.
     */
    public function getAccount(int $userId): ?array
    {
        return $this->database->fetchOne(
            'SELECT id, pseudo, email, password_hash FROM `UTILISATEUR` WHERE id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );
    }

    /**
     * Rôle : Enregistrer les informations de compte déjà validées par le contrôleur.
     * Paramètres : Identifiant du compte, pseudo, adresse et empreinte facultative du nouveau mot de passe.
     * Retour : true lorsque la mise à jour est exécutée, sinon false.
     */
    public function updateAccount(
        int $userId,
        string $pseudo,
        string $email,
        ?string $passwordHash = null
    ): bool {
        $data = ['pseudo' => $pseudo, 'email' => $email];

        if ($passwordHash !== null) {
            $data['password_hash'] = $passwordHash;
        }

        return $this->update($userId, $data);
    }

    /**
     * Rôle : Contrôler l'existence d'une valeur normalisée dans une colonne interne autorisée.
     * Paramètres : Colonne contrôlée, valeur recherchée et identifiant éventuellement exclu.
     * Retour : true lorsqu'une ligne correspond, sinon false.
     */
    private function normalizedValueExists(string $column, string $value, ?int $excludedUserId): bool
    {
        if (!in_array($column, ['pseudo', 'email'], true)) {
            return false;
        }

        $parameters = ['value' => $value];
        $sql = 'SELECT id FROM `UTILISATEUR` WHERE LOWER(`' . $column . '`) = LOWER(:value)';

        if ($excludedUserId !== null) {
            $sql .= ' AND id <> :excluded_id';
            $parameters['excluded_id'] = $excludedUserId;
        }

        $sql .= ' LIMIT 1';
        return $this->database->fetchOne($sql, $parameters) !== null;
    }
}
