<?php
namespace modules\blog\models;

/**
 * Classe LectureDossierModel
 *
 * Cette classe gère la lecture automatique des dossiers de planification,
 * la conversion des fichiers MPP en XLSX, et l'importation des données
 * dans la base de données.
 */
class LectureDossierModel {

    /**
     * Supprime un fichier XLSX converti par numéro d'affaire (fichier uniquement)
     * Respecte le MVC : ne touche que aux fichiers
     *
     * @param string $numeroAffaire Numéro d'affaire du fichier à supprimer
     * @return array Résultat de la suppression de fichier
     */
    public function deleteConvertedFileByNumber($numeroAffaire) {

        $result = [
            'success' => false,
            'message' => '',
            'numero_affaire' => $numeroAffaire,
            'file_found' => null,
            'deleted_file' => null
        ];

        try {
            // Rechercher le fichier XLSX correspondant dans le dossier converted
            $this->console_log("=== RECHERCHE DANS LE DOSSIER CONVERTED ===");
            $foundFile = $this->findXlsxFileByNumber($numeroAffaire);

            if (!$foundFile) {


                $result['message'] = "Aucun fichier XLSX converti trouvé contenant le numéro d'affaire \"$numeroAffaire\" dans le dossier converted.";
                return $result;
            }


            $result['file_found'] = $foundFile;

            // Supprimer le fichier XLSX


            if (!unlink($foundFile['path'])) {


                $result['message'] = "Erreur lors de la suppression du fichier \"" . $foundFile['name'] . "\". Vérifiez les permissions.";
                return $result;
            }



            $result['success'] = true;
            $result['deleted_file'] = $foundFile;
            $result['message'] = "Fichier \"" . $foundFile['name'] . "\" supprimé avec succès.";

        } catch (\Exception $e) {


            $result['message'] = "Erreur inattendue lors de la suppression : " . $e->getMessage();
        }



        return $result;
    }

    /**
     *  Récupère tous les fichiers XLSX dans le dossier converted
     * Explore tous les sous-dossiers de /converted
     *
     * @return array Liste des fichiers XLSX avec leurs informations
     */
    public function getAllConvertedFiles() {
        $this->console_log("=== RÉCUPÉRATION RÉCURSIVE FICHIERS CONVERTED ===");

        $convertedFiles = [];

        try {
            // Vérifier que le dossier converted existe
            if (!is_dir(self::XLSX_OUTPUT_FOLDER)) {
                $this->console_log("❌ Dossier converted introuvable: " . self::XLSX_OUTPUT_FOLDER);
                return $convertedFiles;
            }

            // Collecte récursive de tous les fichiers XLSX convertis
            $this->collectXlsxFilesRecursively(self::XLSX_OUTPUT_FOLDER, $convertedFiles);

            $this->console_log("Total fichiers XLSX converted (récursif): " . count($convertedFiles));

            // Trier par date de modification (plus récents en premier)
            usort($convertedFiles, function($a, $b) {
                return $b['modified'] - $a['modified'];
            });

        } catch (\Exception $e) {
            $this->console_log("💥 EXCEPTION récupération fichiers: " . $e->getMessage());
        }

        return $convertedFiles;
    }

    /**
     * Recherche un fichier XLSX dans le dossier converted par numéro d'affaire
     * Explore tous les sous-dossiers de /converted
     *
     * @param string $numeroAffaire Numéro d'affaire à rechercher
     * @return array|null Informations du fichier trouvé ou null
     */
    private function findXlsxFileByNumber($numeroAffaire) {
        $this->console_log("=== RECHERCHE RÉCURSIVE FICHIER XLSX PAR NUMÉRO ===");
        $this->console_log("Recherche de: " . $numeroAffaire);

        // Vérifier que le dossier converted existe
        if (!is_dir(self::XLSX_OUTPUT_FOLDER)) {
            $this->console_log(" Dossier converted introuvable: " . self::XLSX_OUTPUT_FOLDER);
            return null;
        }

        // Recherche récursive dans tous les sous-dossiers (max 3 niveaux pour converted)
        $foundFile = $this->searchXlsxRecursively(self::XLSX_OUTPUT_FOLDER, $numeroAffaire, 3);

        if ($foundFile) {
            $relativePath = str_replace(self::XLSX_OUTPUT_FOLDER, '', dirname($foundFile['path']));
            $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);
            $location = $relativePath ? "dans sous-dossier: " . $relativePath : "à la racine";

            $this->console_log(" TROUVÉ! Fichier XLSX: " . $foundFile['name'] . " " . $location);
        } else {
            $this->console_log(" Aucun fichier XLSX trouvé contenant le numéro: " . $numeroAffaire);
        }

