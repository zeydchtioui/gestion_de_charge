<?php
namespace modules\blog\models;

// Utiliser le chemin relatif pour l'autoloader
require_once __DIR__ . '/../../../vendor/autoload.php';

/**
 * Classe MppConverterModel
 *
 * Cette classe encapsule la logique de conversion des fichiers MPP vers XLSX
 * en utilisant l'API GroupDocs Cloud. Elle reprend exactement la même logique
 * que convertMppFile.php mais permet de spécifier le fichier à convertir.
 */
class MppConverterModel {

    /**
     * Client Id et Secret pour l'API GroupDocs
     */
    private $myClientId = "e77d5a47-1328-475f-a39a-037d2f258bdd";
    private $myClientSecret = "0adba4bdd2f4bb5ac80fa4fb4ccf8e33";

    /**
     * Nom du stockage GroupDocs
     */
    private $storageName = "Chargeconversion";

    /**
     * Dossier de sortie pour les fichiers convertis
     */
    private $outputFolder;

    /**
     * APIs GroupDocs
     */
    private $fileApi;
    private $convertApi;

    /**
     * Constructeur
     *
     * @param string $outputFolder Dossier de destination (par défaut: uploads)
     */
    public function __construct($outputFolder = null) {
        $this->outputFolder = $outputFolder ?: __DIR__ . '/../../../uploads';

        // S'assurer que le dossier de sortie existe
        if (!is_dir($this->outputFolder)) {
            mkdir($this->outputFolder, 0777, true);
        }

        // Initialiser l'API GroupDocs
        $this->initializeApi();
    }

    /**
     * Initialise l'API GroupDocs
     */
    private function initializeApi() {
        $configuration = new \GroupDocs\Conversion\Configuration();
        $configuration->setAppSid($this->myClientId);
        $configuration->setAppKey($this->myClientSecret);
        $this->fileApi = new \GroupDocs\Conversion\FileApi($configuration);
        $this->convertApi = new \GroupDocs\Conversion\ConvertApi($configuration);
    }

    /**
     * Convertit un fichier MPP vers XLSX
     *
     * @param string $inputFilePath Chemin vers le fichier MPP à convertir
     * @return array Résultat de la conversion
     */
    public function convertMppToXlsx($inputFilePath) {
        // Vérifier que le fichier source existe
        if (!file_exists($inputFilePath)) {
            return [
                'success' => false,
                'message' => "ERREUR: Le fichier source n'existe pas: " . $inputFilePath
            ];
        }

        // Vérifier l'extension du fichier
        $fileExtension = strtolower(pathinfo($inputFilePath, PATHINFO_EXTENSION));
        if ($fileExtension !== 'mpp') {
            return [
                'success' => false,
                'message' => "ERREUR: Le fichier n'est pas au format MPP: " . $inputFilePath
            ];
        }

        // Chemins et noms de fichiers
        $cloudFileName = basename($inputFilePath);
        $cloudOutputFileName = pathinfo($cloudFileName, PATHINFO_FILENAME) . ".xlsx";
        $xlsxOutputPath = $this->outputFolder . DIRECTORY_SEPARATOR . $cloudOutputFileName;



        try {
            // 1. Télécharger le fichier vers le cloud

            $uploadRequest = new \GroupDocs\Conversion\Model\Requests\UploadFileRequest(
                $cloudFileName,
                $inputFilePath,
                $this->storageName
            );

            $uploadResult = $this->fileApi->uploadFile($uploadRequest);


            // 2. Configurer les paramètres pour la conversion en xlsx
            $settings = new \GroupDocs\Conversion\Model\ConvertSettings();
            $settings->setStorageName($this->storageName);
            $settings->setFilePath($cloudFileName);
            $settings->setFormat("xlsx");
            $settings->setOutputPath($cloudOutputFileName);

            // 3. Lancer la conversion

            $result = $this->convertApi->convertDocument(
                new \GroupDocs\Conversion\Model\Requests\ConvertDocumentRequest($settings)
            );


            // 4. Attendre que la conversion soit terminée

            sleep(15);

            // 5. Télécharger le fichier xlsx converti

            $downloadxlsxRequest = new \GroupDocs\Conversion\Model\Requests\DownloadFileRequest(
                $cloudOutputFileName,
                $this->storageName,
                null
            );

            $xlsxResponse = $this->fileApi->downloadFile($downloadxlsxRequest);


            // 6. Enregistrer le fichier xlsx localement

            copy($xlsxResponse->getPathName(), $xlsxOutputPath);


            // 7. Vérifier si le fichier xlsx a été correctement enregistré
            if (file_exists($xlsxOutputPath)) {
                $xlsxSize = filesize($xlsxOutputPath);


                return [
                    'success' => true,
                    'message' => "Conversion réussie: " . basename($inputFilePath) . " converti en " . $cloudOutputFileName,
                    'outputPath' => $xlsxOutputPath,
                    'outputFile' => $cloudOutputFileName,
                    'size' => $xlsxSize
                ];
            } else {

                return [
                    'success' => false,
                    'message' => "ERREUR: Le fichier xlsx n'a pas été trouvé sur le disque local"
                ];
            }

        } catch (\Exception $e) {
            $errorMessage = "ERREUR: " . $e->getMessage();
            $this->log_message($errorMessage);

            // Afficher des détails supplémentaires sur l'erreur
            if (method_exists($e, 'getCode')) {
                $this->log_message("Code d'erreur: " . $e->getCode());
            }

            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                $this->log_message("Détails de la réponse: " . $e->getResponse()->getBody()->getContents());
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'details' => [
                    'code' => method_exists($e, 'getCode') ? $e->getCode() : null,
                    'response' => ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) ?
                        $e->getResponse()->getBody()->getContents() : null
                ]
            ];
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
     * Définit le dossier de sortie
     *
     * @param string $outputFolder Nouveau dossier de sortie
     */
    public function setOutputFolder($outputFolder) {
        $this->outputFolder = $outputFolder;

        // S'assurer que le dossier existe
        if (!is_dir($this->outputFolder)) {
            mkdir($this->outputFolder, 0777, true);
        }
    }

    /**
     * Retourne le dossier de sortie actuel
     *
     * @return string Dossier de sortie
     */
    public function getOutputFolder() {
        return $this->outputFolder;
    }
}