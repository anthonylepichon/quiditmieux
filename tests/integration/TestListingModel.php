<?php

/*
 * Description générale :
 * Tests d'intégration du modèle des annonces.
 *
 * Rôle : Vérifier les principales opérations et règles métier appliquées aux annonces afin de détecter une régression après une modification du code.
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
 * - Utilise le lanceur de tests défini dans tests/LanceurTest.php.
 * - Est exécuté depuis tests/Lancer.php.
 */

// NATIF PHP : __DIR__ contient le chemin absolu du dossier du fichier courant ; elle permet ici de construire un chemin indépendant du poste utilisé.
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/Model.php';
require_once __DIR__ . '/../../src/models/ListingModel.php';

use App\core\Database;
use App\models\ListingModel;

// NATIF PHP : date_default_timezone_set() définit le fuseau horaire utilisé par les fonctions de date ; il garantit ici des calculs cohérents avec le contexte français.
date_default_timezone_set('Europe/Paris');


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

    // NATIF PHP : is_array() vérifie qu’une valeur est un tableau ; il évite ici de parcourir ou transmettre un type inattendu.
    $utilisateurDisponible = is_array($utilisateur)
        // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
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

        // NATIF PHP : DateTime est la classe native de gestion des dates et des heures ; elle fournit ici les instants utilisés par le scénario de test.
        $dateActuelle = new DateTime();

        // Une seconde date est créée car modify() modifierait directement l'objet DateTime sur lequel elle est appelée.
        $dateFin = new DateTime('+2 days');

        $dateFinBaseDeDonnees = $dateFin->format('Y-m-d H:i:s');


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

        // NATIF PHP : uniqid() génère un identifiant basé sur l’heure courante ; il rend ici la donnée de test distincte des données existantes.
        $titreTest = 'TEST_AUTOMATISE_' . uniqid();

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

            // NATIF PHP : is_int() vérifie qu’une valeur est un entier ; il évite ici d’utiliser un autre type dans un traitement numérique.
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
                 * Lecture de l'annonce par la méthode héritée, puis vérification de son propriétaire.
                 */

                $annonceLue = $modeleAnnonce->findById($identifiantAnnonce);

                $titreAnnonceLue = null;
                if (is_array($annonceLue) && isset($annonceLue['titre'])) {
                    $titreAnnonceLue = $annonceLue['titre'];
                }

                $lanceurTests->verifierEgalite(
                    $titreTest,
                    $titreAnnonceLue,
                    "La méthode findById héritée doit retrouver l'annonce créée"
                );

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
                    $dateActuelle
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
                    $dateActuelle
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
                        $dateActuelle
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
                        $dateActuelle
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
                    $dateActuelle
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
    }
}
