# Notes de présentation au jury — QUIDITMIEUX

Ce document rassemble des explications courtes pour présenter les choix techniques du projet. Il ne remplace pas le code : il aide à en expliquer le rôle avec des mots simples.

## Présentation rapide du projet

QUIDITMIEUX est une application Web d’enchères entre particuliers. Un visiteur peut consulter et rechercher des annonces. Un utilisateur connecté peut publier des annonces, les modifier tant qu’aucune enchère n’a été déposée, suivre des ventes et enchérir sur les annonces d’autres utilisateurs.

Le projet utilise PHP, MySQL, PDO, HTML, SCSS et JavaScript sans framework. Il a été construit avec une architecture MVC simple et de la programmation orientée objet.

## Architecture MVC

MVC signifie **Modèle – Vue – Contrôleur**. Le but est de séparer les responsabilités afin que le code soit plus facile à comprendre et à faire évoluer.

| Partie | Rôle dans QUIDITMIEUX | Exemple |
|---|---|---|
| Modèle | Interroge la base de données et porte les règles liées aux données. | `ListingModel`, `BidModel`, `UserModel` |
| Vue | Affiche les informations dans une page HTML. | `templates/pages/listing-detail.php` |
| Contrôleur | Reçoit la demande, vérifie les données, appelle les modèles et choisit la réponse. | `ListingController`, `ParticipationController` |

Exemple : pour déposer une enchère, le contrôleur reçoit le formulaire. Il vérifie que la donnée reçue est correcte, puis il appelle le modèle. Le modèle vérifie les règles métier : annonce encore active, utilisateur différent du vendeur et montant suffisant. Enfin, le contrôleur redirige l’utilisateur ou renvoie une réponse JSON.

## Démarrage de l’application et routes

`index.php` est le point d’entrée unique. Il crée `App`, puis `App` démarre les éléments communs et transmet la demande au `Router`.

Le routeur lit les routes définies dans `src/config/routes.php`. Il vérifie notamment la méthode HTTP, puis appelle le contrôleur et la méthode correspondants. Ainsi, `index.php` ne choisit pas lui-même quelle page doit être exécutée.

## POO et héritage

La programmation orientée objet consiste à regrouper les données et les traitements qui ont le même rôle dans des classes.

- `Controller` est la classe parent des contrôleurs. Elle fournit le rendu d’une vue, la redirection, les messages flash et les réponses JSON.
- `Model` est la classe parent des modèles SQL. Elle centralise PDO et des opérations communes de création, modification et suppression.
- `ListingModel`, `BidModel`, `PhotoModel` et `UserModel` sont des modèles enfants spécialisés dans leurs propres données.

L’héritage évite de recopier les mêmes méthodes dans chaque modèle ou chaque contrôleur. `ListingModel` hérite de `Model`, mais possède aussi ses propres méthodes métier, par exemple la vérification qu’une annonce peut être modifiée ou qu’elle peut recevoir une enchère.

`CategoryModel` n’hérite pas de `Model`, car il ne communique pas avec une table MySQL : il récupère les catégories depuis une API externe. L’héritage est donc utilisé seulement lorsque les responsabilités sont réellement communes.

## Accès à la base de données

La classe `Database` crée une connexion PDO. Les modèles utilisent cette connexion pour exécuter des requêtes préparées.

Une requête préparée sépare la requête SQL des valeurs envoyées par l’utilisateur. Cela évite qu’une valeur saisie soit interprétée comme une instruction SQL.

Les modèles regroupent aussi les règles proches des données. Par exemple, le modèle des annonces contrôle le propriétaire d’une annonce avant une modification ou une suppression.

## Transactions pour les actions importantes

Une transaction permet de considérer plusieurs opérations comme un seul ensemble : soit toutes les opérations réussissent, soit aucune n’est conservée.

Dans le projet, cette logique est utilisée notamment pour les enchères et le suivi d’annonce. Cela évite de laisser la base dans un état incomplet si une erreur survient entre deux requêtes.

Pour une enchère, l’annonce est aussi verrouillée pendant le contrôle. Deux utilisateurs ne peuvent donc pas valider simultanément une enchère à partir du même ancien montant.

## Gestion des photographies

