<?php

/**
 * Description générale : Modèle des comptes utilisateurs de l'application.
 * Rôle : Centraliser les lectures et écritures des comptes ainsi que le hachage des mots de passe. Les contrôleurs n'accèdent jamais directement aux empreintes conservées en base.
 * Tâches : Déclarer la table UTILISATEUR, gérer les mots de passe et vérifier l'unicité des comptes.
 * Liens avec les autres fichiers : Étend Model.php et est utilisé par AuthController.php et AccountController.php.
 */

namespace App\models;

use App\core\Model;

class UserModel extends Model
{
    // ====================
    // ATTRIBUTS
    // ====================

    // Métadonnées utilisées par le modèle parent pour les colonnes modifiables du compte.
    protected string $tableName = 'UTILISATEUR';
    protected string $primaryKeyName = 'id';
    protected array $writableFields = ['pseudo', 'email', 'password_hash'];

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Récupérer les informations privées nécessaires au formulaire du compte. Seuls le pseudo et l'adresse électronique utiles à la modification sont ainsi transmis, sans exposer l'empreinte du mot de passe.
     * Paramètres : Identifiant de l'utilisateur connecté.
     * Retour : Identité du compte, null si le compte est absent ou false en cas d'erreur SQL.
     */
    public function getAccount(int $userId): array|false|null
    {
        // Seules les informations nécessaires au formulaire sont sélectionnées.
        return $this->database->fetchOne(
            'SELECT id, pseudo, email FROM `UTILISATEUR` WHERE id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );
    }

    /**
     * Rôle : Hacher le mot de passe déjà validé puis créer le compte avec le pseudo et l'adresse reçus. Le mot de passe en clair n'est ainsi jamais enregistré dans la base.
     * Paramètres : Pseudo, adresse électronique et mot de passe déjà validés par le contrôleur.
     * Retour : true lorsque le compte est créé, sinon false.
     */
    public function createAccount(string $pseudo, string $email, string $password): bool
    {
        // Le mot de passe est transformé en empreinte avant toute écriture en base.
        $passwordHash = $this->hashPassword($password);

        if ($passwordHash === null) {
            // La création s'arrête si l'empreinte ne peut pas être produite.
            return false;
        }

        // Le modèle parent enregistre uniquement les champs autorisés du nouveau compte.
        return $this->create([
            'pseudo' => $pseudo,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);
    }

    /**
     * Rôle : Rechercher le compte par pseudo ou adresse puis comparer le mot de passe avec son empreinte. Seules les informations publiques du compte authentifié sont renvoyées au contrôleur.
     * Paramètres : Identifiant saisi et mot de passe en clair reçu par le formulaire.
     * Retour : Compte authentifié, null si les identifiants sont incorrects ou false en cas d'erreur SQL.
     */
    public function authenticate(string $login, string $password): array|false|null
    {
        // L'identifiant est recherché comme adresse électronique uniquement s'il contient @.
        // NATIF PHP : str_contains() vérifie si un texte contient une chaîne ; il contrôle ici la présence du caractère recherché.
        $account = $this->findByLogin($login, str_contains($login, '@'));

        if ($account === false || $account === null) {
            // Une erreur SQL ou un compte absent est transmis sans révéler de détail sensible.
            return $account;
        }

        // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
        if (!isset($account['password_hash'])
            // NATIF PHP : is_string() vérifie qu’une valeur est une chaîne de caractères ; il évite ici de traiter un type inattendu comme du texte.
            || !is_string($account['password_hash'])
            // NATIF PHP : password_verify() compare un mot de passe avec son empreinte sécurisée ; il authentifie ici l’utilisateur sans stocker son mot de passe en clair.
            || !password_verify($password, $account['password_hash'])
        ) {
            // Le même résultat est renvoyé pour toute information de connexion incorrecte.
            return null;
        }

        // L'empreinte ne doit jamais quitter le modèle utilisateur.
        unset($account['password_hash']);

        return $account;
    }

    /**
     * Rôle : Comparer le mot de passe actuel saisi avec l'empreinte du compte. Cette vérification autorise ou refuse la modification des informations personnelles.
     * Paramètres : Identifiant du compte et mot de passe en clair reçu par le formulaire.
     * Retour : true si le mot de passe correspond, false s'il est incorrect ou null en cas d'erreur SQL.
     */
    public function verifyPassword(int $userId, string $password): ?bool
    {
        // Seule l'empreinte est nécessaire pour vérifier le mot de passe actuel.
        $account = $this->database->fetchOne(
            'SELECT password_hash FROM `UTILISATEUR` WHERE id = :user_id LIMIT 1',
            ['user_id' => $userId]
        );

        if ($account === false) {
            // Une erreur de lecture reste distincte d'un mot de passe invalide.
            return null;
        }

        if ($account === null
            || !isset($account['password_hash'])
            || !is_string($account['password_hash'])
        ) {
            // Un compte absent ou incomplet ne peut pas être authentifié.
            return false;
        }

        // La comparaison sécurisée est effectuée sans exposer l'empreinte au contrôleur.
        return password_verify($password, $account['password_hash']);
    }

    /**
     * Rôle : Modifier le pseudo, l'adresse et, lorsqu'il est fourni, le mot de passe préalablement haché. Les champs autorisés restent limités par le modèle parent.
     * Paramètres : Identifiant du compte, pseudo, adresse et nouveau mot de passe facultatif déjà validés.
     * Retour : true si la mise à jour réussit, false en cas d'échec SQL ou null si l'empreinte ne peut pas être créée.
     */
    public function updateAccount(int $userId, string $pseudo, string $email, ?string $newPassword = null): ?bool
    {
        // Les coordonnées du compte sont toujours incluses dans la mise à jour.
        $data = ['pseudo' => $pseudo, 'email' => $email];

        if ($newPassword !== null) {
            // Un nouveau mot de passe est enregistré uniquement sous forme d'empreinte.
            $passwordHash = $this->hashPassword($newPassword);

            if ($passwordHash === null) {
                // La mise à jour est annulée si l'empreinte ne peut pas être produite.
                return null;
            }

            $data['password_hash'] = $passwordHash;
        }

        // Le modèle parent limite la mise à jour aux champs autorisés.
        return $this->update($userId, $data);
    }

    /**
     * Rôle : Vérifier si un pseudo appartient déjà à un autre compte afin d'éviter l'échec de la contrainte d'unicité lors de l'inscription ou de la modification.
     * Paramètres : Pseudo recherché et éventuel identifiant de compte à exclure.
     * Retour : true si le pseudo existe, false s'il est disponible ou null en cas d'erreur SQL.
     */
    public function pseudoExists(string $pseudo, ?int $excludedUserId = null): ?bool
    {
        // La vérification commune reçoit la colonne explicitement autorisée.
        return $this->normalizedValueExists('pseudo', $pseudo, $excludedUserId);
    }

    /**
     * Rôle : Vérifier si une adresse électronique appartient déjà à un autre compte afin que deux utilisateurs ne puissent pas partager le même identifiant de connexion.
     * Paramètres : Adresse recherchée et éventuel identifiant de compte à exclure.
     * Retour : true si l'adresse existe, false si elle est disponible ou null en cas d'erreur SQL.
     */
    public function emailExists(string $email, ?int $excludedUserId = null): ?bool
    {
        // La vérification commune reçoit la colonne explicitement autorisée.
        return $this->normalizedValueExists('email', $email, $excludedUserId);
    }

    /**
     * Rôle : Vérifier l'existence normalisée d'une valeur dans une colonne autorisée. Le contrôle de la colonne évite de construire une requête avec un nom de champ extérieur à la liste prévue par le modèle.
     * Paramètres : Colonne contrôlée, valeur recherchée et éventuel identifiant à exclure.
     * Retour : true si un compte correspond, false s'il est absent ou null en cas d'erreur SQL.
     */
    private function normalizedValueExists(string $column, string $value, ?int $excludedUserId): ?bool
    {
        if ($column !== 'pseudo' && $column !== 'email') {
            // La colonne est imposée pour empêcher toute construction SQL inattendue.
            return false;
        }

        // La valeur recherchée est toujours transmise comme paramètre préparé.
        $sql = 'SELECT 1 AS found FROM UTILISATEUR WHERE ' . $column . ' = :value';
        $parameters = ['value' => $value];

        if ($excludedUserId !== null) {
            // Lors d'une modification, le compte actuel ne doit pas être comparé à lui-même.
            $sql .= ' AND id <> :excluded_user_id';
            $parameters['excluded_user_id'] = $excludedUserId;
        }

        // Une seule correspondance suffit pour connaître la disponibilité.
        $sql .= ' LIMIT 1';

        $account = $this->database->fetchOne($sql, $parameters);

        if ($account === false) {
            // L'appelant peut distinguer une erreur SQL d'une valeur disponible.
            return null;
        }

        // Une ligne trouvée signifie que le pseudo ou l'adresse est déjà utilisé.
        return $account !== null;
    }

    /**
     * Rôle : Transformer un mot de passe en empreinte avec l'algorithme recommandé par PHP. Seule cette empreinte peut ensuite être transmise à la méthode d'enregistrement.
     * Paramètres : Mot de passe en clair.
     * Retour : Empreinte créée ou null lorsque sa création échoue.
     */
    private function hashPassword(string $password): ?string
    {
        // NATIF PHP : password_hash() crée une empreinte sécurisée du mot de passe ; il évite ici l’enregistrement du mot de passe en clair.
        // NATIF PHP : PASSWORD_DEFAULT sélectionne l’algorithme de hachage recommandé par PHP ; elle sécurise ici le mot de passe tout en permettant une évolution future.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if (!is_string($passwordHash)) {
            // Un résultat inattendu ne doit jamais être enregistré comme mot de passe.
            return null;
        }

        // Seule l'empreinte sécurisée est renvoyée au reste du modèle.
        return $passwordHash;
    }

    /**
     * Rôle : Rechercher un compte à partir du pseudo ou de l'adresse électronique. L'authentification peut ainsi retrouver l'unique compte correspondant sans exposer directement la requête SQL au contrôleur.
     * Paramètres : Identifiant saisi et indication précisant s'il s'agit d'une adresse électronique.
     * Retour : Compte avec son empreinte, null s'il est absent ou false en cas d'erreur SQL.
     */
    private function findByLogin(string $login, bool $isEmail): array|false|null
    {
        // Le pseudo est la recherche par défaut.
        $column = 'pseudo';

        if ($isEmail) {
            // Une adresse électronique est recherchée dans la colonne correspondante.
            $column = 'email';
        }

        // L'empreinte reste disponible ici uniquement pour la vérification du mot de passe.
        return $this->database->fetchOne(
            'SELECT id, pseudo, email, password_hash FROM UTILISATEUR '
            . 'WHERE ' . $column . ' = :login LIMIT 1',
            ['login' => $login]
        );
    }
}
