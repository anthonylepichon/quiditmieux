/**
 * Description générale : Actualisations périodiques du tableau de bord privé.
 * Rôle : Rafraîchir séparément les ventes et les participations sans recharger la page.
 * Tâches : Éviter les requêtes simultanées, ignorer les réponses obsolètes et reconstruire les cartes en sécurité.
 * Liens avec les autres fichiers : Est chargé par dashboard.php et appelle les routes JSON de UserController.php.
 */

