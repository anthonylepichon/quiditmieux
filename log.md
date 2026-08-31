# Journal des points à revoir

## Modèle de données

- La base locale limite l’adresse électronique à 254 caractères, alors que le MPD simplifié indique 255 caractères. Le code valide 254 caractères afin de respecter la contrainte réelle de la base et la longueur normalisée d’une adresse électronique.
- Le MPD simplifié présente seulement un identifiant de catégorie, tandis que la base locale contient un identifiant externe et le libellé mémorisé. Le code utilise les deux colonnes réelles afin que les annonces restent lisibles lorsque l’API de catégories est indisponible.

## Navigation

- Le pied de page validé demande un lien « Politique de confidentialité », mais aucune page ni aucun contenu juridique correspondant ne figure dans les dix fonctionnalités définies. Le lien est affiché ; sa page de destination reste à définir.
