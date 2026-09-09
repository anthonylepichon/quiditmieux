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

## Différence entre GET et POST

GET et POST sont deux méthodes HTTP utilisées par le navigateur pour envoyer une demande au serveur. La différence essentielle concerne le but de la demande :

- `GET` sert à consulter ou rechercher une information sans modifier les données ;
- `POST` sert à envoyer des données pour créer, modifier, supprimer ou déclencher une action.

### Quand utiliser GET ?

GET est utilisé pour afficher une page ou récupérer des informations. Les paramètres figurent généralement dans l’adresse.

Exemple pour consulter une annonce :

```text
index.php?route=listing_detail&id=12
```

PHP peut récupérer son identifiant avec :

```php
$listingId = $_GET['id'] ?? null;
```

Dans QUIDITMIEUX, GET est notamment adapté pour :

- afficher la page d’accueil ;
- consulter une annonce ;
- rechercher des annonces ;
- afficher le tableau de bord ;
- afficher un formulaire sans encore enregistrer ses données.

Une recherche utilise normalement GET, car son adresse peut être conservée, partagée ou actualisée sans modifier la base de données.

### Quand utiliser POST ?

POST est utilisé lorsqu’une demande doit modifier les données ou déclencher une action. Les valeurs sont envoyées dans le corps de la requête et ne figurent généralement pas dans l’adresse.

Exemple pour transmettre un formulaire de connexion :

```html
<form method="POST" action="index.php?route=login">
    <input type="email" name="email">
    <input type="password" name="password">
    <button type="submit">Se connecter</button>
</form>
```

PHP récupère ces valeurs avec :

```php
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
```

Dans QUIDITMIEUX, POST est notamment adapté pour :

- créer un compte ou se connecter ;
- publier ou modifier une annonce ;
- supprimer une photographie ou une annonce ;
- déposer une enchère ;
- suivre ou ne plus suivre une annonce ;
- se déconnecter.

### Une même fonctionnalité peut utiliser GET et POST

Pour créer une annonce, GET affiche le formulaire et POST enregistre les données saisies :

```text
GET  index.php?route=listing_create → afficher le formulaire
POST index.php?route=listing_create → contrôler et enregistrer l’annonce
```

Le routeur utilise donc le nom de la route et la méthode HTTP pour choisir le traitement autorisé.

### Différence à retenir

| Question | GET | POST |
|---|---|---|
| Quel est son rôle principal ? | Consulter une ressource | Demander une modification |
| Les paramètres sont-ils généralement visibles dans l’adresse ? | Oui | Non |
| La demande peut-elle être partagée ou enregistrée comme favori ? | Oui | Non |
| Une actualisation doit-elle modifier les données ? | Non | Potentiellement oui |
| Exemple dans le projet | Consulter une annonce | Déposer une enchère |

Une suppression ne doit pas être déclenchée par une simple adresse GET. Elle doit utiliser un formulaire POST, avec les vérifications côté serveur et un jeton CSRF.

Enfin, POST ne rend pas automatiquement une demande sécurisée. Les données reçues avec `$_GET` et `$_POST` viennent toutes de l’utilisateur. Le serveur doit donc toujours les contrôler. Le site publié doit également utiliser HTTPS pour protéger les données pendant leur transport.

La règle simple à présenter au jury est :

> **GET = je consulte. POST = je demande une modification.**

## isset(), strtoupper() et REQUEST_METHOD

Ces trois éléments sont utilisés par `App.php` pour identifier correctement la route et la méthode HTTP demandées.

### À quoi sert isset() ?

`isset()` est une fonction native de PHP. Elle vérifie si une variable ou une entrée de tableau existe et si sa valeur est différente de `null`.

Elle retourne un booléen :

- `true` si la valeur existe et ne vaut pas `null` ;
- `false` si la valeur est absente ou vaut `null`.

Dans la méthode qui recherche la route :

```php
if (isset($_GET['route']) && is_string($_GET['route']) && $_GET['route'] !== '') {
    return $_GET['route'];
}

return 'home';
```

