<?php

/**
 * Description générale : Routeur central de l'application.
 * Rôle : Associer chaque demande au contrôleur et à la méthode prévus par la configuration. Cela évite qu'une demande incomplète ou inconnue démarre un contrôleur qui ne lui correspond pas.
 * Tâches : Enregistrer les routes, contrôler la méthode HTTP et appeler le traitement correspondant.
 * Liens avec les autres fichiers : Est utilisé par App.php, reçoit les routes de routes.php et lance les contrôleurs enfants.
 */
// Son rôle est d’enregistrer les routes disponibles, de vérifier leur méthode HTTP
// et d’orienter chaque demande vers le contrôleur et la méthode correspondant à la route demandée.
// Il est nécessaire pour centraliser la navigation de l’application et éviter que le fichier index.php choisisse lui-même le traitement à exécuter.

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
    // METHODES
    // ====================

    /**
     * Rôle : Conserver les services communs de la requête. Cela évite qu'une demande incomplète ou inconnue démarre un contrôleur qui ne lui correspond pas.
     * Paramètres : Gestionnaires Database et Session initialisés par App.
     * Retour : Aucun.
     */
    public function __construct(Database $database, Session $session)
    {
        $this->database = $database;
        $this->session = $session;
    }

    /**
     * Rôle : Enregistrer les routes disponibles. Cela évite qu'une demande incomplète ou inconnue démarre un contrôleur qui ne lui correspond pas.
     * Paramètres : Tableau associatif des définitions de route.
     * Retour : Aucun.
     */
    public function registerRoutes(array $routes): void
    {
        $this->routes = $routes;
    }

    /**
     * Rôle : Rechercher la route demandée, vérifier sa configuration puis exécuter la méthode du contrôleur associé. Cela évite qu'une demande incomplète ou inconnue démarre un contrôleur qui ne lui correspond pas.
     * Paramètres : Nom de la route transmis par App et méthode HTTP utilisée pour envoyer la demande.
     * Retour : Aucun. La méthode exécute l'action prévue ou affiche un message si la demande ne peut pas être traitée.
     */
    public function dispatch(string $routeName, string $requestMethod): void
    {
        // La route provient d'App, qui a déjà refusé les structures inattendues de la requête.
        // Seules les routes enregistrées dans routes.php peuvent être exécutées.
        // App transmet déjà la route home lorsqu'aucun nom de route n'est présent dans l'adresse.
        // (SECURITE: "Seules les routes de la liste déclarée peuvent être exécutées, ce qui empêche de choisir librement une classe ou une méthode dans l'adresse.")
        // Le traitement doit s’arrêter si la route demandée n’a pas été enregistrée.
        // NATIF PHP : isset() vérifie que le nom reçu correspond à une entrée présente dans le tableau des routes.
        if (!isset($this->routes[$routeName])) {
            $this->showError(404, 'Page introuvable.');
            return;
        }

        // (SECURITE: "La méthode HTTP doit correspondre à la route afin qu'une action POST qui modifie des données ne puisse pas être déclenchée par une simple adresse GET.")
        // La méthode HTTP reçue doit correspondre à celle autorisée dans la configuration de la route.
        if (!isset($this->routes[$routeName]['http_method'], $this->routes[$routeName]['controller'], $this->routes[$routeName]['method'])) {
            $this->showError(500, 'La route est mal configurée.');
            return;
        }
        // La définition de route est complète : on peut comparer le verbe HTTP demandé avec celui autorisé.
        $allowedHttpMethod = $this->routes[$routeName]['http_method'];

        if ($requestMethod !== $allowedHttpMethod) {
            $this->showError(405, 'Cette action n’est pas autorisée.');
            return;
        }

        // Chaque route enregistrée indique le contrôleur à utiliser et la méthode de ce contrôleur qui doit être exécutée.
        // La classe et l'action viennent exclusivement de routes.php, jamais de l'adresse saisie par le visiteur.
        $controllerName = $this->routes[$routeName]['controller'];
        $methodName = $this->routes[$routeName]['method'];

        // Le contrôleur doit exister avant de pouvoir créer un objet à partir de son nom.
        if (!class_exists($controllerName)) {
            $this->showError(500, 'Le contrôleur demandé est introuvable.');
            return;
        }

        // Le nom de la classe étant contenu dans une variable, PHP crée dynamiquement un objet correspondant au contrôleur associé à la route.
        if (!is_subclass_of($controllerName, Controller::class)) {
            $this->showError(500, 'Le contrôleur demandé est invalide.');
            return;
        }
        // Le contrôleur sélectionné reçoit les mêmes services communs que tous les autres contrôleurs de la demande.
        $controller = new $controllerName($this->database, $this->session);

        // La méthode indiquée par la route doit exister dans le contrôleur créé.
        if (!method_exists($controller, $methodName)) {
            $this->showError(500, 'L’action demandée est introuvable.');
            return;
        }

        // Le nom de la méthode étant contenu dans une variable, PHP exécute dynamiquement l’action associée à la route.
        $controller->$methodName();
    }

    /**
     * Rôle : Envoyer le code HTTP correspondant à l'erreur puis afficher un message simple et sécurisé. Le navigateur et les outils techniques peuvent ainsi reconnaître la nature réelle du problème au lieu de recevoir systématiquement une réponse 200 OK.
     * Paramètres : Code HTTP à envoyer et message compréhensible à afficher.
     * Retour : Aucun.
     */
    private function showError(int $statusCode, string $message): void
    {
        // NATIF PHP : http_response_code() définit le code HTTP de la réponse ; il distingue ici une page introuvable, une méthode interdite ou une erreur interne.
        http_response_code($statusCode);

        // (SECURITE: "Le message variable est échappé avant affichage afin qu'il ne puisse pas injecter du HTML ou du JavaScript.")
        // NATIF PHP : htmlspecialchars() transforme les caractères HTML spéciaux ; le message est ainsi affiché comme du texte simple.
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }
}
