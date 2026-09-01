<?php

/**
 * Description générale : Modèle des comptes utilisateurs de l'application.
 * Rôle : Créer, authentifier et modifier les comptes utilisateurs.
 * Tâches : Déclarer la table UTILISATEUR, lire un compte et vérifier l'unicité de ses informations.
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
     * Rôle : Rechercher un compte à partir du pseudo ou de l'adresse électronique.
     * Paramètres : Identifiant saisi et indication précisant s'il s'agit d'une adresse électronique.
     * Retour : Compte avec son empreinte, null s'il est absent ou false en cas d'erreur SQL.
     */
    public function findByLogin(string $login, bool $isEmail): array|false|null
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
     * Rôle : Récupérer les informations privées nécessaires au formulaire du compte.
     * Paramètres : Identifiant de l'utilisateur connecté.
     * Retour : Identité et empreinte, null si le compte est absent ou false en cas d'erreur SQL.
     */
    public function getAccount(int $userId): array|false|null
    {
        return $this->database->fetchOne(
            'SELECT id, pseudo, email, password_hash FROM `UTILISATEUR` WHERE id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );
    }

    /**
     * Rôle : Vérifier les identifiants d'un utilisateur sans exposer l'empreinte du mot de passe au contrôleur.
     * Paramètres : Identifiant saisi et mot de passe en clair reçu par le formulaire.
     * Retour : Compte authentifié, null si les identifiants sont incorrects ou false en cas d'erreur SQL.
     */
    public function authenticate(string $login, string $password): array|false|null
    {
        $account = $this->findByLogin($login, str_contains($login, '@'));

        if ($account === false || $account === null) {
            return $account;
        }

        if (!isset($account['password_hash'])
            || !is_string($account['password_hash'])
            || !password_verify($password, $account['password_hash'])
        ) {
            return null;
        }

        unset($account['password_hash']);

        return $account;
    }

    /**
     * Rôle : Enregistrer les informations de compte déjà validées par le contrôleur.
     * Paramètres : Identifiant du compte, pseudo, adresse et empreinte facultative du nouveau mot de passe.
     * Retour : true lorsque la mise à jour est exécutée, sinon false.
     */
    public function updateAccount(int $userId, string $pseudo, string $email, ?string $passwordHash = null): bool
    {
        $data = ['pseudo' => $pseudo, 'email' => $email];
        if ($passwordHash !== null) {
            $data['password_hash'] = $passwordHash;
        }
        return $this->update($userId, $data);
    }

    /**
     * Rôle : Vérifier si un pseudo est déjà enregistré sans tenir compte de la casse.
     * Paramètres : Pseudo recherché et éventuel identifiant de compte à exclure.
     * Retour : true si le pseudo existe, false s'il est disponible ou null en cas d'erreur SQL.
     */
    public function pseudoExists(string $pseudo, ?int $excludedUserId = null): ?bool
    {
        return $this->normalizedValueExists('pseudo', $pseudo, $excludedUserId);
    }

    /**
     * Rôle : Vérifier si une adresse électronique est déjà enregistrée sans tenir compte de la casse.
     * Paramètres : Adresse recherchée et éventuel identifiant de compte à exclure.
     * Retour : true si l'adresse existe, false si elle est disponible ou null en cas d'erreur SQL.
     */
    public function emailExists(string $email, ?int $excludedUserId = null): ?bool
    {
        return $this->normalizedValueExists('email', $email, $excludedUserId);
    }

    /**
     * Rôle : Vérifier l'existence normalisée d'une valeur dans une colonne autorisée.
     * Paramètres : Colonne contrôlée, valeur recherchée et éventuel identifiant à exclure.
     * Retour : true si un compte correspond, false s'il est absent ou null en cas d'erreur SQL.
     */
    private function normalizedValueExists(string $column, string $value, ?int $excludedUserId): ?bool
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

        $account = $this->database->fetchOne($sql, $parameters);

        if ($account === false) {
            return null;
        }

        return $account !== null;
    }
}
