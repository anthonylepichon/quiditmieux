<?php

/**
 * Description générale : Configuration des routes de l'application QUIDITMIEUX.
 * Rôle : Fournir à la classe App les routes que Router doit enregistrer.
 * Tâches : Associer chaque nom de route à sa méthode HTTP, son contrôleur, son action et son type de réponse éventuel.
 * Liens avec les autres fichiers : Est chargé par App.php puis transmis à Router.php pour orienter les demandes.
 */

use App\controllers\AuthController;
use App\controllers\ListingController;

return [
    'home' => [
        'method' => 'GET',
        'controller' => ListingController::class,
        'action' => 'search',
        'response' => 'HTML ou JSON selon le paramètre de format',
    ],
    'register_form' => [
        'method' => 'GET',
        'controller' => AuthController::class,
        'action' => 'showRegisterForm',
        'response' => 'HTML',
    ],
    'register' => [
        'method' => 'POST',
        'controller' => AuthController::class,
        'action' => 'register',
        'response' => 'HTML ou redirection interne',
    ],
];
