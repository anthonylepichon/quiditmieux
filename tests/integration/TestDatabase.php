<?php

/*
 * Description générale :
 * Tests d'intégration du gestionnaire de base de données.
 * Rôle : Vérifier que la classe Database communique correctement avec la base de données et que ses principales méthodes fonctionnent.
 * Tâches :
 * - Vérifier la connexion PDO.
 * - Vérifier l'exécution d'une requête SQL.
 * - Vérifier l'insertion d'un enregistrement.
 * - Vérifier la récupération d'un enregistrement.
 * - Vérifier la récupération de plusieurs enregistrements.
 * - Vérifier la récupération du dernier identifiant inséré.
 * - Vérifier le fonctionnement des transactions.
 *
 * Liens :
 * - Utilise la classe Database située dans src/core/Database.php.
 * - Utilise la configuration privée située dans private/database-secret.php.
 * - Utilise le lanceur de tests défini dans tests/LanceurTest.php.
 * - Est exécuté depuis tests/Lancer.php.
 */

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

        if (is_array($enregistrement)
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
            $nombreEnregistrements = count($enregistrements);
        }

        $lanceurTests->verifierEgalite(
            2,
            $nombreEnregistrements,
            "Plusieurs enregistrements doivent pouvoir être récupérés avec fetchAll"
        );


        /*
         * TEST 8
         * Vérification du démarrage d'une transaction.
         */

        $transactionDemarree = $baseDeDonnees->beginTransaction();

        $lanceurTests->verifierEgalite(
            true,
            $transactionDemarree,
            "Une transaction doit pouvoir être démarrée"
        );


        /*
         * TEST 9
         * Vérification du rollback.
         */

        if ($transactionDemarree) {

            $baseDeDonnees->execute(
                'INSERT INTO test_database (libelle)
                 VALUES (:libelle)',
                [
                    'libelle' => 'Enregistrement à annuler',
                ]
            );

            $transactionAnnulee = $baseDeDonnees->rollback();

            $lanceurTests->verifierEgalite(
                true,
                $transactionAnnulee,
                "Une transaction doit pouvoir être annulée avec rollback"
            );


            /*
             * Vérification que l'insertion a bien été annulée.
             */

            $enregistrementAnnule = $baseDeDonnees->fetchOne(
                'SELECT id, libelle
                 FROM test_database
                 WHERE libelle = :libelle',
                [
                    'libelle' => 'Enregistrement à annuler',
                ]
            );

            $lanceurTests->verifierEgalite(
                null,
                $enregistrementAnnule,
                "Un enregistrement annulé par rollback ne doit pas être conservé"
            );
        }


        /*
         * TEST 10
         * Vérification d'une transaction validée avec commit.
         */

        $transactionDemarree = $baseDeDonnees->beginTransaction();

        $lanceurTests->verifierEgalite(
            true,
            $transactionDemarree,
            "Une nouvelle transaction doit pouvoir être démarrée"
        );


        if ($transactionDemarree) {

            $baseDeDonnees->execute(
                'INSERT INTO test_database (libelle)
                 VALUES (:libelle)',
                [
                    'libelle' => 'Enregistrement validé',
                ]
            );

            $transactionValidee = $baseDeDonnees->commit();

            $lanceurTests->verifierEgalite(
                true,
                $transactionValidee,
                "Une transaction doit pouvoir être validée avec commit"
            );


            /*
             * Vérification que l'enregistrement validé existe.
             */

            $enregistrementValide = $baseDeDonnees->fetchOne(
                'SELECT id, libelle
                 FROM test_database
                 WHERE libelle = :libelle',
                [
                    'libelle' => 'Enregistrement validé',
                ]
            );

            $libelleValide = null;

            if (is_array($enregistrementValide)
                && isset($enregistrementValide['libelle'])
            ) {
                $libelleValide = $enregistrementValide['libelle'];
            }

            $lanceurTests->verifierEgalite(
                'Enregistrement validé',
                $libelleValide,
                "Un enregistrement validé par commit doit être conservé"
            );
        }


        /*
         * Nettoyage de la table temporaire utilisée pendant les tests.
         */

        $baseDeDonnees->execute(
            'DROP TEMPORARY TABLE IF EXISTS test_database'
        );
    }
}
