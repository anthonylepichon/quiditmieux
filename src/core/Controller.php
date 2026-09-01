<?php

/**
 * Description générale : Contrôleur parent abstrait commun de l'application.
 * Rôle : Définir les aides simples que les contrôleurs enfants utilisent par héritage.
 * Tâches : Conserver Database et Session, composer une page avec le layout, lire les paramètres simples, rediriger et produire une réponse JSON.
 * Liens avec les autres fichiers : Est étendu par les contrôleurs enfants et utilise les templates ainsi que base.php.
 */

namespace App\core;

abstract class Controller
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
     * Rôle : Capturer le contenu d'un template puis l'afficher dans le layout principal.
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

        $basePath = $templatesDirectory . '/layout/base.php';

        if (!is_file($basePath)) {
            echo 'La page demandée est momentanément indisponible.';
            return;
        }

        $pageTitle = 'QUIDITMIEUX';
        $pageDescription = 'Ventes aux enchères entre particuliers.';
        $pageScripts = [];
        $isConnected = false;
        $csrfToken = '';
        $currentPage = '';

        ob_start();
        require $templatePath;
        $capturedContent = ob_get_clean();

        if (!is_string($capturedContent)) {
            echo 'La page demandée est momentanément indisponible.';
            return;
        }

        $content = $capturedContent;
        require $basePath;
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

    /**
     * Rôle : Lire une valeur POST simple sans accepter de tableau inattendu.
     * Paramètres : Nom du champ demandé.
     * Retour : Valeur nettoyée ou chaîne vide lorsqu'elle est absente ou invalide.
     */
    protected function readPostString(string $name): string
    {
        if (!isset($_POST[$name]) || !is_string($_POST[$name])) {
            return '';
        }

        return trim($_POST[$name]);
    }

    /**
     * Rôle : Lire une valeur GET simple sans accepter de tableau inattendu.
     * Paramètres : Nom du paramètre demandé.
     * Retour : Valeur nettoyée ou chaîne vide lorsqu'elle est absente ou invalide.
     */
    protected function readGetString(string $name): string
    {
        if (!isset($_GET[$name]) || !is_string($_GET[$name])) {
            return '';
        }

        return trim($_GET[$name]);
    }

    /**
     * Rôle : Lire un identifiant entier strictement positif dans la requête POST.
     * Paramètres : Nom du champ demandé.
     * Retour : Identifiant validé ou null lorsque la valeur est absente ou invalide.
     */
    protected function readPositivePostIdentifier(string $name): ?int
    {
        $identifier = filter_var($this->readPostString($name), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($identifier === false) {
            return null;
        }

        return (int) $identifier;
    }

    /**
     * Rôle : Lire un identifiant entier strictement positif dans la requête GET.
     * Paramètres : Nom du paramètre demandé.
     * Retour : Identifiant validé ou null lorsque la valeur est absente ou invalide.
     */
    protected function readPositiveGetIdentifier(string $name): ?int
    {
        $identifier = filter_var($this->readGetString($name), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($identifier === false) {
            return null;
        }

        return (int) $identifier;
    }

    /**
     * Rôle : Indiquer si le navigateur demande explicitement une réponse JSON.
     * Paramètres : Aucun.
     * Retour : true lorsque le paramètre de format demande JSON, sinon false.
     */
    protected function isJsonRequest(): bool
    {
        return $this->readGetString('format') === 'json';
    }
}
