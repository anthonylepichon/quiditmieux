<?php

/**
 * Description générale : Routeur central de l'application.
 * Rôle : Associer chaque demande au contrôleur et à la méthode prévus par la configuration.
 * Tâches : Enregistrer les routes, contrôler la méthode HTTP et appeler le traitement correspondant.
 * Liens avec les autres fichiers : Est utilisé par App.php, reçoit les routes de routes.php et lance les contrôleurs enfants.
 */

namespace App\core;

class Router
{
    // ====================
    // ATTRIBUTS
    // ====================

    private Database $database;
    private Session $session;
    private array $routes = [];

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Conserver les gestionnaires de base de données et de session à transmettre au contrôleur sélectionné.
     * Paramètres : Gestionnaires de la base de données et de la session créés pour la requête courante.
     * Retour : Aucun.
     */
    public function __construct(Database $database, Session $session)
    {
        $this->database = $database;
        $this->session = $session;
    }

    /**
     * Rôle : Enregistrer les routes disponibles dans l'application.
     * Paramètres : Tableau associatif des définitions de routes.
     * Retour : Aucun.
     */
    public function registerRoutes(array $routes): void
    {
        $this->routes = $routes;
    }

    /**
     * Rôle : Vérifier la demande puis appeler le contrôleur et la méthode associés à la route.
     * Paramètres : Nom de la route demandée et méthode HTTP reçue.
     * Retour : Aucun.
     */
    public function dispatch(string $requestedRoute, string $requestMethod): void
    {
        if (!isset($this->routes[$requestedRoute]) || !is_array($this->routes[$requestedRoute])) {
            echo 'La page demandée est indisponible.';
            return;
        }

        $routeDefinition = $this->routes[$requestedRoute];

        if (!isset(
            $routeDefinition['method'],
            $routeDefinition['controller'],
            $routeDefinition['action']
        )) {
            echo 'La page demandée est indisponible.';
            return;
        }

        if (!is_string($routeDefinition['method'])
            || !is_string($routeDefinition['controller'])
            || !is_string($routeDefinition['action'])
        ) {
            echo 'La page demandée est indisponible.';
            return;
        }

        if (strtoupper($requestMethod) !== strtoupper($routeDefinition['method'])) {
            echo 'Cette action ne peut pas être exécutée de cette manière.';
            return;
        }

        $controllerClass = $routeDefinition['controller'];
        $action = $routeDefinition['action'];

        if (!class_exists($controllerClass) || !is_subclass_of($controllerClass, Controller::class)) {
            echo 'La page demandée est indisponible.';
            return;
        }

        $controller = new $controllerClass($this->database, $this->session);

        if (!method_exists($controller, $action) || !is_callable([$controller, $action])) {
            echo 'La page demandée est indisponible.';
            return;
        }

        $controller->$action();
    }
}
