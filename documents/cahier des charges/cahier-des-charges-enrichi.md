# Cahier des charges enrichi — QUIDITMIEUX

## 1. Statut et objectif du document

Le cahier des charges initial reste conservé sans modification. Le présent document constitue cependant la référence fonctionnelle applicable lorsqu’il précise une règle, arbitre une alternative ou corrige une incohérence du document initial.

Cette version intègre les 28 arbitrages explicitement validés par le développeur le 26 août 2026.

Statut : version révisée validée par le développeur le 26 août 2026 et mise à jour le 2 septembre 2026 pour intégrer la politique de confidentialité.

L’objectif est de réaliser une application Web d’enchères entre particuliers, fonctionnelle dans le périmètre défini, sécurisée et démontrable devant un jury. L’application gère une enchère jusqu’à la désignation de son gagnant, sans prendre en charge la réalisation de la transaction entre les particuliers. Elle repose sur une architecture PHP MVC simple, sans Symfony.

Le fuseau horaire unique utilisé par l’application pour la saisie, le stockage, les calculs et l’affichage des dates est `Europe/Paris`.

## 2. Résultat attendu

L’application doit permettre :

- à tout visiteur de consulter et rechercher les annonces ;
- à un particulier de créer un compte et de se connecter avec son pseudo ou son adresse électronique ;
- à toute personne de consulter la politique de confidentialité avant de transmettre ses données ;
- à un utilisateur connecté de gérer son compte, publier une annonce, suivre une vente et enchérir sur l’annonce d’un autre utilisateur ;
- à un vendeur de suivre l’évolution de ses enchères publiées ;
- à un enchérisseur de savoir s’il possède la meilleure enchère et de consulter les ventes remportées ;
- au système de refuser automatiquement les opérations interdites ou devenues impossibles après la fin d’une enchère.

Le projet doit démontrer un traitement back-end complet : authentification, autorisations, opérations CRUD, validation serveur, utilisation de PDO, relations entre les données, appel d’une API externe, actualisations asynchrones et protections de sécurité essentielles.

## 3. Utilisateurs et droits

### 3.1 Visiteur

Un visiteur non connecté peut :

- consulter les annonces ;
- effectuer une recherche multicritère ;
- consulter le détail public d’une annonce ;
- consulter le prix courant et le nombre d’enchères ;
- accéder aux formulaires d’inscription et de connexion.

Il ne peut pas publier, suivre une annonce, enchérir ni consulter l’historique détaillé des enchères.

### 3.2 Utilisateur connecté

Un utilisateur connecté peut :

- modifier les informations autorisées de son compte ;
- se déconnecter ;
- publier une annonce ;
- modifier ou supprimer sa propre annonce avant la première enchère et avant son échéance ;
- suivre ou ne plus suivre l’annonce active d’un autre utilisateur ;
- enchérir sur l’annonce d’un autre utilisateur ;
- consulter son tableau de bord ;
- consulter l’historique d’une annonce s’il en est le vendeur ou s’il y a déjà enchéri.

Les rôles de vendeur, suiveur, enchérisseur et gagnant dépendent des actions réalisées. Aucun rôle d’administration n’est prévu.

## 4. Périmètre fonctionnel

### 4.1 Comptes et authentification

La création d’un compte demande uniquement :

- un pseudo unique de 3 à 30 caractères, composé uniquement de lettres, de chiffres, de `_` et de `-` ;
- une adresse électronique unique, valide et limitée à 255 caractères ;
- un mot de passe d’au moins 8 caractères comportant au minimum une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial ;
- la confirmation du mot de passe.
- l’acceptation obligatoire de la politique de confidentialité au moyen d’une case non précochée.

Seul le pseudo est visible par les autres utilisateurs. L’adresse électronique et le mot de passe ne sont jamais exposés.

Le pseudo ne peut contenir ni espace ni caractère `@`. La casse saisie est conservée pour son affichage, mais son unicité et son utilisation à la connexion sont contrôlées sans tenir compte de la casse.

L’adresse électronique est également normalisée et comparée sans tenir compte de la casse.

