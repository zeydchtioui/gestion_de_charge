<?php
namespace modules\blog\controllers;

use modules\blog\models\DashboardModel;
use modules\blog\models\AjoutChargeModel;
use modules\blog\views\DashboardView;

/**
 * Classe DashboardController
 *
 * Cette classe sert de pont entre DashboardModel, AjoutChargeModel et DashboardView.
 * Elle s'adapte aux méthodes disponibles dans les modèles et gère l'ajout manuel de charges.
 */
class DashboardController {
    private $model;
    private $ajoutChargeModel;
    private $view;

    /**
     * Gère la suppression d'un fichier XLSX converti par numéro d'affaire
     *
     * @param array $userInfo Informations de l'utilisateur
     */
    private function handleDeleteByNumber($userInfo) {
        try {
            echo "<script>console.log('=== TRAITEMENT SUPPRESSION PAR NUMÉRO D\\'AFFAIRE ===');</script>";

            // Récupérer et valider le numéro d'affaire
            $numeroAffaire = trim($_POST['numero_affaire'] ?? '');

            echo "<script>console.log('Numéro d\\'affaire reçu pour suppression: " . addslashes($numeroAffaire) . "');</script>";

            // Validation côté serveur (même validation que pour la conversion)
            $validationResult = $this->validateNumeroAffaire($numeroAffaire);
            if (!$validationResult['success']) {
                echo "<script>console.log('❌ Validation échouée: " . addslashes($validationResult['message']) . "');</script>";

                $dashboardData = $this->prepareDashboardData();
                echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $validationResult);
                return;
            }

            echo "<script>console.log(' Validation réussie');</script>";

            // Lancer la suppression ciblée via le modèle
            $deletionResult = $this->model->deleteConvertedFileByNumber($numeroAffaire);

            if ($deletionResult['success']) {
                echo "<script>console.log(' Suppression et reconstruction réussies');</script>";

                // Forcer le rechargement des données après reconstruction
                $this->model->refreshData();
            } else {
                echo "<script>console.log(' Erreur suppression: " . addslashes($deletionResult['message']) . "');</script>";
            }

            // Préparer les données et afficher avec le résultat
            $dashboardData = $this->prepareDashboardData();
            echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $deletionResult);

        } catch (\Exception $e) {
            echo "<script>console.log(' Exception suppression par numéro: " . addslashes($e->getMessage()) . "');</script>";

            $result = [
                'success' => false,
                'message' => "Erreur lors de la suppression : " . $e->getMessage()
            ];

            $dashboardData = $this->prepareDashboardData();
            echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);
        }
    }

    /**
     * Constructeur du DashboardController
     */
    public function __construct() {
        $this->model = new DashboardModel();
        $this->ajoutChargeModel = new AjoutChargeModel();
        $this->view = new DashboardView();
    }

    /**
     * Gère les actions liées au tableau de bord
     *
     * @param string $action Action à exécuter
     */
    public function handleRequest($action = '') {
        // Récupérer l'ID utilisateur de la session
        $userId = $_SESSION['user_id'] ?? 'Utilisateur';

        // Récupérer les informations de l'utilisateur
        $userInfo = $this->model->getUserInfo($userId);

        // VÉRIFIER SI C'EST UNE ACTION POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postAction = $_POST['action'] ?? '';

            switch ($postAction) {
                case 'add_charge':
                    $this->handleAddCharge($userInfo);
                    return;
                case 'delete_charge':
                    $this->handleDeleteCharge($userInfo);
                    return;
                case 'convert_by_number':
                    $this->handleConvertByNumber($userInfo);
                    return;
                case 'delete_by_number':
                    $this->handleDeleteByNumber($userInfo);
                    return;
            }
        }

        // Vérifier si une action spécifique est demandée
        $subAction = $_GET['subaction'] ?? '';

        switch ($subAction) {
            case 'import':
                $this->handleImport($userInfo);
                break;
            case 'clear_data':
                $this->handleClearData($userInfo);
                break;
            case 'convert_files':
                $this->handleConvertFiles($userInfo);
                break;
            case 'show_all_files':
                $this->handleShowAllFiles($userInfo);
                break;
            default:
                $this->handleDashboard($userInfo);
                break;
        }
    }

    /**
     * Gère la conversion d'un fichier spécifique par numéro d'affaire
     *
     * @param array $userInfo Informations de l'utilisateur
     */
    private function handleConvertByNumber($userInfo) {
        try {
            echo "<script>console.log('=== TRAITEMENT CONVERSION PAR NUMÉRO D\\'AFFAIRE ===');</script>";

            // Récupérer et valider le numéro d'affaire
            $numeroAffaire = trim($_POST['numero_affaire'] ?? '');

            echo "<script>console.log('Numéro d\\'affaire reçu: " . addslashes($numeroAffaire) . "');</script>";

            // Validation côté serveur
            $validationResult = $this->validateNumeroAffaire($numeroAffaire);
            if (!$validationResult['success']) {
                echo "<script>console.log(' Validation échouée: " . addslashes($validationResult['message']) . "');</script>";

                $dashboardData = $this->prepareDashboardData();
                echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $validationResult);
                return;
            }

            echo "<script>console.log(' Validation réussie');</script>";

            // Lancer la conversion ciblée via le modèle
            $conversionResult = $this->model->processConversion($numeroAffaire);

            if ($conversionResult['success']) {
                echo "<script>console.log(' Conversion réussie');</script>";

                // Forcer le rechargement des données après conversion
                $this->model->refreshData();
            } else {
                echo "<script>console.log(' Erreur conversion: " . addslashes($conversionResult['message']) . "');</script>";
            }

            // Préparer les données et afficher avec le résultat
            $dashboardData = $this->prepareDashboardData();
            echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $conversionResult);

        } catch (\Exception $e) {
            echo "<script>console.log(' Exception conversion par numéro: " . addslashes($e->getMessage()) . "');</script>";

            $result = [
                'success' => false,
                'message' => "Erreur lors de la conversion : " . $e->getMessage()
            ];

            $dashboardData = $this->prepareDashboardData();
            echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);
        }
    }

    /**
     * Valide le format du numéro d'affaire
     *
     * @param string $numeroAffaire Numéro à valider
     * @return array Résultat de la validation
     */
    private function validateNumeroAffaire($numeroAffaire) {
        echo "<script>console.log('=== VALIDATION NUMÉRO D\\'AFFAIRE ===');</script>";

        // Vérifier que le champ n'est pas vide
        if (empty($numeroAffaire)) {
            return [
                'success' => false,
                'message' => "Le numéro d'affaire est obligatoire."
            ];
        }

        // Vérifier le format : XX-XX_XXXX (ex: 24-09_0009)
        $pattern = '/^[0-9]{2}-[0-9]{2}_[0-9]{4}$/';
        if (!preg_match($pattern, $numeroAffaire)) {
            return [
                'success' => false,
                'message' => "Format invalide. Le numéro d'affaire doit respecter le format XX-XX_XXXX (ex: 24-09_0009)."
            ];
        }

        // Vérifier la longueur (sécurité supplémentaire)
        if (strlen($numeroAffaire) !== 10) {
            return [
                'success' => false,
                'message' => "Longueur invalide. Le numéro d'affaire doit faire exactement 10 caractères."
            ];
        }

        echo "<script>console.log(' Numéro d\\'affaire valide: " . addslashes($numeroAffaire) . "');</script>";

        return [
            'success' => true,
            'message' => "Numéro d'affaire valide"
        ];
    }

    /**
     * Gère la suppression d'une charge
     *
     * @param array $userInfo Informations de l'utilisateur
     */
    private function handleDeleteCharge($userInfo) {
        try {
            echo "<script>console.log('=== TRAITEMENT SUPPRESSION CHARGE ===');</script>";

            // Récupérer les données du formulaire
            $donnees = [
                'processus' => trim($_POST['processus'] ?? ''),
                'tache' => trim($_POST['tache'] ?? ''),
                'charge' => trim($_POST['charge'] ?? ''),
                'date' => trim($_POST['date'] ?? '')
            ];

            echo "<script>console.log('Données reçues pour suppression: " . addslashes(json_encode($donnees)) . "');</script>";

            // Supprimer la charge via le modèle
            $result = $this->ajoutChargeModel->supprimerCharge($donnees);

            if ($result['success']) {
                echo "<script>console.log(' Suppression réussie');</script>";

                // Forcer le rechargement des données dans ImportModel
                $this->model->refreshData();

                // Préparer les données et afficher avec le résultat de succès
                $dashboardData = $this->prepareDashboardData();
                echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);
            } else {
                echo "<script>console.log('❌ Erreur suppression: " . addslashes($result['message']) . "');</script>";

                // Préparer les données et afficher avec le résultat d'erreur
                $dashboardData = $this->prepareDashboardData();
                echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);
            }

        } catch (\Exception $e) {
            echo "<script>console.log('💥 Exception suppression charge: " . addslashes($e->getMessage()) . "');</script>";

            $result = [
                'success' => false,
                'message' => "Erreur lors de la suppression : " . $e->getMessage()
            ];

            $dashboardData = $this->prepareDashboardData();
            echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);
        }
    }

    /**
     * Gère l'ajout manuel d'une charge
     *
     * @param array $userInfo Informations de l'utilisateur
     */
    private function handleAddCharge($userInfo) {
        try {
            echo "<script>console.log('=== TRAITEMENT AJOUT CHARGE ===');</script>";

            // Récupérer les données du formulaire
            $donnees = [
                'processus' => trim($_POST['processus'] ?? ''),
                'tache' => trim($_POST['tache'] ?? ''),
                'charge' => trim($_POST['charge'] ?? ''),
                'date' => trim($_POST['date'] ?? '')
            ];

            echo "<script>console.log('Données reçues: " . addslashes(json_encode($donnees)) . "');</script>";

            // Ajouter la charge via le modèle
            $result = $this->ajoutChargeModel->ajouterCharge($donnees);

            if ($result['success']) {
                echo "<script>console.log(' Ajout réussi');</script>";

                // Forcer le rechargement des données dans ImportModel
                $this->model->refreshData();

                // Préparer les données et afficher avec le résultat de succès
                $dashboardData = $this->prepareDashboardData();
                echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);
            } else {
                echo "<script>console.log(' Erreur ajout: " . addslashes($result['message']) . "');</script>";

                // Préparer les données et afficher avec le résultat d'erreur
                $dashboardData = $this->prepareDashboardData();
                echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);
            }

        } catch (\Exception $e) {
            echo "<script>console.log(' Exception ajout charge: " . addslashes($e->getMessage()) . "');</script>";

            $result = [
                'success' => false,
                'message' => "Erreur lors de l'ajout : " . $e->getMessage()
            ];

            $dashboardData = $this->prepareDashboardData();
            echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);
        }
    }

    /**
     * Affiche le tableau de bord principal
     *
     * @param array $userInfo Informations de l'utilisateur
     */
    private function handleDashboard($userInfo) {
        try {
            // Préparer les données pour la vue refactorisée
            $dashboardData = $this->prepareDashboardData();

            // Utiliser la méthode showDashboard de la vue refactorisée
            echo $this->view->showDashboard($userInfo, $dashboardData);

        } catch (\Exception $e) {
            echo $this->view->showErrorMessage("Une erreur est survenue : " . $e->getMessage());
        }
    }

    /**
     * Gère l'importation des données depuis la base vers la mémoire
     *
     * @param array $userInfo Informations de l'utilisateur
     */
    private function handleImport($userInfo) {
        try {
            // Utiliser la méthode refreshData() du modèle
            $success = $this->model->refreshData();

            $result = [
                'success' => $success,
                'message' => $success ?
                    "Les données ont été importées avec succès depuis la base de données." :
                    "Erreur lors de l'importation des données."
            ];

            // Préparer les données et afficher avec le résultat
            $dashboardData = $this->prepareDashboardData();
            echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);

        } catch (\Exception $e) {
            echo $this->view->showErrorMessage("Erreur lors de l'importation : " . $e->getMessage());
        }
    }

    /**
     * Gère la conversion des fichiers MPP et leur importation en base
     *
     * @param array $userInfo Informations de l'utilisateur
     */
    private function handleConvertFiles($userInfo) {
        try {
            // Utiliser la méthode processConversion() du modèle
            $conversionResult = $this->model->processConversion();

            // Formater le message de résultat
            $summary = $conversionResult['summary'] ?? [];
            $message = "Conversion terminée:\n";
            $message .= "• Fichiers MPP trouvés: " . ($summary['mpp_found'] ?? 0) . "\n";
            $message .= "• Fichiers MPP convertis: " . ($summary['mpp_converted'] ?? 0) . "\n";
            $message .= "• Erreurs de conversion: " . ($summary['mpp_errors'] ?? 0) . "\n";
            $message .= "• Fichiers XLSX trouvés: " . ($summary['xlsx_found'] ?? 0) . "\n";
            $message .= "• Fichiers XLSX importés: " . ($summary['xlsx_imported'] ?? 0) . "\n";
            $message .= "• Erreurs d'importation: " . ($summary['xlsx_errors'] ?? 0);

            $result = [
                'success' => ($summary['mpp_converted'] ?? 0) > 0 || ($summary['xlsx_imported'] ?? 0) > 0,
                'message' => $message
            ];

            // Préparer les données et afficher avec le résultat
            $dashboardData = $this->prepareDashboardData();
            echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);

        } catch (\Exception $e) {
            echo $this->view->showErrorMessage("Erreur lors de la conversion : " . $e->getMessage());
        }
    }

    /**
     * Gère la suppression des données
     *
     * @param array $userInfo Informations de l'utilisateur
     */
    private function handleClearData($userInfo) {
        try {
            // Utiliser la méthode clearData() du modèle
            $result = $this->model->clearData();

            // Préparer les données et afficher avec le résultat
            $dashboardData = $this->prepareDashboardData();
            echo $this->view->showDashboardWithResult($userInfo, $dashboardData, $result);

        } catch (\Exception $e) {
            echo $this->view->showErrorMessage("Erreur lors de la suppression des données : " . $e->getMessage());
        }
    }

    /**
     * Gère l'affichage de toutes les données
     *
     * @param array $userInfo Informations de l'utilisateur
     */
    private function handleShowAllFiles($userInfo) {
        try {
            // Préparer toutes les données pour l'affichage
            $allData = $this->prepareAllDataForDisplay();

            // Utiliser showAllData de la vue refactorisée
            echo $this->view->showAllData($userInfo, $allData);

        } catch (\Exception $e) {
            echo $this->view->showErrorMessage("Erreur lors de la récupération des données : " . $e->getMessage());
        }
    }

    /**
     * Prépare les données du dashboard au format attendu par la vue refactorisée
     *
     * @return array Données formatées pour DashboardView
     */
    private function prepareDashboardData() {
        $hasData = $this->model->hasData();
        $dataSummary = $this->model->getDataSummary();

        // Préparer les données d'affichage
        $displayData = [];
        $displayTitle = "Aucune donnée disponible";

        if ($hasData) {
            $recentData = $this->model->getRecentData(10);
            $displayData = [
                'Donnees' => [
                    'headers' => ['Processus', 'Tache', 'Charge', 'Date'],
                    'rows' => $recentData
                ]
            ];
            $displayTitle = "Données récentes (" . count($recentData) . " entrées)";
        }

        // Informations sur les fichiers
        $mppFiles = $this->model->getMppFilesList();
        $xlsxFiles = $this->model->getXlsxFilesList();
        $xlsxFilesDetailed = $this->model->getXlsxFilesDetailed();

        // AJOUTER LES SUGGESTIONS DE PROCESSUS
        $processusSuggestions = $this->ajoutChargeModel->getProcessusSuggestions();

        return [
            'summary' => $dataSummary,
            'files_info' => [
                'mpp_count' => count($mppFiles),
                'xlsx_count' => count($xlsxFiles),
                'xlsx_files_detailed' => $xlsxFilesDetailed
            ],
            'display_data' => $displayData,
            'display_title' => $displayTitle,
            'processus_suggestions' => $processusSuggestions
        ];
    }

