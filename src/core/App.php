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
     * Rôle : Préparer l’objet principal de l’application en mémorisant le chemin de la racine du projet. Ce chemin permet ensuite de retrouver les fichiers de configuration nécessaires au démarrage.
     * Paramètres : $projectRoot contient le chemin absolu du dossier principal du projet, transmis par index.php.
     * Retour : Aucun. Le constructeur enregistre le chemin reçu dans l’attribut $projectRoot.
     */
    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
    }

    /**
     * Rôle : Coordonner le démarrage de l’application. La méthode démarre la session, prépare la connexion à la base de données, charge les routes, puis confie la demande HTTP au routeur.
     * Paramètres : Aucun. La méthode utilise la configuration du projet ainsi que les informations de la requête reçue par le serveur.
     * Retour : Aucun. Le traitement s’arrête si la base ou les routes sont indisponibles ; sinon le routeur appelle le contrôleur correspondant.
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
     * Rôle : Préparer l’accès à MySQL. La méthode charge la configuration privée, crée l’objet Database et vérifie que la connexion PDO est disponible.
     * Paramètres : Aucun. Le chemin du fichier de configuration est construit à partir de l’attribut $projectRoot.
     * Retour : Un objet Database prêt à être utilisé, ou null si le fichier, sa configuration ou la connexion est indisponible.
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
     * Rôle : Charger la table de navigation de l’application depuis src/config/routes.php et vérifier que le fichier retourne bien un tableau.
     * Paramètres : Aucun. Le chemin de routes.php est construit à partir de l’attribut $projectRoot.
     * Retour : Le tableau des routes à transmettre au routeur, ou null si le fichier est absent ou invalide.
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
     * Rôle : Déterminer quelle route a été demandée dans l’adresse de la page. Si aucune valeur de route exploitable n’est fournie, la page d’accueil est choisie.
     * Paramètres : Aucun. La méthode lit directement la valeur route présente dans le tableau $_GET.
     * Retour : Le nom de la route à transmettre au routeur, par exemple home ou listing_detail.
     */
    private function getRequestedRoute(): string
    {
        if (isset($_GET['route']) && is_string($_GET['route']) && $_GET['route'] !== '') {
            return $_GET['route'];
        }

        return 'home';
    }

    /**
     * Rôle : Identifier la méthode HTTP utilisée pour la demande courante afin que le routeur puisse vérifier si elle est autorisée pour la route.
     * Paramètres : Aucun. La méthode lit REQUEST_METHOD dans le tableau $_SERVER.
     * Retour : Le nom de la méthode HTTP en majuscules, par exemple GET ou POST ; GET est utilisé si l’information est absente.
     */
    private function getRequestMethod(): string
    {
        if (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])) {
            return strtoupper($_SERVER['REQUEST_METHOD']);
        }

        return 'GET';
    }
}
