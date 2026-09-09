<?php

/**
 * Description générale : Modèle parent abstrait et générique de l'application.
 * Rôle : Définir les opérations communes d'accès aux données que les modèles SQL utilisent par héritage.
 * Tâches : Conserver Database, hydrater les objets et exécuter les opérations d'écriture génériques réellement utilisées.
 * Liens avec les autres fichiers : Est étendu par les modèles enfants et reçoit Database depuis les contrôleurs.
 */

namespace App\core;

class Model
{
    // ====================
    // ATTRIBUTS
    // ====================

    // Gestionnaire partagé qui exécute les requêtes préparées pour ce modèle.
    protected Database $database;
    // Métadonnées définies par chaque modèle enfant, jamais par une donnée reçue du navigateur.
    protected string $tableName = '';
    protected string $primaryKeyName = 'id';
    protected array $writableFields = [];

    // Nom de la table SQL manipulée par le modèle.

    // Données de l’enregistrement actuellement chargé dans l’objet.
    protected array $data = [];

    // ====================
    // METHODES
    // ====================

    /**
     * Rôle : Conserver l'accès commun à la base de données.
     * Paramètres : Gestionnaire Database créé pendant le démarrage.
     * Retour : Aucun.
     */
    public function __construct(Database $database)
    {
        // La connexion est injectée depuis le contrôleur afin d'éviter toute nouvelle connexion dans le modèle.
        $this->database = $database;
    }

    // Rôle : rechercher un enregistrement à partir de son identifiant.
    // Paramètres : $id représente l’identifiant de l’enregistrement recherché.
    // Retour : tableau contenant les données trouvées, ou false.
    public function findById(int $id)
    {
        // La clé primaire est définie par le modèle enfant et non fournie comme un nom SQL par le visiteur.
        return $this->findOneBy($this->primaryKeyName, $id);
    }

    // Rôle : charger dans l’objet courant l’enregistrement correspondant à un identifiant.
    // Paramètres : $id représente l’identifiant de l’enregistrement à charger.
    // Retour : true si l’enregistrement est trouvé, false dans le cas contraire.
    public function load(int|string $id): bool
    {
        return $this->loadFromField($this->primaryKeyName, $id);
    }

    // Rôle : charger dans l’objet courant l’enregistrement correspondant
    // à la valeur d’un champ précis.
    // Paramètres : $field représente le champ utilisé pour la recherche ;
    // $value représente la valeur recherchée dans ce champ.
    // Retour : true si l’enregistrement est trouvé, false dans le cas contraire.
    protected function loadFromField(string $field, mixed $value): bool
    {
        // findOneBy hydrate l'objet courant lorsque l'enregistrement est trouvé.
        return $this->findOneBy($field, $value) !== false;
    }

    // Rôle : supprimer de la base l’enregistrement chargé dans l’objet courant.
    // Paramètres : Aucun.
    // Retour : true si la suppression est exécutée, false si l’objet n’a pas d’identifiant.
    public function delete(?int $id = null): bool
    {
        // L'identifiant optionnel facilite la suppression par les modèles enfants tout en conservant l'objet courant.
        if ($id !== null) {
            $this->data[$this->primaryKeyName] = $id;
        }
        if (empty($this->data[$this->primaryKeyName])) {
            return false;
        }

        // Le nom de table et la clé proviennent des métadonnées internes du modèle.
        $sql = 'DELETE FROM ' . $this->tableName .
            ' WHERE ' . $this->primaryKeyName . ' = :id';

        $result = $this->database->execute($sql, [
            ':id' => $this->data[$this->primaryKeyName],
        ]);

        if ($result === false) {
            return false;
        }

        // L’identifiant est retiré de l’objet, car l’enregistrement n’existe plus.
        // Les autres données restent disponibles jusqu’à la fin du traitement.
        $this->data[$this->primaryKeyName] = null;

        return true;
    }

