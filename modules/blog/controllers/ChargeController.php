<?php
namespace modules\blog\controllers;

use modules\blog\models\DashboardModel;
use modules\blog\models\ChargeModel;
use modules\blog\models\GraphGeneratorModel;
use modules\blog\views\ChargeView;

/**
 * Classe ChargeController
 *
 * Cette classe gère les opérations liées à l'analyse de charge avec sélection libre de période.
 *
 */
class ChargeController {
    private $dashboardModel;
    private $chargeModel;
    private $graphGenerator;
    private $chargeView;

    /**
     * Constructeur du ChargeController
     */
    public function __construct() {
        $this->dashboardModel = new DashboardModel();
        $this->chargeModel = new ChargeModel();
        $this->graphGenerator = new GraphGeneratorModel();
        $this->chargeView = new ChargeView();
    }

    /**
     * Gère les actions liées à l'analyse de charge avec sélection libre de période (affichage par semaines)
     *
     * @param string $action Action à exécuter
     */
    public function handleRequest($action = '') {
        echo "<script>console.log('=== DÉBUT handleRequest AVEC AFFICHAGE PAR SEMAINES ===');</script>";
        echo "<script>console.log('Action reçue: " . addslashes($action) . "');</script>";
        echo "<script>console.log('URL complète: " . addslashes($_SERVER['REQUEST_URI'] ?? 'N/A') . "');</script>";
        echo "<script>console.log('Paramètres GET: " . addslashes(json_encode($_GET)) . "');</script>";

        // Récupérer l'ID utilisateur de la session
        $userId = $_SESSION['user_id'] ?? 'Utilisateur';

        // Récupérer les informations de l'utilisateur
        $userInfo = $this->dashboardModel->getUserInfo($userId);

        try {
            // Vérifier si des données sont disponibles
            if (!$this->dashboardModel->hasData()) {
                echo "<script>console.log(' ERREUR: Aucune donnée disponible');</script>";
                echo $this->chargeView->showErrorMessage("Aucune donnée disponible. Veuillez d'abord convertir et importer des fichiers depuis le tableau de bord.");
                return;
            }

            echo "<script>console.log(' Données disponibles, début du processus...');</script>";

            // ÉTAPE 1 : Récupérer la plage de dates disponibles
            echo "<script>console.log(' ÉTAPE 1: Récupération de la plage de dates disponibles...');</script>";
            $dateRange = $this->chargeModel->getAvailableDateRange();

            if (!$dateRange['has_data']) {
                echo "<script>console.log(' ERREUR: Aucune plage de dates disponible');</script>";
                echo $this->chargeView->showErrorMessage("Aucune donnée disponible pour l'analyse de charge.");
                return;
            }

            echo "<script>console.log(' Plage disponible: " . addslashes($dateRange['date_min']) . " → " . addslashes($dateRange['date_max']) . "');</script>";

            // ÉTAPE 2 : Récupérer et valider les dates de la période sélectionnée
            echo "<script>console.log(' ÉTAPE 2: Traitement de la période sélectionnée...');</script>";
            $periodSelection = $this->handlePeriodSelection($dateRange);

            // ÉTAPE 3 : Analyser toutes les données pour le récapitulatif général
            echo "<script>console.log(' ÉTAPE 3: Analyse complète pour récapitulatif...');</script>";
            $resultatAnalyseComplete = $this->chargeModel->analyserChargeParPeriode();
            $resultatsFormattés = $this->chargeModel->formaterResultats($resultatAnalyseComplete);

            // ÉTAPE 4 : Obtenir un résumé des données pour l'affichage
            $dataSummary = $this->dashboardModel->getDataSummary();
            $fileName = "Données de la base (" . $dataSummary['total_entries'] . " entrées)";

            echo "<script>console.log(' ÉTAPE 4: Affichage de la page avec graphiques par semaines');</script>";

            // Afficher les résultats avec le nouveau système par semaines
            echo $this->chargeView->showChargeAnalysis(
                $userInfo,
                $fileName,
                $resultatsFormattés,
                $periodSelection['periodData'] ?? [],
                $periodSelection['chartPaths'] ?? [],
                $dateRange
            );

        } catch (\Exception $e) {
            echo "<script>console.log('💥 EXCEPTION: " . addslashes($e->getMessage()) . "');</script>";
            echo $this->chargeView->showErrorMessage("Une erreur est survenue : " . $e->getMessage());
        }

        echo "<script>console.log('=== FIN handleRequest AVEC AFFICHAGE PAR SEMAINES ===');</script>";
    }

