<?php

/*
 * Description générale :
 * Tests d'intégration du modèle des annonces.
 *
 * Rôle :
 * Vérifier que ListingModel communique correctement avec la base de données et applique les principales règles métier des annonces.
 *
 * Tâches :
 * - Créer une annonce de test.
 * - Vérifier la récupération de l'annonce.
 * - Vérifier son propriétaire.
 * - Vérifier les autorisations de modification.
 * - Vérifier les autorisations de participation.
 * - Vérifier la modification d'une annonce.
 * - Vérifier la recherche d'une annonce.
 * - Vérifier la suppression d'une annonce.
 *
 * Liens :
 * - Utilise ListingModel situé dans src/models/ListingModel.php.
 * - Utilise Model situé dans src/core/Model.php.
 * - Utilise Database situé dans src/core/Database.php.
 * - Utilise Clock.
 * - Utilise le lanceur de tests défini dans tests/LanceurTest.php.
 * - Est exécuté depuis tests/Lancer.php.
 */

require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/Model.php';
require_once __DIR__ . '/../../src/core/Clock.php';
require_once __DIR__ . '/../../src/models/ListingModel.php';

use App\core\Database;
use App\core\Clock;
use App\models\ListingModel;


/*
 * Chargement de la configuration privée de la base de données.
 * Les identifiants de connexion ne sont pas dupliqués
 * dans le fichier de test.
 */

$configurationBaseDeDonnees = require __DIR__ . '/../../private/database-secret.php';


/*
 * Création de la connexion et du modèle.
 */

$baseDeDonnees = new Database($configurationBaseDeDonnees);

$modeleAnnonce = new ListingModel($baseDeDonnees);


/*
 * TEST 1
 * Vérification de la connexion à la base de données.
 */

$connexionReussie = $baseDeDonnees->isConnected();

$lanceurTests->verifierEgalite(
    true,
    $connexionReussie,
    "ListingModel doit disposer d'une connexion à la base de données"
);


/*
 * La suite des tests est exécutée uniquement
 * si la connexion fonctionne.
 */

