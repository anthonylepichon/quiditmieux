# Journal des points à revoir

## 7 septembre 2026 — Tests d’intégration locaux indisponibles

- La simplification de `Money` et ses trois tests unitaires sont validés.
- Les tests d’intégration `Database.php` et `ListingModel.php` échouent avant leur premier scénario, car `Database::isConnected()` retourne `false` avec la configuration locale actuelle.
- Aucune correction applicative n’est appliquée : le problème concerne la disponibilité ou les paramètres de la base MySQL locale. Vérifier ultérieurement le service MySQL de Laragon et `private/database-secret.php`.