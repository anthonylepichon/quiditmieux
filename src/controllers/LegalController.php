<?php

/**
 * Description générale : Contrôleur des pages d'information légale de l'application.
 * Rôle : Préparer et afficher les informations publiques relatives à la confidentialité. Les données transmises à l'étape suivante ont ainsi une forme cohérente et cette préparation n'est pas répétée ailleurs.
 * Tâches : Afficher la politique de confidentialité avec l'état de connexion courant.
 * Liens avec les autres fichiers : Étend Controller.php et affiche le template privacy.php.
 */

namespace App\controllers;

use App\core\Controller;

class LegalController extends Controller
{
    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Afficher la politique de confidentialité publique de QUIDITMIEUX. L'utilisateur reçoit ainsi une présentation cohérente sans mélanger l'affichage avec les décisions métier.
     * Paramètres : Aucun.
     * Retour : Aucun, le template de confidentialité est affiché.
     */
    public function showPrivacyPolicy(): void
    {
        // Le template reçoit l'état de connexion pour afficher la navigation adaptée au visiteur.
        // Le jeton CSRF reste disponible si le layout affiche une action POST, notamment la déconnexion.
        $this->render('pages/privacy.php', [
            'is_connected' => $this->session->isUserConnected(),
            'csrf_token' => $this->session->getCsrfToken(),
        ]);
    }
}