La connexion accepte un identifiant unique accompagné du mot de passe. Un identifiant contenant `@` est interprété comme une adresse électronique ; dans le cas contraire, il est interprété comme un pseudo.

L’inscription utilise un champ leurre invisible, ou « honeypot », contrôlé par le serveur afin de limiter les soumissions automatisées sans dépendre d’un service CAPTCHA externe.

Le bouton de création du compte reste inactif et grisé tant que la politique de confidentialité n’est pas acceptée. Cette restriction visuelle est complétée par un contrôle obligatoire côté serveur. La politique est accessible depuis le formulaire d’inscription et depuis le pied de page de l’application.

L’utilisateur connecté peut modifier son pseudo, son adresse électronique ou son mot de passe. Toute modification du compte exige la saisie et la vérification du mot de passe actuel. Un nouveau mot de passe respecte les mêmes règles que lors de l’inscription et doit être confirmé.

Aucun mécanisme fonctionnel de limitation ou de blocage temporaire des tentatives répétées de connexion n’est prévu dans le périmètre validé.

### 4.2 Catégories externes

Les catégories proviennent exclusivement de l’API publique fournie :

`https://api.mywebecom.ovh/play/qdm/categ.php`

L’API permet :

- d’obtenir la liste des catégories ;
- de retrouver le libellé correspondant à un identifiant dans la liste reçue.

Seul l’identifiant externe est enregistré dans l’attribut categorie_id de l’annonce. Le libellé est fourni par l’API ou par le cache local de sa dernière réponse valide. Aucune interface locale d’administration des catégories et aucune catégorie générique de remplacement ne sont créées.

Si l’API est indisponible ou retourne une réponse inexploitable :

- la dernière liste valide présente dans le cache local peut continuer à être utilisée ;
- sans cache exploitable, les annonces existantes restent consultables avec un libellé de catégorie indisponible ;
- sans cache exploitable, la recherche reste disponible avec les autres critères, mais le filtre de catégorie est désactivé ;
- sans cache exploitable, la création et la modification d’une annonce sont bloquées, car elles nécessitent une catégorie validée ;
- l’application affiche une erreur compréhensible sans bloquer les fonctions qui ne dépendent pas de l’API.

### 4.3 Création d’une annonce

Une annonce contient obligatoirement :

- un titre ;
- une catégorie issue de l’API ;
- une description détaillée ;
- un état parmi `neuf`, `très bon état`, `bon état` et `état correct` ;
- un prix de départ strictement positif, exprimé en euros sans  décimales ;
- une date et une heure de fin strictement futures, saisies dans le fuseau `Europe/Paris`, sans durée minimale supplémentaire imposée.

Une annonce peut contenir de zéro à trois photographies. Lorsqu’au moins une photographie existe, la première dans l’ordre de téléversement devient automatiquement l’illustration principale.

Les fichiers transmis doivent être contrôlés côté serveur avant leur conservation. Les formats, tailles et noms de fichiers autorisés seront précisés dans les spécifications techniques, sans remettre en cause la limite fonctionnelle de trois photographies.

### 4.4 Modification et suppression d’une annonce

Le vendeur peut modifier ou supprimer sa propre annonce uniquement lorsque les deux conditions suivantes sont réunies :

- aucune enchère n’a été enregistrée ;
- la date et l’heure de fin ne sont pas atteintes.

Dès la première enchère ou dès que l’échéance est atteinte :

- toute modification de l’annonce est bloquée ;
- toute suppression de l’annonce est bloquée.

Ces règles sont contrôlées côté serveur, même si le bouton correspondant n’est pas affiché dans l’interface.

Tant que la modification reste autorisée, le vendeur peut ajouter, remplacer ou supprimer des photographies sans dépasser trois fichiers au total. Il ne peut pas les réorganiser manuellement. Lorsqu’il supprime la photographie principale, la suivante dans l’ordre de téléversement devient automatiquement principale. Toute nouvelle photographie est ajoutée à la fin.

