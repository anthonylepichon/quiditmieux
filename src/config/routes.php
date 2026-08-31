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
    'listing_detail' => [
        'method' => 'GET',
        'controller' => ListingController::class,
        'action' => 'showDetail',
        'response' => 'HTML',
    ],
    'listing_create_form' => [
        'method' => 'GET',
        'controller' => ListingController::class,
        'action' => 'showCreateForm',
        'response' => 'HTML ou redirection interne',
    ],
    'listing_create' => [
        'method' => 'POST',
        'controller' => ListingController::class,
        'action' => 'create',
        'response' => 'HTML ou redirection interne',
    ],
    'listing_edit_form' => [
        'method' => 'GET',
        'controller' => ListingController::class,
        'action' => 'showEditForm',
        'response' => 'HTML ou redirection interne',
    ],
    'listing_update' => [
        'method' => 'POST',
        'controller' => ListingController::class,
        'action' => 'update',
        'response' => 'HTML ou redirection interne',
    ],
    'listing_delete' => [
        'method' => 'POST',
        'controller' => ListingController::class,
        'action' => 'delete',
        'response' => 'Redirection interne',
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
    'login_form' => [
        'method' => 'GET',
        'controller' => AuthController::class,
        'action' => 'showLoginForm',
        'response' => 'HTML',
    ],
    'login' => [
        'method' => 'POST',
        'controller' => AuthController::class,
        'action' => 'login',
        'response' => 'HTML ou redirection interne',
    ],
    'logout' => [
        'method' => 'POST',
        'controller' => AuthController::class,
        'action' => 'logout',
        'response' => 'Redirection interne',
    ],
];
