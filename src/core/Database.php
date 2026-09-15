<?php

/**
 * Description générale : Gestionnaire commun de la base de données de l'application.
 * Rôle : Créer la connexion PDO et fournir les opérations communes d'exécution des requêtes. Une seule configuration de connexion est ainsi utilisée par tous les modèles, ce qui évite des ouvertures différentes ou mal configurées.
 * Tâches : Configurer PDO, exécuter une requête et récupérer un ou plusieurs enregistrements.
 * Liens avec les autres fichiers : Est créée par App.php puis transmise par Router.php aux contrôleurs et aux modèles.
 */

namespace App\core;

// NATIF PHP : PDO est la classe native qui représente une connexion à la base de données ; elle permet ici d'exécuter des requêtes préparées.
use PDO;
// NATIF PHP : PDOException est l’exception native produite par PDO ; elle permet ici de détecter un échec de connexion ou de requête SQL.
use PDOException;

class Database
{
    // ====================
    // ATTRIBUTS
    // ====================

    // La connexion est nulle tant que PDO n'a pas réussi à joindre la base de données.
    private ?PDO $bdd = null;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Créer la connexion PDO à partir de la configuration privée. Une seule configuration de connexion est ainsi utilisée par tous les modèles, ce qui évite des ouvertures différentes ou mal configurées.
     * Paramètres : Tableau contenant les paramètres de connexion à la base de données.
     * Retour : Aucun.
     */
    public function __construct(array $databaseConfig)
    {
        // Le DSN rassemble les paramètres techniques sans afficher ni enregistrer le mot de passe.
        $dsn = 'mysql:host=' . $databaseConfig['host']
            . ';port=' . $databaseConfig['port']
            . ';dbname=' . $databaseConfig['database']
            . ';charset=' . $databaseConfig['charset'];

        // PDO peut échouer si le serveur, les identifiants ou la base sont indisponibles.
        try {
            $this->bdd = new PDO(
                $dsn,
                $databaseConfig['username'],
                $databaseConfig['password'],
                [
                    // NATIF PHP : PDO::ATTR_ERRMODE désigne le réglage de gestion des erreurs ; il permet ici de définir le comportement de PDO face à une erreur SQL.
                    // NATIF PHP : PDO::ERRMODE_EXCEPTION demande à PDO de lancer une exception ; elle permet ici au bloc catch de traiter un échec de connexion.
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    // NATIF PHP : PDO::ATTR_DEFAULT_FETCH_MODE désigne le format de récupération par défaut ; il évite ici de le répéter dans chaque requête.
                    // NATIF PHP : PDO::FETCH_ASSOC retourne les lignes avec les noms de colonnes comme clés ; elle rend ici les résultats plus faciles à exploiter.
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException) {
            // Aucun détail technique n'est envoyé au visiteur : App décide du message générique à afficher.
            $this->bdd = null;
        }
    }

    /**
     * Rôle : Indiquer si la connexion PDO a été créée correctement. L'application peut ainsi interrompre proprement les traitements qui nécessitent MySQL lorsque la connexion a échoué.
     * Paramètres : Aucun.
     * Retour : true si la connexion est disponible, sinon false.
     */
    public function isConnected(): bool
    {
        // instanceof confirme que l'objet conservé est bien une connexion PDO utilisable.
        return $this->bdd instanceof PDO;
    }

    /**
     * Rôle : Préparer et exécuter une requête ne nécessitant pas de résultat à retourner. La préparation centralisée des requêtes évite de concaténer directement les valeurs reçues dans le SQL.
     * Paramètres : Requête SQL et tableau facultatif de paramètres.
     * Retour : true si la requête est exécutée, sinon false.
     */
    public function execute(string $sql, array $parameters = []): bool
    {
        // Sans connexion active, aucune requête ne doit être tentée.
        if ($this->bdd === null) {
            return false;
        }

        // La requête est préparée avant de recevoir ses valeurs afin de les séparer du code SQL.
        try {
            $statement = $this->bdd->prepare($sql);
            // Les paramètres proviennent des modèles et sont liés par PDO lors de l'exécution.
            return $statement->execute($parameters);
        } catch (PDOException) {
            // L'échec est signalé au modèle par false, sans exposer le message PDO.
            return false;
        }
    }

    /**
     * Rôle : Préparer une requête et récupérer son premier enregistrement. Cela évite que chaque modèle répète la préparation de la requête et la lecture d'une ligne unique.
     * Paramètres : Requête SQL et tableau facultatif de paramètres.
     * Retour : Tableau du premier enregistrement, null s'il est absent ou false en cas d'erreur SQL.
     */
    public function fetchOne(string $sql, array $parameters = []): array|false|null
    {
        // Sans connexion active, aucune lecture ne doit être tentée.
        if ($this->bdd === null) {
            return false;
        }

        // La préparation et l'exécution restent centralisées ici pour tous les modèles.
        try {
            $statement = $this->bdd->prepare($sql);
            $statement->execute($parameters);
            $record = $statement->fetch();
        } catch (PDOException) {
            return false;
        }

        // fetch() retourne false lorsqu'aucune ligne ne correspond ; l'application utilise null pour cet état normal.
        if ($record === false) {
            return null;
        }

        return $record;
    }

    /**
     * Rôle : Préparer une requête et récupérer tous ses enregistrements. Cela évite que chaque modèle répète la préparation de la requête et la lecture d'une liste de résultats.
     * Paramètres : Requête SQL et tableau facultatif de paramètres.
     * Retour : Tableau des enregistrements, éventuellement vide, ou false en cas d'erreur SQL.
     */
    public function fetchAll(string $sql, array $parameters = []): array|false
    {
        // Sans connexion active, aucune lecture ne doit être tentée.
        if ($this->bdd === null) {
            return false;
        }

        // La requête est préparée et exécutée avant de récupérer toutes les lignes associatives.
        try {
            $statement = $this->bdd->prepare($sql);
            $statement->execute($parameters);
            return $statement->fetchAll();
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Rôle : Récupérer l'identifiant entier généré par la dernière insertion. Le modèle peut ainsi retrouver immédiatement la ligne créée pour poursuivre le traitement ou effectuer une redirection.
     * Paramètres : Aucun.
     * Retour : Identifiant généré ou false en cas d'erreur PDO.
     */
    public function getLastInsertId(): int|false
    {
        // L'identifiant n'existe que lorsqu'une connexion PDO a exécuté une insertion.
        if ($this->bdd === null) {
            return false;
        }

        // PDO fournit le dernier identifiant généré par la connexion courante.
        try {
            return (int) $this->bdd->lastInsertId();
        } catch (PDOException) {
            return false;
        }
    }
}