La modification, la suppression et l’enregistrement d’une première enchère sont des opérations concurrentes. Elles sont traitées de manière transactionnelle : la première opération validée détermine l’état utilisé par les suivantes. Une enchère déjà enregistrée bloque donc la modification ou la suppression ; une annonce déjà supprimée ne peut recevoir d’enchère ; une enchère déposée après une modification autorisée est contrôlée avec les nouvelles informations.

### 4.5 Consultation et recherche

La recherche est accessible avec ou sans compte. Avant toute recherche personnalisée, la page affiche les douze ventes actives dont l’échéance est la plus proche. Les résultats sont paginés par groupes de douze annonces.

Les critères suivants peuvent être combinés :

- texte présent dans le titre ou la description ;
- catégorie ;
- état de l’objet ;
- prix courant minimum ;
- prix courant maximum ;
- état de la vente parmi `Toutes`, `En cours` et `Terminées`.

La recherche textuelle ne tient compte ni de la casse ni des accents. Elle accepte plusieurs mots ou parties de mots présents dans le titre ou la description, sans imposer leur ordre de saisie.

Les prix minimum et maximum sont facultatifs, strictement positif sans décimales, sont renseignés. Un champ vide signifie qu’aucune limite correspondante n’est appliquée. Lorsque les deux valeurs sont présentes, le prix minimum doit être inférieur ou égal au prix maximum ; dans le cas contraire, la recherche est refusée avec un message compréhensible.

Le choix `Toutes` inclut les ventes en cours et terminées. Le choix `En cours` conserve l’ordre d’échéance croissante utilisé par défaut. Les autres résultats restent ordonnés de manière déterministe selon les règles précisées dans les spécifications.

Lorsqu’aucune enchère n’existe, le prix courant correspond au prix de départ. Sinon, il correspond à la meilleure enchère enregistrée.

Chaque résultat affiche au minimum :

- le titre ;
- la photographie principale éventuelle ;
- le prix courant ;
- la date et l’heure de fin.

Le détail public ajoute les informations de l’annonce, son état, sa catégorie, le pseudo du vendeur et le nombre d’enchères.

### 4.6 Suivi d’une annonce

Un utilisateur connecté peut suivre ou ne plus suivre l’annonce active d’un autre utilisateur sans enchérir. Il ne peut pas commencer à suivre une annonce dont l’échéance est atteinte.

Le suivi est indépendant des enchères. Une annonce sur laquelle l’utilisateur a enchéri reste néanmoins présente dans son tableau de bord, même s’il ne la suit pas ou ne la suit plus.

Suivre une annonce sans enchérir ne donne pas accès à l’historique détaillé des enchères.

À la fin d’une vente, une annonce seulement suivie disparaît du tableau de bord de l’utilisateur. Une participation comportant au moins une enchère suit les règles d’historique définies pour le tableau de bord.

### 4.7 Enchères

Un utilisateur connecté peut enchérir uniquement si :

- l’annonce appartient à un autre utilisateur ;
- la vente n’est pas terminée ;
- le montant proposé ne possède aucune décimales ;
- le montant est supérieur d’au moins `1 €` au prix courant.

Une enchère enregistrée est définitive et irrévocable. Elle ne peut être ni modifiée, ni supprimée, ni retirée par son auteur.

Le serveur recalcule le prix courant et contrôle de nouveau toutes les conditions juste avant l’enregistrement. Une information reçue du navigateur n’est jamais considérée comme suffisante pour autoriser l’enchère. Les enchères concurrentes sont sérialisées de manière transactionnelle afin qu’une enchère devenue insuffisante soit refusée.

Le prix courant et le nombre total d’enchères sont publics.

L’historique détaillé contient :

- le pseudo de l’enchérisseur ;
- le montant ;
- la date et l’heure de l’enchère.

Cet historique est visible uniquement :

- par le vendeur de l’annonce ;
- par un utilisateur ayant déjà enchéri sur cette annonce.

### 4.8 Fin d’une vente

La vente est terminée dès que la date et l’heure du serveur sont supérieures ou égales à l’échéance enregistrée. À partir de cet instant :

- aucune nouvelle enchère n’est acceptée ;
- une annonce sans enchère est indiquée comme `non adjugée` ;
- une annonce ayant reçu au moins une enchère est indiquée comme `adjugée` ;
- l’auteur de la meilleure enchère est désigné comme gagnant.