La classe `PhotoStorage` gère uniquement les fichiers physiques : elle crée un nom unique, déplace le fichier téléversé dans `public/uploads/annonces`, supprime un fichier lorsque nécessaire et construit son adresse publique.

Le modèle `PhotoModel` gère les informations enregistrées en base de données : nom du fichier, annonce associée et ordre d’affichage. Cette séparation évite de mélanger la gestion des fichiers et les requêtes SQL.

Les photographies sont contrôlées côté serveur : seuls les formats JPG, PNG et WebP sont acceptés et le nombre est limité à trois par annonce.

## Montants des enchères

Les prix sont volontairement gérés en euros entiers. La classe `Money` valide le montant saisi et le formate pour l’affichage, par exemple `1 250 €`.

Ce choix évite les imprécisions liées aux nombres décimaux de type `float`. Les contrôles du montant minimal sont faits côté serveur avant l’enregistrement de l’enchère.

## Dates, UTC et Europe/Paris

UTC est un fuseau horaire de référence international. Les dates sont enregistrées en UTC dans la base de données afin d’avoir une seule référence pour tous les calculs.

L’utilisateur saisit et lit les dates dans le fuseau `Europe/Paris`. L’application convertit entre l’affichage local et la référence UTC. La classe `Clock` centralise l’heure courante pour éviter que différents fichiers utilisent des sources d’heure différentes.

Le fuseau affiché ne change pas le calcul de l’échéance : il indique seulement comment la date est présentée à l’utilisateur.

## JavaScript et AJAX

JavaScript améliore l’interface, mais les fonctions essentielles restent organisées autour des routes PHP.

AJAX permet d’envoyer une demande au serveur et de mettre à jour une partie de la page sans la recharger entièrement. Dans le projet, il est utilisé pour :

- actualiser le tableau de bord ;
- rechercher et paginer les annonces ;
- suivre ou arrêter de suivre une annonce ;
- déposer une enchère ;
- faire fonctionner le carrousel de photographies.

Les actualisations du tableau de bord respectent le besoin fonctionnel : les ventes sont actualisées toutes les 10 secondes et les annonces suivies ou enchéries toutes les 2 secondes.

## Sécurité mise en œuvre

- mots de passe hachés avec les fonctions natives de PHP ;
- requêtes PDO préparées ;
- validation des données côté serveur ;
- contrôle de la connexion et de la propriété d’une annonce ;
- jeton CSRF pour les formulaires qui modifient des données ;
- échappement des valeurs avant affichage dans les vues ;
- contrôle des fichiers téléversés ;
- champ invisible anti-robot à l’inscription ;
- acceptation explicite de la politique de confidentialité.

Les validations JavaScript améliorent le confort de l’utilisateur, mais les contrôles importants sont toujours répétés côté serveur.

## API des catégories

Les catégories ne sont pas stockées dans une table locale. `CategoryModel` appelle une API externe qui fournit la liste et le libellé des catégories.

Un cache temporaire est conservé. Il limite les appels répétés à l’API et permet encore d’afficher les catégories connues lors d’une indisponibilité temporaire du service externe.

## Tests automatisés

Le projet contient des tests unitaires et des tests d’intégration, lancés avec :

```bash
php tests/Lancer.php
```

Les tests unitaires vérifient une classe isolée. Les tests d’intégration vérifient les échanges avec la base de données. Ils permettent de détecter une régression après une modification du code.

## Limites assumées du projet

Le projet ne gère pas le paiement, la livraison, la messagerie, la modération, la récupération de mot de passe ni la suppression autonome d’un compte. Ces éléments sont volontairement hors périmètre du cahier des charges.

## Formulation de conclusion possible

> J’ai construit une application d’enchères en PHP avec une architecture MVC simple. Les contrôleurs coordonnent les demandes, les modèles regroupent l’accès aux données et les règles métier, et les vues affichent les informations. J’ai utilisé l’héritage pour partager les traitements communs, PDO et les requêtes préparées pour la base de données, et JavaScript pour améliorer certaines interactions sans remplacer le fonctionnement serveur. Les règles importantes, notamment les enchères, les droits du vendeur et les photographies, sont contrôlées côté serveur.