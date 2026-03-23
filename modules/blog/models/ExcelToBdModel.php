<?php
namespace modules\blog\models;

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;

/**
 * Classe ExcelToBdModel
 *
 * Cette classe gère l'importation des données d'un fichier Excel de planification
 * vers la base de données. Elle traite les données de tâches et d'affectations
 * pour les stocker dans la table Donnees.
 */
class ExcelToBdModel {
    /**
     * @var \PDO $db Connexion à la base de données
     */
    private $db;

    /**
     * Constructeur de ExcelToBdModel
     */
    public function __construct() {
        $this->db = SingletonModel::getInstance()->getConnection();
    }

    /**
     * Importe les données d'un fichier XLSX de planification dans la base de données
     *
     * @param string $filePath Chemin vers le fichier XLSX
     * @return array Résultat de l'importation
     */
    public function importExcelToDatabase($filePath) {
        try {
            // Vérifier que le fichier existe
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'message' => "Le fichier n'existe pas : " . $filePath,
                    'file' => basename($filePath)
                ];
            }

            $this->console_log("=== DÉBUT IMPORTATION ===");
            $this->console_log("Fichier: " . basename($filePath));

            // Lire le fichier Excel
            $excelData = $this->readExcelFile($filePath);

            if (!$excelData) {
                return [
                    'success' => false,
                    'message' => "Impossible de lire le fichier Excel",
                    'file' => basename($filePath)
                ];
            }

            $this->console_log("Nombre de feuilles trouvées: " . count($excelData));

            // Extraire les données des feuilles
            $taches = $this->extractTasksData($excelData);
            $affectations = $this->extractAssignmentsData($excelData);

            $this->console_log("Tâches extraites: " . count($taches));
            $this->console_log("Affectations extraites: " . count($affectations));

            if (empty($taches)) {
                return [
                    'success' => false,
                    'message' => "Aucune tâche trouvée dans le fichier",
                    'file' => basename($filePath)
                ];
            }

            if (empty($affectations)) {
                return [
                    'success' => false,
                    'message' => "Aucune affectation trouvée dans le fichier",
                    'file' => basename($filePath)
                ];
            }

            // Croiser les données et calculer les attributions par jour
            $donnees = $this->calculateDailyAssignments($taches, $affectations);

            $this->console_log("Données quotidiennes calculées: " . count($donnees));

            // Importer les données dans la base
            $importResult = $this->insertDataToDatabase($donnees);

            $this->console_log("=== FIN IMPORTATION ===");