les trois contrôles signifient :

1. `isset($_GET['route'])` vérifie que le paramètre `route` a été transmis dans l’adresse ;
2. `is_string($_GET['route'])` vérifie que sa valeur est une chaîne de caractères ;
3. `$_GET['route'] !== ''` vérifie que cette chaîne n’est pas vide.

Le symbole `&&` signifie « ET ». Les trois conditions doivent donc être vraies pour utiliser la route reçue.

PHP vérifie les conditions de gauche à droite. Si `isset()` retourne `false`, PHP arrête la vérification du `&&`. Il ne tente donc pas de lire une entrée `route` inexistante, ce qui évite un avertissement `Undefined array key`.

Une valeur absente et une chaîne vide sont deux cas différents :

| Contenu de `$_GET` | Résultat de `isset()` | Résultat final |
|---|---|---|
| aucune clé `route` | `false` | la route `home` est utilisée |
| `'route' => null` | `false` | la route `home` est utilisée |
| `'route' => ''` | `true`, mais le texte est vide | la route `home` est utilisée |
| `'route' => 'listing_detail'` | `true` | `listing_detail` est utilisée |

La règle simple à retenir est :

> **`isset()` vérifie qu’une valeur existe et qu’elle n’est pas égale à `null`.**

### Qu’est-ce que $_SERVER ?

`$_SERVER` est une variable superglobale native de PHP. Il s’agit d’un tableau associatif créé automatiquement avant l’exécution de l’application.

Il contient notamment des informations concernant :

- la demande HTTP reçue ;
- le serveur Web ;
- le fichier PHP exécuté ;
- certaines données transmises par le navigateur.

Une variable superglobale est accessible dans toutes les fonctions et méthodes sans avoir besoin de la transmettre comme paramètre ou d’utiliser le mot-clé `global`.

Le cheminement est le suivant :

```text
Navigateur
    ↓ envoie une requête HTTP
Serveur Web, par exemple Apache
    ↓ transmet les informations à PHP
PHP
    ↓ construit le tableau $_SERVER
Application
```

Les informations sont rangées sous différentes clés :

```php
$_SERVER['REQUEST_METHOD'];
$_SERVER['HTTPS'];
$_SERVER['REQUEST_URI'];
$_SERVER['SERVER_NAME'];
```

Toutes les clés ne sont pas obligatoirement présentes. Leur disponibilité dépend notamment du serveur, de sa configuration et de la manière dont PHP est exécuté. Il faut donc vérifier une clé avec `isset()` avant de l’utiliser.

### À quoi correspond REQUEST_METHOD ?

`REQUEST_METHOD` est une clé du tableau `$_SERVER` :

```php
$_SERVER['REQUEST_METHOD']
```

Elle indique la méthode HTTP employée par le navigateur :

- `GET` pour consulter généralement une page ou rechercher des informations ;
- `POST` pour demander généralement une création, une modification, une suppression ou une autre action.

Pour une consultation :

```text
GET /index.php?route=listing_detail&id=12
```

PHP fournit généralement :

```php
$_SERVER['REQUEST_METHOD'] === 'GET';
```

Pour un formulaire :

```html
<form method="POST" action="index.php?route=login">
```

PHP fournit généralement :

```php
$_SERVER['REQUEST_METHOD'] === 'POST';
```

Dans `App.php` :

```php
private function getRequestMethod(): string
{
    if (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])) {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    return 'GET';
}
```

Le traitement se déroule ainsi :

1. `isset()` vérifie que le serveur a fourni la clé ;
2. `is_string()` vérifie que sa valeur est du texte ;
3. `strtoupper()` la normalise en majuscules ;
4. la méthode retourne par exemple `GET` ou `POST` ;
5. si l’information est absente ou invalide, `GET` est utilisé par défaut.

### Quelques clés courantes de $_SERVER

