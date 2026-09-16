<?php

/**
 * Description générale : Test de la réponse produite pour une route inconnue.
 * Rôle : Vérifier que le routeur retourne un statut 404 et un message compréhensible.
 * Tâches : Créer le routeur sans route enregistrée puis lui demander une route inexistante.
 * Liens : Utilise Database, Session, Router et le lanceur de tests.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\core\Database;
use App\core\Router;
use App\core\Session;

$configurationRouter = require __DIR__ . '/../../private/database-secret.php';
$databaseRouter = new Database($configurationRouter);
$sessionRouter = new Session();

$lanceurTests->verifierEgalite(
    true,
    $databaseRouter->isConnected(),
    'La base doit être disponible pour construire le routeur'
);

if ($databaseRouter->isConnected()) {
    $routerTested = new Router($databaseRouter, $sessionRouter);
    $routerTested->registerRoutes([]);

    http_response_code(200);
    ob_start();
    $routerTested->dispatch('route_inconnue_pour_test', 'GET');
    $routerOutput = (string) ob_get_clean();
    $routerStatus = http_response_code();
    http_response_code(200);

    $lanceurTests->verifierEgalite(
        404,
        $routerStatus,
        'Une route inconnue doit retourner le statut HTTP 404'
    );
    $lanceurTests->verifierEgalite(
        'Page introuvable.',
        $routerOutput,
        'Une route inconnue doit afficher un message compréhensible'
    );
}