L’état final est déterminé automatiquement à partir de l’échéance et des enchères enregistrées. Il ne dépend pas d’une validation manuelle du vendeur et devient verrouillé.

L’adjudication signifie uniquement que l’application a désigné un gagnant. Le paiement, la livraison et la mise en relation des particuliers ne sont pas pris en charge.

### 4.9 Tableau de bord

Le tableau de bord regroupe trois zones.

Chaque annonce présentée dans le tableau de bord affiche au minimum :

- son titre ;
- sa photographie principale éventuelle ;
- son prix courant ou final ;
- son échéance ou son état final ;
- le rôle ou le statut de l’utilisateur pour cette annonce ;
- un lien vers le détail de l’annonce.

Un état important n’est jamais transmis uniquement par une couleur. Un libellé ou une icône compréhensible accompagne toujours l’indication colorée.

#### Ventes de l’utilisateur

Pour chaque annonce publiée :

- les informations communes d’identification ;
- le prix courant ;
- la date et l’heure de fin lorsque la vente est active ;
- l’état `adjugée` ou `non adjugée` après la fin ;
- une actualisation des informations toutes les 10 secondes uniquement pendant la vente active.

Les annonces terminées restent présentes dans cette zone avec leur état final, sans actualisation périodique.

#### Annonces suivies ou enchéries

Pour chaque annonce concernée :

- les informations communes d’identification ;
- le prix courant ;
- la date et l’heure de fin ou l’état final ;
- une actualisation toutes les 2 secondes uniquement pendant la vente active ;
- un état vert accompagné du libellé `Meilleure enchère` lorsque l’utilisateur est le mieux-disant ;
- un état rouge accompagné du libellé `Enchère dépassée` lorsqu’il a enchéri mais n’est plus le mieux-disant ;
- un état neutre accompagné du libellé `Annonce suivie` lorsqu’il suit seulement l’annonce.

Après la fin :

- une annonce seulement suivie disparaît de cette zone ;
- une annonce perdue sur laquelle l’utilisateur avait enchéri reste visible comme historique, sans actualisation périodique ;
- une annonce remportée quitte cette zone afin d’éviter un doublon avec la zone `Enchères remportées`.

#### Enchères remportées

Cette zone présente exclusivement les annonces terminées pour lesquelles l’utilisateur possède l’enchère gagnante. Chaque annonce utilise les informations communes d’identification et l’état `Enchère remportée`. Elle n’apparaît plus dans la zone `Annonces suivies ou enchéries`.

## 5. Règles de validation et de sécurité

Les protections suivantes font partie du résultat obligatoire :

- validation de toutes les données côté serveur ;
- unicité du pseudo et de l’adresse électronique selon leurs règles de normalisation ;
- stockage sécurisé des mots de passe sous forme d’empreinte ;
- vérification du mot de passe actuel avant toute modification du compte ;
- requêtes préparées pour toutes les données variables ;
- contrôle de l’authentification et de la propriété de la ressource avant chaque action protégée ;
- protection CSRF de toute requête qui modifie les données, qu’elle provienne d’un formulaire HTML classique ou d’un appel AJAX ;
- information de l’utilisateur sur le traitement de ses données et contrôle serveur de l’acceptation de la politique lors de l’inscription ;
- échappement des données affichées dans les templates ;
- contrôle sécurisé des photographies téléversées ;
- suppression des fichiers devenus inutiles après le remplacement ou la suppression autorisée d’une photographie ;
- refus des montants invalides, des enchères concurrentes devenues insuffisantes et des enchères déposées à l’échéance ou après celle-ci ;
- traitement transactionnel des conflits entre une première enchère et la modification ou la suppression de l’annonce ;
- messages d’erreur compréhensibles sans exposition d’informations techniques sensibles.

Les contrôles importants sont toujours répétés sur le serveur. Les restrictions visuelles de l’interface ne constituent jamais une protection suffisante.

## 6. Templates de page

Le périmètre prévoit huit templates de page.

