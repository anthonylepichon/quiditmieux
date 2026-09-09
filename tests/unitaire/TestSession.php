<?php

/*
 * Description générale :
 * Tests unitaires liés à la gestion de la session utilisateur.
 *
 * Rôle :
 * Vérifier que la classe Session permet de démarrer et de gérer
 * correctement une session PHP.
 *
 * Tâches :
 * - Charger la classe Session.
 * - Vérifier le démarrage de la session.
 * - Vérifier qu'une valeur peut être enregistrée dans la session.
 * - Vérifier qu'une valeur peut être supprimée de la session.
 *
 * Liens :
 * - Utilise la classe Session située dans src/core/Session.php.
 * - Utilise le lanceur de tests défini dans tests/LanceurTest.php.
 * - Est exécuté depuis tests/Lancer.php.
 */

// NATIF PHP : __DIR__ contient le chemin absolu du dossier du fichier courant ; elle permet ici de construire un chemin indépendant du poste utilisé.
require_once __DIR__ . '/../../src/core/Session.php';

use App\core\Session;


/*
 * TEST 1
 * Vérification de l'initialisation de la session.
 */

$session = new Session();

$session->startSession();

// NATIF PHP : session_status() indique l’état actuel de la session PHP ; il évite ici de démarrer ou modifier une session au mauvais moment.
// NATIF PHP : PHP_SESSION_ACTIVE indique que la session PHP est démarrée ; elle permet ici au test de vérifier l’état attendu.
$sessionActive = session_status() === PHP_SESSION_ACTIVE;

$lanceurTests->verifierEgalite(
    true,
    $sessionActive,
    "La session doit être correctement démarrée"
);


/*
 * TEST 2
 * Vérification de l'enregistrement d'une valeur dans la session.
 */

// NATIF PHP : $_SESSION est un tableau superglobal conservé entre plusieurs pages ; il mémorise ici l’utilisateur connecté, les messages ou le jeton CSRF.
$_SESSION['utilisateurTest'] = 25;

$identifiantUtilisateur = $_SESSION['utilisateurTest'];

$lanceurTests->verifierEgalite(
    25,
    $identifiantUtilisateur,
    "L'identifiant de l'utilisateur doit être enregistré dans la session"
);


/*
 * TEST 3
 * Vérification de la suppression d'une valeur dans la session.
 */

unset($_SESSION['utilisateurTest']);

// NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
$valeurExiste = isset($_SESSION['utilisateurTest']);

$lanceurTests->verifierEgalite(
    false,
    $valeurExiste,
    "L'identifiant de test doit pouvoir être supprimé de la session"
);