    /**
     * Gère la sélection et validation de la période libre (avec génération de graphiques par semaines)
     *
     * @param array $dateRange Plage de dates disponibles
     * @return array Données de la période et graphiques générés (par semaines)
     */
    private function handlePeriodSelection($dateRange) {
        echo "<script>console.log('=== TRAITEMENT SÉLECTION PÉRIODE LIBRE (AFFICHAGE SEMAINES) ===');</script>";

        // Récupérer les dates depuis les paramètres GET
        $dateDebut = trim($_GET['date_debut'] ?? '');
        $dateFin = trim($_GET['date_fin'] ?? '');

        echo "<script>console.log('Dates reçues - Début: " . addslashes($dateDebut) . ", Fin: " . addslashes($dateFin) . "');</script>";

        // Si aucune date fournie, ne pas traiter (affichage initial sans graphiques)
        if (empty($dateDebut) || empty($dateFin)) {
            echo "<script>console.log(' Aucune période sélectionnée, affichage initial');</script>";
            return [
                'periodData' => [],
                'chartPaths' => []
            ];
        }

        // Validation des dates
        $validationResult = $this->validatePeriodDates($dateDebut, $dateFin, $dateRange);
        if (!$validationResult['success']) {
            echo "<script>console.log(' Validation période échouée: " . addslashes($validationResult['message']) . "');</script>";
            throw new \Exception($validationResult['message']);
        }

        echo "<script>console.log(' Validation période réussie');</script>";

        // ÉTAPE A : Récupérer les données pour la période sélectionnée (maintenant organisées par semaines)
        echo "<script>console.log(' RÉCUPÉRATION DONNÉES PÉRIODE (CALCUL PAR SEMAINES): " . addslashes($dateDebut) . " → " . addslashes($dateFin) . "');</script>";
        $periodData = $this->chargeModel->getDailyDataForPeriod($dateDebut, $dateFin);

        if (isset($periodData['error'])) {
            echo "<script>console.log(' ERREUR récupération données période: " . addslashes($periodData['error']) . "');</script>";
            throw new \Exception($periodData['error']);
        }

        // INFORMATION PLUS PRÉCISE sur le traitement par semaines
        $nombreSemaines = $periodData['graphiquesData']['periode_info']['nombre_semaines'] ?? 'N/A';
        echo "<script>console.log(' Données période récupérées: " . $periodData['donneesCount'] . " entrées sur " . $periodData['nombreJoursOuvres'] . " jours ouvrés → " . $nombreSemaines . " semaine(s) pour graphiques');</script>";

        // ÉTAPE B : Génération des graphiques pour la période sélectionnée (moyennes par semaines)
        echo "<script>console.log(' GÉNÉRATION DES GRAPHIQUES PAR SEMAINES POUR LA PÉRIODE...');</script>";
        $chartPaths = $this->graphGenerator->generatePeriodCharts($periodData['graphiquesData']);
        echo "<script>console.log(' Graphiques par semaines générés: " . count($chartPaths) . " fichiers');</script>";

        return [
            'periodData' => $periodData,
            'chartPaths' => $chartPaths
        ];
    }

    /**
     * Valide les dates de la période sélectionnée
     *
     * @param string $dateDebut Date de début
     * @param string $dateFin Date de fin
     * @param array $dateRange Plage de dates disponibles
     * @return array Résultat de la validation
     */
    private function validatePeriodDates($dateDebut, $dateFin, $dateRange) {
        echo "<script>console.log('=== VALIDATION DES DATES DE PÉRIODE ===');</script>";

        // Validation format de date
        try {
            $debutObj = new \DateTime($dateDebut);
            $finObj = new \DateTime($dateFin);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Format de date invalide. Veuillez utiliser le format YYYY-MM-DD."
            ];
        }

        // Validation : début doit être antérieur ou égal à la fin
        if ($debutObj > $finObj) {
            return [
                'success' => false,
                'message' => "La date de début doit être antérieure ou égale à la date de fin."
            ];
        }