if ($connexionReussie) {

    /*
     * Recherche d'un utilisateur existant.
     *
     * L'annonce de test doit appartenir à un utilisateur
     * réellement présent dans la base afin de respecter
     * les éventuelles clés étrangères.
     */

    $utilisateur = $baseDeDonnees->fetchOne(
        'SELECT id FROM `UTILISATEUR` ORDER BY id ASC LIMIT 1'
    );

    $utilisateurDisponible = is_array($utilisateur)
        && isset($utilisateur['id']);

    $lanceurTests->verifierEgalite(
        true,
        $utilisateurDisponible,
        "Un utilisateur doit être disponible pour réaliser les tests d'annonce"
    );


    if ($utilisateurDisponible) {

        $identifiantUtilisateur = (int) $utilisateur['id'];

        /*
         * Utilisation d'un identifiant différent afin de tester
         * les règles liées à un utilisateur qui n'est pas propriétaire.
         */

        $identifiantAutreUtilisateur = $identifiantUtilisateur + 1000000;


        /*
         * Date actuelle et date de fin future.
         */

        $dateActuelleUtc = new DateTimeImmutable(
            'now',
            new DateTimeZone('UTC')
        );

        $dateFinUtc = $dateActuelleUtc->modify('+2 days');

        $dateFinBaseDeDonnees = Clock::formatForDatabase($dateFinUtc);


        /*
         * Une catégorie fictive est utilisée ici.
         *
         * ListingModel stocke directement l'identifiant fourni
         * dans la colonne categorie_id.
         */

        $identifiantCategorie = 1;


        /*
         * Création d'un titre unique pour éviter de confondre
         * l'annonce de test avec une annonce existante.
         */

        $titreTest = 'TEST_AUTOMATISE_' . uniqid();


        /*
         * Démarrage d'une transaction.
         *
         * Toutes les modifications réalisées pendant ce fichier
         * seront annulées à la fin du test.
         */

        $transactionDemarree = $baseDeDonnees->beginTransaction();

        $lanceurTests->verifierEgalite(
            true,
            $transactionDemarree,
            "Une transaction doit pouvoir être démarrée pour tester ListingModel"
        );


        if ($transactionDemarree) {

            /*
             * TEST 2
             * Création d'une annonce.
             */

            $identifiantAnnonce = $modeleAnnonce->createListing(
                $identifiantUtilisateur,
                $titreTest,
                'Description utilisée pour les tests automatisés.',
                'Bon état',
                100,
                $dateFinBaseDeDonnees,
                $identifiantCategorie
            );

            $annonceCreee = is_int($identifiantAnnonce)
                && $identifiantAnnonce > 0;

            $lanceurTests->verifierEgalite(
                true,
                $annonceCreee,
                "Une annonce doit pouvoir être créée avec ListingModel"
            );


            if ($annonceCreee) {

                /*
                 * TEST 3
                 * Vérification du propriétaire de l'annonce.
                 */

                $estProprietaire = $modeleAnnonce->isOwnedBy(
                    $identifiantAnnonce,
                    $identifiantUtilisateur
                );

                $lanceurTests->verifierEgalite(
                    true,
                    $estProprietaire,
                    "L'annonce doit appartenir à l'utilisateur qui l'a créée"
                );


                /*
                 * TEST 4
                 * Vérification avec un autre utilisateur.
                 */

                $estProprietaire = $modeleAnnonce->isOwnedBy(
                    $identifiantAnnonce,
                    $identifiantAutreUtilisateur
                );

                $lanceurTests->verifierEgalite(
                    false,
                    $estProprietaire,
                    "Un autre utilisateur ne doit pas être considéré comme propriétaire"
                );


                /*
                 * TEST 5
                 * Vérification de l'autorisation de modification
                 * pour le propriétaire.
                 *
                 * L'annonce est active et ne possède aucune enchère.
                 */

                $modificationAutorisee = $modeleAnnonce->canBeModifiedBy(
                    $identifiantAnnonce,
                    $identifiantUtilisateur,
                    $dateActuelleUtc
                );

                $lanceurTests->verifierEgalite(
                    true,
                    $modificationAutorisee,
                    "Le propriétaire doit pouvoir modifier une annonce active sans enchère"
                );


                /*
                 * TEST 6
                 * Vérification de l'interdiction de modification
                 * pour un utilisateur qui n'est pas propriétaire.
                 */

                $modificationAutorisee = $modeleAnnonce->canBeModifiedBy(
                    $identifiantAnnonce,
                    $identifiantAutreUtilisateur,
                    $dateActuelleUtc
                );

                $lanceurTests->verifierEgalite(
                    false,
                    $modificationAutorisee,
                    "Un utilisateur non propriétaire ne doit pas pouvoir modifier l'annonce"
                );


                /*
                 * Vérification du motif de l'interdiction.
                 */

                $motifInterdiction = $modeleAnnonce
                    ->getLastManagementRestriction();

                $lanceurTests->verifierEgalite(
                    'owner',
                    $motifInterdiction,
                    "Le motif d'interdiction doit indiquer que l'utilisateur n'est pas propriétaire"
                );


                /*
                 * TEST 7
                 * Le propriétaire ne doit pas pouvoir participer
                 * à sa propre annonce.
                 */

                $participationAutorisee = $modeleAnnonce
                    ->canReceiveParticipationFrom(
                        $identifiantAnnonce,
                        $identifiantUtilisateur,
                        $dateActuelleUtc
                    );

                $lanceurTests->verifierEgalite(
                    false,
                    $participationAutorisee,
                    "Le propriétaire ne doit pas pouvoir participer à sa propre annonce"
                );


                /*
                 * Vérification du motif de refus.
                 */

                $motifParticipation = $modeleAnnonce
                    ->getLastParticipationRestriction();

                $lanceurTests->verifierEgalite(
                    'owner',
                    $motifParticipation,
                    "Le motif de refus de participation doit être owner"
                );


                /*
                 * TEST 8
                 * Un autre utilisateur doit pouvoir participer
                 * à une annonce encore active.
                 */

                $participationAutorisee = $modeleAnnonce
                    ->canReceiveParticipationFrom(
                        $identifiantAnnonce,
                        $identifiantAutreUtilisateur,
                        $dateActuelleUtc
                    );

                $lanceurTests->verifierEgalite(
                    true,
                    $participationAutorisee,
                    "Un autre utilisateur doit pouvoir participer à une annonce active"
                );


                /*
                 * TEST 9
                 * Modification de l'annonce.
                 */

                $nouveauTitre = $titreTest . '_MODIFIE';

                $modificationReussie = $modeleAnnonce->updateListing(
                    $identifiantAnnonce,
                    $nouveauTitre,
                    'Description modifiée pendant le test.',
                    'Très bon état',
                    150,
                    $dateFinBaseDeDonnees,
                    $identifiantCategorie
                );

                $lanceurTests->verifierEgalite(
                    true,
                    $modificationReussie,
                    "Une annonce autorisée doit pouvoir être modifiée"
                );


                /*
                 * TEST 10
                 * Récupération des informations de l'annonce.
                 */

                $detailAnnonce = $modeleAnnonce->getDetail(
                    $identifiantAnnonce
                );

                $titreObtenu = null;

                if (is_array($detailAnnonce)
                    && isset($detailAnnonce['titre'])
                ) {
                    $titreObtenu = $detailAnnonce['titre'];
                }

                $lanceurTests->verifierEgalite(
                    $nouveauTitre,
                    $titreObtenu,
                    "Le détail de l'annonce doit contenir le titre modifié"
                );


                /*
                 * TEST 11
                 * Recherche de l'annonce avec son titre.
                 */

                $criteresRecherche = [
                    'words' => [$nouveauTitre],
                    'category_id' => null,
                    'item_state' => null,
                    'minimum_price_in_euros' => null,
                    'maximum_price_in_euros' => null,
                    'sale_state' => 'active',
                ];

                $resultatRecherche = $modeleAnnonce->searchListings(
                    $criteresRecherche,
                    1,
                    10,
                    $dateActuelleUtc
                );

                $rechercheReussie = isset(
                    $resultatRecherche['success']
                )
                    && $resultatRecherche['success'] === true;

                $lanceurTests->verifierEgalite(
                    true,
                    $rechercheReussie,
                    "La recherche des annonces doit être exécutée correctement"
                );


                /*
                 * Vérification que l'annonce créée est présente
                 * dans le résultat de recherche.
                 */

                $annonceTrouvee = false;

                if (isset($resultatRecherche['listings'])
                    && is_array($resultatRecherche['listings'])
                ) {
                    foreach ($resultatRecherche['listings'] as $annonce) {

                        if (isset($annonce['id'])
                            && (int) $annonce['id'] === $identifiantAnnonce
                        ) {
                            $annonceTrouvee = true;
                            break;
                        }
                    }
                }

                $lanceurTests->verifierEgalite(
                    true,
                    $annonceTrouvee,
                    "L'annonce créée doit être retrouvée par la recherche"
                );


                /*
                 * TEST 12
                 * Suppression de l'annonce.
                 */

                $suppressionReussie = $modeleAnnonce->deleteListing(
                    $identifiantAnnonce
                );

                $lanceurTests->verifierEgalite(
                    true,
                    $suppressionReussie,
                    "Une annonce doit pouvoir être supprimée"
                );


                /*
                 * Vérification que l'annonce n'existe plus.
                 */

                $annonceSupprimee = $modeleAnnonce->getDetail(
                    $identifiantAnnonce
                );

                $lanceurTests->verifierEgalite(
                    null,
                    $annonceSupprimee,
                    "Une annonce supprimée ne doit plus être récupérable"
                );
            }


            /*
             * Annulation de toutes les modifications réalisées
             * pendant les tests.
             */

            $baseDeDonnees->rollback();
        }
    }
}
