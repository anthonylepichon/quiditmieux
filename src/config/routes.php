<?php

/**
 * Description générale : Configuration des routes de l'application QUIDITMIEUX.
 * Rôle : Fournir à la classe App les routes que Router doit enregistrer.
 * Tâches : Associer chaque nom de route à son contrôleur, sa méthode PHP et sa méthode HTTP.
 * Liens avec les autres fichiers : Est chargé par App.php, qui enregistre chaque définition dans Router.php.
 */

use App\controllers\AuthController;
use App\controllers\LegalController;
use App\controllers\ListingController;
use App\controllers\ParticipationController;
use App\controllers\UserController;

return [
    'home' => [
        'controller' => ListingController::class,
        'method' => 'search',
        'http_method' => 'GET',
    ],
    'privacy' => [
        'controller' => LegalController::class,
        'method' => 'showPrivacyPolicy',
        'http_method' => 'GET',
    ],
    'listing_detail' => [
        'controller' => ListingController::class,
        'method' => 'showDetail',
        'http_method' => 'GET',
    ],
    'listing_create_form' => [
        'controller' => ListingController::class,
        'method' => 'showCreateForm',
        'http_method' => 'GET',
    ],
    'listing_create' => [
        'controller' => ListingController::class,
        'method' => 'create',
        'http_method' => 'POST',
    ],
    'listing_edit_form' => [
        'controller' => ListingController::class,
        'method' => 'showEditForm',
        'http_method' => 'GET',
    ],
    'listing_update' => [
        'controller' => ListingController::class,
        'method' => 'update',
        'http_method' => 'POST',
    ],
    'listing_delete' => [
        'controller' => ListingController::class,
        'method' => 'delete',
        'http_method' => 'POST',
    ],
    'register_form' => [
        'controller' => AuthController::class,
        'method' => 'showRegisterForm',
        'http_method' => 'GET',
    ],
    'register' => [
        'controller' => AuthController::class,
        'method' => 'register',
        'http_method' => 'POST',
    ],
    'login_form' => [
        'controller' => AuthController::class,
        'method' => 'showLoginForm',
        'http_method' => 'GET',
    ],
    'login' => [
        'controller' => AuthController::class,
        'method' => 'login',
        'http_method' => 'POST',
    ],
    'logout' => [
        'controller' => AuthController::class,
        'method' => 'logout',
        'http_method' => 'POST',
    ],
    'follow_listing' => [
        'controller' => ParticipationController::class,
        'method' => 'follow',
        'http_method' => 'POST',
    ],
    'unfollow_listing' => [
        'controller' => ParticipationController::class,
        'method' => 'unfollow',
        'http_method' => 'POST',
    ],
    'place_bid' => [
        'controller' => ParticipationController::class,
        'method' => 'placeBid',
        'http_method' => 'POST',
    ],
    'dashboard' => [
        'controller' => UserController::class,
        'method' => 'showDashboard',
        'http_method' => 'GET',
    ],
    'dashboard_sales' => [
        'controller' => UserController::class,
        'method' => 'refreshSales',
        'http_method' => 'GET',
    ],
    'dashboard_participations' => [
        'controller' => UserController::class,
        'method' => 'refreshParticipations',
        'http_method' => 'GET',
    ],
    'account_form' => [
        'controller' => UserController::class,
        'method' => 'showAccountForm',
        'http_method' => 'GET',
    ],
    'account_update' => [
        'controller' => UserController::class,
        'method' => 'updateAccount',
        'http_method' => 'POST',
    ],
];
