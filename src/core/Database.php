<?php

/**
 * Description générale : Gestionnaire commun de la base de données de l'application.
 * Rôle : Créer la connexion PDO et fournir les opérations communes d'exécution des requêtes.
 * Tâches : Configurer PDO, exécuter une requête et récupérer un ou plusieurs enregistrements.
 * Liens avec les autres fichiers : Est créée par App.php puis transmise par Router.php aux contrôleurs et aux modèles.
 */

namespace App\core;

use PDO;
use PDOException;

class Database
{
    // ====================
    // ATTRIBUTS
    // ====================

    private ?PDO $connection = null;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Créer la connexion PDO à partir de la configuration privée.
     * Paramètres : Tableau contenant les paramètres de connexion à la base de données.
     * Retour : Aucun.
     */
    public function __construct(array $databaseConfig)
    {
        if (!$this->configurationIsValid($databaseConfig)) {
            return;
        }

        $dsn = 'mysql:host=' . $databaseConfig['host']
            . ';port=' . $databaseConfig['port']
            . ';dbname=' . $databaseConfig['database']
            . ';charset=' . $databaseConfig['charset'];

        try {
            $this->connection = new PDO(
                $dsn,
                $databaseConfig['username'],
                $databaseConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException) {
            $this->connection = null;
        }
    }

    /**
     * Rôle : Indiquer si la connexion PDO a été créée correctement.
     * Paramètres : Aucun.
     * Retour : true si la connexion est disponible, sinon false.
     */
    public function isConnected(): bool
    {
        return $this->connection instanceof PDO;
    }

    /**
     * Rôle : Préparer et exécuter une requête ne nécessitant pas de résultat à retourner.
     * Paramètres : Requête SQL et tableau facultatif de paramètres.
     * Retour : true si la requête est exécutée, sinon false.
     */
    public function execute(string $sql, array $parameters = []): bool
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $statement = $this->connection->prepare($sql);
            return $statement->execute($parameters);
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Rôle : Préparer une requête et récupérer son premier enregistrement.
     * Paramètres : Requête SQL et tableau facultatif de paramètres.
     * Retour : Tableau du premier enregistrement, null s'il est absent ou false en cas d'erreur SQL.
     */
    public function fetchOne(string $sql, array $parameters = []): array|false|null
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($parameters);
            $record = $statement->fetch();
        } catch (PDOException) {
            return false;
        }

        if ($record === false) {
            return null;
        }

        if (!is_array($record)) {
            return false;
        }

        return $record;
    }

    /**
     * Rôle : Préparer une requête et récupérer tous ses enregistrements.
     * Paramètres : Requête SQL et tableau facultatif de paramètres.
     * Retour : Tableau des enregistrements, éventuellement vide, ou false en cas d'erreur SQL.
     */
    public function fetchAll(string $sql, array $parameters = []): array|false
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($parameters);
            return $statement->fetchAll();
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Rôle : Récupérer l'identifiant entier généré par la dernière insertion.
     * Paramètres : Aucun.
     * Retour : Identifiant généré, null s'il n'est pas disponible ou false en cas d'erreur PDO.
     */
    public function getLastInsertId(): int|false|null
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $lastInsertId = $this->connection->lastInsertId();
        } catch (PDOException) {
            return false;
        }

        if ($lastInsertId === false) {
            return false;
        }

        if ($lastInsertId === '0') {
            return null;
        }

        return (int) $lastInsertId;
    }

    /**
     * Rôle : Démarrer une transaction lorsqu'aucune transaction n'est déjà active.
     * Paramètres : Aucun.
     * Retour : true si la transaction est active, sinon false.
     */
    public function beginTransaction(): bool
    {
        if ($this->connection === null || $this->connection->inTransaction()) {
            return false;
        }

        try {
            return $this->connection->beginTransaction();
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Rôle : Valider la transaction active.
     * Paramètres : Aucun.
     * Retour : true si la validation réussit, sinon false.
     */
    public function commit(): bool
    {
        if ($this->connection === null || !$this->connection->inTransaction()) {
            return false;
        }

        try {
            return $this->connection->commit();
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Rôle : Annuler la transaction active afin de préserver la cohérence des données.
     * Paramètres : Aucun.
     * Retour : true si l'annulation réussit ou si aucune transaction n'est active, sinon false.
     */
    public function rollback(): bool
    {
        if ($this->connection === null) {
            return false;
        }

        if (!$this->connection->inTransaction()) {
            return true;
        }

        try {
            return $this->connection->rollBack();
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Rôle : Vérifier la présence et le type des paramètres nécessaires à PDO.
     * Paramètres : Tableau de configuration à contrôler.
     * Retour : true si la configuration est exploitable, sinon false.
     */
    private function configurationIsValid(array $databaseConfig): bool
    {
        if (!isset(
            $databaseConfig['host'],
            $databaseConfig['port'],
            $databaseConfig['database'],
            $databaseConfig['charset'],
            $databaseConfig['username'],
            $databaseConfig['password']
        )) {
            return false;
        }

        if (!is_string($databaseConfig['host'])
            || (!is_int($databaseConfig['port']) && !is_string($databaseConfig['port']))
            || !is_string($databaseConfig['database'])
            || !is_string($databaseConfig['charset'])
            || !is_string($databaseConfig['username'])
            || !is_string($databaseConfig['password'])
        ) {
            return false;
        }

        return true;
    }
}
