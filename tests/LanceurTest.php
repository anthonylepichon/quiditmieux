<?php

/*
 * Description générale :
 * Classe utilitaire permettant d'exécuter et de comptabiliser
 * les résultats des tests automatisés de l'application.
 *
 * Rôle : Fournir les méthodes communes permettant d'organiser les vérifications et d'afficher leurs résultats pendant le développement.
 * Centraliser les vérifications effectuées par les différents
 * fichiers de tests unitaires et d'intégration.
 *
 * Tâches :
 * - Identifier l'élément actuellement testé.
 * - Comparer une valeur obtenue avec une valeur attendue.
 * - Indiquer si un test est réussi ou échoué.
 * - Compter les tests réussis et échoués pour chaque élément testé.
 * - Compter le nombre total de tests réussis et échoués.
 * - Afficher un résumé détaillé des résultats à la fin de l'exécution.
 *
 * Liens :
 * - Utilisé par le fichier tests/Lancer.php.
 * - Utilisé par les tests présents dans tests/unitaire/.
 * - Utilisé par les tests présents dans tests/integration/.
 */

class LanceurTest
{
    // ====================
    // ATTRIBUTS
    // ====================

    private int $testsReussis = 0;
    private int $testsEchoues = 0;

    private string $itemActuel = '';

    private array $resultatsParItem = [];


    // ====================
    // MÉTHODES
    // ====================

    /**
     * Rôle : Définir l'élément dont les tests vont être exécutés afin de regrouper clairement ses résultats.
     * Paramètres : Nom de l'élément testé.
     * Retour : Aucun.
     */
    public function commencerItem(string $nomItem): void
    {
        $this->itemActuel = $nomItem;

        // NATIF PHP : isset() vérifie qu’une variable ou une entrée de tableau existe et ne vaut pas null ; il évite ici de lire une valeur absente.
        if (!isset($this->resultatsParItem[$nomItem])) {
            $this->resultatsParItem[$nomItem] = [
                'reussis' => 0,
                'echoues' => 0,
            ];
        }

        // NATIF PHP : PHP_EOL contient le retour à la ligne du système ; elle produit ici une sortie de test lisible sur chaque environnement.
        echo PHP_EOL;
        echo "----- " . $nomItem . " -----" . PHP_EOL;
    }


    /**
     * Rôle : Comparer une valeur obtenue avec la valeur attendue afin de signaler immédiatement un comportement incorrect.
     * Paramètres : Valeur attendue, valeur obtenue et message du test.
     * Retour : Aucun.
     */
    public function verifierEgalite(
        $valeurAttendue,
        $valeurObtenue,
        string $message
    ): void {
        if ($valeurAttendue === $valeurObtenue) {

            echo "✅ " . $message . PHP_EOL;

            $this->testsReussis++;

            if ($this->itemActuel !== '') {
                $this->resultatsParItem[$this->itemActuel]['reussis']++;
            }

        } else {

            echo "❌ " . $message . PHP_EOL;

            echo "   Valeur attendue : ";
            // NATIF PHP : var_dump() affiche le type et la valeur détaillée d’une donnée ; il fournit ici un diagnostic lors d’un échec de test.
            var_dump($valeurAttendue);

            echo "   Valeur obtenue : ";
            var_dump($valeurObtenue);

            $this->testsEchoues++;

            if ($this->itemActuel !== '') {
                $this->resultatsParItem[$this->itemActuel]['echoues']++;
            }
        }
    }


    /**
     * Rôle : Afficher les résultats de chaque élément testé afin d'identifier rapidement les vérifications réussies ou échouées.
     * ainsi que le résultat total de la campagne de tests.
     * Paramètres : Aucun.
     * Retour : Aucun.
     */
    public function afficherResume(): void
    {
        echo PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo "               RÉSULTATS" . PHP_EOL;
        echo "========================================" . PHP_EOL;

        foreach ($this->resultatsParItem as $nomItem => $resultats) {

            echo PHP_EOL;
            echo $nomItem . PHP_EOL;

            echo "Tests réussis : "
                . $resultats['reussis']
                . PHP_EOL;

            echo "Tests échoués : "
                . $resultats['echoues']
                . PHP_EOL;
        }

        echo PHP_EOL;
        echo "----------------------------------------" . PHP_EOL;
        echo "TOTAL" . PHP_EOL;
        echo "----------------------------------------" . PHP_EOL;

        echo "Tests réussis : "
            . $this->testsReussis
            . PHP_EOL;

        echo "Tests échoués : "
            . $this->testsEchoues
            . PHP_EOL;
    }
}
