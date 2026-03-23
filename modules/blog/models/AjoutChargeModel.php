<?php
namespace modules\blog\models;

/**
 * Classe AjoutChargeModel
 *
 * Cette classe gère l'ajout manuel de charges dans la base de données.
 * Elle permet à l'utilisateur de saisir directement des données de charge
 * via un formulaire dans le dashboard.
 */
class AjoutChargeModel {
    /**
     * @var \PDO $db Connexion à la base de données
     */
    private $db;

    /**
     * Constructeur de AjoutChargeModel
     */
    public function __construct() {
        $this->db = SingletonModel::getInstance()->getConnection();
    }

    /**
     * Ajoute une nouvelle charge dans la base de données
     *
     * @param array $donnees Données de la charge à ajouter
     * @return array Résultat de l'ajout
     */
    public function ajouterCharge($donnees) {
        $this->console_log("=== DÉBUT AJOUT CHARGE MANUELLE ===");
        $this->console_log("Données reçues: " . json_encode($donnees));

        // Valider les données
        $validation = $this->validerDonnees($donnees);
        if (!$validation['success']) {
            $this->console_log("Validation échouée: " . $validation['message']);
            return $validation;
        }

        $this->console_log("Validation réussie");

        try {
            // Préparer la requête d'insertion
            $sql = "INSERT INTO Donnees (Processus, Tache, Charge, Date) VALUES (:processus, :tache, :charge, :date)";
            $stmt = $this->db->prepare($sql);

            // Bind des paramètres
            $stmt->bindParam(':processus', $donnees['processus']);
            $stmt->bindParam(':tache', $donnees['tache']);
            $stmt->bindParam(':charge', $donnees['charge'], \PDO::PARAM_STR); // Charge peut être décimale
            $stmt->bindParam(':date', $donnees['date']);

            // Exécuter la requête
            $this->console_log("Exécution de la requête SQL...");
            $stmt->execute();

            $this->console_log("Charge ajoutée avec succès en base de données");

            return [
                'success' => true,
                'message' => "Charge ajoutée avec succès : {$donnees['processus']} - {$donnees['tache']} ({$donnees['charge']} personne(s)) le {$donnees['date']}"
            ];

        } catch (\PDOException $e) {
            $this->console_log("ERREUR SQL: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erreur lors de l'ajout en base de données : " . $e->getMessage()
            ];
        }
    }

    /**
     * Valide les données avant insertion
     *
     * @param array $donnees Données à valider
     * @return array Résultat de la validation
     */
    private function validerDonnees($donnees) {
        $this->console_log("=== VALIDATION DES DONNÉES ===");

        // Vérifier que tous les champs requis sont présents
        $champsRequis = ['processus', 'tache', 'charge', 'date'];
        foreach ($champsRequis as $champ) {
            if (!isset($donnees[$champ]) || empty(trim($donnees[$champ]))) {
                return [
                    'success' => false,
                    'message' => "Le champ '{$champ}' est obligatoire."
                ];
            }
        }

        // Valider le processus (longueur max)
        if (strlen($donnees['processus']) > 40) {
            return [
                'success' => false,
                'message' => "Le processus ne peut pas dépasser 40 caractères."
            ];
        }

        // Valider la tâche (longueur max)
        if (strlen($donnees['tache']) > 200) {
            return [
                'success' => false,
                'message' => "La tâche ne peut pas dépasser 200 caractères."
            ];
        }

        // Valider la charge (doit être un entier positif)
        if (!is_numeric($donnees['charge'])) {
            return [
                'success' => false,
                'message' => "La charge doit être un nombre."
            ];
        }
        $charge = intval($donnees['charge']);
        if ($charge != $donnees['charge'] || $charge <= 0) {
            return [
                'success' => false,
                'message' => "La charge doit être un nombre entier positif (ex: 1, 2, 3...)."
            ];
        }

        // Valider la date (format YYYY-MM-DD)
        $date = \DateTime::createFromFormat('Y-m-d', $donnees['date']);
        if (!$date || $date->format('Y-m-d') !== $donnees['date']) {
            return [
                'success' => false,
                'message' => "La date doit être au format YYYY-MM-DD."
            ];
        }

        $this->console_log("Toutes les validations sont OK");

        return [
            'success' => true,
            'message' => "Données valides"
        ];
    }

