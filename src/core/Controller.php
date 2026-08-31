<?php

/**
 * Description générale : Contrôleur parent commun de l'application.
 * Rôle : Fournir les aides simples partagées par les contrôleurs enfants.
 * Tâches : Conserver Database et Session, afficher un template, rediriger et produire une réponse JSON.
 * Liens avec les autres fichiers : Est étendu par les contrôleurs enfants et utilise les templates de l'application.
 */

namespace App\core;

class Controller
{
    // ====================
    // ATTRIBUTS
    // ====================

    protected Database $database;
    protected Session $session;

    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Conserver les gestionnaires de base de données et de session partagés pour la requête courante.
     * Paramètres : Objets Database et Session partagés avec les contrôleurs enfants.
     * Retour : Aucun.
     */
    public function __construct(Database $database, Session $session)
    {
        $this->database = $database;
        $this->session = $session;
    }

    /**
     * Rôle : Afficher un template en lui transmettant les données utiles dans un tableau.
     * Paramètres : Chemin du template relatif au dossier templates et tableau des données d'affichage.
     * Retour : Aucun.
     */
    protected function render(string $template, array $data = []): void
    {
        $templatesDirectory = realpath(dirname(__DIR__, 2) . '/templates');
        $templatePath = realpath(dirname(__DIR__, 2) . '/templates/' . ltrim($template, '/\\'));

        if ($templatesDirectory === false || $templatePath === false || !is_file($templatePath)) {
            echo 'La page demandée est momentanément indisponible.';
            return;
        }

        $allowedPathPrefix = $templatesDirectory . DIRECTORY_SEPARATOR;

        if (!str_starts_with($templatePath, $allowedPathPrefix)) {
            echo 'La page demandée est momentanément indisponible.';
            return;
        }

        require $templatePath;
    }

    /**
     * Rôle : Rediriger vers une route interne avec ses paramètres éventuels.
     * Paramètres : Nom de la route et tableau facultatif de paramètres simples.
     * Retour : Aucun, car l'exécution se termine après l'envoi de la redirection.
     */
    protected function redirect(string $route, array $parameters = []): void
    {
        $queryParameters = ['route' => $route];

        foreach ($parameters as $name => $value) {
            if (is_string($name) && (is_scalar($value) || $value === null)) {
                $queryParameters[$name] = $value;
            }
        }

        header('Location: index.php?' . http_build_query($queryParameters));
        exit;
    }

    /**
     * Rôle : Produire une réponse JSON simple pour les échanges avec JavaScript.
     * Paramètres : Tableau des informations à encoder.
     * Retour : Aucun.
     */
    protected function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            echo '{"success":false,"message":"Réponse indisponible."}';
            return;
        }

        echo $json;
    }
}
