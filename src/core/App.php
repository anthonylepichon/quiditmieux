<?php

/**
 * Description générale : Classe principale de l'application.
 * Rôle : Initialiser la session, la base de données et les routes avant de confier la requête au routeur. L'application dispose ainsi de tous ses services avant l'exécution d'un contrôleur.
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
     * Rôle : Mémoriser le chemin absolu de la racine du projet afin de retrouver ensuite la configuration privée de la base et le fichier des routes, quel que soit le dossier depuis lequel PHP est lancé.
     * Paramètres : $projectRoot contient le chemin absolu du dossier principal du projet, transmis par index.php.
     * Retour : Aucun. Le constructeur enregistre le chemin reçu dans l’attribut $projectRoot.
     */
    public function __construct(string $projectRoot)
    {
        // Conserve la racine du projet afin de construire ensuite les chemins vers les fichiers de configuration.
        $this->projectRoot = $projectRoot;
    }

    /**
     * Rôle : Démarrer la session, préparer la connexion à la base, charger les routes puis transmettre la requête au routeur. Le traitement s'arrête avant tout contrôleur si un service indispensable est indisponible.
     * Paramètres : Aucun. La méthode utilise la configuration du projet ainsi que les informations de la requête reçue par le serveur.
     * Retour : Aucun. Le traitement s’arrête si la base ou les routes sont indisponibles ; sinon le routeur appelle le contrôleur correspondant.
     */
    public function run(): void
    {
        // Crée le gestionnaire qui conservera les informations de session pendant la navigation.
        $session = new Session();
        // Démarre une nouvelle session PHP ou reprend la session existante avant de traiter la demande.
        $session->startSession();

        // Charge la configuration privée puis prépare la connexion PDO destinée aux modèles.
        $database = $this->createDatabase();

        // Arrête proprement le démarrage si la configuration ou la connexion à MySQL est indisponible.
        if ($database === null) {
            return;
        }

        // Charge la liste des adresses autorisées et le traitement associé à chacune.
        $routes = $this->loadRoutes();

        // Arrête le traitement si le fichier des routes est absent ou invalide.
        if ($routes === null) {
            return;
        }

        // Fournit au routeur la base de données et la session nécessaires aux futurs contrôleurs.
        $router = new Router($database, $session);
        // Enregistre dans le routeur toutes les routes définies par la configuration.
        $router->registerRoutes($routes);
        // Recherche la route demandée puis appelle le contrôleur et la méthode correspondants.
        $router->dispatch($this->getRequestedRoute(), $this->getRequestMethod());
    }

    /**
     * Rôle : Charger les paramètres privés de MySQL, créer Database et vérifier que PDO est connecté. Aucun contrôleur utilisant les modèles n'est lancé si la configuration ou la connexion est indisponible.
     * Paramètres : Aucun. Le chemin du fichier de configuration est construit à partir de l’attribut $projectRoot.
     * Retour : Un objet Database prêt à être utilisé, ou null si le fichier, sa configuration ou la connexion est indisponible.
     */
    private function createDatabase(): ?Database
    {
        // Construit le chemin absolu du fichier privé contenant les paramètres de connexion à MySQL.
        $databaseConfigPath = $this->projectRoot . '/private/database-secret.php';

        // Vérifie que le fichier de configuration existe avant de tenter son chargement.
        if (!is_file($databaseConfigPath)) {
            echo 'La configuration de la base de données est indisponible.';
            return null;
        }

        // Charge les paramètres de connexion retournés par le fichier privé.
        $databaseConfig = require $databaseConfigPath;

        // Vérifie que la configuration possède la forme attendue par la classe Database.
        if (!is_array($databaseConfig)) {
            echo 'La configuration de la base de données est indisponible.';
            return null;
        }

        // Crée l'objet Database responsable de la connexion PDO à partir des paramètres chargés.
        $database = new Database($databaseConfig);

        // Vérifie que PDO a réellement établi la connexion avant de poursuivre le démarrage.
        if (!$database->isConnected()) {
            echo 'La connexion à la base de données est momentanément indisponible.';
            return null;
        }

        // Retourne la connexion prête à être transmise au routeur puis aux modèles.
        return $database;
    }

    /**
     * Rôle : Charger la table de navigation depuis src/config/routes.php et vérifier qu'elle est exploitable. Le routeur ne reçoit ainsi que la configuration prévue par l'application.
     * Paramètres : Aucun. Le chemin de routes.php est construit à partir de l’attribut $projectRoot.
     * Retour : Le tableau des routes à transmettre au routeur, ou null si le fichier est absent ou invalide.
     */
    private function loadRoutes(): ?array
    {
        // Construit le chemin absolu du fichier qui décrit la navigation de la plateforme.
        $routesConfigPath = $this->projectRoot . '/src/config/routes.php';

        // Vérifie que le fichier des routes existe avant de tenter son chargement.
        if (!is_file($routesConfigPath)) {
            echo 'La navigation de l’application est indisponible.';
            return null;
        }

        // Charge le tableau associant chaque route à une méthode HTTP, un contrôleur et une action.
        $routes = require $routesConfigPath;

        // Refuse une configuration incorrecte afin de ne pas transmettre de mauvaises données au routeur.
        if (!is_array($routes)) {
            echo 'La navigation de l’application est indisponible.';
            return null;
        }

        // Retourne au démarrage de la plateforme la liste des routes vérifiée.
        return $routes;
    }

    /**
     * Rôle : Lire le nom de route demandé dans l'adresse et choisir home lorsqu'il est absent ou inutilisable. Le routeur reçoit toujours un nom de route sous forme de texte.
     * Paramètres : Aucun. La méthode lit directement la valeur route présente dans le tableau $_GET.
     * Retour : Le nom de la route à transmettre au routeur, par exemple home ou listing_detail.
     */
    private function getRequestedRoute(): string
    {
        // Vérifie que le paramètre route existe, contient du texte et possède une valeur non vide.
        if (isset($_GET['route']) && is_string($_GET['route']) && $_GET['route'] !== '') {
            // Retourne la route indiquée dans l'adresse, par exemple index.php?route=listing_detail.
            return $_GET['route'];
        }

        // Choisit la page d'accueil lorsqu'aucune route exploitable ne figure dans l'adresse.
        return 'home';
    }

    /**
     * Rôle : Lire et normaliser la méthode HTTP de la requête afin que le routeur puisse la comparer à GET ou POST déclaré pour la route.
     * Paramètres : Aucun. La méthode lit REQUEST_METHOD dans le tableau $_SERVER.
     * Retour : Le nom de la méthode HTTP en majuscules, par exemple GET ou POST ; GET est utilisé si l’information est absente.
     */
    private function getRequestMethod(): string
    {
        // Vérifie que le serveur a indiqué la méthode HTTP employée pour appeler la page.
        if (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])) {
            // Met la valeur en majuscules afin de la comparer aux méthodes GET et POST enregistrées.
            return strtoupper($_SERVER['REQUEST_METHOD']);
        }

        // Utilise GET par défaut lorsque le serveur ne fournit pas cette information.
        return 'GET';
    }
}
