<?php

/**
 * Description générale : Classe principale de l'application.
 * Rôle : Coordonner le démarrage de l'application et transmettre la demande au routeur.
 * Tâches : Initialiser Session et Database, charger les routes et lancer leur traitement.
 * Liens avec les autres fichiers : Est lancée par index.php et utilise Session.php, Database.php, Router.php et les configurations privées.
 */

namespace App\core;

// Cette classe charge les routes, puis demande au routeur d’appeler le bon contrôleur.
class App
{
    // ====================
    // ATTRIBUTS
    // ====================

    private string $projectRoot;

    // ====================
    // METHODES
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
        // La session est créée une seule fois pour toute la demande HTTP.
        // Elle sera transmise aux contrôleurs par le routeur.
        $session = new Session();
        $session->startSession();

        // La configuration de connexion reste privée : elle ne doit jamais être placée dans le dépôt Git.
        $databaseConfigPath = $this->projectRoot . '/private/database-secret.php';

        if (!is_file($databaseConfigPath)) {
            echo 'La configuration de la base de données est indisponible.';
            return;
        }

        // Le fichier privé retourne uniquement les paramètres nécessaires à PDO.
        $databaseConfig = require $databaseConfigPath;
        if (!is_array($databaseConfig)) {
            echo 'La configuration de la base de données est indisponible.';
            return;
        }

        // La connexion est créée une seule fois puis partagée avec tous les modèles de la demande.
        $database = new Database($databaseConfig);
        if (!$database->isConnected()) {
            echo 'La connexion à la base de données est momentanément indisponible.';
            return;
        }

        // Les routes sont centralisées dans leur fichier de configuration, sans logique métier ici.
        $routes = require $this->projectRoot . '/src/config/routes.php';
        if (!is_array($routes)) {
            echo 'La navigation de l’application est indisponible.';
            return;
        }

        // La route d'accueil est utilisée lorsqu'aucune route textuelle n'est demandée.
        $route = 'home';
        if (isset($_GET['route']) && is_string($_GET['route']) && $_GET['route'] !== '') {
            $route = $_GET['route'];
        }
        // La méthode HTTP est transmise au routeur afin qu'il refuse les actions appelées avec le mauvais verbe.
        $method = 'GET';
        if (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])) {
            $method = strtoupper($_SERVER['REQUEST_METHOD']);
        }

        // Le routeur reçoit les services communs et choisit ensuite le seul contrôleur autorisé par la configuration.
        $router = new Router($database, $session);
        $router->registerRoutes($routes);
        $router->dispatch($route, $method);
    }
}