    /**
     * Récupère la liste des processus existants pour le dropdown
     *
     * @return array Liste des processus uniques
     */
    public function getProcessusExistants() {
        $this->console_log("=== RÉCUPÉRATION DES PROCESSUS EXISTANTS ===");

        try {
            $sql = "SELECT DISTINCT Processus FROM Donnees ORDER BY Processus ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $processus = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $this->console_log("Processus trouvés: " . count($processus));
            $this->console_log("Liste: " . implode(', ', $processus));

            return $processus;

        } catch (\PDOException $e) {
            $this->console_log("❌ ERREUR récupération processus: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère la liste des tâches existantes pour un processus donné
     *
     * @param string $processus Nom du processus
     * @return array Liste des tâches pour ce processus
     */
    public function getTachesParProcessus($processus) {
        $this->console_log("=== RÉCUPÉRATION DES TÂCHES POUR: " . $processus . " ===");

        try {
            $sql = "SELECT DISTINCT Tache FROM Donnees WHERE Processus = :processus ORDER BY Tache ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':processus', $processus);
            $stmt->execute();

            $taches = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $this->console_log("Tâches trouvées: " . count($taches));

            return $taches;

        } catch (\PDOException $e) {
            $this->console_log("❌ ERREUR récupération tâches: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retourne une liste de processus prédéfinis (si la BD est vide)
     *
     * @return array Liste des processus par défaut
     */
    public function getProcessusParDefaut() {
        return [
            'CHAUDNQ' => 'Chaudronnerie Non Qualifiée',
            'CHAUDQ' => 'Chaudronnerie Qualifiée',
            'SOUDNQ' => 'Soudure Non Qualifiée',
            'SOUDQ' => 'Soudure Qualifiée',
            'CT' => 'Contrôle',
            'CALC' => 'Calcul',
            'PROJ' => 'Projet',
            'METH' => 'Méthode'
        ];
    }
    /**
    * Supprime une charge de la base de données
    *
    * @param array $donnees Données de la charge à supprimer
    * @return array Résultat de la suppression
    */
    public function supprimerCharge($donnees) {
        $this->console_log("=== DÉBUT SUPPRESSION CHARGE ===");
        $this->console_log("Données reçues: " . json_encode($donnees));

        // Valider les données (même validation que l'ajout)
        $validation = $this->validerDonnees($donnees);
        if (!$validation['success']) {
            $this->console_log("Validation échouée: " . $validation['message']);
            return $validation;
        }

        $this->console_log("Validation réussie");

        try {
            // Vérifier si la ligne existe exactement
            $this->console_log("Recherche de la ligne exacte en base...");
            $existeQuery = "SELECT COUNT(*) as count FROM Donnees WHERE Processus = :processus AND Tache = :tache AND Charge = :charge AND Date = :date";
            $stmtExiste = $this->db->prepare($existeQuery);
            $stmtExiste->bindParam(':processus', $donnees['processus']);
            $stmtExiste->bindParam(':tache', $donnees['tache']);
            $stmtExiste->bindParam(':charge', $donnees['charge']);
            $stmtExiste->bindParam(':date', $donnees['date']);
            $stmtExiste->execute();

            $result = $stmtExiste->fetch(\PDO::FETCH_ASSOC);
            $count = intval($result['count']);

            $this->console_log("Lignes trouvées correspondantes: " . $count);

            if ($count === 0) {
                $this->console_log("Aucune ligne correspondante trouvée");
                return [
                    'success' => false,
                    'message' => "Aucune charge correspondante trouvée en base de données. Vérifiez que tous les champs correspondent exactement à une ligne existante."
                ];
            }

            if ($count > 1) {
                $this->console_log("Plusieurs lignes correspondantes trouvées: " . $count);
                // On peut choisir de supprimer toutes les lignes correspondantes ou s'arrêter
                // Pour la sécurité, on informe l'utilisateur
            }

            // Supprimer la/les ligne(s) correspondante(s)
            $this->console_log("Suppression de " . $count . " ligne(s)...");
            $deleteQuery = "DELETE FROM Donnees WHERE Processus = :processus AND Tache = :tache AND Charge = :charge AND Date = :date";
            $stmtDelete = $this->db->prepare($deleteQuery);
            $stmtDelete->bindParam(':processus', $donnees['processus']);
            $stmtDelete->bindParam(':tache', $donnees['tache']);
            $stmtDelete->bindParam(':charge', $donnees['charge']);
            $stmtDelete->bindParam(':date', $donnees['date']);

            $deleteSuccess = $stmtDelete->execute();
            $rowsAffected = $stmtDelete->rowCount();

            if ($deleteSuccess && $rowsAffected > 0) {
                $this->console_log("Suppression réussie: " . $rowsAffected . " ligne(s) supprimée(s)");

                $message = "Charge supprimée avec succès : {$donnees['processus']} - {$donnees['tache']} ({$donnees['charge']} personne(s)) le {$donnees['date']}";
                if ($rowsAffected > 1) {
                    $message .= " (" . $rowsAffected . " lignes supprimées)";
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'rows_deleted' => $rowsAffected
                ];
            } else {
                $this->console_log("Échec de la suppression");
                return [
                    'success' => false,
                    'message' => "Erreur lors de la suppression en base de données."
                ];
            }

        } catch (\PDOException $e) {
            $this->console_log("ERREUR SQL: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erreur lors de la suppression en base de données : " . $e->getMessage()
            ];
        }
    }

    /**
     * Obtient les suggestions de processus (existants + prédéfinis)
     *
     * @return array Liste combinée des processus
     */
    public function getProcessusSuggestions() {
        $existants = $this->getProcessusExistants();
        $parDefaut = array_keys($this->getProcessusParDefaut());

        // Combiner et dédupliquer
        $suggestions = array_unique(array_merge($existants, $parDefaut));
        sort($suggestions);

        $this->console_log("Suggestions processus: " . implode(', ', $suggestions));

        return $suggestions;
    }

    /**
     * Function pour la journalisation dans la console du navigateur
     *
     * @param string $message Message à logger
     */
    private function console_log($message) {
        echo "<script>console.log('[AjoutChargeModel] " . addslashes($message) . "');</script>";
    }

}