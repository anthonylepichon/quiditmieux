<?php

/**
 * Description générale : Contrôleur parent abstrait commun de l'application.
 * Rôle : Définir les aides simples que les contrôleurs enfants utilisent par héritage.
 * Tâches : Conserver Database et Session, composer une page, lire et contrôler les données communes, construire les adresses internes et produire les réponses.
 * Liens avec les autres fichiers : Est étendu par les contrôleurs enfants et utilise les templates ainsi que base.php.
 */

namespace App\core;

class Controller
{
    // ====================
    // ATTRIBUTS
    // ====================

    // Gestionnaire commun de la base, créé une seule fois par App.
    protected Database $database;
    // Gestionnaire commun de la session, créé une seule fois par App.
    protected Session $session;

    // ====================
    // METHODES
    // ====================

    /**
     * Rôle : Rediriger le navigateur vers une route interne de l'application.
     * Paramètres : Nom de route et paramètres complémentaires facultatifs.
     * Retour : Aucun, l'exécution s'arrête après l'envoi de la redirection.
     */
    protected function redirect(string $route, array $parameters = []): void
    {
        // L'adresse est construite par une seule méthode afin de garder le format des routes cohérent.
        header('Location: ' . $this->buildRouteUrl($route, $parameters));

        // Aucun contenu ne doit être produit après l’envoi de la redirection.
        exit;
    }

    // Rôle : produire une réponse au format JSON avec le code HTTP demandé.
    // Paramètres : $data contient les informations à convertir en JSON ;
    // $statusCode représente le code HTTP de la réponse, 200 par défaut.
    // Retour : Aucun. La réponse JSON est affichée puis le script est arrêté.
    protected function json(array $data, int $statusCode = 200): void
    {
        // Le statut HTTP décrit le résultat de l'échange avant l'envoi du contenu JSON.
        http_response_code($statusCode);
        // Le type de contenu permet au JavaScript d'interpréter correctement la réponse.
        header('Content-Type: application/json; charset=utf-8');

        // Les caractères français restent lisibles dans la réponse JSON.
        echo json_encode($data, JSON_UNESCAPED_UNICODE);

        // Aucun autre contenu ne doit être ajouté à la réponse JSON.
        exit;
    }

    /**
     * Rôle : Construire une adresse interne à partir d'une route et de paramètres simples.
     * Paramètres : Nom de route et paramètres facultatifs.
     * Retour : Adresse relative utilisable dans un lien ou une redirection.
     */
    protected function buildRouteUrl(string $route, array $parameters = []): string
    {
        // Le nom de route est toujours séparé des paramètres complémentaires.
        return 'index.php?' . http_build_query(array_merge(['route' => $route], $parameters));
    }

    /**
     * Rôle : Afficher un template de page dans le layout commun.
     * Paramètres : Chemin relatif au dossier templates et données de présentation.
     * Retour : Aucun.
     */
    protected function render(string $template, array $data = []): void
    {
        // Les chemins réels servent à vérifier que le fichier reste dans le dossier templates.
        $templatesDirectory = realpath(dirname(__DIR__, 2) . '/templates');
        $templatePath = realpath(dirname(__DIR__, 2) . '/templates/' . ltrim($template, '/\\'));
        // Un template absent ou situé hors du dossier autorisé n'est jamais chargé.
        if ($templatesDirectory === false || $templatePath === false || !is_file($templatePath)
            || !str_starts_with($templatePath, $templatesDirectory . DIRECTORY_SEPARATOR)) {
            echo 'La page demandée est momentanément indisponible.';
            return;
        }
        // Les données du contrôleur deviennent des variables utilisables par le template.
        extract($data, EXTR_SKIP);
        // Ces valeurs par défaut garantissent un layout complet pour chaque page.
        $pageTitle = $data['page_title'] ?? 'QUIDITMIEUX';
        $pageDescription = $data['page_description'] ?? 'Ventes aux enchères entre particuliers.';
        $pageScripts = $data['page_scripts'] ?? [];
        // Le contenu spécifique est capturé avant d'être placé dans le layout commun.
        ob_start();
        require $templatePath;
        $content = (string) ob_get_clean();
        require $templatesDirectory . '/layout/base.php';
    }

    /**
     * Rôle : Lire une valeur POST textuelle.
     * Paramètres : Nom du champ attendu.
     * Retour : Chaîne nettoyée ou chaîne vide.
     */
    protected function readPostString(string $name): string
    {
        // Les tableaux sont refusés : un champ textuel doit contenir uniquement une chaîne.
        if (!isset($_POST[$name]) || !is_string($_POST[$name])) {
            return '';
        }
        // Les espaces accidentels ne font pas partie de la valeur métier.
        return trim($_POST[$name]);
    }

    /**
     * Rôle : Lire une valeur GET textuelle.
     * Paramètres : Nom du paramètre attendu.
     * Retour : Chaîne nettoyée ou chaîne vide.
     */
    protected function readGetString(string $name): string
    {
        // Les tableaux sont refusés : un paramètre d'adresse doit contenir uniquement une chaîne.
        if (!isset($_GET[$name]) || !is_string($_GET[$name])) {
            return '';
        }
        // Les espaces accidentels ne font pas partie de la valeur métier.
        return trim($_GET[$name]);
    }

