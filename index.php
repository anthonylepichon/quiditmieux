<?php

/**
 * Description générale : Point d'entrée unique de l'application.
 * Rôle : Charger les ressources indispensables puis lancer la classe principale de l'application.
 * Tâches : Définir l'encodage HTML, charger l'autoload puis démarrer App.
 * Liens avec les autres fichiers : Utilise l'autoload et App.php.
 */

use App\core\App;

// L'application traite toutes les dates dans le fuseau horaire français.
date_default_timezone_set('Europe/Paris');

// L'encodage UTF-8 est défini une seule fois pour toutes les réponses HTML de l'application.
ini_set('default_charset', 'UTF-8');

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// L'autoload rend disponibles les classes du projet à partir de leur namespace.
require_once __DIR__ . '/vendor/autoload.php';

// La classe principale reçoit la racine du projet afin de retrouver les configurations nécessaires.
$application = new App(__DIR__);
$application->run();
