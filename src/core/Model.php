<?php

/**
 * Description générale : Modèle parent abstrait commun aux modèles qui utilisent une table SQL.
 * Rôle : Regrouper les opérations CRUD simples partagées par les modèles enfants.
 * Tâches : Conserver Database, lire un enregistrement par son identifiant, contrôler les champs autorisés, exécuter les écritures SQL et normaliser les listes d'identifiants.
 * Liens avec les autres fichiers : Est étendu par les modèles SQL et reçoit Database depuis les contrôleurs.
 */

namespace App\core;

abstract class Model
{
    // ====================
    // ATTRIBUTS
    // ====================

    // Gestionnaire partagé qui exécute les requêtes préparées pour ce modèle.
    protected Database $database;

    // Métadonnées définies par chaque modèle enfant et jamais par les données du navigateur.
    protected string $tableName = '';
    protected string $primaryKeyName = 'id';
    protected array $writableFields = [];

    // Données du dernier enregistrement créé, utilisées notamment pour récupérer son identifiant.
    protected array $data = [];

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Conserver l'accès commun à la base de données.
     * Paramètres : Gestionnaire Database créé pendant le démarrage.
     * Retour : Aucun.
     */
    public function __construct(Database $database)
    {
        // La même connexion est transmise aux modèles afin d'éviter d'en créer une nouvelle dans chaque classe.
        $this->database = $database;
    }

    /**
     * Rôle : Créer un enregistrement avec les champs autorisés par le modèle enfant.
     * Paramètres : Tableau associatif contenant les données à enregistrer.
     * Retour : true si l'insertion réussit, sinon false.
     */
    public function create(array $data): bool
    {
        // NATIF PHP : empty() vérifie ici que le tableau contient au moins une donnée à enregistrer.
        if (empty($data)) {
            return false;
        }

        $fields = [];
        $placeholders = [];
        $parameters = [];

        foreach ($data as $field => $value) {
            // NATIF PHP : is_string() confirme que la clé du tableau peut représenter un nom de colonne.
            // NATIF PHP : in_array() limite les colonnes aux champs autorisés par le modèle enfant.
            if (
                !is_string($field)
                || $field === $this->primaryKeyName
                || !in_array($field, $this->writableFields, true)
            ) {
                return false;
            }

            $fields[] = $field;
            $placeholders[] = ':' . $field;
            $parameters[':' . $field] = $value;
        }

        // NATIF PHP : implode() transforme un tableau en une chaîne de caractères. Ici, il sépare par des virgules les noms des colonnes et les emplacements comme :pseudo ou :email afin de construire la requête SQL.
        $sql = 'INSERT INTO ' . $this->tableName
            . ' (' . implode(', ', $fields) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        $result = $this->database->execute($sql, $parameters);
        if ($result === false) {
            return false;
        }

        // L'objet conserve les données créées et l'identifiant fourni par MySQL.
        $this->data = $data;
        $this->data[$this->primaryKeyName] = $this->database->getLastInsertId();

        return true;
    }

    /**
     * Rôle : Rechercher un enregistrement de la table du modèle enfant à partir de son identifiant.
     * Paramètres : Identifiant de l'enregistrement recherché.
     * Retour : Données trouvées, null si l'enregistrement est absent ou l'identifiant invalide, false en cas d'erreur SQL.
     */
    public function findById(int $id): array|false|null
    {
        if ($id <= 0) {
            return null;
        }

        // Le nom de la table et celui de la clé primaire sont définis dans le modèle enfant.
        $sql = 'SELECT * FROM ' . $this->tableName
            . ' WHERE ' . $this->primaryKeyName . ' = :id LIMIT 1';

        // L'identifiant reste séparé du texte SQL grâce à la requête préparée exécutée par Database.
        return $this->database->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Rôle : Modifier un enregistrement identifié avec les champs autorisés par le modèle enfant.
     * Paramètres : Identifiant de l'enregistrement et tableau associatif des données à modifier.
     * Retour : true si la modification réussit, sinon false.
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0 || $data === []) {
            return false;
        }

        $assignments = [];
        $parameters = [];

        foreach ($data as $field => $value) {
            // Les noms de colonnes sont contrôlés avant d'être ajoutés au texte SQL.
            if (
                !is_string($field)
                || $field === $this->primaryKeyName
                || !in_array($field, $this->writableFields, true)
            ) {
                return false;
            }

            $assignments[] = $field . ' = :' . $field;
            $parameters[':' . $field] = $value;
        }

        $parameters[':' . $this->primaryKeyName] = $id;

        $sql = 'UPDATE ' . $this->tableName
            . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . $this->primaryKeyName
            . ' = :' . $this->primaryKeyName;

        return $this->database->execute($sql, $parameters);
    }

    /**
     * Rôle : Supprimer un enregistrement à partir de son identifiant.
     * Paramètres : Identifiant de l'enregistrement à supprimer.
     * Retour : true si la suppression réussit, sinon false.
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $sql = 'DELETE FROM ' . $this->tableName
            . ' WHERE ' . $this->primaryKeyName . ' = :id';

        return $this->database->execute($sql, [':id' => $id]);
    }

    /**
     * Rôle : Récupérer l’identifiant attribué automatiquement par la base de données au dernier enregistrement ajouté.
     * Paramètres : Nom du champ à lire.
     * Retour : Valeur du champ ou null lorsque le champ est absent.
     */
    public function getValue(string $field): mixed
    {
        // NATIF PHP : array_key_exists() distingue un champ absent d'un champ présent dont la valeur vaut null.
        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }

        return null;
    }

    /**
     * Rôle : Préparer la liste des identifiants d'annonces utilisée dans les requêtes SQL groupées. La méthode transforme les valeurs en nombres entiers, ignore les identifiants inférieurs à 1 et retire les doublons.
     * Paramètres : $identifiers contient les identifiants à contrôler.
     * Retour : Liste d'identifiants entiers, positifs et sans doublon.
     */
    protected function normalizePositiveIdentifiers(array $identifiers): array
    {
        $validIdentifiers = [];

        foreach ($identifiers as $identifier) {
            $identifier = (int) $identifier;

            // NATIF PHP : in_array() vérifie si l'identifiant est déjà présent afin d'éviter les doublons.
            if ($identifier > 0 && !in_array($identifier, $validIdentifiers, true)) {
                $validIdentifiers[] = $identifier;
            }
        }

        return $validIdentifiers;
    }
}
