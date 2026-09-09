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

- PHP 8.1 ou version ultérieure ;
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
    ├── models/          Accès aux données et règles métier
    └── services/        Services techniques, dont le stockage des photographies

    templates/
    ├── layout/          Structure HTML, header et footer communs
    ├── pages/           Pages complètes
    └── fragments/       Fragments réutilisables

    resources/scss/      Sources SCSS
    public/assets/       CSS, JavaScript, polices et illustrations
    public/uploads/      Photographies téléversées
    tests/               Tests unitaires et d’intégration
    documents/           Cahier des charges, conceptualisation et supports

Les contrôleurs héritent de Controller, qui fournit les opérations communes de rendu, de redirection, de validation et de réponse JSON. Les modèles SQL héritent de Model. CategoryModel reste indépendant, car les catégories viennent d’une API externe et non d’une table MySQL. Le service PhotoStorage centralise le stockage et la suppression des fichiers photographiques.

## Installation locale

### Prérequis

- PHP 8.1 avec les extensions PDO MySQL, cURL, mbstring et fileinfo ;
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

## Données de démonstration

Le script ci-dessous correspond au jeu de données local utilisé pour la démonstration. Il doit être exécuté après la création des tables et sur des tables vides, car les identifiants sont conservés pour respecter toutes les relations.

Les neuf comptes utilisent le même mot de passe de démonstration : DemoQdm1!

Les adresses utilisent le domaine réservé example.test et ne correspondent pas à des boîtes électroniques réelles. Le neuvième compte local a été anonymisé avant publication afin de ne pas placer de donnée personnelle dans le dépôt.

Les noms enregistrés dans PHOTOGRAPHIE correspondent aux images de démonstration. L’archive [photos-objets.zip](public/uploads/photos-objets.zip) doit être extraite dans public/uploads/annonces pour que les photographies soient visibles dans l’application.