    // Rôle : créer un enregistrement avec les données reçues
    // et hydrater l’objet avec son nouvel identifiant.
    // Paramètres : $data contient les champs et les valeurs à enregistrer.
    // Retour : true si l’insertion est exécutée, false si les données sont invalides.
    public function insert(array $data): bool
    {
        // Une insertion vide ne peut correspondre à aucun enregistrement métier valide.
        if (empty($data)) {
            return false;
        }

        $fields = [];
        $placeholders = [];
        $parameters = [];

        // (SECURITE: "La liste blanche des champs empêche une clé reçue de devenir librement un nom de colonne dans la requête SQL.")
        // Seuls les champs déclarés dans le modèle peuvent être enregistrés.
        foreach ($data as $key => $value) {
            if (
                $key === $this->primaryKeyName ||
                in_array($key, $this->writableFieldNames(), true) === false
            ) {
                return false;
            }

            $fields[] = $key;
            $placeholders[] = ':' . $key;
            $parameters[':' . $key] = $value;
        }

        // Les noms de colonnes ont été validés par la liste blanche avant la construction de la requête.
        $sql = 'INSERT INTO ' . $this->tableName .
            ' (' . implode(', ', $fields) . ')' .
            ' VALUES (' . implode(', ', $placeholders) . ')';

        $result = $this->database->execute($sql, $parameters);

        if ($result === false) {
            return false;
        }

        // L’objet représente maintenant l’enregistrement créé.
        $this->data = $data;
        $this->data[$this->primaryKeyName] = $this->database->getLastInsertId();

        return true;
    }

    // Rôle : enregistrer dans la base les modifications de l’objet courant.
    // Paramètres : Aucun. Les nouvelles valeurs proviennent de l’attribut $data.
    // Retour : true si la modification ou l’insertion est exécutée,
    // false si les données sont invalides.
    public function update(?int $id = null, ?array $data = null): bool
    {
        // Lorsqu'un identifiant et des données sont fournis, l'objet est d'abord chargé puis modifié de façon contrôlée.
        if ($id !== null && $data !== null) {
            if (!$this->load($id) || !$this->setFields($data)) {
                return false;
            }
        }
        // Ce comportement validé crée l’enregistrement lorsque l’objet
        // ne possède pas encore d’identifiant.
        if (empty($this->data[$this->primaryKeyName])) {
            return $this->insert($this->data);
        }

        // Les affectations SQL et leurs valeurs sont construites séparément pour conserver les requêtes préparées.
        $assignments = [];
        $parameters = [];

        foreach ($this->data as $key => $value) {
            // La clé primaire sert à retrouver la ligne et ne doit pas être modifiée.
            if ($key === $this->primaryKeyName) {
                continue;
            }

            // (SECURITE: "Seuls les champs déclarés modifiables peuvent être intégrés dynamiquement à la requête UPDATE.")
            if (in_array($key, $this->writableFieldNames(), true) === false) {
                return false;
            }

            $assignments[] = $key . ' = :' . $key;
            $parameters[':' . $key] = $value;
        }

        if ($assignments === []) {
            return false;
        }

        $parameters[':' . $this->primaryKeyName] =
            $this->data[$this->primaryKeyName];

        $sql = 'UPDATE ' . $this->tableName .
            ' SET ' . implode(', ', $assignments) .
            ' WHERE ' . $this->primaryKeyName .
            ' = :' . $this->primaryKeyName;

        return $this->database->execute($sql, $parameters);
    }

    // Rôle : récupérer tous les enregistrements de la table du modèle.
    // Paramètres : Aucun.
    // Retour : tableau contenant les enregistrements trouvés, ou tableau vide.
    public function findAll(): array
    {
        // Seules les colonnes connues du modèle sont sélectionnées.
        $sql = 'SELECT ' . implode(', ', $this->fieldNames()) .
            ' FROM ' . $this->tableName;

        $records = $this->database->fetchAll($sql);
        return is_array($records) ? $records : [];
    }

    // Rôle : choisir entre la création et la modification
    // selon la présence d’un identifiant dans l’objet.
    // Paramètres : Aucun. Les données proviennent de l’attribut $data.
    // Retour : true si l’enregistrement est effectué, false dans le cas contraire.
    public function save(): bool
    {
        if (empty($this->data[$this->primaryKeyName])) {
            return $this->insert($this->data);
        }

        return $this->update();
    }

    /**
     * Rôle : Créer un enregistrement à partir des champs autorisés.
     * Paramètres : Données à insérer.
     * Retour : true si l'insertion réussit, sinon false.
     */
    public function create(array $data): bool
    {
        return $this->insert($data);
    }