| Clé | Information généralement fournie |
|---|---|
| `REQUEST_METHOD` | Méthode HTTP utilisée, par exemple `GET` ou `POST` |
| `REQUEST_URI` | Partie de l’adresse demandée après le domaine |
| `SERVER_NAME` | Nom du serveur, par exemple `localhost` |
| `SERVER_PORT` | Port utilisé, généralement `80` ou `443` |
| `REMOTE_ADDR` | Adresse IP avec laquelle le serveur voit le client |
| `HTTP_USER_AGENT` | Description déclarée par le navigateur |
| `SCRIPT_FILENAME` | Chemin du fichier PHP exécuté |
| `HTTPS` | Information indiquant si HTTPS est actif |

### Différence entre $_SERVER, $_GET et $_POST

| Tableau | Question | Exemple |
|---|---|---|
| `$_SERVER` | Comment la demande a-t-elle été envoyée ? | `REQUEST_METHOD` contient `POST` |
| `$_GET` | Quels paramètres figurent dans l’adresse ? | `route` contient `login` |
| `$_POST` | Quelles données le formulaire a-t-il envoyées ? | `email` contient l’adresse saisie |
| `$_FILES` | Quels fichiers ont été téléversés ? | informations sur une photographie |
| `$_SESSION` | Quelles données restent disponibles entre les pages ? | identifiant de l’utilisateur connecté |

Pour `index.php?route=login` appelé par un formulaire POST, PHP peut fournir :

```php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['route'] = 'login';
$_POST['email'] = 'anthony@example.com';
```

Ainsi, `$_SERVER` indique comment la demande est envoyée, `$_GET` indique le traitement demandé et `$_POST` contient les champs du formulaire.

### Fiabilité des informations

Certaines valeurs, comme `REQUEST_METHOD`, sont établies par le serveur dans le contexte de la requête. D’autres reprennent des informations déclarées par le navigateur et peuvent être modifiées par l’utilisateur :

```php
$_SERVER['HTTP_USER_AGENT'];
$_SERVER['HTTP_REFERER'];
$_SERVER['HTTP_HOST'];
```

Elles ne doivent pas être considérées seules comme des preuves pour une décision de sécurité.

Lorsque PHP est lancé depuis le terminal avec `php tests/Lancer.php`, il ne reçoit pas une requête Web classique. Des clés comme `REQUEST_METHOD` ou `HTTPS` peuvent alors être absentes, ce qui justifie également leur vérification avec `isset()`.

La règle simple à retenir est :

> **`$_SERVER` est un tableau automatiquement créé par PHP qui contient les informations techniques concernant le serveur et la demande en cours.**

Dans QUIDITMIEUX, `$_SERVER['REQUEST_METHOD']` indique si l’application a été appelée avec GET ou POST. Les autres clés présentées dans ce chapitre sont des exemples généraux fournis par PHP, mais elles ne sont pas toutes utilisées par le projet.

### À quoi sert strtoupper() ?

`strtoupper()` est une fonction native de PHP qui retourne une chaîne de caractères écrite en majuscules.

```php
strtoupper('get');   // Retourne GET
strtoupper('post');  // Retourne POST
```

Elle ne modifie pas directement la variable reçue : elle retourne une nouvelle chaîne.

Dans `App.php` :

```php
if (isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])) {
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

return 'GET';
```

le traitement est le suivant :

1. `isset()` vérifie que le serveur a fourni `REQUEST_METHOD` ;
2. `is_string()` vérifie que cette information est du texte ;
3. `strtoupper()` garantit une valeur en majuscules, par exemple `GET` ou `POST` ;
4. `return` renvoie cette valeur au routeur ;
5. si la valeur est absente ou invalide, la méthode utilise `GET` par défaut.

La mise en majuscules facilite la comparaison avec les méthodes enregistrées dans les routes :

```php
'post' === 'POST';              // false
strtoupper('post') === 'POST';  // true
```

La règle simple à retenir est :

> **`REQUEST_METHOD` indique comment la demande HTTP a été envoyée ; `strtoupper()` transforme son nom en majuscules avant sa comparaison par le routeur.**

