<?php

/**
 * Description générale : Configuration des routes de l'application QUIDITMIEUX.
 * Rôle : Fournir à la classe App les routes que Router doit enregistrer.
 * Tâches : Associer chaque nom de route à sa méthode HTTP, son contrôleur et son action.
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
    ],
    'privacy' => [
        'method' => 'GET',
        'controller' => LegalController::class,
        'action' => 'showPrivacyPolicy',
    ],
    'listing_detail' => [
        'method' => 'GET',
        'controller' => ListingController::class,
        'action' => 'showDetail',
    ],
    'listing_create_form' => [
        'method' => 'GET',
        'controller' => ListingController::class,
        'action' => 'showCreateForm',
    ],
    'listing_create' => [
        'method' => 'POST',
        'controller' => ListingController::class,
        'action' => 'create',
    ],
    'listing_edit_form' => [
        'method' => 'GET',
        'controller' => ListingController::class,
        'action' => 'showEditForm',
    ],
    'listing_update' => [
        'method' => 'POST',
        'controller' => ListingController::class,
        'action' => 'update',
    ],
    'listing_delete' => [
        'method' => 'POST',
        'controller' => ListingController::class,
        'action' => 'delete',
    ],
    'register_form' => [
        'method' => 'GET',
        'controller' => AuthController::class,
        'action' => 'showRegisterForm',
    ],
    'register' => [
        'method' => 'POST',
        'controller' => AuthController::class,
        'action' => 'register',
    ],
    'login_form' => [
        'method' => 'GET',
        'controller' => AuthController::class,
        'action' => 'showLoginForm',
    ],
    'login' => [
        'method' => 'POST',
        'controller' => AuthController::class,
        'action' => 'login',
    ],
    'logout' => [
        'method' => 'POST',
        'controller' => AuthController::class,
        'action' => 'logout',
    ],
    'follow_listing' => [
        'method' => 'POST',
        'controller' => ParticipationController::class,
        'action' => 'follow',
    ],
    'unfollow_listing' => [
        'method' => 'POST',
        'controller' => ParticipationController::class,
        'action' => 'unfollow',
    ],
    'place_bid' => [
        'method' => 'POST',
        'controller' => ParticipationController::class,
        'action' => 'placeBid',
    ],
    'dashboard' => [
        'method' => 'GET',
        'controller' => UserController::class,
        'action' => 'showDashboard',
    ],
    'dashboard_sales' => [
        'method' => 'GET',
        'controller' => UserController::class,
        'action' => 'refreshSales',
    ],
    'dashboard_participations' => [
        'method' => 'GET',
        'controller' => UserController::class,
        'action' => 'refreshParticipations',
    ],
    'account_form' => [
        'method' => 'GET',
        'controller' => UserController::class,
        'action' => 'showAccountForm',
    ],
    'account_update' => [
        'method' => 'POST',
        'controller' => UserController::class,
        'action' => 'updateAccount',
    ],
];