| Template proposé | Accès | Rôle principal |
|---|---|---|
| `home.php` | Public | Afficher la recherche multicritère et la liste des résultats. |
| `listing-detail.php` | Public avec zones conditionnelles | Afficher le détail, le suivi, la saisie d’une enchère et l’historique selon les droits. |
| `register.php` | Public | Créer un compte avec la protection anti-robots. |
| `login.php` | Public | Se connecter avec un pseudo ou une adresse électronique. |
| `listing-form.php` | Utilisateur connecté | Partager le formulaire de création et de modification d’une annonce. |
| `dashboard.php` | Utilisateur connecté | Regrouper les ventes, annonces suivies ou enchéries et enchères remportées. |
| `account.php` | Utilisateur connecté | Modifier le pseudo, l’adresse électronique ou le mot de passe. |
| `privacy.php` | Public | Informer sur les données traitées, leurs finalités, leur conservation et les droits des personnes. |

Les layouts et fragments réutilisables ne sont pas comptés comme des pages supplémentaires.

### 6.1 Matrice obligatoire des états

Chaque template possède un état initial et les déclinaisons réellement nécessaires à ses règles fonctionnelles. Chaque état reçoit un titre explicite dans la maquette.

| Template | États obligatoires à représenter |
|---|---|
| `home.php` | État initial avec douze ventes actives ; résultats filtrés ; aucun résultat ; critères invalides ; API de catégories indisponible. |
| `listing-detail.php` | Consultation publique ; utilisateur connecté simple suiveur ; meilleur enchérisseur ; enchérisseur dépassé ; vendeur avant toute enchère avec actions autorisées ; vendeur après une première enchère avec actions verrouillées ; vente non adjugée ; vente adjugée avec affichages conditionnels selon le vendeur, le gagnant, un enchérisseur perdant ou un autre visiteur. |
| `register.php` | Formulaire initial avec bouton inactif ; politique acceptée et bouton actif ; politique non acceptée ; erreurs de validation ; pseudo déjà utilisé ; adresse électronique déjà utilisée ; mot de passe ou confirmation invalide ; soumission détectée par le honeypot ; inscription réussie. |
| `login.php` | Formulaire initial ; identifiants invalides ; connexion réussie avec redirection. |
| `listing-form.php` | Création initiale ; création avec photographies ; erreurs de validation ; modification autorisée ; gestion autorisée des photographies ; modification verrouillée après une enchère ; modification verrouillée après l’échéance ; API de catégories indisponible. |
| `dashboard.php` | Tableau de bord vide ; ventes actives du vendeur ; ventes adjugées ou non adjugées ; simple suivi ; meilleure enchère ; enchère dépassée ; enchère perdue terminée ; enchère remportée ; erreur temporaire d’actualisation. |
| `account.php` | Formulaire initial ; erreurs de validation ; pseudo ou adresse électronique déjà utilisé ; mot de passe actuel incorrect ; nouveau mot de passe invalide ; modification réussie. |
| `privacy.php` | Affichage public de la version à jour de la politique de confidentialité. |

Dans chaque section de la maquette, l’état initial est placé à gauche et les autres déclinaisons sont alignées horizontalement à sa droite.

### 6.2 Responsive et accessibilité

Les interfaces de référence sont maquettées pour une largeur desktop de 1440 pixels. L’application développée doit rester utilisable à partir d’une largeur de 390 pixels, sans exiger une maquette mobile complète de chaque état.

Les interfaces respectent au minimum les règles suivantes :

- contrastes suffisants entre les textes, les contrôles et leurs arrière-plans ;
- navigation possible au clavier pour les actions et formulaires ;
- focus visible ;
- libellés compréhensibles associés aux champs ;
- information importante accompagnée d’un texte ou d’une icône et jamais transmise uniquement par une couleur ;
- mise en page sans superposition ni contenu indispensable masqué aux largeurs prévues.

## 7. Contrôleurs proposés

Huit contrôleurs répartissent le périmètre par responsabilité sans ajouter de couche architecturale.

