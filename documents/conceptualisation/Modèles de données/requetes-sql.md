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
