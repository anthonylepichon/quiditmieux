<?php

/**
 * Description générale : Modèle parent abstrait et générique de l'application.
 * Rôle : Définir les opérations communes d'accès aux données que les modèles SQL utilisent par héritage.
 * Tâches : Conserver Database, hydrater les objets et exécuter les opérations CRUD génériques.
 * Liens avec les autres fichiers : Est étendu par les modèles enfants et reçoit Database depuis les contrôleurs.
 */

namespace App\core;

abstract class Model
{
    // ====================
    // ATTRIBUTS
    // ====================

    protected Database $database;
    protected string $tableName = '';
    protected string $primaryKeyName = '';
    protected array $writableFields = [];
    protected array $recordData = [];

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Conserver Database et hydrater éventuellement le modèle avec des données existantes.
     * Paramètres : Gestionnaire de base de données et tableau facultatif de données.
     * Retour : Aucun.
     */
    public function __construct(Database $database, array $data = [])
    {
        $this->database = $database;
        $this->hydrate($data);
    }

    /**
     * Rôle : Enregistrer dans l'objet uniquement les champs définis par le modèle enfant.
     * Paramètres : Tableau associatif des données à charger.
     * Retour : Aucun.
     */
    public function hydrate(array $data): void
    {
        foreach ($data as $field => $value) {
            if ($field === $this->primaryKeyName || in_array($field, $this->writableFields, true)) {
                $this->recordData[$field] = $value;
            }
        }
    }

    /**
     * Rôle : Récupérer une valeur actuellement conservée par le modèle.
     * Paramètres : Nom du champ recherché.
     * Retour : Valeur du champ ou null lorsqu'elle est absente.
     */
    public function getValue(string $field): mixed
    {
        if (array_key_exists($field, $this->recordData)) {
            return $this->recordData[$field];
        }

        return null;
    }

    /**
     * Rôle : Modifier une valeur lorsque le champ est modifiable et différent de la clé primaire.
     * Paramètres : Nom du champ et nouvelle valeur.
     * Retour : true si la valeur est enregistrée dans l'objet, sinon false.
     */
    public function setValue(string $field, mixed $value): bool
    {
        if ($field === $this->primaryKeyName || !in_array($field, $this->writableFields, true)) {
            return false;
        }

        $this->recordData[$field] = $value;
        return true;
    }

    /**
     * Rôle : Rechercher un enregistrement à partir de sa clé primaire.
     * Paramètres : Identifiant de l'enregistrement.
     * Retour : Objet du modèle enfant, null si l'enregistrement est absent ou false en cas d'erreur SQL.
     */
    public function find(int $identifier): static|false|null
    {
        if (!$this->metadataIsValid()) {
            return false;
        }

        $sql = 'SELECT * FROM `' . $this->tableName . '`'
            . ' WHERE `' . $this->primaryKeyName . '` = :identifier LIMIT 1';
        $data = $this->database->fetchOne($sql, ['identifier' => $identifier]);

        if ($data === false || $data === null) {
            return $data;
        }

        if (!is_array($data)) {
            return false;
        }

        return new static($this->database, $data);
    }

    /**
     * Rôle : Récupérer tous les enregistrements de la table du modèle enfant.
     * Paramètres : Aucun.
     * Retour : Tableau d'objets du modèle enfant, éventuellement vide, ou false en cas d'erreur SQL.
     */
    public function findAll(): array|false
    {
        if (!$this->metadataIsValid()) {
            return false;
        }

        $sql = 'SELECT * FROM `' . $this->tableName . '`';
        $rows = $this->database->fetchAll($sql);

        if ($rows === false) {
            return false;
        }

        $models = [];

        foreach ($rows as $row) {
            $models[] = new static($this->database, $row);
        }

        return $models;
    }

    /**
     * Rôle : Créer un enregistrement avec les champs autorisés par le modèle enfant.
     * Paramètres : Tableau associatif des valeurs à enregistrer.
     * Retour : true en cas de création, sinon false.
     */
    protected function create(array $data): bool
    {
        if (!$this->metadataIsValid()) {
            return false;
        }

        $filteredData = $this->filterAllowedData($data);

        if ($filteredData === []) {
            return false;
        }

        $columns = [];
        $placeholders = [];

        foreach (array_keys($filteredData) as $field) {
            $columns[] = '`' . $field . '`';
            $placeholders[] = ':' . $field;
        }

        $sql = 'INSERT INTO `' . $this->tableName . '` (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';
        $created = $this->database->execute($sql, $filteredData);

        if (!$created) {
            return false;
        }

        $this->hydrate($filteredData);
        $lastIdentifier = $this->database->getLastInsertId();

        if ($lastIdentifier === false) {
            return false;
        }

        if ($lastIdentifier !== null) {
            $this->recordData[$this->primaryKeyName] = $lastIdentifier;
        }

        return true;
    }

    /**
     * Rôle : Modifier un enregistrement avec les champs autorisés par le modèle enfant.
     * Paramètres : Identifiant de l'enregistrement et tableau associatif des nouvelles valeurs.
     * Retour : true si la requête est exécutée, sinon false.
     */
    protected function update(int $identifier, array $data): bool
    {
        if (!$this->metadataIsValid()) {
            return false;
        }

        $filteredData = $this->filterAllowedData($data);

        if ($filteredData === []) {
            return false;
        }

        $assignments = [];

        foreach (array_keys($filteredData) as $field) {
            $assignments[] = '`' . $field . '` = :' . $field;
        }

        $filteredData['identifier'] = $identifier;
        $sql = 'UPDATE `' . $this->tableName . '` SET ' . implode(', ', $assignments)
            . ' WHERE `' . $this->primaryKeyName . '` = :identifier';
        $updated = $this->database->execute($sql, $filteredData);

        if ($updated && $this->getValue($this->primaryKeyName) === $identifier) {
            $this->hydrate($data);
        }

        return $updated;
    }

    /**
     * Rôle : Supprimer un enregistrement à partir de sa clé primaire.
     * Paramètres : Identifiant de l'enregistrement.
     * Retour : true si la requête est exécutée, sinon false.
     */
    protected function delete(int $identifier): bool
    {
        if (!$this->metadataIsValid()) {
            return false;
        }

        $sql = 'DELETE FROM `' . $this->tableName . '`'
            . ' WHERE `' . $this->primaryKeyName . '` = :identifier';
        return $this->database->execute($sql, ['identifier' => $identifier]);
    }

    /**
     * Rôle : Conserver uniquement les données scalaires ou nulles déclarées comme modifiables.
     * Paramètres : Tableau associatif de données candidates.
     * Retour : Tableau limité aux champs autorisés.
     */
    protected function filterAllowedData(array $data): array
    {
        $filteredData = [];

        foreach ($this->writableFields as $field) {
            if (array_key_exists($field, $data) && (is_scalar($data[$field]) || $data[$field] === null)) {
                $filteredData[$field] = $data[$field];
            }
        }

        return $filteredData;
    }

    /**
     * Rôle : Vérifier que les noms SQL proviennent bien de métadonnées internes valides.
     * Paramètres : Aucun.
     * Retour : true si la table, la clé primaire et les champs sont utilisables, sinon false.
     */
    protected function metadataIsValid(): bool
    {
        $names = array_merge([$this->tableName, $this->primaryKeyName], $this->writableFields);

        foreach ($names as $name) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1) {
                return false;
            }
        }

        return $this->tableName !== '' && $this->primaryKeyName !== '';
    }
}