## Le constructeur __construct() et le mot-clé $this

### À quoi sert __construct() ?

`__construct()` est une méthode spéciale native de PHP appelée le constructeur. Elle est exécutée automatiquement lorsqu’un objet est créé avec `new`.

Dans `index.php`, la création de la classe principale ressemble à ceci :

```php
$app = new App(__DIR__);
```

Cette instruction provoque automatiquement l’appel du constructeur de `App` :

```php
public function __construct(string $projectRoot)
{
    $this->projectRoot = $projectRoot;
}
```

Le déroulement est le suivant :

1. `new App(__DIR__)` demande à PHP de créer un nouvel objet `App` ;
2. PHP appelle automatiquement `App::__construct()` ;
3. la valeur de `__DIR__`, qui représente le dossier de `index.php`, est reçue dans le paramètre `$projectRoot` ;
4. le constructeur enregistre ce chemin dans l’objet ;
5. les autres méthodes de `App` peuvent ensuite le réutiliser.

Une classe peut fonctionner sans constructeur. Il devient utile lorsqu’un objet doit recevoir ou préparer des informations dès sa création.

Un constructeur ne retourne pas lui-même l’objet avec `return`. C’est l’instruction `new App(...)` qui crée l’objet et le place ici dans la variable `$app`.

### À quoi sert $this ?

Dans une méthode non statique, `$this` désigne l’objet actuellement utilisé. On peut le lire comme « cet objet ».

La flèche `->` permet ensuite d’accéder à un attribut ou à une méthode de cet objet :

```php
$this->projectRoot;   // Accède à un attribut de l’objet.
$this->loadRoutes();  // Appelle une méthode de l’objet.
```

Dans le constructeur :

```php
$this->projectRoot = $projectRoot;
```

les deux écritures ont des rôles différents :

- `$projectRoot` est le paramètre temporaire reçu par le constructeur ;
- `$this->projectRoot` est l’attribut conservé dans l’objet `App`.

Cette ligne signifie donc :

> « Enregistre la valeur reçue dans l’attribut `projectRoot` de cet objet. »

### Explication du chemin de configuration

La méthode qui prépare la base de données contient cette instruction :

```php
$databaseConfigPath = $this->projectRoot . '/private/database-secret.php';
```

Elle se décompose ainsi :

- `$this->projectRoot` récupère la racine du projet conservée dans l’objet ;
- le point `.` concatène, donc assemble, deux chaînes de caractères ;
- `'/private/database-secret.php'` ajoute le chemin du fichier privé ;
- le résultat complet est placé dans la variable locale `$databaseConfigPath`.

Si `$this->projectRoot` contient `C:\laragon\www\quiditmieux`, le résultat est approximativement :

```text
C:\laragon\www\quiditmieux/private/database-secret.php
```

`$databaseConfigPath` est une variable locale : elle sert uniquement pendant l’exécution de la méthode. À l’inverse, `$this->projectRoot` est un attribut : sa valeur reste disponible dans les différentes méthodes tant que l’objet `App` existe.

La règle simple à retenir est :

> **`__construct()` prépare un objet au moment de sa création ; `$this` permet ensuite d’accéder aux attributs et aux méthodes de cet objet.**

## Comprendre le symbole ! et la fonction is_file()

Dans cette condition, le symbole utilisé est un point d’exclamation `!`, et non un point d’interrogation `?` :

```php
if (!is_file($databaseConfigPath)) {
    echo 'La configuration de la base de données est indisponible.';
    return null;
}
```

La fonction native `is_file()` vérifie si le chemin reçu correspond à un fichier existant :

- elle retourne `true` si le fichier existe et si le chemin désigne bien un fichier ;
- elle retourne `false` si le fichier n’existe pas ou si le chemin désigne autre chose, par exemple un dossier.

Le symbole `!` signifie « non ». Il inverse le résultat booléen placé après lui :

