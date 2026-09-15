<?php

/**
 * Description générale : Modèle parent abstrait commun aux modèles qui utilisent une table SQL.
 * Rôle : Regrouper les opérations simples de création, de modification et de suppression partagées par les modèles enfants.
 * Tâches : Conserver Database, contrôler les champs autorisés, exécuter les écritures SQL et normaliser les listes d'identifiants.
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

        // NATIF PHP : implode() assemble les noms de colonnes et les marqueurs validés pour construire la requête.
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
     * Rôle : Lire une valeur conservée après la création d'un enregistrement.
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
     * Rôle : Conserver uniquement les identifiants entiers strictement positifs et supprimer les doublons.
     * Paramètres : Liste d'identifiants à contrôler.
     * Retour : Liste des identifiants valides et uniques.
     */
    protected function normalizePositiveIdentifiers(array $identifiers): array
    {
        $normalizedIdentifiers = [];

        foreach ($identifiers as $identifier) {
            if (!is_int($identifier) && !is_string($identifier)) {
                continue;
            }

            // NATIF PHP : filter_var() avec FILTER_VALIDATE_INT vérifie que la valeur est un entier positif.
            $normalizedIdentifier = filter_var(
                $identifier,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($normalizedIdentifier === false) {
                continue;
            }

            // L'identifiant sert de clé afin qu'une même valeur ne puisse apparaître qu'une seule fois.
            $normalizedIdentifiers[(int) $normalizedIdentifier] = (int) $normalizedIdentifier;
        }

        // NATIF PHP : array_values() retire les clés techniques et retourne une liste simple.
        return array_values($normalizedIdentifiers);
    }
}
