# Journal des points à revoir

## Modèle de données

- La base locale limite l’adresse électronique à 254 caractères, alors que le MPD simplifié indique 255 caractères. Le code valide 254 caractères afin de respecter la contrainte réelle de la base et la longueur normalisée d’une adresse électronique.
- Le MPD simplifié présente seulement un identifiant de catégorie, tandis que la base locale contient un identifiant externe et le libellé mémorisé. Le code utilise les deux colonnes réelles afin que les annonces restent lisibles lorsque l’API de catégories est indisponible.

## Navigation

- Le pied de page validé demande un lien « Politique de confidentialité », mais aucune page ni aucun contenu juridique correspondant ne figure dans les dix fonctionnalités définies. Le lien est affiché ; sa page de destination reste à définir.

## Erreurs corrigées pendant le développement

- La première réduction de `UserModel.php` à la seule inscription avait supprimé par erreur la fermeture de la classe. Le contrôle de syntaxe PHP l’a détecté ; le modèle a été reconstruit avec ses méthodes d’unicité et validé de nouveau.
- La première réduction de `PhotoModel.php` aux lectures utiles au détail avait aussi retiré sa méthode de normalisation et la fermeture de la classe. Le contrôle de syntaxe l’a détecté ; la méthode commune et la fermeture ont été rétablies.
