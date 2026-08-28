<?php

/**
 * Description générale :
 * Démarrage technique de l'application QUIDITMIEUX.
 *
 * Rôle :
 * Charger les fichiers communs et créer la connexion PDO de la requête courante.
 *
 * Tâches :
 * Charger l'autoload, lire la configuration privée et initialiser PDO sans exposer de secret.
 *
 * Liens avec les autres fichiers :
 * Utilise vendor/autoload.php et private/database-secret.php avant le futur routage.
 */

// L'autoload permet de charger les futures classes du projet sans multiplier les inclusions manuelles.
require_once __DIR__ . '/vendor/autoload.php';

// La configuration réelle reste dans un fichier local exclu de Git afin de protéger les identifiants.
$databaseConfigPath = __DIR__ . '/private/database-secret.php';

if (!is_file($databaseConfigPath)) {
    echo 'La configuration de la base de données est indisponible.';
    exit;
}

$databaseConfig = require $databaseConfigPath;

// Une seule connexion PDO est créée pour la requête en cours et sera transmise aux futurs contrôleurs et modèles.
$dsn = 'mysql:host=' . $databaseConfig['host']
    . ';port=' . $databaseConfig['port']
    . ';dbname=' . $databaseConfig['database']
    . ';charset=' . $databaseConfig['charset'];

try {
    $pdo = new PDO(
        $dsn,
        $databaseConfig['username'],
        $databaseConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException) {
    // Le message reste volontairement générique pour ne révéler ni identifiant ni détail technique.
    echo 'La connexion à la base de données est momentanément indisponible.';
    exit;
}
