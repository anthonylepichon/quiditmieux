# Requêtes SQL — QUIDITMIEUX

## 1. Statut du document

Cette proposition traduit le MPD avancé validé en requêtes compatibles avec MySQL et MariaDB.

Elle doit être relue et validée avant sa première exécution dans phpMyAdmin.

La base `quiditmieux` est déjà créée avec le jeu de caractères `utf8mb4` et la collation `utf8mb4_unicode_ci`.

## 2. Structure proposée

La structure contient cinq tables :

- `UTILISATEUR` : comptes et authentification ;
- `ANNONCE` : objets proposés aux enchères ;
- `PHOTOGRAPHIE` : zéro à trois photographies ordonnées par annonce ;
- `ASSOC_UTILISATEUR_ANNONCE` : association entre un utilisateur et une annonce suivie ;
- `ENCHERE` : offres définitives enregistrées pour les annonces.

Les tables utilisent le moteur `InnoDB` afin de prendre en charge les clés étrangères et les transactions.

## 3. Requêtes de création

```sql
USE `quiditmieux`;

CREATE TABLE IF NOT EXISTS `UTILISATEUR` (
    `utilisateur_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pseudo` VARCHAR(30) NOT NULL,
    `email` VARCHAR(254) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,

    CONSTRAINT `pk_utilisateur`
        PRIMARY KEY (`utilisateur_id`),
    CONSTRAINT `uq_utilisateur_pseudo`
        UNIQUE (`pseudo`),
    CONSTRAINT `uq_utilisateur_email`
        UNIQUE (`email`)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ANNONCE` (
    `annonce_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `utilisateur_id` INT UNSIGNED NOT NULL,
    `titre` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `etat_objet` VARCHAR(20) NOT NULL,
    `prix_depart` DECIMAL(10,2) NOT NULL,
    `date_heure_fin` DATETIME NOT NULL,
    `categorie_id_externe` INT UNSIGNED NOT NULL,
    `categorie_libelle` VARCHAR(255) NOT NULL,

    CONSTRAINT `pk_annonce`
        PRIMARY KEY (`annonce_id`),
    CONSTRAINT `chk_annonce_prix_depart`
        CHECK (`prix_depart` > 0),
    CONSTRAINT `fk_annonce_utilisateur`
        FOREIGN KEY (`utilisateur_id`)
        REFERENCES `UTILISATEUR` (`utilisateur_id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    INDEX `idx_annonce_utilisateur` (`utilisateur_id`),
    INDEX `idx_annonce_date_heure_fin` (`date_heure_fin`),
    INDEX `idx_annonce_categorie_externe` (`categorie_id_externe`)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PHOTOGRAPHIE` (
    `photographie_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `annonce_id` INT UNSIGNED NOT NULL,
    `ref_fichier` VARCHAR(255) NOT NULL,
    `ordre` TINYINT UNSIGNED NOT NULL,

    CONSTRAINT `pk_photographie`
        PRIMARY KEY (`photographie_id`),
    CONSTRAINT `uq_photographie_annonce_ordre`
        UNIQUE (`annonce_id`, `ordre`),
    CONSTRAINT `chk_photographie_ordre`
        CHECK (`ordre` BETWEEN 1 AND 3),
    CONSTRAINT `fk_photographie_annonce`
        FOREIGN KEY (`annonce_id`)
        REFERENCES `ANNONCE` (`annonce_id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ASSOC_UTILISATEUR_ANNONCE` (
    `assoc_utilisateur_annonce_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `utilisateur_id` INT UNSIGNED NOT NULL,
    `annonce_id` INT UNSIGNED NOT NULL,

    CONSTRAINT `pk_assoc_utilisateur_annonce`
        PRIMARY KEY (`assoc_utilisateur_annonce_id`),
    CONSTRAINT `uq_assoc_utilisateur_annonce`
        UNIQUE (`utilisateur_id`, `annonce_id`),
    CONSTRAINT `fk_assoc_utilisateur_annonce_utilisateur`
        FOREIGN KEY (`utilisateur_id`)
        REFERENCES `UTILISATEUR` (`utilisateur_id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_assoc_utilisateur_annonce_annonce`
        FOREIGN KEY (`annonce_id`)
        REFERENCES `ANNONCE` (`annonce_id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,

    INDEX `idx_assoc_utilisateur_annonce_annonce` (`annonce_id`)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ENCHERE` (
    `enchere_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `utilisateur_id` INT UNSIGNED NOT NULL,
    `annonce_id` INT UNSIGNED NOT NULL,
    `montant` DECIMAL(10,2) NOT NULL,
    `date_heure_enchere` DATETIME NOT NULL,

    CONSTRAINT `pk_enchere`
        PRIMARY KEY (`enchere_id`),
    CONSTRAINT `chk_enchere_montant`
        CHECK (`montant` > 0),
    CONSTRAINT `fk_enchere_utilisateur`
        FOREIGN KEY (`utilisateur_id`)
        REFERENCES `UTILISATEUR` (`utilisateur_id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_enchere_annonce`
        FOREIGN KEY (`annonce_id`)
        REFERENCES `ANNONCE` (`annonce_id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    INDEX `idx_enchere_utilisateur_annonce` (`utilisateur_id`, `annonce_id`),
    INDEX `idx_enchere_annonce_montant` (`annonce_id`, `montant`)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
```

## 4. Règles garanties par la base

La structure SQL garantit notamment :

- l'unicité du pseudo ;
- l'unicité de l'adresse électronique ;
- un prix de départ strictement positif ;
- un montant d'enchère strictement positif ;
- un ordre de photographie compris entre 1 et 3 ;
- un seul ordre identique par annonce ;
- un seul suivi pour un même couple utilisateur et annonce ;
- l'existence des utilisateurs et annonces référencés ;
- la suppression automatique des photographies et suivis liés lorsque la suppression de l'annonce est autorisée ;
- le refus de supprimer une annonce possédant une enchère ;
- le refus de supprimer un utilisateur encore référencé comme vendeur ou enchérisseur.

## 5. Règles contrôlées par l'application

Les règles suivantes dépendent de plusieurs données, de l'utilisateur connecté, de l'heure courante ou d'opérations concurrentes. Elles seront donc contrôlées par le serveur, avec une transaction lorsqu'elle est nécessaire :

- pseudo et adresse électronique normalisés avant l'enregistrement ;
- valeur autorisée pour l'état de l'objet ;
- date de fin future lors de la création ;
- dates saisies en `Europe/Paris` puis enregistrées en UTC ;
- catégorie validée auprès de l'API externe ;
- maximum de trois photographies par annonce ;
- références de fichiers générées par l'application ;
- interdiction de suivre sa propre annonce ;
- interdiction d'enchérir sur sa propre annonce ;
- interdiction d'enchérir à l'échéance ou après celle-ci ;
- montant supérieur d'au moins `0,01 €` au prix courant ;
- sérialisation des enchères concurrentes ;
- blocage transactionnel d'une modification ou suppression après la première enchère.

Ces contrôles applicatifs ne remplacent pas les contraintes d'intégrité déjà présentes dans la base.

## 6. Données de démonstration

### 6.1 Prérequis

Ces insertions sont prévues pour des tables vides et ne doivent être exécutées qu'une seule fois.

Les huit comptes utilisent le mot de passe de démonstration suivant :

```text
Demo-QDM-2026!
```

La base contient uniquement l'empreinte générée avec `password_hash()` par PHP 8.3. Le mot de passe en clair n'est jamais enregistré dans la table `UTILISATEUR`.

Les vingt-cinq fichiers JPEG correspondants sont disponibles dans `public/assets/images/photos-objets`. Leur nom correspond exactement à la valeur enregistrée dans la colonne `ref_fichier`. L'archive `public/assets/images/photos-objets.zip` permet de récupérer l'ensemble des photographies en une seule fois.

### 6.2 Insertions

```sql
USE `quiditmieux`;

START TRANSACTION;

INSERT INTO `UTILISATEUR` (
    `utilisateur_id`,
    `pseudo`,
    `email`,
    `password_hash`
) VALUES
    (
        1,
        'AliceDemo',
        'alice.demo@example.test',
        '$2y$10$ItyyeLPWoggbwSAf/y93v.W4ipyjGItiEbqEAcbyxfiByUFFWaEDC'
    ),
    (
        2,
        'BilalDemo',
        'bilal.demo@example.test',
        '$2y$10$ItyyeLPWoggbwSAf/y93v.W4ipyjGItiEbqEAcbyxfiByUFFWaEDC'
    ),
    (
        3,
        'ChloeDemo',
        'chloe.demo@example.test',
        '$2y$10$ItyyeLPWoggbwSAf/y93v.W4ipyjGItiEbqEAcbyxfiByUFFWaEDC'
    ),
    (
        4,
        'DavidDemo',
        'david.demo@example.test',
        '$2y$10$ItyyeLPWoggbwSAf/y93v.W4ipyjGItiEbqEAcbyxfiByUFFWaEDC'
    ),
    (
        5,
        'EmmaDemo',
        'emma.demo@example.test',
        '$2y$10$ItyyeLPWoggbwSAf/y93v.W4ipyjGItiEbqEAcbyxfiByUFFWaEDC'
    ),
    (
        6,
        'FaridDemo',
        'farid.demo@example.test',
        '$2y$10$ItyyeLPWoggbwSAf/y93v.W4ipyjGItiEbqEAcbyxfiByUFFWaEDC'
    ),
    (
        7,
        'GabrielDemo',
        'gabriel.demo@example.test',
        '$2y$10$ItyyeLPWoggbwSAf/y93v.W4ipyjGItiEbqEAcbyxfiByUFFWaEDC'
    ),
    (
        8,
        'HanaDemo',
        'hana.demo@example.test',
        '$2y$10$ItyyeLPWoggbwSAf/y93v.W4ipyjGItiEbqEAcbyxfiByUFFWaEDC'
    );

INSERT INTO `ANNONCE` (
    `annonce_id`,
    `utilisateur_id`,
    `titre`,
    `description`,
    `etat_objet`,
    `prix_depart`,
    `date_heure_fin`,
    `categorie_id_externe`,
    `categorie_libelle`
) VALUES
    (
        1,
        1,
        'Vélo de ville restauré',
        'Vélo confortable révisé et prêt à rouler.',
        'très bon état',
        120.00,
        UTC_TIMESTAMP() + INTERVAL 14 DAY,
        17,
        'Vélos et cyclisme'
    ),
    (
        2,
        1,
        'Console rétro avec deux manettes',
        'Console fonctionnelle fournie avec ses câbles et deux manettes.',
        'bon état',
        40.00,
        UTC_TIMESTAMP() + INTERVAL 7 DAY,
        14,
        'Jeux vidéo'
    ),
    (
        3,
        1,
        'Vase ancien décoratif',
        'Vase ancien en bon état général avec quelques traces du temps.',
        'bon état',
        60.00,
        UTC_TIMESTAMP() - INTERVAL 1 DAY,
        20,
        'Art et antiquités'
    ),
    (
        4,
        2,
        'Chaussures de randonnée',
        'Chaussures peu portées, propres et sans défaut important.',
        'très bon état',
        35.00,
        UTC_TIMESTAMP() - INTERVAL 2 DAY,
        9,
        'Chaussures'
    ),
    (5, 4, 'Montre mécanique vintage', 'Montre révisée avec bracelet en cuir neuf.', 'très bon état', 95.00, UTC_TIMESTAMP() + INTERVAL 3 DAY, 10, 'Bijoux et montres'),
    (6, 5, 'Jeu de société familial', 'Jeu complet pour quatre à six joueurs.', 'bon état', 25.00, UTC_TIMESTAMP() + INTERVAL 5 DAY, 15, 'Jeux et jouets'),
    (7, 6, 'Casque de moto intégral', 'Casque homologué avec visière claire et housse.', 'bon état', 35.00, UTC_TIMESTAMP() + INTERVAL 9 DAY, 18, 'Auto et moto'),
    (8, 7, 'Tableau paysager encadré', 'Peinture décorative signée avec cadre en bois.', 'très bon état', 80.00, UTC_TIMESTAMP() + INTERVAL 12 DAY, 20, 'Art et antiquités'),
    (9, 8, 'Vélo de route aluminium', 'Vélo léger entretenu récemment.', 'bon état', 200.00, UTC_TIMESTAMP() + INTERVAL 6 DAY, 17, 'Vélos et cyclisme'),
    (10, 2, 'Édition collector jeu vidéo', 'Boîte complète avec livret et objets de collection.', 'très bon état', 50.00, UTC_TIMESTAMP() + INTERVAL 4 DAY, 14, 'Jeux vidéo'),
    (11, 3, 'Baskets urbaines neuves', 'Paire jamais portée conservée dans sa boîte.', 'neuf', 55.00, UTC_TIMESTAMP() + INTERVAL 10 DAY, 9, 'Chaussures'),
    (12, 4, 'Bracelet en argent', 'Bracelet poinçonné livré dans son écrin.', 'très bon état', 75.00, UTC_TIMESTAMP() + INTERVAL 8 DAY, 10, 'Bijoux et montres'),
    (13, 5, 'Jeu de construction complet', 'Ensemble trié avec notice et boîte.', 'bon état', 45.00, UTC_TIMESTAMP() + INTERVAL 11 DAY, 15, 'Jeux et jouets'),
    (14, 6, 'Barres de toit universelles', 'Barres verrouillables avec deux clés.', 'état correct', 60.00, UTC_TIMESTAMP() + INTERVAL 13 DAY, 18, 'Auto et moto'),
    (15, 7, 'Sculpture décorative en bronze', 'Petite sculpture sur socle en bois.', 'très bon état', 110.00, UTC_TIMESTAMP() + INTERVAL 15 DAY, 20, 'Art et antiquités'),
    (16, 8, 'BMX freestyle renforcé', 'BMX solide avec pneus et poignées récents.', 'bon état', 140.00, UTC_TIMESTAMP() + INTERVAL 2 DAY, 17, 'Vélos et cyclisme'),
    (17, 4, 'Montre automatique classique', 'Montre automatique avec boîte de rangement.', 'bon état', 120.00, UTC_TIMESTAMP() - INTERVAL 1 DAY, 10, 'Bijoux et montres'),
    (18, 5, 'Peluche de collection', 'Peluche propre avec son étiquette d origine.', 'très bon état', 20.00, UTC_TIMESTAMP() - INTERVAL 3 DAY, 15, 'Jeux et jouets'),
    (19, 6, 'Coffre de toit compact', 'Coffre avec fixations et double des clés.', 'bon état', 90.00, UTC_TIMESTAMP() - INTERVAL 2 DAY, 18, 'Auto et moto'),
    (20, 7, 'Gravure numérotée', 'Gravure encadrée et numérotée en série limitée.', 'très bon état', 70.00, UTC_TIMESTAMP() - INTERVAL 4 DAY, 20, 'Art et antiquités');

INSERT INTO `PHOTOGRAPHIE` (
    `photographie_id`,
    `annonce_id`,
    `ref_fichier`,
    `ordre`
) VALUES
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

INSERT INTO `ASSOC_UTILISATEUR_ANNONCE` (
    `assoc_utilisateur_annonce_id`,
    `utilisateur_id`,
    `annonce_id`
) VALUES
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
    (18, 2, 16);

INSERT INTO `ENCHERE` (
    `enchere_id`,
    `utilisateur_id`,
    `annonce_id`,
    `montant`,
    `date_heure_enchere`
) VALUES
    (1, 2, 2, 55.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (2, 3, 2, 70.00, UTC_TIMESTAMP() - INTERVAL 1 DAY),
    (3, 3, 3, 80.00, UTC_TIMESTAMP() - INTERVAL 3 DAY),
    (4, 2, 3, 100.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (5, 1, 5, 110.00, UTC_TIMESTAMP() - INTERVAL 3 DAY),
    (6, 2, 5, 125.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (7, 5, 5, 140.00, UTC_TIMESTAMP() - INTERVAL 1 DAY),
    (8, 6, 6, 30.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (9, 8, 6, 42.00, UTC_TIMESTAMP() - INTERVAL 1 DAY),
    (10, 1, 7, 45.00, UTC_TIMESTAMP() - INTERVAL 4 DAY),
    (11, 7, 7, 60.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (12, 3, 7, 75.00, UTC_TIMESTAMP() - INTERVAL 1 DAY),
    (13, 2, 8, 95.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (14, 5, 8, 120.00, UTC_TIMESTAMP() - INTERVAL 1 DAY),
    (15, 6, 9, 220.00, UTC_TIMESTAMP() - INTERVAL 4 DAY),
    (16, 4, 9, 250.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (17, 1, 9, 275.00, UTC_TIMESTAMP() - INTERVAL 1 DAY),
    (18, 3, 10, 65.00, UTC_TIMESTAMP() - INTERVAL 3 DAY),
    (19, 7, 10, 80.00, UTC_TIMESTAMP() - INTERVAL 1 DAY),
    (20, 5, 12, 90.00, UTC_TIMESTAMP() - INTERVAL 3 DAY),
    (21, 8, 12, 105.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (22, 6, 12, 125.00, UTC_TIMESTAMP() - INTERVAL 1 DAY),
    (23, 1, 14, 70.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (24, 2, 14, 85.00, UTC_TIMESTAMP() - INTERVAL 1 DAY),
    (25, 3, 16, 160.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (26, 4, 16, 190.00, UTC_TIMESTAMP() - INTERVAL 1 DAY),
    (27, 5, 17, 150.00, UTC_TIMESTAMP() - INTERVAL 4 DAY),
    (28, 1, 17, 175.00, UTC_TIMESTAMP() - INTERVAL 2 DAY),
    (29, 7, 19, 100.00, UTC_TIMESTAMP() - INTERVAL 5 DAY),
    (30, 8, 19, 130.00, UTC_TIMESTAMP() - INTERVAL 3 DAY);

COMMIT;
```

### 6.3 Situations obtenues

Le jeu de données permet de contrôler les situations suivantes :

- l'affichage initial de douze annonces actives et une seconde page de résultats ;
- des recherches combinées sur plusieurs catégories, états, prix et échéances ;
- une annonce active sans enchère, modifiable par sa vendeuse ;
- une annonce active possédant plusieurs enchères, donc verrouillée ;
- un utilisateur dont l'enchère a été dépassée ;
- une utilisatrice possédant la meilleure enchère ;
- une annonce terminée et adjugée avec un gagnant et une perdante ;
- une annonce terminée sans enchère, donc non adjugée ;
- une annonce simplement suivie sans enchère ;
- une annonce suivie sur laquelle l'utilisateur a aussi enchéri ;
- des annonces avec zéro, une, deux ou trois photographies.
