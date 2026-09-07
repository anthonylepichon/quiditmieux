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
     * Rôle : Démarrer et sécuriser la session lorsqu'aucune session n'est déjà active.
     * Paramètres : Aucun.
     * Retour : Aucun.
     */
    public function demarrerSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $secureCookie = false;

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $secureCookie = true;
        }

        $cookieParameters = session_get_cookie_params();

        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParameters['path'],
            'domain' => $cookieParameters['domain'],
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * Rôle : Enregistrer l'identifiant de l'utilisateur authentifié et renouveler la session.
     * Paramètres : Identifiant de l'utilisateur connecté.
     * Retour : Aucun.
     */
    public function connecterUtilisateur(int $userId): void
    {
        $this->demarrerSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    /**
     * Rôle : Retirer l'authentification de la session et renouveler son identifiant.
     * Paramètres : Aucun.
     * Retour : Aucun.
     */
    public function deconnecterUtilisateur(): void
    {
        $this->demarrerSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $cookieParameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookieParameters['path'],
                'domain' => $cookieParameters['domain'],
                'secure' => $cookieParameters['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
    }

    /**
     * Rôle : Indiquer si un utilisateur est actuellement authentifié.
     * Paramètres : Aucun.
     * Retour : true lorsqu'un identifiant utilisateur est présent, sinon false.
     */
    public function estUtilisateurConnecte(): bool
    {
        $this->demarrerSession();

        return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
    }

    /**
     * Rôle : Obtenir l'identifiant de l'utilisateur actuellement authentifié.
     * Paramètres : Aucun.
     * Retour : Identifiant de l'utilisateur ou null lorsque personne n'est connecté.
     */
    public function obtenirIdentifiantUtilisateurConnecte(): ?int
    {
        if (!$this->estUtilisateurConnecte()) {
            return null;
        }

        return $_SESSION['user_id'];
    }

    /**
     * Rôle : Enregistrer un message temporaire destiné à la prochaine page affichée.
     * Paramètres : Type du message et texte à afficher.
     * Retour : Aucun.
     */
    public function enregistrerMessageTemporaire(string $type, string $message): void
    {
        $this->demarrerSession();

        if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }

        $_SESSION['flash_messages'][$type] = $message;
    }

    /**
     * Rôle : Récupérer puis supprimer un message temporaire de la session.
     * Paramètres : Type du message recherché.
     * Retour : Texte du message ou null lorsqu'il est absent.
     */
    public function recupererMessageTemporaire(string $type): ?string
    {
        $this->demarrerSession();

        if (!isset($_SESSION['flash_messages'][$type])) {
            return null;
        }

        $message = $_SESSION['flash_messages'][$type];
        unset($_SESSION['flash_messages'][$type]);

        if ($_SESSION['flash_messages'] === []) {
            unset($_SESSION['flash_messages']);
        }

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
    public function obtenirJetonCsrf(): string
    {
        $this->demarrerSession();

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Rôle : Comparer un jeton reçu avec celui conservé dans la session.
     * Paramètres : Jeton reçu, éventuellement absent.
     * Retour : true lorsque les jetons correspondent, sinon false.
     */
    public function estJetonCsrfValide(?string $token): bool
    {
        $this->demarrerSession();

        if ($token === null || $token === '') {
            return false;
        }

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
