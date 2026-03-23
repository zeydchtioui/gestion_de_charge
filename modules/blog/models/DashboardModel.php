<?php
namespace modules\blog\models;

/**
 * Classe DashboardModel
 *
 * Cette classe gère les données nécessaires pour le tableau de bord
 * en déléguant les tâches aux modèles spécialisés.
 */
class DashboardModel {
    /**
     * @var ImportModel $importModel Instance pour récupérer les données
     */
    private $importModel;

    /**
     * @var LectureDossierModel $lectureDossierModel Instance pour la conversion
     */
    private $lectureDossierModel;

    /**
     * Constructeur du DashboardModel
     */
    public function __construct() {
        // Utiliser le Singleton pour ImportModel
        $this->importModel = ImportModel::getInstance();
        $this->lectureDossierModel = new LectureDossierModel();
    }

    /**
     * Récupère les informations de l'utilisateur
     *
     * @param string $userId ID de l'utilisateur
     * @return array Informations de l'utilisateur
     */
    public function getUserInfo($userId) {
        // Informations basiques de l'utilisateur
        return [
            'id' => $userId,
            'nom' => $userId // Utiliser l'ID comme nom par défaut
        ];
    }

    /**
     * Récupère toutes les données depuis la base de données
     *
     * @return array Données de la table Donnees
     */
    public function getAllData() {
        return $this->importModel->getAllData();
    }

    /**
     * Vérifie si des données sont présentes
     *
     * @return bool True si des données existent
     */
    public function hasData() {
        return $this->importModel->hasData();
    }

    /**
     * Récupère les statistiques des données pour le dashboard
     *
     * @return array Statistiques basiques
     */
    public function getDataSummary() {
        $donnees = $this->importModel->getAllData();

        if (empty($donnees)) {
            return [
                'total_entries' => 0,
                'date_debut' => null,
                'date_fin' => null,
                'processus_uniques' => 0
            ];
        }

        $dates = array_column($donnees, 'Date');
        $processus = array_unique(array_column($donnees, 'Processus'));

        return [
            'total_entries' => count($donnees),
            'date_debut' => min($dates),
            'date_fin' => max($dates),
            'processus_uniques' => count($processus)
        ];
    }

    /**
     * Lance le processus de conversion ciblée par numéro d'affaire
     *
     * @param string $numeroAffaire Numéro d'affaire pour conversion ciblée (obligatoire)
     * @return array Résultat du processus complet
     */
    public function processConversion($numeroAffaire) {
        if (empty($numeroAffaire)) {
            return [
                'success' => false,
                'message' => 'Le numéro d\'affaire est obligatoire pour la conversion ciblée.'
            ];
        }

        // Conversion ciblée par numéro d'affaire
        $result = $this->lectureDossierModel->processFileByNumber($numeroAffaire);

        // Forcer le rechargement des données après la conversion
        $this->importModel->refreshData();

        return $result;
    }

    /**
     *Supprime un fichier XLSX converti par numéro d'affaire et reconstruit la BD
     *
     * @param string $numeroAffaire Numéro d'affaire du fichier à supprimer
     * @return array Résultat de la suppression et reconstruction
     */
    public function deleteConvertedFileByNumber($numeroAffaire) {
        $result = [
            'success' => false,
            'message' => '',
            'numero_affaire' => $numeroAffaire,
            'file_deletion' => null,
            'database_clear' => null,
            'reconstruction' => null
        ];

        try {
            // Étape 1 : Supprimer le fichier via LectureDossierModel
            $fileDeletionResult = $this->lectureDossierModel->deleteConvertedFileByNumber($numeroAffaire);
            $result['file_deletion'] = $fileDeletionResult;

            if (!$fileDeletionResult['success']) {
                $result['message'] = $fileDeletionResult['message'];
                return $result;
            }

            // Étape 2 : Vider la base de données via ImportModel
            $databaseClearResult = $this->importModel->clearTable();
            $result['database_clear'] = $databaseClearResult;

            if (!$databaseClearResult) {
                $result['message'] = "Fichier supprimé mais erreur lors du vidage de la base de données.";
                return $result;
            }

            // Étape 3 : Obtenir la liste des fichiers XLSX restants
            $remainingFiles = $this->lectureDossierModel->getAllConvertedFiles();

            // Étape 4 : Réimporter tous les fichiers restants
            $reconstructionResult = $this->reconstructDatabaseFromFiles($remainingFiles);
            $result['reconstruction'] = $reconstructionResult;

            if ($reconstructionResult['success']) {
                $result['success'] = true;
                $result['message'] = sprintf(
                    "Suppression et reconstruction terminées avec succès !\n\n" .
                    "🗑️ Fichier supprimé : %s\n" .
                    "💾 Base de données vidée et reconstruite\n" .
                    "📁 Fichiers XLSX restants traités : %d\n" .
                    "📊 Nouvelles entrées importées : %d\n" .
                    "⚠️ Erreurs d'importation : %d",
                    $fileDeletionResult['deleted_file']['name'],
                    $reconstructionResult['files_processed'],
                    $reconstructionResult['total_imported'],
                    $reconstructionResult['total_errors']
                );
            } else {
                $result['message'] = "Fichier supprimé et base vidée, mais erreur lors de la reconstruction : " . $reconstructionResult['message'];
            }

        } catch (\Exception $e) {
            $result['message'] = "Erreur inattendue lors du processus : " . $e->getMessage();
        }

        return $result;
    }

