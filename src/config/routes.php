<?php

/**
 * Description générale : Configuration des routes de l'application QUIDITMIEUX.
 * Rôle : Fournir à la classe App les routes que Router doit enregistrer.
 * Tâches : Associer chaque nom de route à sa méthode HTTP, son contrôleur, son action et son type de réponse éventuel.
 * Liens avec les autres fichiers : Est chargé par App.php puis transmis à Router.php pour orienter les demandes.
 */

use App\controllers\AuthController;
use App\controllers\LegalController;
use App\controllers\ListingController;
use App\controllers\ParticipationController;
use App\controllers\UserController;

return [
    'home' => [
        'method' => 'GET',
        'controller' => ListingController::class,
        'action' => 'search',
        'response' => 'HTML ou JSON selon le paramètre de format',
    ],
    'privacy' => [
        'method' => 'GET',
        'controller' => LegalController::class,
        'action' => 'showPrivacyPolicy',
        'response' => 'HTML',
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
    'follow_listing' => [
        'method' => 'POST',
        'controller' => ParticipationController::class,
        'action' => 'follow',
        'response' => 'JSON ou redirection interne',
    ],
    'unfollow_listing' => [
        'method' => 'POST',
        'controller' => ParticipationController::class,
        'action' => 'unfollow',
        'response' => 'JSON ou redirection interne',
    ],
    'place_bid' => [
        'method' => 'POST',
        'controller' => ParticipationController::class,
        'action' => 'placeBid',
        'response' => 'JSON ou redirection interne',
    ],
    'dashboard' => [
        'method' => 'GET',
        'controller' => UserController::class,
        'action' => 'showDashboard',
        'response' => 'HTML ou redirection interne',
    ],
    'dashboard_sales' => [
        'method' => 'GET',
        'controller' => UserController::class,
        'action' => 'refreshSales',
        'response' => 'JSON',
    ],
    'dashboard_participations' => [
        'method' => 'GET',
        'controller' => UserController::class,
        'action' => 'refreshParticipations',
        'response' => 'JSON',
    ],
    'account_form' => [
        'method' => 'GET',
        'controller' => UserController::class,
        'action' => 'showAccountForm',
        'response' => 'HTML ou redirection interne',
    ],
    'account_update' => [
        'method' => 'POST',
        'controller' => UserController::class,
        'action' => 'updateAccount',
        'response' => 'HTML ou redirection interne',
    ],
];
