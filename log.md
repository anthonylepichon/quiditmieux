# Journal des points à revoir

## Modèle de données

- Le fichier `documents/conceptualisation/Modèles de données/requetes-sql.md` nomme encore les clés primaires `utilisateur_id`, `annonce_id`, `photographie_id`, `assoc_utilisateur_annonce_id` et `enchere_id`, alors que le MPD simplifié validé et la base locale utilisent volontairement `id` dans chaque table. Le code suit le MPD et la base réelle.
- Le cahier des charges limite l'adresse électronique à 254 caractères, tandis que le MPD simplifié et la base locale utilisent `VARCHAR(255)`. La validation applicative conserve la limite fonctionnelle de 254 caractères.

## Spécifications HTTP

- Certaines lignes JavaScript demandent des réponses 403, 404 ou 500, alors que les règles techniques de la phase 3 interdisent de déclarer explicitement ces statuts. Le code utilise des réponses fonctionnelles et des messages génériques sans ajouter ces statuts interdits.

## Organisation des templates

- Les sept pages prévues répètent actuellement une partie de l'en-tête et du pied de page. Des fragments partagés réduiraient cette duplication, mais leur création n'était pas prévue dans la liste initiale issue des spécifications. Ce point pourra être repris lors de l'audit final sans changer le périmètre fonctionnel.
- Le pied de page validé demande un lien « Politique de confidentialité », mais aucune route, aucun contrôleur, aucun template ni contenu juridique correspondant ne figure parmi les dix fonctionnalités ordonnées. Le lien est présent comme prévu dans l'interface, mais sa page de destination reste à définir.
