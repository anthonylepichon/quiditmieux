<?php

/**
 * Description générale : Page publique présentant la politique de confidentialité de QUIDITMIEUX.
 * Rôle : Informer clairement les utilisateurs sur les données traitées et leurs droits. Le template reste ainsi consacré à la présentation des données déjà préparées, sans décider des règles métier.
 * Tâches : Présenter les finalités, bases légales, destinataires, durées, protections et droits applicables.
 * Liens avec les autres fichiers : Est affiché par LegalController.php puis inséré dans base.php.
 */

/** @var array<string, mixed> $data Données préparées par le contrôleur. */

$isConnected = (bool) ($data['is_connected'] ?? false);
$csrfToken = (string) ($data['csrf_token'] ?? '');
$currentPage = 'privacy';
$pageTitle = 'Politique de confidentialité — QUIDITMIEUX';
$pageDescription = 'Découvrez comment QUIDITMIEUX utilise et protège vos données personnelles.';
?>
<main class="privacy-page container">
    <header class="privacy-hero">
        <p class="eyebrow">VOS DONNÉES PERSONNELLES</p>
        <h1>Politique de confidentialité</h1>
        <p>Cette page explique simplement quelles données sont utilisées par QUIDITMIEUX, pourquoi elles le sont et quels sont vos droits.</p>
        <p class="privacy-hero__date">Dernière mise à jour : 2 septembre 2026</p>
    </header>

    <div class="privacy-content glass-panel">
        <nav class="privacy-summary" aria-label="Sommaire de la politique de confidentialité">
            <h2>Sommaire</h2>
            <ol>
                <li><a href="#responsable">Responsable du traitement</a></li>
                <li><a href="#donnees">Données utilisées</a></li>
                <li><a href="#finalites">Finalités et bases légales</a></li>
                <li><a href="#destinataires">Destinataires</a></li>
                <li><a href="#conservation">Conservation</a></li>
                <li><a href="#droits">Vos droits</a></li>
                <li><a href="#securite">Sécurité et cookies</a></li>
            </ol>
        </nav>

        <section id="responsable">
            <h2>1. Responsable du traitement</h2>
            <p>Le responsable du traitement est Anthony Lepichon, auteur du projet pédagogique QUIDITMIEUX.</p>
            <p>Pour toute question ou demande concernant vos données, vous pouvez utiliser les coordonnées publiques disponibles sur le <a href="https://github.com/anthonylepichon" rel="noopener noreferrer">profil GitHub du responsable du projet</a>.</p>
        </section>

        <section id="donnees">
            <h2>2. Données utilisées</h2>
            <p>QUIDITMIEUX traite uniquement les informations nécessaires à son fonctionnement :</p>
            <ul>
                <li>les données du compte : pseudo, adresse électronique et mot de passe conservé sous forme d’empreinte sécurisée ;</li>
                <li>les données des annonces : titre, description, état, catégorie, prix, échéance et photographies ;</li>
                <li>les participations : annonces suivies, montants et dates des enchères ;</li>
                <li>les données techniques indispensables : identifiant de session et journaux techniques éventuellement produits par le serveur d’hébergement.</li>
            </ul>
            <p>Les champs obligatoires sont signalés dans les formulaires. Sans les informations demandées lors de l’inscription, le compte ne peut pas être créé.</p>
        </section>

        <section id="finalites">
            <h2>3. Finalités et bases légales</h2>
            <div class="privacy-table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Utilisation</th>
                            <th scope="col">Objectif</th>
                            <th scope="col">Base légale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Compte et session</td>
                            <td>Créer le compte, authentifier l’utilisateur et maintenir sa connexion.</td>
                            <td>Exécution du service demandé par l’utilisateur.</td>
                        </tr>
                        <tr>
                            <td>Annonces et photographies</td>
                            <td>Publier, modifier et présenter une vente aux enchères.</td>
                            <td>Exécution du service demandé par l’utilisateur.</td>
                        </tr>
                        <tr>
                            <td>Suivis et enchères</td>
                            <td>Permettre la participation aux ventes et désigner le gagnant.</td>
                            <td>Exécution du service demandé par l’utilisateur.</td>
                        </tr>
                        <tr>
                            <td>Données techniques</td>
                            <td>Sécuriser l’application, prévenir les erreurs et assurer son fonctionnement.</td>
                            <td>Intérêt légitime à protéger et maintenir le service.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p>La case présente lors de l’inscription confirme que la politique a été lue. Elle ne constitue pas un accord pour recevoir de la publicité et aucune prospection commerciale n’est réalisée.</p>
        </section>

        <section id="destinataires">
            <h2>4. Destinataires et transferts</h2>
            <p>Les données sont accessibles uniquement au responsable du projet et, lorsque l’application est hébergée, au prestataire technique strictement nécessaire à son fonctionnement. Elles ne sont ni vendues ni louées.</p>
            <p>L’API externe des catégories est interrogée par le serveur. QUIDITMIEUX ne lui transmet pas les données du compte, des annonces, des suivis ou des enchères.</p>
            <p>Aucun transfert volontaire de données personnelles en dehors de l’Union européenne n’est prévu par l’application.</p>
        </section>

        <section id="conservation">
            <h2>5. Durée de conservation</h2>
            <ul>
                <li>les données du compte, des annonces, des photographies, des suivis et des enchères sont conservées pendant la durée d’utilisation du compte et tant qu’elles sont nécessaires au fonctionnement et à l’historique des ventes ;</li>
                <li>la session technique prend fin lors de la déconnexion ou de l’expiration de la session sur le serveur ;</li>
                <li>les éventuels journaux techniques sont conservés pendant la durée définie par l’hébergeur, uniquement pour la sécurité et le diagnostic.</li>
            </ul>
            <p>Dans cette version pédagogique, aucune suppression automatique du compte n’est disponible. Une demande d’effacement est donc traitée manuellement, en tenant compte des données qui doivent rester nécessaires à l’intégrité des enchères.</p>
        </section>

        <section id="droits">
            <h2>6. Vos droits</h2>
            <p>Selon votre situation, vous pouvez demander l’accès à vos données, leur rectification, leur effacement, la limitation de leur traitement ou leur portabilité. Vous pouvez aussi vous opposer aux traitements fondés sur l’intérêt légitime.</p>
            <p>Pour exercer un droit, contactez le responsable indiqué au début de cette page en précisant votre pseudo et la nature de votre demande. Une vérification de votre identité peut être demandée si elle est nécessaire pour protéger votre compte.</p>
            <p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez adresser une réclamation à la <a href="https://www.cnil.fr/fr/plaintes" rel="noopener noreferrer">Commission nationale de l’informatique et des libertés (CNIL)</a>.</p>
        </section>

        <section id="securite">
            <h2>7. Sécurité et cookies</h2>
            <p>QUIDITMIEUX protège les données au moyen de mots de passe hachés, de requêtes préparées, de contrôles d’accès, de jetons contre les requêtes frauduleuses et de validations des fichiers téléversés.</p>
            <p>L’application utilise uniquement la session technique indispensable à l’authentification et à la sécurité. Elle n’intègre ni cookie publicitaire ni outil de mesure d’audience.</p>
            <p>La politique peut évoluer si les fonctions, l’hébergement ou les traitements changent. La date de mise à jour affichée en haut de la page permet d’identifier la version applicable.</p>
        </section>
    </div>
</main>