| Résultat de `is_file()` | Résultat après `!` | Signification |
|---|---|---|
| `true` | `false` | Le fichier existe : le bloc `if` n’est pas exécuté. |
| `false` | `true` | Le fichier est absent ou invalide : le bloc `if` est exécuté. |

La condition se lit donc ainsi :

> « Si le chemin ne correspond pas à un fichier existant, afficher le message puis arrêter cette méthode. »

`return null;` termine immédiatement la méthode et indique qu’aucun objet `Database` utilisable ne peut être retourné.

Sans le symbole `!`, la condition aurait le sens opposé :

```php
if (is_file($databaseConfigPath)) {
    // Ce bloc est exécuté lorsque le fichier existe.
}
```

La règle simple à retenir est :

> **`!` signifie « non » et inverse une condition.**

Exemples du même principe :

```php
if (!is_array($databaseConfig)) {
    // La configuration n’est pas un tableau.
}

if (!$database->isConnected()) {
    // La base de données n’est pas connectée.
}
```

## POO et héritage

La programmation orientée objet consiste à regrouper les données et les traitements qui ont le même rôle dans des classes.

- `Controller` est la classe parent des contrôleurs. Elle fournit le rendu d’une vue, la redirection, les messages flash et les réponses JSON.
- `Model` est la classe parent des modèles SQL. Elle centralise PDO et des opérations communes de création, modification et suppression.
- `ListingModel`, `BidModel`, `PhotoModel` et `UserModel` sont des modèles enfants spécialisés dans leurs propres données.

Une classe abstraite est une classe de base qui ne peut pas être utilisée directement avec `new`. Elle sert à partager des attributs et des méthodes avec des classes enfants plus spécialisées. Dans ce projet, `Model` et `Controller` sont abstraites : on crée un `ListingModel` ou un `ListingController`, mais jamais directement un objet `Model` ou `Controller`.

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

Les prix sont volontairement gérés en euros entiers. Les contrôleurs vérifient que la saisie contient seulement des chiffres, puis PHP les convertit avec `(int)`. La base de données stocke également ces montants dans des colonnes entières.

Pour l’affichage, la méthode `formatEuros()` de `Controller` utilise `number_format()`, par exemple pour obtenir `1 250 €`. Ce choix évite les imprécisions liées aux nombres décimaux de type `float`. Les contrôles du montant minimal sont faits côté serveur avant l’enregistrement de l’enchère.
## Dates et Europe/Paris

L’application étant destinée à la France, le fuseau `Europe/Paris` est défini une seule fois dans `index.php`. Les dates sont donc saisies, enregistrées, comparées et affichées dans ce même fuseau.

`DateTimeImmutable` reste utilisé pour valider et manipuler les dates sans modifier l’objet d’origine. Le compte à rebours reçoit une date ISO avec le décalage Paris : son calcul reste donc exact, y compris lors du passage à l’heure d’été ou d’hiver.
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

## `DateTimeImmutable`

`DateTimeImmutable` est une classe native de PHP : elle est fournie par le langage et ne demande ni fichier à créer, ni chargement par Composer.

Elle permet de représenter une date et une heure, par exemple l’instant actuel avec `new DateTimeImmutable()` ou une date issue de la base avec `DateTimeImmutable::createFromFormat()`.

Elle est dite « immutable » car un objet date déjà créé ne change pas. Par exemple, `modify('+1 day')` renvoie un nouvel objet pour le lendemain et conserve l’objet initial. Cela évite de modifier une date par erreur pendant un traitement.

Dans ce projet, elle sert à valider les dates de fin, comparer une échéance avec l’heure actuelle et préparer l’affichage des dates. Le fuseau `Europe/Paris` est défini une seule fois dans `index.php`.
Ce réglage est utile car PHP utiliserait sinon le fuseau défini dans la configuration du serveur. Il garantit que les dates de fin, les contrôles des ventes terminées, les enchères et les affichages utilisent tous l’heure française. C’est une ligne simple placée au point d’entrée afin d’avoir une seule référence de date pour toute l’application.