    /**
     * Rôle : Lire un identifiant positif POST.
     * Paramètres : Nom du champ attendu.
     * Retour : Identifiant ou null.
     */
    protected function readPositivePostIdentifier(string $name): ?int
    {
        // filter_var vérifie simultanément le type entier et la valeur minimale autorisée.
        $value = filter_var($this->readPostString($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? null : (int) $value;
    }

    /**
     * Rôle : Lire un identifiant positif GET.
     * Paramètres : Nom du paramètre attendu.
     * Retour : Identifiant ou null.
     */
    protected function readPositiveGetIdentifier(string $name): ?int
    {
        // filter_var vérifie simultanément le type entier et la valeur minimale autorisée.
        $value = filter_var($this->readGetString($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? null : (int) $value;
    }

    /**
     * Rôle : Conserver les services communs transmis par le routeur.
     * Paramètres : Gestionnaires de base de données et de session.
     * Retour : Aucun.
     */
    public function __construct(Database $database, Session $session)
    {
        // Les deux dépendances sont reçues du routeur : le contrôleur ne recrée ni connexion ni session.
        $this->database = $database;
        $this->session = $session;
    }

    /**
     * Rôle : Indiquer si une réponse JSON est demandée.
     * Paramètres : Aucun.
     * Retour : true lorsque le format JSON est demandé, sinon false.
     */
    protected function isJsonRequest(): bool { return $this->readGetString('format') === 'json'; }

    /**
     * Rôle : Exiger une session authentifiée avant d'accéder à une action privée.
     * Paramètres : Nom de la route à rejoindre après authentification.
     * Retour : Identifiant utilisateur ou null après la redirection.
     */
    protected function requireConnectedUser(string $destination): ?int
    {
        // La session injectée par App est la seule source de vérité pour l'authentification.
        $userId = $this->session->getConnectedUserId();
        if ($userId !== null) { return $userId; }
        $this->redirect('login_form', ['destination' => $destination]);
        return null;
    }

    /**
     * Rôle : Vérifier le jeton CSRF soumis avec un formulaire.
     * Paramètres : Aucun, le jeton est lu dans les données POST.
     * Retour : true lorsque le jeton correspond à la session, sinon false.
     */
    protected function isSubmittedCsrfTokenValid(): bool { return $this->session->isCsrfTokenValid($this->readPostString('csrf_token')); }

    /**
     * Rôle : Vérifier la robustesse minimale d'un mot de passe.
     * Paramètres : Mot de passe à contrôler.
     * Retour : true lorsque les règles sont respectées, sinon false.
     */
    protected function isStrongPassword(string $password): bool
    {
        // Chaque règle est vérifiée côté serveur avant d'autoriser la création ou la modification du compte.
        return strlen($password) >= 8 && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1 && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }

    /**
     * Rôle : Vérifier un pseudo utilisateur.
     * Paramètres : Pseudo à contrôler.
     * Retour : true lorsque le format est valide, sinon false.
     */
    protected function isValidPseudo(string $pseudo): bool
    {
        // La longueur et la liste de caractères évitent des pseudos ambigus ou non prévus par l'application.
        return mb_strlen($pseudo) >= 3 && mb_strlen($pseudo) <= 30
            && preg_match('/^[A-Za-z0-9_-]+$/D', $pseudo) === 1;
    }

    /**
     * Rôle : Vérifier une adresse électronique.
     * Paramètres : Adresse à contrôler.
     * Retour : true lorsque le format est valide, sinon false.
     */
    protected function isValidEmail(string $email): bool
    {
        // La limite de longueur est vérifiée avant le format pour rester compatible avec la base de données.
        return mb_strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Rôle : Formater un montant exprimé en euros entiers.
     * Paramètres : Montant à afficher.
     * Retour : Montant lisible avec le symbole euro.
     */
    protected function formatEuros(int $amount): string
    {
        // Le montant reste un entier dans les modèles ; seul son affichage reçoit le symbole euro.
        return number_format($amount, 0, ',', ' ') . ' €';
    }

    /**
     * Rôle : Formater une date en français.
     * Paramètres : Date et indication de présence de l'année.
     * Retour : Date lisible.
     */
    // NATIF PHP : DateTime représente une date et une heure ; le contrôleur l’utilise ici pour produire un libellé français destiné à l’affichage.
    protected function formatFrenchDate(\DateTime $date, bool $includeYear): string
    {
        // Les mois sont explicitement traduits pour ne pas dépendre de la configuration régionale du serveur.
        $months = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $label = $date->format('j') . ' ' . $months[(int) $date->format('n')];

        // L'année est ajoutée uniquement lorsque le contexte de la page le demande.
        if ($includeYear) { $label .= ' ' . $date->format('Y'); }
        return $label;
    }

    /**
     * Rôle : Formater une date et une heure en français.
     * Paramètres : Date, présence de l'année et séparateur médian.
     * Retour : Date et heure lisibles.
     */
    protected function formatFrenchDateTime(\DateTime $date, bool $includeYear, bool $useMiddleDot): string
    {
        // Le séparateur dépend du composant visuel qui affiche la date et l'heure.
        return $this->formatFrenchDate($date, $includeYear) . ($useMiddleDot ? ' · ' : ' à ') . $date->format('H:i');
    }
}