// Même modification dans prepareAllDataForDisplay() :
    /**
     * Prépare toutes les données pour l'affichage complet
     *
     * @return array Données formatées pour l'affichage de toutes les données
     */
    private function prepareAllDataForDisplay() {
        $allData = $this->model->getAllData();
        $dataSummary = $this->model->getDataSummary();

        $displayData = [];
        if (!empty($allData)) {
            $displayData = [
                'Donnees' => [
                    'headers' => ['Processus', 'Tache', 'Charge', 'Date'],
                    'rows' => $allData
                ]
            ];
        }

        // Informations sur les fichiers
        $mppFiles = $this->model->getMppFilesList();
        $xlsxFiles = $this->model->getXlsxFilesList();
        $xlsxFilesDetailed = $this->model->getXlsxFilesDetailed();

        // AJOUTER LES SUGGESTIONS DE PROCESSUS
        $processusSuggestions = $this->ajoutChargeModel->getProcessusSuggestions();

        return [
            'summary' => $dataSummary,
            'files_info' => [
                'mpp_count' => count($mppFiles),
                'xlsx_count' => count($xlsxFiles),
                'xlsx_files_detailed' => $xlsxFilesDetailed
            ],
            'display_data' => $displayData,
            'display_title' => "Toutes les données (" . count($allData) . " entrées)",
            'processus_suggestions' => $processusSuggestions
        ];
    }
}