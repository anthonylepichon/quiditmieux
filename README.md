# QUIDITMIEUX

QUIDITMIEUX est une application Web d’enchères entre particuliers réalisée en PHP dans le cadre de la certification Développeur Web Full Stack, niveau 5 (Bac+2). Le projet met en œuvre une architecture MVC simple, la programmation orientée objet, l’héritage, PDO, une API externe et des interactions JavaScript.

Le dépôt GitHub du projet est disponible à l’adresse suivante : [anthonylepichon/quiditmieux](https://github.com/anthonylepichon/quiditmieux).

## Fonctionnalités

- consulter et rechercher des annonces sans compte ;
- filtrer les résultats par texte, catégorie, état, prix et état de la vente ;
- créer un compte et se connecter avec un pseudo ou une adresse électronique ;
- consulter et accepter la politique de confidentialité lors de l’inscription ;
- modifier les informations de son compte ;
- publier, modifier et supprimer une annonce selon les règles métier ;
- ajouter jusqu’à trois photographies à une annonce ;
- suivre ou ne plus suivre une vente ;
- enchérir sur l’annonce d’un autre utilisateur ;
- consulter l’historique autorisé d’une vente ;
- suivre ses ventes, participations et enchères remportées depuis le tableau de bord.

## Technologies utilisées

- PHP 8.3 ou version ultérieure ;
- MySQL ou MariaDB ;
- PDO et requêtes préparées ;
- Composer pour l’autoload PSR-4 ;
- HTML5, SCSS et CSS ;
- JavaScript sans framework ;
- API publique des catégories QUIDITMIEUX.

## Architecture

Le point d’entrée unique [index.php](index.php) démarre l’application. La classe App initialise la session et la connexion PDO, puis transmet la demande au Router.

    src/
    ├── config/          Configuration des routes
    ├── controllers/     Coordination des demandes HTTP
    ├── core/            Socle de l’application
    └── models/          Accès aux données et règles métier

    templates/
    ├── layout/          Structure HTML, header et footer communs
    ├── pages/           Pages complètes
    └── partials/        Fragments réutilisables

    resources/scss/      Sources SCSS
    public/assets/       CSS, JavaScript, polices et illustrations
    public/uploads/      Photographies téléversées
    tests/               Tests unitaires et d’intégration
    documents/           Cahier des charges, conceptualisation et supports

Les contrôleurs héritent de Controller, qui fournit les opérations communes de rendu, de redirection, de validation et de réponse JSON. Les modèles SQL héritent de Model. CategoryModel reste indépendant, car les catégories viennent d’une API externe et non d’une table MySQL.

## Installation locale

### Prérequis

- PHP 8.3 avec les extensions PDO MySQL, cURL, mbstring et fileinfo ;
- MySQL ou MariaDB ;
- Composer ;
- un serveur Web local tel que Laragon ;
- Sass uniquement si les styles SCSS doivent être recompilés.

### Étapes

1. Placer le projet dans le dossier Web local, par exemple C:\laragon\www\quiditmieux.
2. Installer l’autoload Composer :

       composer install

3. Créer la base de données conformément au MPD présenté dans la section « Conceptualisation ».
4. Copier le fichier de configuration d’exemple :

       Copy-Item private/database.example.php private/database-secret.php

5. Renseigner dans private/database-secret.php l’hôte, le port, le nom de la base, l’utilisateur, le mot de passe et l’encodage.
6. Vérifier que public/uploads/annonces existe et que PHP possède le droit d’y écrire.
7. Ouvrir l’application, par exemple à l’adresse http://localhost/quiditmieux/.

Le fichier private/database-secret.php, les photographies téléversées et le cache local ne sont pas versionnés.

## Création de la base de données

Le script suivant crée la base et les cinq tables conformément au MPD. Il peut être exécuté depuis phpMyAdmin ou depuis un client MySQL. Si un autre nom de base est utilisé, il faut modifier les deux premières lignes et reporter ce nom dans private/database-secret.php.

```sql
CREATE DATABASE IF NOT EXISTS quiditmieux
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE quiditmieux;

CREATE TABLE IF NOT EXISTS UTILISATEUR (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pseudo VARCHAR(30) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT uq_utilisateur_pseudo UNIQUE (pseudo),
    CONSTRAINT uq_utilisateur_email UNIQUE (email)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ANNONCE (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id INT UNSIGNED NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    etat_objet VARCHAR(20) NOT NULL,
    prix_depart INT UNSIGNED NOT NULL,
    date_heure_fin DATETIME NOT NULL,
    categorie_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_annonce_utilisateur (utilisateur_id),
    INDEX idx_annonce_date_heure_fin (date_heure_fin),
    INDEX idx_annonce_categorie_date_fin (categorie_id, date_heure_fin),
    INDEX idx_annonce_etat_date_fin (etat_objet, date_heure_fin),
    CONSTRAINT fk_annonce_utilisateur
        FOREIGN KEY (utilisateur_id)
        REFERENCES UTILISATEUR (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    CONSTRAINT chk_annonce_prix_depart
        CHECK (prix_depart > 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS PHOTOGRAPHIE (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    annonce_id INT UNSIGNED NOT NULL,
    ref_fichier VARCHAR(255) NOT NULL,
    ordre TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT uq_photographie_annonce_ordre
        UNIQUE (annonce_id, ordre),
    CONSTRAINT fk_photographie_annonce
        FOREIGN KEY (annonce_id)
        REFERENCES ANNONCE (id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,
    CONSTRAINT chk_photographie_ordre
        CHECK (ordre BETWEEN 1 AND 3)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ENCHERE (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id INT UNSIGNED NOT NULL,
    annonce_id INT UNSIGNED NOT NULL,
    montant INT UNSIGNED NOT NULL,
    date_heure_enchere DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_enchere_utilisateur_annonce (utilisateur_id, annonce_id),
    INDEX idx_enchere_classement (
        annonce_id,
        montant DESC,
        date_heure_enchere,
        id
    ),
    CONSTRAINT fk_enchere_utilisateur
        FOREIGN KEY (utilisateur_id)
        REFERENCES UTILISATEUR (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    CONSTRAINT fk_enchere_annonce
        FOREIGN KEY (annonce_id)
        REFERENCES ANNONCE (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
    CONSTRAINT chk_enchere_montant
        CHECK (montant > 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ASSOC_UTILISATEUR_ANNONCE (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilisateur_id INT UNSIGNED NOT NULL,
    annonce_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT uq_assoc_utilisateur_annonce
        UNIQUE (utilisateur_id, annonce_id),
    INDEX idx_assoc_utilisateur_annonce_annonce (annonce_id),
    CONSTRAINT fk_assoc_utilisateur_annonce_utilisateur
        FOREIGN KEY (utilisateur_id)
        REFERENCES UTILISATEUR (id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,
    CONSTRAINT fk_assoc_utilisateur_annonce_annonce
        FOREIGN KEY (annonce_id)
        REFERENCES ANNONCE (id)
        ON DELETE CASCADE
        ON UPDATE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

## Styles

Les styles sont écrits dans resources/scss puis compilés vers public/assets/css/main.css :

    npx sass resources/scss/main.scss public/assets/css/main.css --no-source-map

Le fichier CSS compilé est conservé dans le dépôt afin que l’application fonctionne sans compilation supplémentaire.

## Tests automatisés

Le lanceur fourni exécute les tests unitaires puis les tests d’intégration :

    php tests/Lancer.php

Les tests d’intégration utilisent la configuration locale de la base. Ils doivent donc être lancés avec une base disponible et cohérente avec le MPD.

## Base de données

Le projet utilise les entités suivantes :

- UTILISATEUR pour les comptes ;
- ANNONCE pour les ventes ;
- PHOTOGRAPHIE pour les images associées ;
- ENCHERE pour les offres déposées ;
- ASSOC_UTILISATEUR_ANNONCE pour le suivi des annonces.

La catégorie est identifiée dans ANNONCE par categorie_id. Son libellé provient de l’API externe et peut être servi depuis un cache local afin de limiter les appels et de conserver un fonctionnement raisonnable pendant une panne temporaire.

## Sécurité et confidentialité

Les principales protections mises en œuvre sont :

- mots de passe hachés avec les fonctions natives de PHP ;
- requêtes SQL préparées ;
- validation des données côté serveur ;
- contrôle de l’authentification et de la propriété des ressources ;
- jetons CSRF sur les opérations qui modifient les données ;
- échappement des valeurs affichées ;
- contrôle du type, de la taille et du nom des photographies ;
- champ anti-robot invisible lors de l’inscription ;
- politique de confidentialité accessible depuis le formulaire et le footer.

La case de confidentialité n’est pas précochée. Le bouton d’inscription reste désactivé tant qu’elle n’est pas acceptée, et le serveur répète obligatoirement ce contrôle.

## Conceptualisation

Les livrables validés qui ont guidé le développement sont conservés dans documents/conceptualisation.

### Schéma ergonomique

[Ouvrir le schéma ergonomique](<documents/conceptualisation/Schéma Ergonomique/Schéma ergonomique.png>)

[![Aperçu du schéma ergonomique](<documents/conceptualisation/Schéma Ergonomique/Schéma ergonomique.png>)](<documents/conceptualisation/Schéma Ergonomique/Schéma ergonomique.png>)

### Spécifications

[Télécharger le tableau des spécifications](<documents/conceptualisation/Spécifications/Specifications-QDM.xlsx>)

### Modèle conceptuel de données

[Ouvrir le MCD](<documents/conceptualisation/Modèles de données/MCD.jpg>)

[![Aperçu du MCD](<documents/conceptualisation/Modèles de données/MCD.jpg>)](<documents/conceptualisation/Modèles de données/MCD.jpg>)

### Modèle physique de données

[Ouvrir le MPD](<documents/conceptualisation/Modèles de données/MPD.png>)

[![Aperçu du MPD](<documents/conceptualisation/Modèles de données/MPD.png>)](<documents/conceptualisation/Modèles de données/MPD.png>)

## Limites du périmètre

Le projet ne prend pas en charge le paiement, la livraison, la messagerie, la modération, la récupération d’un mot de passe oublié ni la suppression autonome d’un compte. La politique de confidentialité précise que les demandes relatives aux données sont traitées manuellement dans cette version pédagogique.

## Auteur

Projet conçu et développé par Anthony Lepichon.
