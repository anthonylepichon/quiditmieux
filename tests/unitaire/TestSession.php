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

require_once __DIR__ . '/../../src/core/Session.php';

use App\core\Session;


/*
 * TEST 1
 * Vérification de l'initialisation de la session.
 */

$session = new Session();

$session->demarrerSession();

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

$valeurExiste = isset($_SESSION['utilisateurTest']);

$lanceurTests->verifierEgalite(
    false,
    $valeurExiste,
    "L'identifiant de test doit pouvoir être supprimé de la session"
);