    // Rôle : rechercher un enregistrement à partir de la valeur d’un champ précis
    // et hydrater l’objet courant avec les données trouvées.
    // Paramètres : $field représente le champ utilisé pour la recherche ;
    // $value représente la valeur recherchée.
    // Retour : tableau contenant les données trouvées, ou false.
    public function findOneBy(string $field, mixed $value)
    {
        // Le champ doit appartenir aux métadonnées du modèle avant d'être placé dans la requête SQL.
        // (SECURITE: "Le nom de colonne est contrôlé par une liste blanche avant d'être intégré à la requête SQL.")
        if (in_array($field, $this->fieldNames(), true) === false) {
            return false;
        }

        $sql = 'SELECT ' . implode(', ', $this->fieldNames()) .
            ' FROM ' . $this->tableName .
            ' WHERE ' . $field . ' = :value';

        $result = $this->database->fetchOne($sql, [
            ':value' => $value,
        ]);

        // null indique une recherche valide qui ne retourne simplement aucune ligne.
        if ($result === null) {
            return false;
        }

        // L’objet courant représente maintenant l’enregistrement trouvé.
        $this->data = $result;

        return $this->data;
    }

    // Rôle : récupérer des enregistrements et les transformer en objets
    // appartenant au modèle métier qui utilise cette méthode.
    // Paramètres : $field représente le champ facultatif utilisé pour filtrer ;
    // $value représente la valeur facultative recherchée ;
    // $orderBy représente le champ utilisé pour le tri ;
    // $direction représente le sens ASC ou DESC du tri.
    // Retour : tableau contenant les objets créés, ou tableau vide.
    public function list(
        ?string $field = null,
        mixed $value = null,
        string $orderBy = 'id',
        string $direction = 'ASC'
    ): array {
        // (SECURITE: "Les champs de filtre et de tri sont limités aux colonnes déclarées par le modèle afin d'éviter une injection par un identifiant SQL.")
        if (
            $field !== null &&
            in_array($field, $this->fieldNames(), true) === false
        ) {
            return [];
        }

        if (in_array($orderBy, $this->fieldNames(), true) === false) {
            $orderBy = $this->primaryKeyName;
        }

        // (SECURITE: "Le sens de tri est limité à ASC ou DESC avant son insertion dans la requête SQL.")
        // Seules les directions DESC et ASC sont autorisées.
        if (strtoupper($direction) === 'DESC') {
            $direction = 'DESC';
        } else {
            $direction = 'ASC';
        }

        // Le tri ayant été limité aux champs déclarés, il peut être ajouté sans accepter de SQL externe.
        $sql = 'SELECT ' . implode(', ', $this->fieldNames()) .
            ' FROM ' . $this->tableName;

        $parameters = [];

        if ($field !== null) {
            $sql .= ' WHERE ' . $field . ' = :value';
            $parameters[':value'] = $value;
        }

        $sql .= ' ORDER BY ' . $orderBy . ' ' . $direction;

        $rows = $this->database->fetchAll($sql, $parameters);
        if ($rows === false) {
            return [];
        }

        // Le tableau final contient des objets métier plutôt que des tableaux SQL bruts.
        $objects = [];

        // Chaque résultat devient un nouvel objet du même modèle.
        // Le clonage évite de rappeler le constructeur et d’ouvrir une nouvelle connexion PDO.
        foreach ($rows as $row) {
            $object = clone $this;
            $object->data = $row;
            $objects[] = $object;
        }

        return $objects;
    }

    // Rôle : récupérer les noms SQL des champs pouvant être sélectionnés,
    // en ajoutant la clé primaire lorsqu’elle n’est pas déjà déclarée.
    // Paramètres : Aucun.
    // Retour : tableau contenant les noms des champs sélectionnables.
    protected function fieldNames(): array
    {
        // Les modèles peuvent déclarer une simple liste de champs
        // ou un tableau associatif contenant leur configuration.
        $fieldNames = $this->writableFields;

        // La clé primaire est nécessaire aux recherches et aux mises à jour génériques.
        if (in_array($this->primaryKeyName, $fieldNames, true) === false) {
            array_unshift($fieldNames, $this->primaryKeyName);
        }

        return $fieldNames;
    }