```sql
START TRANSACTION;

INSERT INTO UTILISATEUR (id, pseudo, email, password_hash) VALUES
    (1, 'AliceDemo', 'alice.demo@example.test', '$2y$10$XMU1YsfZQYFiJ2bVtiU5LODm6/o6JobyKC172sdDUshQM37898NrC'),
    (2, 'BilalDemo', 'bilal.demo@example.test', '$2y$10$XMU1YsfZQYFiJ2bVtiU5LODm6/o6JobyKC172sdDUshQM37898NrC'),
    (3, 'ChloeDemo', 'chloe.demo@example.test', '$2y$10$XMU1YsfZQYFiJ2bVtiU5LODm6/o6JobyKC172sdDUshQM37898NrC'),
    (4, 'DavidDemo', 'david.demo@example.test', '$2y$10$XMU1YsfZQYFiJ2bVtiU5LODm6/o6JobyKC172sdDUshQM37898NrC'),
    (5, 'EmmaDemo', 'emma.demo@example.test', '$2y$10$XMU1YsfZQYFiJ2bVtiU5LODm6/o6JobyKC172sdDUshQM37898NrC'),
    (6, 'FaridDemo', 'farid.demo@example.test', '$2y$10$XMU1YsfZQYFiJ2bVtiU5LODm6/o6JobyKC172sdDUshQM37898NrC'),
    (7, 'GabrielDemo', 'gabriel.demo@example.test', '$2y$10$XMU1YsfZQYFiJ2bVtiU5LODm6/o6JobyKC172sdDUshQM37898NrC'),
    (8, 'HanaDemo', 'hana.demo@example.test', '$2y$10$XMU1YsfZQYFiJ2bVtiU5LODm6/o6JobyKC172sdDUshQM37898NrC'),
    (9, 'IrisDemo', 'iris.demo@example.test', '$2y$10$XMU1YsfZQYFiJ2bVtiU5LODm6/o6JobyKC172sdDUshQM37898NrC');

INSERT INTO ANNONCE (id, utilisateur_id, titre, description, etat_objet, prix_depart, date_heure_fin, categorie_id) VALUES
    (1, 1, 'Vélo de ville restauré', 'Vélo confortable révisé et prêt à rouler.', 'très bon état', 120, '2026-09-11 16:55:10', 17),
    (2, 1, 'Console rétro avec deux manettes', 'Console fonctionnelle fournie avec ses câbles et deux manettes.', 'bon état', 40, '2026-09-04 16:55:10', 14),
    (3, 1, 'Vase ancien décoratif', 'Vase ancien en bon état général avec quelques traces du temps.', 'bon état', 60, '2026-08-27 16:55:10', 20),
    (4, 2, 'Chaussures de randonnée', 'Chaussures peu portées, propres et sans défaut important.', 'très bon état', 35, '2026-08-26 16:55:10', 9),
    (5, 4, 'Montre mécanique vintage', 'Montre révisée avec bracelet en cuir neuf.', 'très bon état', 95, '2026-08-31 16:55:10', 10),
    (6, 5, 'Jeu de société familial', 'Jeu complet pour quatre à six joueurs.', 'bon état', 25, '2026-09-02 16:55:10', 15),
    (7, 6, 'Casque de moto intégral', 'Casque homologué avec visière claire et housse.', 'bon état', 35, '2026-09-06 16:55:10', 18),
    (8, 7, 'Tableau paysager encadré', 'Peinture décorative signée avec cadre en bois.', 'très bon état', 80, '2026-09-09 16:55:10', 20),
    (9, 8, 'Vélo de route aluminium', 'Vélo léger entretenu récemment.', 'bon état', 200, '2026-09-03 16:55:10', 17),
    (10, 2, 'Édition collector jeu vidéo', 'Boîte complète avec livret et objets de collection.', 'très bon état', 50, '2026-09-01 16:55:10', 14),
    (11, 3, 'Baskets urbaines neuves', 'Paire jamais portée conservée dans sa boîte.', 'neuf', 55, '2026-09-07 16:55:10', 9),
    (12, 4, 'Bracelet en argent', 'Bracelet poinçonné livré dans son écrin.', 'très bon état', 75, '2026-09-05 16:55:10', 10),
    (13, 5, 'Jeu de construction complet', 'Ensemble trié avec notice et boîte.', 'bon état', 45, '2026-09-08 16:55:10', 15),
    (14, 6, 'Barres de toit universelles', 'Barres verrouillables avec deux clés.', 'état correct', 60, '2026-09-10 16:55:10', 18),
    (15, 7, 'Sculpture décorative en bronze', 'Petite sculpture sur socle en bois.', 'très bon état', 110, '2026-09-12 16:55:10', 20),
    (16, 8, 'BMX freestyle renforcé', 'BMX solide avec pneus et poignées récents.', 'bon état', 140, '2026-08-30 16:55:10', 17),
    (17, 4, 'Montre automatique classique', 'Montre automatique avec boîte de rangement.', 'bon état', 120, '2026-08-27 16:55:10', 10),
    (18, 5, 'Peluche de collection', 'Peluche propre avec son étiquette d origine.', 'très bon état', 20, '2026-08-25 16:55:10', 15),
    (19, 6, 'Coffre de toit compact', 'Coffre avec fixations et double des clés.', 'bon état', 90, '2026-08-26 16:55:10', 18),
    (20, 7, 'Gravure numérotée', 'Gravure encadrée et numérotée en série limitée.', 'très bon état', 70, '2026-08-24 16:55:10', 20);

INSERT INTO PHOTOGRAPHIE (id, annonce_id, ref_fichier, ordre) VALUES
    (1, 1, 'demo-velo-ville-1.jpg', 1),
    (2, 1, 'demo-velo-ville-2.jpg', 2),
    (3, 2, 'demo-console-retro-1.jpg', 1),
    (4, 3, 'demo-vase-ancien-1.jpg', 1),
    (5, 5, 'demo-montre-vintage-1.jpg', 1),
    (6, 6, 'demo-jeu-familial-1.jpg', 1),
    (7, 6, 'demo-jeu-familial-2.jpg', 2),
    (8, 7, 'demo-casque-moto-1.jpg', 1),
    (9, 8, 'demo-tableau-paysage-1.jpg', 1),
    (10, 8, 'demo-tableau-paysage-2.jpg', 2),
    (11, 8, 'demo-tableau-paysage-3.jpg', 3),
    (12, 9, 'demo-velo-route-1.jpg', 1),
    (13, 9, 'demo-velo-route-2.jpg', 2),
    (14, 10, 'demo-jeu-collector-1.jpg', 1),
    (15, 12, 'demo-bracelet-argent-1.jpg', 1),
    (16, 12, 'demo-bracelet-argent-2.jpg', 2),
    (17, 13, 'demo-jeu-construction-1.jpg', 1),
    (18, 14, 'demo-barres-toit-1.jpg', 1),
    (19, 14, 'demo-barres-toit-2.jpg', 2),
    (20, 15, 'demo-sculpture-bronze-1.jpg', 1),
    (21, 15, 'demo-sculpture-bronze-2.jpg', 2),
    (22, 15, 'demo-sculpture-bronze-3.jpg', 3),
    (23, 16, 'demo-bmx-1.jpg', 1),
    (24, 16, 'demo-bmx-2.jpg', 2),
    (25, 17, 'demo-montre-automatique-1.jpg', 1);

INSERT INTO ENCHERE (id, utilisateur_id, annonce_id, montant, date_heure_enchere) VALUES
    (1, 2, 2, 55, '2026-08-26 16:55:10'),
    (2, 3, 2, 70, '2026-08-27 16:55:10'),
    (3, 3, 3, 80, '2026-08-25 16:55:10'),
    (4, 2, 3, 100, '2026-08-26 16:55:10'),
    (5, 1, 5, 110, '2026-08-25 16:55:10'),
    (6, 2, 5, 125, '2026-08-26 16:55:10'),
    (7, 5, 5, 140, '2026-08-27 16:55:10'),
    (8, 6, 6, 30, '2026-08-26 16:55:10'),
    (9, 8, 6, 42, '2026-08-27 16:55:10'),
    (10, 1, 7, 45, '2026-08-24 16:55:10'),
    (11, 7, 7, 60, '2026-08-26 16:55:10'),
    (12, 3, 7, 75, '2026-08-27 16:55:10'),
    (13, 2, 8, 95, '2026-08-26 16:55:10'),
    (14, 5, 8, 120, '2026-08-27 16:55:10'),
    (15, 6, 9, 220, '2026-08-24 16:55:10'),
    (16, 4, 9, 250, '2026-08-26 16:55:10'),
    (17, 1, 9, 275, '2026-08-27 16:55:10'),
    (18, 3, 10, 65, '2026-08-25 16:55:10'),
    (19, 7, 10, 80, '2026-08-27 16:55:10'),
    (20, 5, 12, 90, '2026-08-25 16:55:10'),
    (21, 8, 12, 105, '2026-08-26 16:55:10'),
    (22, 6, 12, 125, '2026-08-27 16:55:10'),
    (23, 1, 14, 70, '2026-08-26 16:55:10'),
    (24, 2, 14, 85, '2026-08-27 16:55:10'),
    (25, 3, 16, 160, '2026-08-26 16:55:10'),
    (26, 4, 16, 190, '2026-08-27 16:55:10'),
    (27, 5, 17, 150, '2026-08-24 16:55:10'),
    (28, 1, 17, 175, '2026-08-26 16:55:10'),
    (29, 7, 19, 100, '2026-08-23 16:55:10'),
    (30, 8, 19, 130, '2026-08-25 16:55:10'),
    (31, 9, 13, 50, '2026-08-31 22:09:12');

INSERT INTO ASSOC_UTILISATEUR_ANNONCE (id, utilisateur_id, annonce_id) VALUES
    (1, 3, 1),
    (2, 2, 2),
    (3, 4, 1),
    (4, 5, 1),
    (5, 6, 2),
    (6, 7, 2),
    (7, 8, 2),
    (8, 1, 5),
    (9, 2, 5),
    (10, 3, 6),
    (11, 4, 6),
    (12, 6, 8),
    (13, 8, 8),
    (14, 1, 9),
    (15, 5, 10),
    (16, 7, 12),
    (17, 3, 14),
    (18, 2, 16),
    (25, 9, 6);

COMMIT;
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

## Diagrammes de classes UML

Les diagrammes suivants présentent l’architecture de l’application sous plusieurs angles afin de conserver des schémas lisibles. Chaque aperçu est cliquable pour ouvrir l’image en taille réelle.

### 1. Vue générale

[![Diagramme UML — Vue générale](<documents/readme/01 - UML - Vue générale - QDM.png>)](<documents/readme/01 - UML - Vue générale - QDM.png>)

### 2. Socle technique

[![Diagramme UML — Socle technique](<documents/readme/02 - UML - Socle technique - QDM.png>)](<documents/readme/02 - UML - Socle technique - QDM.png>)

### 3. Comptes et authentification

[![Diagramme UML — Comptes et authentification](<documents/readme/03 - UML - Compte et authentification - QDM.png>)](<documents/readme/03 - UML - Compte et authentification - QDM.png>)

### 4. Annonces et photographies

[![Diagramme UML — Annonces et photographies](<documents/readme/04 - UML - Annonces et photographie - QDM.png>)](<documents/readme/04 - UML - Annonces et photographie - QDM.png>)

### 5. Suivis et enchères

[![Diagramme UML — Suivis et enchères](<documents/readme/05 - UML - Suivis et enchères - QDM.png>)](<documents/readme/05 - UML - Suivis et enchères - QDM.png>)

### 6. Templates et vues

[![Diagramme UML — Templates et vues](<documents/readme/06 - Templates - QDM.png>)](<documents/readme/06 - Templates - QDM.png>)

## Limites du périmètre

Le projet ne prend pas en charge le paiement, la livraison, la messagerie, la modération, la récupération d’un mot de passe oublié ni la suppression autonome d’un compte. La politique de confidentialité précise que les demandes relatives aux données sont traitées manuellement dans cette version pédagogique.

## Auteur

Projet conçu et développé par Anthony Lepichon.
