<?php

/**
 * Description générale : Classe principale de l'application.
 * Rôle : Coordonner le démarrage de l'application et transmettre la demande au routeur.
 * Tâches : Initialiser Session et Database, charger les routes et lancer leur traitement.
 * Liens avec les autres fichiers : Est lancée par index.php et utilise Session.php, Database.php, Router.php et les configurations privées.
 */

namespace App\core;

class App
{
    // ====================
    // ATTRIBUTS
    // ====================
    
    private string $projectRoot;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Conserver le chemin absolu de la racine du projet.
     * Paramètres : Chemin absolu de la racine du projet.
     * Retour : Aucun.
     */
    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
    }

    /**
     * Rôle : Démarrer les services nécessaires puis transmettre la demande au routeur.
     * Paramètres : Aucun.
     * Retour : Aucun.
     */
    public function run(): void
    {
        $session = new Session();
        $session->demarrerSession();

        $database = $this->createDatabase();

        if ($database === null) {
            return;
        }

        $routes = $this->loadRoutes();

        if ($routes === null) {
            return;
        }

        $router = new Router($database, $session);
        $router->registerRoutes($routes);
        $router->dispatch($this->getRequestedRoute(), $this->getRequestMethod());
    }

    /**
     * Rôle : Créer le gestionnaire de base de données à partir de la configuration privée.
     * Paramètres : Aucun.
     * Retour : Objet Database ou null lorsque la configuration ou la connexion est indisponible.
     */
    private function createDatabase(): ?Database
    {
        $databaseConfigPath = $this->projectRoot . '/private/database-secret.php';

        if (!is_file($databaseConfigPath)) {
            echo 'La configuration de la base de données est indisponible.';
            return null;
        }

        $databaseConfig = require $databaseConfigPath;

        if (!is_array($databaseConfig)) {
            echo 'La configuration de la base de données est indisponible.';
            return null;
        }

        $database = new Database($databaseConfig);

        if (!$database->isConnected()) {
            echo 'La connexion à la base de données est momentanément indisponible.';
            return null;
        }

        return $database;
    }

    /**
     * Rôle : Charger la liste des routes configurées pour l'application.
     * Paramètres : Aucun.
     * Retour : Tableau des routes ou null lorsque la configuration est indisponible.
     */
    private function loadRoutes(): ?array
    {
        $routesConfigPath = $this->projectRoot . '/src/config/routes.php';

        if (!is_file($routesConfigPath)) {
            echo 'La navigation de l’application est indisponible.';
            return null;
        }

        $routes = require $routesConfigPath;

        if (!is_array($routes)) {
            echo 'La navigation de l’application est indisponible.';
            return null;
        }

        return $routes;
    }

    /**
     * Rôle : Obtenir le nom de la route demandée ou utiliser la route d'accueil par défaut.
     * Paramètres : Aucun.
     * Retour : Nom de la route à traiter.
     */
    private function getRequestedRoute(): string
    {
        if (isset($_GET['route']) && is_string($_GET['route']) && $_GET['route'] !== '') {
            return $_GET['route'];
        }

        return 'home';
    }

    /**
     * Rôle : Obtenir la méthode HTTP de la demande courante.
     * Paramètres : Aucun.
     * Retour : Méthode HTTP en lettres majuscules.
     */
    private function getRequestMethod(): string
    {
        if (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])) {
            return strtoupper($_SERVER['REQUEST_METHOD']);
        }

        return 'GET';
    }
}