        return $foundFile;
    }

    /**
     * Fonction récursive pour explorer tous les dossiers XLSX
     *
     * @param string $directory Dossier à explorer
     * @param string $numeroAffaire Numéro d'affaire recherché
     * @param int $maxDepth Profondeur maximale
     * @param int $currentDepth Profondeur actuelle
     * @return array|null Fichier trouvé ou null
     */
    private function searchXlsxRecursively($directory, $numeroAffaire, $maxDepth = 3, $currentDepth = 0) {
        // Sécurité : limiter la profondeur
        if ($currentDepth >= $maxDepth) {
            $this->console_log(" Profondeur maximale atteinte (" . $maxDepth . ") pour: " . basename($directory));
            return null;
        }

        $indentLevel = str_repeat("  ", $currentDepth);
        $this->console_log($indentLevel . " Exploration XLSX niveau $currentDepth: " . basename($directory));

        if (!is_dir($directory) || !is_readable($directory)) {
            $this->console_log($indentLevel . " Dossier inaccessible: " . $directory);
            return null;
        }

        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $directory . DIRECTORY_SEPARATOR . $item;

            // Si c'est un fichier XLSX, vérifier le numéro
            if (is_file($itemPath) && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'xlsx') {
                $this->console_log($indentLevel . " Fichier XLSX: " . $item);

                if ($this->fileContainsNumber($item, $numeroAffaire)) {
                    $this->console_log($indentLevel . " MATCH XLSX TROUVÉ! Numéro dans: " . $item);

                    return [
                        'name' => $item,
                        'path' => $itemPath,
                        'size' => filesize($itemPath),
                        'modified' => filemtime($itemPath),
                        'directory' => $directory,
                        'depth' => $currentDepth,
                        'relative_path' => str_replace(self::XLSX_OUTPUT_FOLDER, '', $directory)
                    ];
                }
            }
            // Si c'est un dossier, explorer récursivement
            elseif (is_dir($itemPath)) {
                $this->console_log($indentLevel . " Sous-dossier XLSX: " . $item);

                $result = $this->searchXlsxRecursively($itemPath, $numeroAffaire, $maxDepth, $currentDepth + 1);
                if ($result) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Dossier source contenant les fichiers MPP (à la racine du projet)
     */
    const MPP_SOURCE_FOLDER = __DIR__ . '/../../../uploads';

    /**
     * Dossier de destination pour les fichiers XLSX convertis
     */
    const XLSX_OUTPUT_FOLDER = __DIR__ . '/../../../converted';

    /**
     * Instance du converteur MPP
     */
    private $mppConverter;

    /**
     * Instance du transporteur XLSX vers BD
     */
    private $excelToBdModel;

    /**
     * Constructeur
     */
    public function __construct() {
        $this->console_log("=== INITIALISATION DE LectureDossierModel ===");
        $this->console_log("Dossier source MPP: " . realpath(self::MPP_SOURCE_FOLDER));
        $this->console_log("Dossier destination XLSX: " . realpath(self::XLSX_OUTPUT_FOLDER));

        // Initialiser le converteur MPP avec le dossier de destination
        $this->mppConverter = new MppConverterModel(self::XLSX_OUTPUT_FOLDER);

        // Initialiser le transporteur XLSX vers BD
        $this->excelToBdModel = new ExcelToBdModel();

        // S'assurer que les dossiers existent
        $this->ensureDirectoriesExist();

        $this->console_log("=== INITIALISATION TERMINÉE ===");
    }

    /**
     * Lance la conversion d'un fichier spécifique par numéro d'affaire
     *
     * @param string $numeroAffaire Numéro d'affaire à rechercher (ex: "24-09_0009")
     * @return array Résultat détaillé du processus
     */
    public function processFileByNumber($numeroAffaire) {

        $result = [
            'success' => false,
            'message' => '',
            'numero_affaire' => $numeroAffaire,
            'file_found' => null,
            'conversion' => null,
            'importation' => null
        ];

        try {
            // Étape 1 : Rechercher le fichier MPP correspondant

            $foundFile = $this->findMppFileByNumber($numeroAffaire);

            if (!$foundFile) {


                $result['message'] = "Aucun fichier MPP trouvé contenant le numéro d'affaire \"$numeroAffaire\" dans le dossier uploads.";
                return $result;
            }


            $result['file_found'] = $foundFile;

            // Étape 2 : Convertir le fichier MPP vers XLSX


            $conversionResult = $this->mppConverter->convertMppToXlsx($foundFile['path']);
            $result['conversion'] = $conversionResult;

            if (!$conversionResult['success']) {


                $result['message'] = "Erreur lors de la conversion du fichier \"" . $foundFile['name'] . "\" : " . $conversionResult['message'];
                return $result;
            }



            // Étape 3 : Importer le fichier XLSX en base de données


            $importationResult = $this->excelToBdModel->importExcelToDatabase($conversionResult['outputPath']);
            $result['importation'] = $importationResult;

            if (!$importationResult['success']) {


                $result['message'] = "Conversion réussie mais erreur lors de l'importation : " . $importationResult['message'];
                return $result;
            }



            // Succès complet
            $result['success'] = true;
            $result['message'] = sprintf(
                "Conversion réussie !\n" .
                "• Fichier trouvé : %s\n" .
                "• Fichier converti : %s\n" .
                "• Entrées importées : %d\n" .
                "• Erreurs : %d",
                $foundFile['name'],
                $conversionResult['outputFile'],
                $importationResult['importCount'],
                $importationResult['errorCount']
            );



        } catch (\Exception $e) {


            $result['message'] = "Erreur inattendue lors du processus : " . $e->getMessage();
        }



        return $result;
    }

    /**
     * Recherche un fichier MPP par numéro d'affaire (VERSION RÉCURSIVE)
     * Explore tous les sous-dossiers de /uploads avec limite de profondeur
     *
     * @param string $numeroAffaire Numéro d'affaire à rechercher
     * @return array|null Informations du fichier trouvé ou null
     */
    private function findMppFileByNumber($numeroAffaire) {


        // Vérifier que le dossier source existe
        if (!is_dir(self::MPP_SOURCE_FOLDER)) {

            return null;
        }

        // Recherche récursive dans tous les sous-dossiers (max 5 niveaux de profondeur)
        $foundFile = $this->searchMppRecursively(self::MPP_SOURCE_FOLDER, $numeroAffaire, 5);

        if ($foundFile) {
            $relativePath = str_replace(self::MPP_SOURCE_FOLDER, '', dirname($foundFile['path']));
            $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);
            $location = $relativePath ? "dans sous-dossier: " . $relativePath : "à la racine";


        } else {

        }

        return $foundFile;
    }

    /**
     * Fonction récursive pour explorer tous les dossiers MPP
     * Recherche avec limite de profondeur pour éviter les boucles infinies
     *
     * @param string $directory Dossier à explorer
     * @param string $numeroAffaire Numéro d'affaire recherché
     * @param int $maxDepth Profondeur maximale (par défaut 5 niveaux)
     * @param int $currentDepth Profondeur actuelle
     * @return array|null Fichier trouvé ou null
     */
    private function searchMppRecursively($directory, $numeroAffaire, $maxDepth = 5, $currentDepth = 0) {
        // Sécurité : limiter la profondeur pour éviter les boucles infinies et les performances dégradées
        if ($currentDepth >= $maxDepth) {
            $this->console_log(" Profondeur maximale atteinte (" . $maxDepth . ") pour: " . basename($directory));
            return null;
        }

        $indentLevel = str_repeat("  ", $currentDepth); // Indentation pour le debug
        $this->console_log($indentLevel . " Exploration niveau $currentDepth: " . basename($directory));

        if (!is_dir($directory) || !is_readable($directory)) {
            $this->console_log($indentLevel . " Dossier inaccessible: " . $directory);
            return null;
        }

        $items = scandir($directory);
        $fileCount = 0;
        $dirCount = 0;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $directory . DIRECTORY_SEPARATOR . $item;

            // Si c'est un fichier MPP, vérifier le numéro
            if (is_file($itemPath) && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'mpp') {
                $fileCount++;
                $this->console_log($indentLevel . " Fichier MPP: " . $item);

                if ($this->fileContainsNumber($item, $numeroAffaire)) {
                    $this->console_log($indentLevel . " MATCH TROUVÉ! Numéro dans: " . $item);

                    return [
                        'name' => $item,
                        'path' => $itemPath,
                        'size' => filesize($itemPath),
                        'modified' => filemtime($itemPath),
                        'directory' => $directory,
                        'depth' => $currentDepth,
                        'relative_path' => str_replace(self::MPP_SOURCE_FOLDER, '', $directory)
                    ];
                } else {
                    $this->console_log($indentLevel . " Pas de match dans: " . $item);
                }
            }
            // Si c'est un dossier, explorer récursivement
            elseif (is_dir($itemPath)) {
                $dirCount++;
                $this->console_log($indentLevel . " Sous-dossier détecté: " . $item);

                $result = $this->searchMppRecursively($itemPath, $numeroAffaire, $maxDepth, $currentDepth + 1);
                if ($result) {
                    return $result; // Retourner dès qu'on trouve un match
                }
            }
        }

        $this->console_log($indentLevel . " Niveau $currentDepth terminé - Fichiers MPP: $fileCount, Sous-dossiers: $dirCount");
        return null; // Rien trouvé dans ce dossier et ses sous-dossiers
    }

    /**
     * Vérifie si un nom de fichier contient le numéro d'affaire
     *
     * @param string $fileName Nom du fichier
     * @param string $numeroAffaire Numéro d'affaire à chercher
     * @return bool True si le numéro est trouvé
     */
    private function fileContainsNumber($fileName, $numeroAffaire) {
        $this->console_log("Vérification: '" . $numeroAffaire . "' dans '" . $fileName . "'");

        // Recherche exacte du numéro d'affaire dans le nom du fichier
        $found = (strpos($fileName, $numeroAffaire) !== false);

        $this->console_log("Résultat: " . ($found ? "TROUVÉ" : "PAS TROUVÉ"));

        return $found;
    }



    /**
     * S'assure que les dossiers nécessaires existent
     */
    private function ensureDirectoriesExist() {
        $this->console_log("=== VÉRIFICATION DES DOSSIERS ===");

        if (!is_dir(self::MPP_SOURCE_FOLDER)) {
            $this->console_log("Création du dossier source : " . self::MPP_SOURCE_FOLDER);
            $this->log_message("Création du dossier source : " . self::MPP_SOURCE_FOLDER);
            mkdir(self::MPP_SOURCE_FOLDER, 0777, true);
        } else {
            $this->console_log("Dossier source OK : " . self::MPP_SOURCE_FOLDER);
        }

        if (!is_dir(self::XLSX_OUTPUT_FOLDER)) {
            $this->console_log("Création du dossier de destination : " . self::XLSX_OUTPUT_FOLDER);
            $this->log_message("Création du dossier de destination : " . self::XLSX_OUTPUT_FOLDER);
            mkdir(self::XLSX_OUTPUT_FOLDER, 0777, true);
        } else {
            $this->console_log("Dossier destination OK : " . self::XLSX_OUTPUT_FOLDER);
        }
    }

    /**
     * Function pour la journalisation avec timestamp
     *
     * @param string $message Message à logger
     */
    private function log_message($message) {
        $timestamp = date('[Y-m-d H:i:s]');
        echo $timestamp . " " . $message . "\n";
    }

    /**
     * Function pour la journalisation dans la console du navigateur
     *
     * @param string $message Message à logger
     */
    private function console_log($message) {
        echo "<script>console.log('[LectureDossierModel] " . addslashes($message) . "');</script>";
    }

    /**
     * Retourne la liste des fichiers MPP dans le dossier source
     * Explore tous les sous-dossiers de /uploads
     *
     * @return array Liste des fichiers MPP
     */
    public function getMppFiles() {
        $this->console_log("=== getMppFiles() RÉCURSIF ===");

        $mppFiles = [];

        if (!is_dir(self::MPP_SOURCE_FOLDER)) {
            $this->console_log("Dossier source introuvable: " . self::MPP_SOURCE_FOLDER);
            return $mppFiles;
        }

        // Collecte récursive de tous les fichiers MPP
        $this->collectMppFilesRecursively(self::MPP_SOURCE_FOLDER, $mppFiles);

        $this->console_log("Total fichiers MPP trouvés (récursif): " . count($mppFiles));

        // Trier par nom pour un affichage cohérent
        usort($mppFiles, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $mppFiles;
    }

    /**
     * Collecte récursivement tous les fichiers MPP
     *
     * @param string $directory Dossier à explorer
     * @param array &$mppFiles Tableau de fichiers MPP (passé par référence)
     * @param int $maxDepth Profondeur maximale
     * @param int $currentDepth Profondeur actuelle
     */
    private function collectMppFilesRecursively($directory, &$mppFiles, $maxDepth = 5, $currentDepth = 0) {
        // Sécurité : limiter la profondeur
        if ($currentDepth >= $maxDepth) {
            return;
        }

        if (!is_dir($directory) || !is_readable($directory)) {
            return;
        }

        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $directory . DIRECTORY_SEPARATOR . $item;

            // Si c'est un fichier MPP, l'ajouter à la liste
            if (is_file($itemPath) && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'mpp') {
                $relativePath = str_replace(self::MPP_SOURCE_FOLDER, '', $directory);
                $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);

                $this->console_log("Fichier MPP trouvé: " . $item .
                    ($relativePath ? " (dans: " . $relativePath . ")" : " (racine)"));

                $mppFiles[] = [
                    'name' => $item,
                    'path' => $itemPath,
                    'size' => filesize($itemPath),
                    'modified' => filemtime($itemPath),
                    'directory' => $directory,
                    'relative_path' => $relativePath ?: '/',
                    'depth' => $currentDepth
                ];
            }
            // Si c'est un dossier, explorer récursivement
            elseif (is_dir($itemPath)) {
                $this->collectMppFilesRecursively($itemPath, $mppFiles, $maxDepth, $currentDepth + 1);
            }
        }
    }

    /**
     * Retourne la liste des fichiers XLSX dans le dossier de destination (VERSION RÉCURSIVE)
     * Explore tous les sous-dossiers de /converted
     *
     * @return array Liste des fichiers XLSX
     */
    public function getXlsxFiles() {
        $this->console_log("=== getXlsxFiles() RÉCURSIF ===");

        $xlsxFiles = [];

        if (!is_dir(self::XLSX_OUTPUT_FOLDER)) {
            $this->console_log("Dossier destination introuvable: " . self::XLSX_OUTPUT_FOLDER);
            return $xlsxFiles;
        }

        // Collecte récursive de tous les fichiers XLSX
        $this->collectXlsxFilesRecursively(self::XLSX_OUTPUT_FOLDER, $xlsxFiles);

        $this->console_log("Total fichiers XLSX trouvés (récursif): " . count($xlsxFiles));

        // Trier par nom pour un affichage cohérent
        usort($xlsxFiles, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $xlsxFiles;
    }

    /**
     * Collecte récursivement tous les fichiers XLSX
     *
     * @param string $directory Dossier à explorer
     * @param array &$xlsxFiles Tableau de fichiers XLSX (passé par référence)
     * @param int $maxDepth Profondeur maximale
     * @param int $currentDepth Profondeur actuelle
     */
    private function collectXlsxFilesRecursively($directory, &$xlsxFiles, $maxDepth = 3, $currentDepth = 0) {
        // Sécurité : limiter la profondeur
        if ($currentDepth >= $maxDepth) {
            return;
        }

        if (!is_dir($directory) || !is_readable($directory)) {
            return;
        }

        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $directory . DIRECTORY_SEPARATOR . $item;

            // Si c'est un fichier XLSX, l'ajouter à la liste
            if (is_file($itemPath) && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'xlsx') {
                $relativePath = str_replace(self::XLSX_OUTPUT_FOLDER, '', $directory);
                $relativePath = trim($relativePath, DIRECTORY_SEPARATOR);

                $this->console_log("Fichier XLSX trouvé: " . $item .
                    ($relativePath ? " (dans: " . $relativePath . ")" : " (racine)"));

                $xlsxFiles[] = [
                    'name' => $item,
                    'path' => $itemPath,
                    'size' => filesize($itemPath),
                    'modified' => filemtime($itemPath),
                    'directory' => $directory,
                    'relative_path' => $relativePath ?: '/',
                    'depth' => $currentDepth
                ];
            }
            // Si c'est un dossier, explorer récursivement
            elseif (is_dir($itemPath)) {
                $this->collectXlsxFilesRecursively($itemPath, $xlsxFiles, $maxDepth, $currentDepth + 1);
            }
        }
    }

    /**
     * Retourne la liste détaillée des fichiers XLSX avec numéro d'affaire et nom extrait
     * Explore tous les sous-dossiers de /converted
     *
     * @return array Liste des fichiers XLSX avec détails (numéro d'affaire, nom propre, etc.)
     */
    public function getXlsxFilesDetailed() {
        $this->console_log("=== getXlsxFilesDetailed() RÉCURSIF ===");

        $xlsxFilesDetailed = [];

        if (!is_dir(self::XLSX_OUTPUT_FOLDER)) {
            $this->console_log("Dossier destination introuvable: " . self::XLSX_OUTPUT_FOLDER);
            return $xlsxFilesDetailed;
        }

        // Récupérer tous les fichiers XLSX (méthode récursive)
        $xlsxFiles = [];
        $this->collectXlsxFilesRecursively(self::XLSX_OUTPUT_FOLDER, $xlsxFiles);

        $this->console_log("Fichiers XLSX trouvés pour analyse détaillée: " . count($xlsxFiles));

        foreach ($xlsxFiles as $file) {
            $this->console_log("Analyse détaillée du fichier: " . $file['name']);

            // Extraire le numéro d'affaire et le nom propre
            $fileDetails = $this->extractFileDetails($file['name']);

            $xlsxFilesDetailed[] = [
                'name' => $file['name'],
                'path' => $file['path'],
                'size' => $file['size'],
                'size_formatted' => $this->formatFileSize($file['size']),
                'modified' => $file['modified'],
                'modified_formatted' => date('d/m/Y H:i', $file['modified']),
                'numero_affaire' => $fileDetails['numero_affaire'],
                'nom_propre' => $fileDetails['nom_propre'],
                'has_numero' => $fileDetails['has_numero'],
                'directory' => $file['directory'],
                'relative_path' => $file['relative_path'],
                'depth' => $file['depth']
            ];
        }

        // Trier par numéro d'affaire puis par nom
        usort($xlsxFilesDetailed, function($a, $b) {
            if ($a['has_numero'] && $b['has_numero']) {
                return strcmp($a['numero_affaire'], $b['numero_affaire']);
            } elseif ($a['has_numero']) {
                return -1; // Fichiers avec numéro en premier
            } elseif ($b['has_numero']) {
                return 1;
            } else {
                return strcmp($a['name'], $b['name']);
            }
        });

        $this->console_log("Total fichiers XLSX détaillés (récursif): " . count($xlsxFilesDetailed));
        return $xlsxFilesDetailed;
    }

    /**
     * Extrait le numéro d'affaire et le nom propre d'un nom de fichier
     *
     * @param string $filename Nom du fichier (ex: "AFF24-09_0009 planning en cours.xlsx")
     * @return array Détails extraits
     */
    private function extractFileDetails($filename) {
        $this->console_log("=== extractFileDetails pour: " . $filename . " ===");

        // Supprimer l'extension
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        // Regex pour trouver le pattern XX-XX_XXXX (numéro d'affaire)
        $pattern = '/(\d{2}-\d{2}_\d{4})/';

        $details = [
            'numero_affaire' => null,
            'nom_propre' => $nameWithoutExt,
            'has_numero' => false
        ];

        if (preg_match($pattern, $nameWithoutExt, $matches)) {
            $numeroAffaire = $matches[1];
            $details['numero_affaire'] = $numeroAffaire;
            $details['has_numero'] = true;

            // Extraire le nom propre en supprimant le préfixe et le numéro d'affaire
            $nomPropre = $nameWithoutExt;

            // Supprimer le préfixe "AFF" s'il existe
            $nomPropre = preg_replace('/^AFF/', '', $nomPropre);

            // Supprimer le numéro d'affaire
            $nomPropre = str_replace($numeroAffaire, '', $nomPropre);

            // Nettoyer les espaces multiples et trim
            $nomPropre = trim(preg_replace('/\s+/', ' ', $nomPropre));

            // Si le nom propre est vide après nettoyage, utiliser un nom par défaut
            if (empty($nomPropre)) {
                $nomPropre = "planning";
            }

            $details['nom_propre'] = $nomPropre;

            $this->console_log("Numéro d'affaire trouvé: " . $numeroAffaire);
            $this->console_log("Nom propre extrait: " . $nomPropre);
        } else {
            $this->console_log("Aucun numéro d'affaire trouvé dans: " . $filename);
            // Le nom propre reste le nom complet du fichier sans extension
        }

        return $details;
    }

    /**
     *  Formate la taille d'un fichier en octets de manière lisible
     *
     * @param int $bytes Taille en octets
     * @return string Taille formatée (ex: "1.2 MB")
     */
    private function formatFileSize($bytes) {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = 0;

        while ($bytes >= 1024 && $power < count($units) - 1) {
            $bytes /= 1024;
            $power++;
        }

        return round($bytes, 1) . ' ' . $units[$power];
    }
}