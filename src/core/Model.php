<?php

/**
 * Description générale : Modèle parent abstrait et générique de l'application.
 * Rôle : Définir les opérations communes d'accès aux données que les modèles SQL utilisent par héritage.
 * Tâches : Conserver Database, hydrater les objets et exécuter les opérations d'écriture génériques réellement utilisées.
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
     * Rôle : Conserver uniquement des identifiants entiers strictement positifs et uniques.
     * Paramètres : Valeurs candidates à normaliser.
     * Retour : Liste d'identifiants utilisables dans une requête préparée.
     */
    protected function normalizePositiveIdentifiers(array $identifiers): array
    {
        $normalizedIdentifiers = [];

        foreach ($identifiers as $identifier) {
            if (!is_int($identifier) && !is_string($identifier)) {
                continue;
            }

            if (filter_var($identifier, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                continue;
            }

            $normalizedIdentifiers[(int) $identifier] = (int) $identifier;
        }

        return array_values($normalizedIdentifiers);
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
