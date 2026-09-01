# Journal des points à revoir

## Modèle de données

- La base locale limite l’adresse électronique à 254 caractères, alors que le MPD simplifié indique 255 caractères. Le code valide 254 caractères afin de respecter la contrainte réelle de la base et la longueur normalisée d’une adresse électronique.
- Le MPD simplifié présente seulement un identifiant de catégorie, tandis que la base locale contient un identifiant externe et le libellé mémorisé. Le code utilise les deux colonnes réelles afin que les annonces restent lisibles lorsque l’API de catégories est indisponible.

## Navigation

- Le pied de page validé demande un lien « Politique de confidentialité », mais aucune page ni aucun contenu juridique correspondant ne figure dans les dix fonctionnalités définies. Le lien est affiché ; sa page de destination reste à définir.

## Messages d’interface

- La maquette définit précisément les messages des 53 états prévus, mais ne définit aucun texte pour certaines défaillances techniques exceptionnelles : panne d’écriture en base, suppression impossible, paramètres forgés ou résultat de recherche inexploitable. Les états représentés utilisent strictement les textes de la maquette ; les rares replis techniques conservent une formulation exacte et non trompeuse lorsqu’aucun état Figma ne peut s’appliquer.

## Erreurs corrigées pendant le développement

- La première réduction de `UserModel.php` à la seule inscription avait supprimé par erreur la fermeture de la classe. Le contrôle de syntaxe PHP l’a détecté ; le modèle a été reconstruit avec ses méthodes d’unicité et validé de nouveau.
- La première réduction de `PhotoModel.php` aux lectures utiles au détail avait aussi retiré sa méthode de normalisation et la fermeture de la classe. Le contrôle de syntaxe l’a détecté ; la méthode commune et la fermeture ont été rétablies.
- Le contrôle du compte remplaçait le message « mot de passe actuel requis » par « mot de passe incorrect » lorsque le champ était vide. La vérification de l’empreinte est désormais exécutée seulement lorsque le champ obligatoire a été renseigné.
- La page de détail utilisait la propriété CSS `--space-7`, absente du thème clair. Le contrôle des jetons l’a détectée ; les espacements concernés utilisent maintenant la propriété existante `--space-8`.
- L’état Figma d’une vente adjugée suggère des informations sur le gagnant, mais le contrôleur de détail ne fournit volontairement aucune identité de gagnant. L’interface conserve donc un résultat final générique afin de ne pas inventer ni exposer une donnée absente.
- Les états Figma de l’accueil utilisent des nombres fixes de cartes de démonstration, tandis que l’application affiche le nombre réel de résultats. Les dimensions et espacements correspondent aux états de référence lorsque le même nombre de cartes est présent ; la grille continue ensuite naturellement pour ne masquer aucune annonce réelle.