| Contrôleur proposé | Responsabilités principales |
|---|---|
| `AuthController` | Afficher et traiter l’inscription, afficher et traiter la connexion, déconnecter l’utilisateur. |
| `ListingSearchController` | Afficher l’accueil, rechercher les annonces et préparer leur pagination. |
| `ListingDetailController` | Afficher le détail d’une annonce, ses photographies, son historique et son état de suivi. |
| `ListingManagementController` | Afficher et traiter la création, la modification et la suppression d’une annonce selon les droits du vendeur. |
| `ParticipationController` | Enregistrer une enchère définitive, suivre une annonce active et arrêter son suivi. Ces actions représentent la participation de l’utilisateur à une enchère. |
| `AccountController` | Afficher et traiter la modification sécurisée du compte. |
| `DashboardController` | Afficher et actualiser les ventes, suivis et enchères du tableau de bord. |
| `LegalController` | Afficher les informations légales publiques, notamment la politique de confidentialité. |

Le détail des méthodes sera fixé dans les spécifications et le schéma ergonomique.

## 8. Démonstration attendue devant le jury

La démonstration doit permettre de montrer au minimum :

1. la consultation et la recherche combinée sans connexion ;
2. la consultation de la politique de confidentialité, son acceptation obligatoire puis la création sécurisée d’un compte et la connexion avec le pseudo ou l’adresse électronique ;
3. la création d’une annonce avec ou sans photographie ;
4. la modification d’une annonce qui ne possède encore aucune enchère ;
5. le suivi d’une annonce par un autre utilisateur ;
6. le dépôt d’une enchère valide et le refus d’une enchère interdite ou insuffisante ;
7. le déblocage conditionnel de l’historique après une première enchère ;
8. le blocage de la modification et de la suppression après la première enchère ;
9. l’actualisation différenciée des informations du tableau de bord ;
10. les états accessibles du mieux-disant, de l’utilisateur dépassé et du simple suiveur ;
11. la fin automatique d’une enchère, son adjudication éventuelle et la désignation du gagnant ;
12. la modification sécurisée du compte et la déconnexion.

## 9. Éléments hors périmètre

Les fonctions suivantes ne sont pas prévues :

- paiement en ligne ;
- organisation de la livraison ;
- messagerie interne ;
- échange public ou privé de coordonnées supplémentaires ;
- notation des utilisateurs ;
- administration ou modération des annonces ;
- administration locale des catégories ;
- récupération d’un mot de passe oublié ;
- suppression d’un compte ;
- notifications par courriel, SMS ou notification poussée ;
- modification, suppression ou retrait d’une enchère enregistrée ;
- remise en vente par modification d’une annonce terminée ou duplication automatique de celle-ci ;
- nombre illimité de photographies ;
- sélection manuelle d’une autre photographie principale ;
- maquettes mobiles exhaustives de chaque état ;
- utilisation de Symfony.

## 10. Critères fonctionnels de réussite

Le projet est fonctionnellement conforme lorsque :

- les droits des visiteurs, vendeurs, suiveurs et enchérisseurs sont respectés ;
- chaque opération interdite est refusée côté serveur ;
- les recherches combinées retournent des résultats cohérents ;
- les prix courants et états d’adjudication sont calculés correctement ;
- les échéances stockées au format français de l’application empêchent toute nouvelle enchère dès l’instant de fin ;
- la visibilité de l’historique respecte les règles définies ;
- le tableau de bord présente les trois zones sans doublon, les états accessibles et les actualisations prévues uniquement pour les ventes actives ;
- les catégories externes sont utilisées sans créer de gestion locale et les annonces existantes restent consultables pendant une panne de l’API ;
- les données privées et les fichiers transmis sont protégés ;
- la politique de confidentialité est accessible et l’inscription est impossible tant que sa case d’acceptation n’est pas cochée ;
- la matrice des états des huit templates est respectée dans la maquette et dans l’application ;
- l’application reste utilisable à partir de 390 pixels et respecte les exigences d’accessibilité définies ;
- les huit templates et les huit contrôleurs `AuthController`, `LegalController`, `ListingSearchController`, `ListingDetailController`, `ListingManagementController`, `ParticipationController`, `AccountController` et `DashboardController` couvrent toutes les fonctions prévues sans ajouter de périmètre.