    /**
     *Reconstruit la base de données à partir d'une liste de fichiers XLSX
     *
     * @param array $files Liste des fichiers à importer
     * @return array Résultat de la reconstruction
     */
    private function reconstructDatabaseFromFiles($files) {
        $result = [
            'success' => false,
            'message' => '',
            'files_processed' => 0,
            'total_imported' => 0,
            'total_errors' => 0,
            'details' => []
        ];

        try {
            if (empty($files)) {
                $result['success'] = true;
                $result['message'] = "Aucun fichier XLSX à réimporter";
                return $result;
            }

            // Initialiser ExcelToBdModel pour l'importation
            $excelToBdModel = new \modules\blog\models\ExcelToBdModel();

            // Réimporter chaque fichier XLSX
            foreach ($files as $fileInfo) {
                $importResult = $excelToBdModel->importExcelToDatabase($fileInfo['path']);
                $result['files_processed']++;

                if ($importResult['success']) {
                    $result['total_imported'] += $importResult['importCount'];
                } else {
                    $result['total_errors']++;
                }

                $result['details'][] = [
                    'file' => $fileInfo['name'],
                    'success' => $importResult['success'],
                    'imported' => $importResult['importCount'] ?? 0,
                    'message' => $importResult['message']
                ];
            }

            $result['success'] = true;
            $result['message'] = sprintf(
                "Réimportation terminée: %d fichier(s) traité(s), %d entrée(s) importée(s), %d erreur(s)",
                $result['files_processed'],
                $result['total_imported'],
                $result['total_errors']
            );

        } catch (\Exception $e) {
            $result['message'] = "Erreur lors de la réimportation : " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Vide la table de données
     *
     * @return array Résultat de l'opération
     */
    public function clearData() {
        $success = $this->importModel->clearTable();

        return [
            'success' => $success,
            'message' => $success ?
                "La table de données a été vidée avec succès." :
                "Erreur lors de la suppression des données."
        ];
    }

    /**
     * Force le rechargement des données depuis la base
     *
     * @return bool Succès du rechargement
     */
    public function refreshData() {
        return $this->importModel->refreshData();
    }

    /**
     * Récupère des données limitées pour l'affichage (compatibilité)
     *
     * @param int $limit Nombre d'entrées à retourner
     * @return array Données limitées
     */
    public function getRecentData($limit = 50) {
        $donnees = $this->importModel->getAllData();

        // Trier par date décroissante et limiter
        usort($donnees, function($a, $b) {
            return strcmp($b['Date'], $a['Date']);
        });

        return array_slice($donnees, 0, $limit);
    }

    /**
     * Obtenir la liste des fichiers MPP disponibles (pour info)
     *
     * @return array Liste des fichiers MPP
     */
    public function getMppFilesList() {
        return $this->lectureDossierModel->getMppFiles();
    }

    /**
     * Obtenir la liste des fichiers XLSX convertis (pour info)
     *
     * @return array Liste des fichiers XLSX
     */
    public function getXlsxFilesList() {
        return $this->lectureDossierModel->getXlsxFiles();
    }

    /**
     * Obtenir la liste détaillée des fichiers XLSX convertis (avec numéros d'affaire)
     *
     * @return array Liste détaillée des fichiers XLSX
     */
    public function getXlsxFilesDetailed() {
        return $this->lectureDossierModel->getXlsxFilesDetailed();
    }
}