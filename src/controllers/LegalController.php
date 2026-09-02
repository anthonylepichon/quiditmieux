<?php

/**
 * Description générale : Contrôleur des pages d'information légale de l'application.
 * Rôle : Préparer et afficher les informations publiques relatives à la confidentialité.
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
     * Rôle : Afficher la politique de confidentialité publique de QUIDITMIEUX.
     * Paramètres : Aucun.
     * Retour : Aucun, le template de confidentialité est affiché.
     */
    public function showPrivacyPolicy(): void
    {
        $this->render('pages/privacy.php', [
            'is_connected' => $this->session->estUtilisateurConnecte(),
            'csrf_token' => $this->session->obtenirJetonCsrf(),
        ]);
    }
}