        // VALIDATION ASSOUPLIE : Permettre des périodes plus larges que les données disponibles
        // On affiche simplement un avertissement informatif, mais on n'empêche pas la sélection
        if ($dateRange['has_data']) {
            $rangeMinObj = new \DateTime($dateRange['date_min']);
            $rangeMaxObj = new \DateTime($dateRange['date_max']);

            $isOutsideRange = ($debutObj < $rangeMinObj || $finObj > $rangeMaxObj);

            if ($isOutsideRange) {
                echo "<script>console.log(' INFO: Période sélectionnée dépasse les données disponibles (" . addslashes($dateRange['date_min_formatted']) . " - " . addslashes($dateRange['date_max_formatted']) . "). Les jours sans données apparaîtront vides.');</script>";
                // Ne pas retourner d'erreur, juste informer
            }
        }

        // Avertissement pour les très longues périodes (> 180 jours)
        $diffTime = $finObj->getTimestamp() - $debutObj->getTimestamp();
        $diffDays = ceil($diffTime / (24 * 60 * 60)) + 1;

        if ($diffDays > 180) {
            echo "<script>console.log(' AVERTISSEMENT: Période très longue (" . $diffDays . " jours) → sera affichée par semaines');</script>";
            // On pourrait ajouter une limitation ici si nécessaire
        }

        echo "<script>console.log(' Dates validées: " . addslashes($dateDebut) . " → " . addslashes($dateFin) . " (" . $diffDays . " jours) → affichage par semaines');</script>";

        return [
            'success' => true,
            'message' => "Dates valides",
            'nombre_jours' => $diffDays
        ];
    }

    /**
     * Valide une date individuelle
     *
     * @param string $dateStr Date à valider
     * @param string $fieldName Nom du champ pour le message d'erreur
     * @return array Résultat de la validation
     */
    private function validateSingleDate($dateStr, $fieldName) {
        if (empty($dateStr)) {
            return [
                'success' => false,
                'message' => "Le champ '$fieldName' est obligatoire."
            ];
        }

        try {
            $dateObj = new \DateTime($dateStr);
            // Vérifier que la chaîne correspond exactement au format attendu
            if ($dateObj->format('Y-m-d') !== $dateStr) {
                throw new \Exception("Format incorrect");
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Format de date invalide pour '$fieldName'. Utilisez le format YYYY-MM-DD."
            ];
        }

        return [
            'success' => true,
            'message' => "Date valide",
            'date_object' => $dateObj
        ];
    }

    /**
     * Gère les cas d'erreur de données manquantes
     *
     * @param string $userErrorMessage Message d'erreur pour l'utilisateur
     * @param string $debugMessage Message de debug pour la console
     */
    private function handleDataError($userErrorMessage, $debugMessage = '') {
        if (!empty($debugMessage)) {
            echo "<script>console.log('❌ " . addslashes($debugMessage) . "');</script>";
        }
        echo $this->chargeView->showErrorMessage($userErrorMessage);
    }

    /**
     * Log une étape du processus
     *
     * @param string $step Nom de l'étape
     * @param string $message Message de l'étape
     * @param bool $success Succès ou non (pour l'icône)
     */
    private function logStep($step, $message, $success = true) {
        $icon = $success ? '✓' : '❌';
        echo "<script>console.log('" . $icon . " [" . addslashes($step) . "] " . addslashes($message) . "');</script>";
    }

    /**
     * Obtient des informations de debug sur la période
     *
     * @param string $dateDebut Date de début
     * @param string $dateFin Date de fin
     * @return array Informations calculées
     */
    private function calculatePeriodInfo($dateDebut, $dateFin) {
        try {
            $debutObj = new \DateTime($dateDebut);
            $finObj = new \DateTime($dateFin);

            $diffTime = $finObj->getTimestamp() - $debutObj->getTimestamp();
            $totalDays = ceil($diffTime / (24 * 60 * 60)) + 1;

            // Estimation approximative des jours ouvrés (5/7 des jours)
            $estimatedWorkingDays = floor($totalDays * 5 / 7);
            // Estimation du nombre de semaines pour affichage
            $estimatedWeeks = ceil($estimatedWorkingDays / 5);

            return [
                'total_days' => $totalDays,
                'estimated_working_days' => $estimatedWorkingDays,
                'estimated_weeks' => $estimatedWeeks,
                'period_length' => $totalDays <= 7 ? 'courte' : ($totalDays <= 30 ? 'moyenne' : 'longue')
            ];
        } catch (\Exception $e) {
            return [
                'total_days' => 0,
                'estimated_working_days' => 0,
                'estimated_weeks' => 0,
                'period_length' => 'invalide'
            ];
        }
    }
}