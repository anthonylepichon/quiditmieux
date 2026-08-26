QuiDitMieux
Nous sommes QuiDitMieux, une plateforme d’enchères entre particuliers.

Nous souhaitons mettre en place un site Web permettant à des particuliers de proposer des objets à la vente et d’enchérir sur les objets proposés par les autres utilisateurs.

Le site doit être accessible à tout le monde. Il doit être possible de consulter les objets proposés à la vente et d’effectuer des recherches sans disposer de compte.

Pour proposer un objet à la vente ou participer à une enchère, il est nécessaire de disposer d’un compte et d’être connecté.

Comptes utilisateurs
Il est possible de créer un compte en indiquant :

un pseudo ;

une adresse de courriel ;

un mot de passe.

Nous ne souhaitons pas collecter d’autres informations personnelles et seul le pseudo doit être visible par les autres utilisateurs.

La connexion peut se faire avec l’adresse de courriel ou le pseudo, au choix de l’utilisateur. L'email n'st pas visible par les autres utilisateurs, bien sûr.

La création d’un compte doit si possible être sécurisée par un mécanisme anti-robots.

Un utilisateur connecté peut modifier les informations de son compte.

Mise en vente d’un objet
Un utilisateur connecté peut proposer un objet à la vente.

Une annonce comporte :

un titre ;

une catégorie ;

une description détaillée ;

l’état de l’objet : neuf, très bon état, bon état ou état correct ;

un prix de départ ;

une date et une heure de fin des enchères ;

une à trois photographies de l'objet (ou un nombre non limité de photographies)

Lorsqu’une ou plusieurs photographies sont disponibles, l’une d’elles est utilisée comme illustration principale de l’annonce (la première ou une désignée par l'utilisateur).

Il doit être possible de créer une annonce sans photographie.

Un vendeur peut modifier ou supprimer une annonce qu’il a créée tant qu’aucune enchère n’a été placée.

Une fois qu’une première enchère a été enregistrée, les informations essentielles de la vente ne peuvent plus être modifiées.

Enchères
Un utilisateur connecté peut enchérir sur un objet proposé par un autre utilisateur.

Il peut aussi suivre cet objet sans enchérir immédiatement (il n'a pas accès à la liste des enchères tant qu'il n'a pas enchéri au moins une fois sur l'annonce).

Il n’est pas possible d’enchérir sur sa propre annonce.

Une enchère doit obligatoirement être supérieure :

au prix de départ si aucune enchère n’a encore été placée ;

à la meilleure enchère existante dans le cas contraire.

Pour chaque annonce suivie, le site affiche notamment :

le prix courant ;

le nombre d’enchères ;

la date et l’heure de fin de la vente ;

le pseudo du vendeur ;

l’historique des enchères, avec le pseudo de l’enchérisseur, le montant et la date de l’enchère.

Une fois la date et l’heure de fin dépassées, aucune nouvelle enchère ne peut être enregistrée.

L’utilisateur ayant proposé l’enchère la plus élevée est alors considéré comme le gagnant de la vente.

Tableau de bord
Un tableau de bord est disponible pour chaque utilisateur avec :

ses annonces en cours, avec le dernier prix proposé mis à jour toutes les 10 secondes, et la fin de l'enchère (ou l'info vendu / non vendu)
ses annonces suivies ou enchéries, avec le dernier prix proposé mis à jour toutes les 2 secondes (en vert si l'utilisateur est le mieux disant, en vert sinon), et la fin de l'enchère
ses enchères remportées
Recherche des annonces
Le site doit permettre de rechercher les annonces (en étant connecté ou pas) selon différents critères, notamment :

un texte présent dans le titre ou la description ;

la catégorie ;

l’état de l’objet ;

le prix ;

les ventes encore en cours ou les ventes terminées.

Plusieurs critères doivent pouvoir être combinés.

Les résultats doivent présenter au minimum :

le titre de l’annonce ;

son éventuelle photographie principale ;

son prix courant ;

la date et l’heure de fin de l’enchère.

L'historique des enchères n'est accessibles qu'au vendeur, et aux personnes ayant enchéri.

Catégories
Nous utiliserons les catégories fournies par une API externe, pour ne pas avoir à maintenir nous même la liste des catégories.

Nous fournissons une API permettant d’accéder à la liste des catégories.

L’accès à cette API est libre et ne nécessite pas de mot de passe.

Détail de l'API
https://api.mywebecom.ovh/play/qdm/categ.php

Retourne au format JSON l’ensemble des catégories, avec leur identifiant en index et leur libellé en valeur.

Exemple :

{
"20": "Art et antiquités",
"18": "Auto et moto",
"10": "Bijoux et montres",
"9": "Chaussures"
}
Recherche d’une catégorie par son identifiant
https://api.mywebecom.ovh/play/qdm/categ.php?id=17

Retourne au format JSON les informations correspondant à la catégorie demandée.

Exemple :

{
"id": "17",
"libelle": "Vélos et cyclisme"
}
Si l’identifiant demandé n’existe pas, l’API retourne une erreur.

Recherche de catégories
https://api.mywebecom.ovh/play/qdm/categ.php?search=xxx

Permet de rechercher les catégories dont le libellé correspond au texte indiqué.

La recherche :

ne tient pas compte des majuscules et minuscules ;

ne tient pas compte des accents ;

accepte plusieurs mots ou parties de mots ;

ne tient pas compte de l’ordre dans lequel ces mots sont saisis.

Par exemple :

https://api.mywebecom.ovh/play/qdm/categ.php?search=jeu

peut retourner :

{
"15": "Jeux et jouets",
"14": "Jeux vidéo"
}
Une recherche sur :

https://api.mywebecom.ovh/play/qdm/categ.php?search=video%20jeu

permet de retrouver la catégorie Jeux vidéo.

Les résultats retournés par l’API sont classés par ordre alphabétique.
