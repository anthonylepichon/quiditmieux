<?php

/**
 * Description générale : Contrôleur parent abstrait commun de l'application.
 * Rôle : Définir les aides simples que les contrôleurs enfants utilisent par héritage.
 * Tâches : Conserver Database et Session, composer une page, lire et contrôler les données communes, construire les adresses internes et produire les réponses.
 * Liens avec les autres fichiers : Est étendu par les contrôleurs enfants et utilise les templates ainsi que base.php.
 */

namespace App\core;

use DateTimeImmutable;

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
        header('Location: ' . $this->buildRouteUrl($route, $parameters));
        exit;
    }

    /**
     * Rôle : Construire une adresse interne à partir d'une route connue de l'application.
     * Paramètres : Nom de route et paramètres scalaires facultatifs.
     * Retour : Adresse relative utilisable par une redirection ou un lien.
     */
    protected function buildRouteUrl(string $route, array $parameters = []): string
    {
        $queryParameters = ['route' => $route];

        foreach ($parameters as $name => $value) {
            if (is_string($name) && (is_scalar($value) || $value === null)) {
                $queryParameters[$name] = $value;
            }
        }

        return 'index.php?' . http_build_query($queryParameters);
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

    /**
     * Rôle : Exiger un utilisateur connecté avant l'exécution d'une page privée.
     * Paramètres : Destination interne à retrouver après la connexion.
     * Retour : Identifiant de l'utilisateur ou null lorsqu'une redirection est envoyée.
     */
    protected function requireConnectedUser(string $destination): ?int
    {
        $userId = $this->session->obtenirIdentifiantUtilisateurConnecte();

        if ($userId !== null) {
            return $userId;
        }

        $this->redirect('login_form', ['destination' => $destination]);
        return null;
    }

    /**
     * Rôle : Vérifier le jeton CSRF transmis par un formulaire POST.
     * Paramètres : Aucun, le jeton est lu dans la requête POST.
     * Retour : true lorsque le jeton est valide, sinon false.
     */
    protected function isSubmittedCsrfTokenValid(): bool
    {
        return $this->session->estJetonCsrfValide($this->readPostString('csrf_token'));
    }

    /**
     * Rôle : Vérifier la robustesse minimale commune des mots de passe.
     * Paramètres : Mot de passe à contrôler.
     * Retour : true lorsque toutes les règles sont respectées, sinon false.
     */
    protected function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }

    /**
     * Rôle : Vérifier le format commun d'un pseudo utilisateur.
     * Paramètres : Pseudo à contrôler.
     * Retour : true lorsque la longueur et les caractères sont autorisés, sinon false.
     */
    protected function isValidPseudo(string $pseudo): bool
    {
        if (mb_strlen($pseudo) < 3 || mb_strlen($pseudo) > 30) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9_-]+$/D', $pseudo) === 1;
    }

    /**
     * Rôle : Vérifier le format commun d'une adresse électronique.
     * Paramètres : Adresse électronique à contrôler.
     * Retour : true lorsque la longueur et le format sont valides, sinon false.
     */
    protected function isValidEmail(string $email): bool
    {
        if (mb_strlen($email) > 255) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Rôle : Formater un montant entier en euros pour l’affichage.
     * Paramètres : Montant exprimé en euros entiers.
     * Retour : Montant lisible avec le symbole euro.
     */
    protected function formatEuros(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' €';
    }

    /**
     * Rôle : Formater une date avec un mois français complet ou abrégé.
     * Paramètres : Date en heure locale et présence souhaitée de l'année.
     * Retour : Date lisible en français sans indication de l'heure.
     */
    protected function formatFrenchDate(DateTimeImmutable $date, bool $includeYear): string
    {
        $fullMonths = [
            1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
        ];
        $shortMonths = [
            1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
            'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.',
        ];
        $months = $shortMonths;

        if ($includeYear) {
            $months = $fullMonths;
        }

        $label = $date->format('j') . ' ' . $months[(int) $date->format('n')];

        if ($includeYear) {
            $label .= ' ' . $date->format('Y');
        }

        return $label;
    }

    /**
     * Rôle : Formater une date et une heure en français selon le contexte d'affichage.
     * Paramètres : Date locale, présence de l'année et utilisation du séparateur médian.
     * Retour : Date et heure lisibles en français.
     */
    protected function formatFrenchDateTime(
        DateTimeImmutable $date,
        bool $includeYear,
        bool $useMiddleDot
    ): string {
        $separator = ' à ';

        if ($useMiddleDot) {
            $separator = ' · ';
        }

        return $this->formatFrenchDate($date, $includeYear)
            . $separator
            . $date->format('H:i');
    }
}