            return [
                'success' => true,
                'message' => $importResult['message'],
                'file' => basename($filePath),
                'importCount' => $importResult['importCount'],
                'errorCount' => $importResult['errorCount']
            ];

        } catch (\Exception $e) {
            $this->console_log("ERREUR: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erreur lors de l'importation : " . $e->getMessage(),
                'file' => basename($filePath)
            ];
        }
    }

    /**
     * Lit un fichier Excel et retourne son contenu
     *
     * @param string $filePath Chemin vers le fichier Excel
     * @return array|false Données du fichier Excel ou false en cas d'erreur
     */
    private function readExcelFile($filePath) {
        try {
            $reader = ReaderEntityFactory::createReaderFromFile($filePath);
            $reader->open($filePath);

            $data = [];
            $sheetIndex = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                $sheetData = [];
                $headers = [];
                $isFirstRow = true;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowData = $row->toArray();

                    if ($isFirstRow) {
                        // Nettoyer les en-têtes (enlever espaces en début/fin)
                        $headers = array_map('trim', $rowData);
                        $isFirstRow = false;
                    } else {
                        $formattedRow = [];
                        foreach ($rowData as $cellIndex => $cellValue) {
                            if (isset($headers[$cellIndex])) {
                                $formattedRow[$headers[$cellIndex]] = $cellValue;
                            }
                        }
                        $sheetData[] = $formattedRow;
                    }
                }

                $data[$sheetIndex] = [
                    'name' => $sheet->getName(),
                    'headers' => $headers,
                    'rows' => $sheetData
                ];

                $this->console_log("Feuille " . $sheetIndex . ": " . $sheet->getName() . " (" . count($sheetData) . " lignes)");
                $this->console_log("En-têtes: " . implode(', ', $headers));

                $sheetIndex++;
            }

            $reader->close();
            return $data;

        } catch (IOException | ReaderNotOpenedException $e) {
            $this->console_log("Erreur lecture Excel : " . $e->getMessage());
            error_log("Erreur lecture Excel : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extrait les données des tâches de la feuille Task_Table
     *
     * @param array $excelData Données du fichier Excel
     * @return array Données des tâches avec dates
     */
    private function extractTasksData($excelData) {
        $this->console_log("=== EXTRACTION DES TÂCHES ===");

        // Chercher la feuille Task_Table
        $taskSheet = null;
        foreach ($excelData as $sheet) {
            if ($sheet['name'] === 'Task_Table') {
                $taskSheet = $sheet;
                break;
            }
        }

        if (!$taskSheet) {
            $this->console_log("Feuille Task_Table non trouvée");
            return [];
        }

        $this->console_log("Feuille Task_Table trouvée avec " . count($taskSheet['rows']) . " lignes");

        $taches = [];
        foreach ($taskSheet['rows'] as $row) {
            // Chercher les colonnes nécessaires (flexibilité pour les noms)
            $nom = $row['Nom de la tâche'] ?? $row['Task Name'] ?? null;
            $debut = $row['Start'] ?? $row['Début'] ?? null;
            $fin = $row['Finish'] ?? $row['Fin'] ?? null;

            // Ignorer les lignes vides ou invalides
            if (empty($nom) || empty($debut) || empty($fin)) {
                continue;
            }

            $this->console_log("Tâche trouvée: " . $nom . " (" . $debut . " → " . $fin . ")");

            $taches[] = [
                'nom' => trim($nom),
                'debut' => $this->parseDate($debut),
                'fin' => $this->parseDate($fin)
            ];
        }

        $this->console_log("Total tâches extraites: " . count($taches));
        return $taches;
    }

    /**
     * Extrait les données d'affectation de la feuille Assignment_Table
     *
     * @param array $excelData Données du fichier Excel
     * @return array Données des affectations
     */
    private function extractAssignmentsData($excelData) {
        $this->console_log("=== EXTRACTION DES AFFECTATIONS ===");

        // Chercher la feuille Assignment_Table
        $assignmentSheet = null;
        foreach ($excelData as $sheet) {
            if ($sheet['name'] === 'Assignment_Table') {
                $assignmentSheet = $sheet;
                break;
            }
        }

        if (!$assignmentSheet) {
            $this->console_log("Feuille Assignment_Table non trouvée");
            return [];
        }

        $this->console_log("Feuille Assignment_Table trouvée avec " . count($assignmentSheet['rows']) . " lignes");

        $affectations = [];
        foreach ($assignmentSheet['rows'] as $row) {
            // Chercher les colonnes nécessaires
            $tache = $row['Task name'] ?? $row['Nom de la tâche'] ?? null;
            $ressource = $row['Resource name'] ?? $row['Nom de la ressource'] ?? null;
            $work = $row['Work'] ?? $row['Travail'] ?? null;
            $duration = $row['Duration'] ?? $row['Durée'] ?? null;

            // Ignorer les lignes vides
            if (empty($tache) || empty($ressource) || empty($work) || empty($duration)) {
                continue;
            }

            // Calculer la charge
            $charge = $this->calculateCharge($work, $duration);

            $this->console_log("Affectation: " . $tache . " → " . $ressource . " (" . $work . "/" . $duration . " = " . $charge . ")");

            $affectations[] = [
                'tache' => trim($tache),
                'ressource' => trim($ressource),
                'charge' => $charge
            ];
        }

        $this->console_log("Total affectations extraites: " . count($affectations));
        return $affectations;
    }

    /**
     * Calcule la charge à partir du work et de la duration
     *
     * @param string $work Ex: "70h", "14 hrs"
     * @param string $duration Ex: "10 days", "2 days"
     * @return float Charge calculée (nombre de personnes)
     */
    private function calculateCharge($work, $duration) {
        // Extraire les heures du work
        preg_match('/(\d+(?:\.\d+)?)/', $work, $workMatches);
        $heures = isset($workMatches[1]) ? floatval($workMatches[1]) : 0;

        // Extraire les jours de la duration
        preg_match('/(\d+(?:\.\d+)?)/', $duration, $durationMatches);
        $jours = isset($durationMatches[1]) ? floatval($durationMatches[1]) : 1;

        if ($jours == 0) {
            return 0;
        }

        // Calculer heures par jour
        $heuresParJour = $heures / $jours;

        // Calculer la charge (1 personne = 7h/jour)
        $charge = $heuresParJour / 7;

        $this->console_log("Calcul charge: " . $heures . "h ÷ " . $jours . "j = " . $heuresParJour . "h/j ÷ 7 = " . $charge);

        return $charge;
    }

    /**
     * Calcule les attributions quotidiennes en croisant tâches et affectations
     *
     * @param array $taches Liste des tâches avec dates
     * @param array $affectations Liste des affectations avec charges calculées
     * @return array Données formatées pour insertion en base
     */
    private function calculateDailyAssignments($taches, $affectations) {
        $this->console_log("=== CALCUL DES ATTRIBUTIONS QUOTIDIENNES ===");

        $donnees = [];

        // Pour chaque tâche, calculer les jours d'attribution
        foreach ($taches as $tache) {
            $this->console_log("Traitement tâche: " . $tache['nom']);

            // Trouver les affectations correspondantes à cette tâche
            $affectationsTache = array_filter($affectations, function($affectation) use ($tache) {
                return $affectation['tache'] === $tache['nom'];
            });

            if (empty($affectationsTache)) {
                $this->console_log("Aucune affectation trouvée pour: " . $tache['nom']);
                continue;
            }

            $this->console_log("Affectations trouvées: " . count($affectationsTache));

            // Pour chaque affectation de cette tâche
            foreach ($affectationsTache as $affectation) {
                // Calculer tous les jours ouvrés entre début et fin
                $joursOuvres = $this->getWorkingDaysBetween($tache['debut'], $tache['fin']);

                $this->console_log("Jours ouvrés pour " . $tache['nom'] . ": " . count($joursOuvres));

                // Créer une entrée pour chaque jour ouvré avec la charge calculée
                foreach ($joursOuvres as $jour) {
                    $donnees[] = [
                        'processus' => substr($affectation['ressource'], 0, 40), // Limité à 40 caractères
                        'tache' => substr($tache['nom'], 0, 200), // Limité à 200 caractères
                        'charge' => $affectation['charge'], // Charge calculée (nombre de personnes)
                        'date' => $jour->format('Y-m-d')
                    ];
                }
            }
        }

        $this->console_log("Total entrées quotidiennes générées: " . count($donnees));
        return $donnees;
    }

    /**
     * Insère les données dans la base de données
     *
     * @param array $donnees Données à insérer
     * @return array Résultat de l'insertion
     */
    private function insertDataToDatabase($donnees) {
        $this->console_log("=== INSERTION EN BASE ===");

        $stmt = $this->db->prepare("INSERT INTO Donnees (Processus, Tache, Charge, Date) VALUES (:processus, :tache, :charge, :date)");

        $importCount = 0;
        $errorCount = 0;

        foreach ($donnees as $donnee) {
            try {
                $stmt->bindParam(':processus', $donnee['processus']);
                $stmt->bindParam(':tache', $donnee['tache']);
                $stmt->bindParam(':charge', $donnee['charge']);
                $stmt->bindParam(':date', $donnee['date']);
                $stmt->execute();
                $importCount++;
            } catch (\PDOException $e) {
                $errorCount++;
                $this->console_log("Erreur insertion: " . $e->getMessage());
                error_log("Erreur insertion BD: " . $e->getMessage() . " - Données: " . json_encode($donnee));
            }
        }

        $this->console_log("Insertion terminée: " . $importCount . " réussies, " . $errorCount . " erreurs");

        return [
            'importCount' => $importCount,
            'errorCount' => $errorCount,
            'message' => "Importation terminée. $importCount entrées importées, $errorCount erreurs."
        ];
    }

    /**
     * Parse une date depuis le format du fichier Excel GroupDocs
     *
     * @param string $dateStr Date sous forme de chaîne (ex: "Mon 3/17/25")
     * @return \DateTime|null Objet DateTime ou null si parsing échoue
     */
    private function parseDate($dateStr) {
        try {
            // Format GroupDocs: "Mon 3/17/25"
            // Enlever le jour de la semaine si présent
            $dateStr = preg_replace('/^[A-Za-z]{3}\s+/', '', trim($dateStr));

            // Essayer différents formats
            $formats = [
                'm/d/y',    // 3/17/25
                'm/d/Y',    // 3/17/2025
                'd/m/y',    // 17/3/25
                'd/m/Y',    // 17/3/2025
                'Y-m-d',    // 2025-03-17
                'd-m-Y'     // 17-03-2025
            ];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $dateStr);
                if ($date !== false) {
                    $this->console_log("Date parsée: " . $dateStr . " → " . $date->format('Y-m-d'));
                    return $date;
                }
            }

            // Si aucun format ne marche, essayer avec strtotime
            $timestamp = strtotime($dateStr);
            if ($timestamp !== false) {
                $date = new \DateTime();
                $date->setTimestamp($timestamp);
                $this->console_log("Date parsée (strtotime): " . $dateStr . " → " . $date->format('Y-m-d'));
                return $date;
            }

        } catch (\Exception $e) {
            $this->console_log("Erreur parsing date '$dateStr': " . $e->getMessage());
            error_log("Erreur parsing date '$dateStr': " . $e->getMessage());
        }

        return null;
    }

    /**
     * Obtient tous les jours ouvrés entre deux dates (exclut weekends)
     *
     * @param \DateTime $debut Date de début
     * @param \DateTime $fin Date de fin
     * @return array Liste des jours ouvrés
     */
    private function getWorkingDaysBetween($debut, $fin) {
        if (!$debut || !$fin) {
            return [];
        }

        $joursOuvres = [];
        $current = clone $debut;

        while ($current <= $fin) {
            // Exclure samedi (6) et dimanche (7)
            if ($current->format('N') < 6) {
                $joursOuvres[] = clone $current;
            }
            $current->add(new \DateInterval('P1D'));
        }

        return $joursOuvres;
    }

    /**
     * Function pour la journalisation dans la console du navigateur
     *
     * @param string $message Message à logger
     */
    private function console_log($message) {
        echo "<script>console.log('[ExcelToBdModel] " . addslashes($message) . "');</script>";
    }
}