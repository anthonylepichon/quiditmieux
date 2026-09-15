<?php

/*
 * Description générale :
 * Tests d'intégration du gestionnaire de base de données.
 * Rôle : Vérifier que la classe Database communique correctement avec la base de données et que ses principales méthodes fonctionnent. Cette vérification permet de détecter une régression avant la présentation ou la livraison du projet.
 * Tâches :
 * - Vérifier la connexion PDO.
 * - Vérifier l'exécution d'une requête SQL.
 * - Vérifier l'insertion d'un enregistrement.
 * - Vérifier la récupération d'un enregistrement.
 * - Vérifier la récupération de plusieurs enregistrements.
 * - Vérifier la récupération du dernier identifiant inséré.
 *
 * Liens :
 * - Utilise la classe Database située dans src/core/Database.php.
 * - Utilise la configuration privée située dans private/database-secret.php.
 * - Utilise le lanceur de tests défini dans tests/LanceurTest.php.
 * - Est exécuté depuis tests/Lancer.php.
 */

// NATIF PHP : __DIR__ contient le chemin absolu du dossier du fichier courant ; elle permet ici de construire un chemin indépendant du poste utilisé.
require_once __DIR__ . '/../../src/core/Database.php';

use App\core\Database;


/*
 * Chargement de la configuration privée de la base de données.
 * Les identifiants de connexion ne sont ainsi pas dupliqués
 * dans les fichiers de tests.
 */

$configurationBaseDeDonnees = require __DIR__ . '/../../private/database-secret.php';


/*
 * Création de la connexion à la base de données.
 */

$baseDeDonnees = new Database($configurationBaseDeDonnees);


/*
 * TEST 1
 * Vérification de la connexion à la base de données.
 */

$connexionReussie = $baseDeDonnees->isConnected();

$lanceurTests->verifierEgalite(
    true,
    $connexionReussie,
    "La connexion à la base de données doit être établie"
);


/*
 * Les autres tests sont exécutés uniquement
 * si la connexion à la base fonctionne.
 */

if ($connexionReussie) {

    /*
     * Suppression éventuelle d'une ancienne table temporaire.
     */

    $baseDeDonnees->execute(
        'DROP TEMPORARY TABLE IF EXISTS test_database'
    );


    /*
     * TEST 2
     * Création d'une table temporaire.
     *
     * Cette table appartient uniquement à la connexion utilisée
     * pendant les tests et ne modifie pas les tables de l'application.
     */

    $tableCreee = $baseDeDonnees->execute(
        'CREATE TEMPORARY TABLE test_database (
            id INT AUTO_INCREMENT PRIMARY KEY,
            libelle VARCHAR(100) NOT NULL
        )'
    );

    $lanceurTests->verifierEgalite(
        true,
        $tableCreee,
        "Une table temporaire doit pouvoir être créée"
    );


    /*
     * La suite nécessite que la table temporaire existe.
     */

    if ($tableCreee) {

        /*
         * TEST 3
         * Vérification de la méthode execute().
         */

        $insertionReussie = $baseDeDonnees->execute(
            'INSERT INTO test_database (libelle)
             VALUES (:libelle)',
            [
                'libelle' => 'Premier test',
            ]
        );

        $lanceurTests->verifierEgalite(
            true,
            $insertionReussie,
            "Un enregistrement doit pouvoir être ajouté avec execute"
        );


        /*
         * TEST 4
         * Vérification de getLastInsertId().
         */

        $dernierIdentifiant = $baseDeDonnees->getLastInsertId();

        $lanceurTests->verifierEgalite(
            1,
            $dernierIdentifiant,
            "Le dernier identifiant inséré doit être récupéré"
        );


        /*
         * TEST 5
         * Vérification de fetchOne().
         */

        $enregistrement = $baseDeDonnees->fetchOne(
            'SELECT id, libelle
             FROM test_database
             WHERE id = :id',
            [
                'id' => 1,
            ]
        );

        $libelleObtenu = null;

        // NATIF PHP : is_array() vérifie qu’une valeur est un tableau ; il évite ici de parcourir ou transmettre un type inattendu.
        if (is_array($enregistrement)
            // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
            && isset($enregistrement['libelle'])
        ) {
            $libelleObtenu = $enregistrement['libelle'];
        }

        $lanceurTests->verifierEgalite(
            'Premier test',
            $libelleObtenu,
            "Un enregistrement doit pouvoir être récupéré avec fetchOne"
        );


        /*
         * TEST 6
         * Vérification que fetchOne() retourne null
         * lorsqu'aucun enregistrement n'est trouvé.
         */

        $enregistrementAbsent = $baseDeDonnees->fetchOne(
            'SELECT id, libelle
             FROM test_database
             WHERE id = :id',
            [
                'id' => 9999,
            ]
        );

        $lanceurTests->verifierEgalite(
            null,
            $enregistrementAbsent,
            "fetchOne doit retourner null lorsqu'aucun enregistrement n'est trouvé"
        );


        /*
         * TEST 7
         * Ajout d'un deuxième enregistrement.
         */

        $baseDeDonnees->execute(
            'INSERT INTO test_database (libelle)
             VALUES (:libelle)',
            [
                'libelle' => 'Deuxième test',
            ]
        );


        /*
         * Vérification de fetchAll().
         */

        $enregistrements = $baseDeDonnees->fetchAll(
            'SELECT id, libelle
             FROM test_database
             ORDER BY id ASC'
        );

        $nombreEnregistrements = 0;

        if (is_array($enregistrements)) {
            // NATIF PHP : count() compte les éléments d’un tableau ; il permet ici de connaître la quantité avant le traitement.
            $nombreEnregistrements = count($enregistrements);
        }

        $lanceurTests->verifierEgalite(
            2,
            $nombreEnregistrements,
            "Plusieurs enregistrements doivent pouvoir être récupérés avec fetchAll"
        );




        /*
         * Nettoyage de la table temporaire utilisée pendant les tests.
         */

        $baseDeDonnees->execute(
            'DROP TEMPORARY TABLE IF EXISTS test_database'
        );
    }
}
