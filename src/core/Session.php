<?php

/**
 * Description générale : Gestionnaire de session de l'application.
 * Rôle : Sécuriser la session PHP et conserver l'identifiant de l'utilisateur connecté.
 * Tâches : Démarrer la session, gérer l'authentification, les messages temporaires et les jetons CSRF.
 * Liens avec les autres fichiers : Est créée par App.php, est transmise aux contrôleurs par Router.php.
 */

namespace App\core;

class Session
{
    // ====================
    // METHODES
    // ====================

    /**
     * Rôle : Démarrer la session lorsqu'aucune session n'est déjà active.
     * Paramètres : Aucun.
     * Retour : Aucun.
     */
    public function startSession(): void
    {
        // Une session déjà ouverte est conservée afin de ne pas envoyer une seconde fois les en-têtes HTTP.
        // NATIF PHP : session_status() indique l’état actuel de la session PHP ; il évite ici de redémarrer une session déjà active.
        // NATIF PHP : PHP_SESSION_NONE indique qu’aucune session PHP n’existe ; elle permet ici de savoir si session_start() doit être appelé.
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        // Le cookie est marqué Secure uniquement lorsqu'une connexion HTTPS est réellement utilisée.
        $secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        // Les paramètres déjà définis par PHP sont conservés pour le chemin et le domaine du cookie.
        $cookieParameters = session_get_cookie_params();
        // Le mode strict refuse les identifiants de session inconnus fournis par un visiteur.
        ini_set('session.use_strict_mode', '1');
        // Le cookie reste inaccessible au JavaScript et n'est envoyé que dans le contexte de navigation attendu.
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParameters['path'],
            'domain' => $cookieParameters['domain'],
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // NATIF PHP : session_start() démarre une session ou reprend la session existante ; il rend disponibles les données conservées dans $_SESSION.
        session_start();
    }

    /**
     * Rôle : Enregistrer l'identifiant de l'utilisateur authentifié et renouveler la session.
     * Paramètres : Identifiant de l'utilisateur connecté.
     * Retour : Aucun.
     */
    public function connectUser(int $userId): void
    {
        // La session est ouverte avant toute écriture dans son tableau de données.
        $this->startSession();
        // NATIF PHP : session_regenerate_id() remplace l’identifiant de session tout en conservant ses données ; il limite ici le détournement de session après connexion.
        session_regenerate_id(true);
        // NATIF PHP : $_SESSION est un tableau superglobal conservé entre plusieurs pages ; il mémorise ici l’utilisateur connecté, les messages ou le jeton CSRF.
        $_SESSION['user_id'] = $userId;
    }

    /**
     * Rôle : Retirer les informations de l'utilisateur puis détruire sa session.
     * Paramètres : Aucun.
     * Retour : Aucun.
     */
    public function deconnectUser(): void
    {
        // La session est ouverte avant la suppression de ses données côté serveur.
        $this->startSession();

        // Retire toutes les informations conservées pour l’utilisateur pendant sa navigation.
        $_SESSION = [];

        // NATIF PHP : session_destroy() supprime les données de la session côté serveur ; il termine ici la connexion de l’utilisateur.
        session_destroy();
    }

    /**
     * Rôle : Indiquer si un utilisateur est actuellement authentifié.
     * Paramètres : Aucun.
     * Retour : true lorsqu'un identifiant utilisateur est présent, sinon false.
     */
    public function isUserConnected(): bool
    {
        // La vérification reste fiable même si la méthode est appelée avant le démarrage explicite de la session.
        $this->startSession();

        // NATIF PHP : isset() vérifie que l’entrée user_id existe et ne vaut pas null ; il détermine ici si un utilisateur est authentifié.
        // NATIF PHP : is_int() vérifie qu’une valeur est un entier ; il évite ici d’utiliser un autre type dans un traitement numérique.
        return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
    }

    /**
     * Rôle : Obtenir l'identifiant de l'utilisateur actuellement authentifié.
     * Paramètres : Aucun.
     * Retour : Identifiant de l'utilisateur ou null lorsque personne n'est connecté.
     */
    public function getConnectedUserId(): ?int
    {
        // L'identifiant n'est lu que lorsqu'il a passé le contrôle de connexion et de type.
        if (!$this->isUserConnected()) {
            return null;
        }

        return $_SESSION['user_id'];
    }

    /**
     * Rôle : Conserver temporairement dans la session un message préparé par un contrôleur avant une redirection. La page affichée après la redirection pourra ainsi récupérer ce message et informer l’utilisateur du résultat de l’action réalisée.
     * Paramètres : Type du message et texte à afficher.
     * Retour : Aucun.
     */
    public function setFlashMessage(string $type, string $message): void
    {
        // La session est ouverte avant la préparation de la zone de messages temporaires.
        $this->startSession();

        // NATIF PHP : is_array() vérifie qu’une valeur est un tableau ; il évite ici de parcourir ou transmettre un type inattendu.
        if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }

        // Le message reste disponible jusqu'à ce que la page suivante le lise.
        $_SESSION['flash_messages'][$type] = $message;
    }

    /**
     * Rôle : Récupérer un message temporaire enregistré dans la session sous le type demandé, puis le supprimer immédiatement afin qu’il ne soit affiché qu’une seule fois après la redirection.
     * Paramètres : Type du message recherché.
     * Retour : Texte du message ou null lorsqu'il est absent.
     */
    public function getFlashMessage(string $type): ?string
    {
        // La session est ouverte avant la lecture et la suppression du message temporaire.
        $this->startSession();

        if (!isset($_SESSION['flash_messages'][$type])) {
            return null;
        }

        // Le message est copié puis supprimé pour qu'il ne s'affiche qu'une seule fois.
        $message = $_SESSION['flash_messages'][$type];
        unset($_SESSION['flash_messages'][$type]);

        if ($_SESSION['flash_messages'] === []) {
            unset($_SESSION['flash_messages']);
        }

        // NATIF PHP : is_string() vérifie qu’une valeur est une chaîne de caractères ; il évite ici de traiter un type inattendu comme du texte.
        if (!is_string($message)) {
            return null;
        }

        return $message;
    }

    /**
     * Rôle : Obtenir le jeton CSRF de la session ou en créer un lorsqu'il est absent.
     * Paramètres : Aucun.
     * Retour : Jeton CSRF utilisable dans les formulaires concernés.
     */
    public function getCsrfToken(): string
    {
        // La session conserve le même jeton pour les formulaires successifs d'un même visiteur.
        $this->startSession();

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            // NATIF PHP : bin2hex() convertit des octets en texte hexadécimal ; il produit ici une valeur sûre à stocker dans un nom ou un jeton.
            // NATIF PHP : random_bytes() génère des octets aléatoires sécurisés ; il crée ici un jeton ou un nom de fichier difficile à deviner.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Rôle : Comparer un jeton reçu avec celui conservé dans la session.
     * Paramètres : Jeton reçu, éventuellement absent.
     * Retour : true lorsque les jetons correspondent, sinon false.
     */
    public function isCsrfTokenValid(?string $token): bool
    {
        // La session doit être disponible pour comparer le jeton reçu avec celui qui a été généré.
        $this->startSession();

        if ($token === null || $token === '') {
            return false;
        }

        // Un jeton absent de la session ne peut pas valider une action qui modifie des données.
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            return false;
        }

        // NATIF PHP : hash_equals() compare deux chaînes en limitant les attaques basées sur le temps de réponse ; il sécurise ici la vérification du jeton.
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
