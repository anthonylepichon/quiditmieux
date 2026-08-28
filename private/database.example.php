<?php

/**
 * Description générale :
 * Exemple de configuration de la base de données QUIDITMIEUX.
 *
 * Rôle :
 * Montrer la structure attendue sans contenir d'identifiant réel.
 *
 * Tâches :
 * Fournir les paramètres nécessaires à la future création de la connexion PDO.
 *
 * Liens avec les autres fichiers :
 * Sert de modèle à private/database-secret.php, chargé localement par index.php.
 */

return [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'nom_de_la_base',
    'username' => 'nom_utilisateur',
    'password' => 'mot_de_passe',
    'charset' => 'utf8mb4',
];
