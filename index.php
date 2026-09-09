<?php

// Ce fichier contient le point d’entrée unique de l’application QuiDitMieux.
// Son rôle est de configurer et de démarrer l’application avant de transmettre la demande au composant chargé de son traitement.
// Il est nécessaire pour centraliser le démarrage de l’application et éviter de répéter cette préparation dans chaque contrôleur.

// Permet d’utiliser le nom court App pour désigner la classe principale.
use App\core\App;

// On force l'encodage UTF-8 pour les textes français affichés par l'application.
ini_set('default_charset', 'UTF-8');
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// L’autoloader Composer permet de charger automatiquement les classes utilisées.
require_once __DIR__ . '/vendor/autoload.php';

// L'objet principal reçoit la racine du projet afin de localiser les configurations privées.
$app = new App(__DIR__);

// Le démarrage centralisé prépare les services communs puis délègue la demande au routeur.
$app->run();
