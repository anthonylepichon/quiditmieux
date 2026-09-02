<?php

/*
 * Description générale :
 * Point d'entrée permettant de lancer l'ensemble des tests
 * automatisés de l'application.
 *
 * Rôle :
 * Charger le lanceur de tests, exécuter les tests unitaires
 * et les tests d'intégration, puis afficher le résumé des résultats.
 *
 * Tâches :
 * - Initialiser le lanceur de tests.
 * - Exécuter les tests unitaires.
 * - Exécuter les tests d'intégration.
 * - Identifier chaque élément testé.
 * - Afficher le résumé détaillé des résultats.
 *
 * Liens :
 * - Utilise tests/LanceurTest.php.
 * - Exécute les fichiers présents dans tests/unitaire/.
 * - Exécute les fichiers présents dans tests/integration/.
 */


/*
 * Démarre la mise en mémoire tampon de la sortie.
 * Cela évite que les affichages réalisés avec echo soient envoyés
 * immédiatement, afin de permettre notamment le démarrage d'une
 * session PHP sans erreur d'en-têtes déjà envoyés.
 */

ob_start();


/*
 * Chargement de la classe permettant de gérer
 * et comptabiliser les résultats des tests.
 */

require_once __DIR__ . '/LanceurTest.php';


/*
 * Création du lanceur de tests.
 */

$lanceurTests = new LanceurTest();


/*
 * Affichage du titre général.
 */

echo "========================================" . PHP_EOL;
echo "          LANCEMENT DES TESTS" . PHP_EOL;
echo "========================================" . PHP_EOL;


/*
 * ====================
 * TESTS UNITAIRES
 * ====================
 */

echo PHP_EOL;
echo "----- TESTS UNITAIRES -----" . PHP_EOL;


/*
 * Tests de Session.php.
 */

$lanceurTests->commencerItem('Session.php');

require_once __DIR__ . '/unitaire/TestSession.php';


/*
 * Tests de CategoryModel.php.
 */

$lanceurTests->commencerItem('CategoryModel.php');

require_once __DIR__ . '/unitaire/TestCategoryModel.php';


/*
 * ====================
 * TESTS D'INTÉGRATION
 * ====================
 */

echo PHP_EOL;
echo "----- TESTS D'INTÉGRATION -----" . PHP_EOL;


/*
 * Tests de Database.php.
 */

$lanceurTests->commencerItem('Database.php');

require_once __DIR__ . '/integration/TestDatabase.php';


/*
 * Tests de ListingModel.php.
 */

$lanceurTests->commencerItem('ListingModel.php');

require_once __DIR__ . '/integration/TestListingModel.php';


/*
 * ====================
 * RÉSULTATS
 * ====================
 */

/*
 * Affichage du résumé détaillé des tests,
 * séparé par élément testé.
 */

$lanceurTests->afficherResume();


/*
 * Envoie au terminal tout le contenu mis en mémoire tampon,
 * puis désactive la mise en mémoire tampon.
 */

ob_end_flush();