    // Rôle : récupérer les champs pouvant être modifiés,
    // sans inclure la clé primaire.
    // Paramètres : Aucun.
    // Retour : tableau contenant les noms des champs modifiables.
    protected function writableFieldNames(): array
    {
        // La clé primaire sert uniquement à identifier une ligne : elle ne peut pas être modifiée.
        $writableFields = [];
        foreach ($this->fieldNames() as $field) {
            if ($field !== $this->primaryKeyName) {
                $writableFields[] = $field;
            }
        }
        return $writableFields;
    }

    /**
     * Rôle : Conserver uniquement des identifiants entiers strictement positifs et uniques.
     * Paramètres : Liste d'identifiants à contrôler.
     * Retour : Liste normalisée des identifiants valides.
     */
    protected function normalizePositiveIdentifiers(array $identifiers): array
    {
        // Le tableau associatif élimine les doublons tout en conservant des identifiants entiers.
        $normalizedIdentifiers = [];

        foreach ($identifiers as $identifier) {
            if (!is_int($identifier) && !is_string($identifier)) {
                continue;
            }

            $normalizedIdentifier = filter_var(
                $identifier,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($normalizedIdentifier === false) {
                continue;
            }

            $normalizedIdentifiers[(int) $normalizedIdentifier] = (int) $normalizedIdentifier;
        }

        // Les clés techniques utilisées pour supprimer les doublons ne sont pas transmises au modèle enfant.
        return array_values($normalizedIdentifiers);
    }

    // Rôle : récupérer la valeur textuelle d’un champ
    // ou une valeur par défaut si ce champ est vide.
    // Paramètres : $field représente le champ à lire ;
    // $default représente le texte retourné lorsque le champ est vide.
    // Retour : valeur textuelle du champ ou valeur par défaut.
    public function text(string $field, string $default = ''): string
    {
        // Cette aide fournit une valeur affichable même lorsqu'un champ optionnel est absent.
        $value = $this->get($field);

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    // Rôle : récupérer la valeur d’un champ de l’objet courant.
    // Paramètres : $field représente le nom du champ à lire.
    // Retour : valeur contenue dans le champ, ou null si le champ est absent.
    public function get(string $field)
    {
        // array_key_exists distingue un champ absent d'un champ présent dont la valeur vaut null.
        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }

        return null;
    }

    /**
     * Rôle : Lire une valeur de l'objet courant.
     * Paramètres : Nom du champ à lire.
     * Retour : Valeur du champ ou null.
     */
    public function getValue(string $field): mixed
    {
        return $this->get($field);
    }

    // Rôle : modifier un champ autorisé de l’objet courant.
    // Paramètres : $field représente le champ à modifier ;
    // $value représente la nouvelle valeur du champ.
    // Retour : true si la modification est acceptée, false dans le cas contraire.
    public function set(string $field, mixed $value): bool
    {
        // La clé primaire et les champs inconnus ne peuvent jamais être écrits par cette méthode.
        // (SECURITE: "Un champ inconnu ou une clé primaire ne peut pas être modifié par l'hydratation de l'objet.")
        if (
            $field === $this->primaryKeyName ||
            in_array($field, $this->writableFieldNames(), true) === false
        ) {
            return false;
        }

        $this->data[$field] = $value;

        return true;
    }

    // Rôle : modifier plusieurs champs autorisés de l’objet courant.
    // Paramètres : $fields contient les champs et les nouvelles valeurs.
    // Retour : true si toutes les modifications sont acceptées,
    // false dès qu’une modification est refusée.
    public function setFields(array $fields): bool
    {
        // Chaque champ passe par set() afin d'appliquer la même liste blanche à une modification groupée.
        foreach ($fields as $field => $value) {
            if ($this->set((string) $field, $value) === false) {
                return false;
            }
        }

        return true;
    }

    // Rôle : récupérer l’identifiant de l’objet courant.
    // Paramètres : Aucun.
    // Retour : identifiant de l’objet ou null lorsqu’il n’est pas chargé.
    public function id(): mixed
    {
        return $this->get($this->primaryKeyName);
    }
}
