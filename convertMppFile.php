<?php
// Utiliser le chemin relatif pour l'autoloader
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Script simplifié pour la conversion de fichiers MPP vers XLSX
 *
 * Ce script effectue les étapes suivantes:
 * 1. Téléchargement d'un fichier MPP vers le cloud
 * 2. Conversion directe du fichier en XLSX
 * 3. Téléchargement du résultat converti
 */

// Client Id et Secret pour l'API GroupDocs
$myClientId = "e77d5a47-1328-475f-a39a-037d2f258bdd";
$myClientSecret = "0adba4bdd2f4bb5ac80fa4fb4ccf8e33";

// Function pour la journalisation avec timestamp
function log_message($message) {
    $timestamp = date('[Y-m-d H:i:s]');
    echo $timestamp . " " . $message . "\n";
}

// Chemins de fichiers
$inputFilePath = __DIR__ . '/uploads/TestConversion.mpp';
$outputFolder = __DIR__ . '/uploads';
$xlsxOutputPath = $outputFolder . "/" . pathinfo(basename($inputFilePath), PATHINFO_FILENAME) . ".xlsx";

// Vérifier que le fichier source existe
if (!file_exists($inputFilePath)) {
    log_message("ERREUR: Le fichier source n'existe pas: " . $inputFilePath);
    exit;
}

log_message("Démarrage de la conversion MPP vers XLSX");
log_message("Fichier source: " . $inputFilePath);
log_message("Destination après conversion: " . $xlsxOutputPath);

// Create instance of the API
log_message("Initialisation de l'API GroupDocs");
$configuration = new GroupDocs\Conversion\Configuration();
$configuration->setAppSid($myClientId);
$configuration->setAppKey($myClientSecret);
$fileApi = new GroupDocs\Conversion\FileApi($configuration);
$convertApi = new GroupDocs\Conversion\ConvertApi($configuration);

try {
    // 1. Définir le nom du stockage et du fichier
    $storageName = "Chargeconversion";
    $cloudFileName = basename($inputFilePath);
    $cloudOutputFileName = pathinfo($cloudFileName, PATHINFO_FILENAME) . ".xlsx";

    log_message("Stockage cible: " . $storageName);
    log_message("Nom du fichier dans le cloud: " . $cloudFileName);
    log_message("Nom du fichier converti: " . $cloudOutputFileName);

    // 2. Télécharger le fichier vers le cloud
    log_message("Téléchargement du fichier vers le cloud...");
    $uploadRequest = new GroupDocs\Conversion\Model\Requests\UploadFileRequest(
        $cloudFileName,
        $inputFilePath,
        $storageName
    );

    $uploadResult = $fileApi->uploadFile($uploadRequest);
    log_message("Fichier téléchargé avec succès vers le cloud");

    // 3. Configurer les paramètres pour la conversion en xlsx
    log_message("Configuration des paramètres de conversion en xlsx...");
    $settings = new GroupDocs\Conversion\Model\ConvertSettings();
    $settings->setStorageName($storageName);
    $settings->setFilePath($cloudFileName);
    $settings->setFormat("xlsx");
    $settings->setOutputPath($cloudOutputFileName);

    // 4. Lancer la conversion
    log_message("Démarrage de la conversion en xlsx...");
    $result = $convertApi->convertDocument(
        new GroupDocs\Conversion\Model\Requests\ConvertDocumentRequest($settings)
    );
    log_message("Conversion en xlsx terminée");

    // 5. Attendre que la conversion soit terminée
    log_message("Attente de 15 secondes pour s'assurer que la conversion est terminée...");
    sleep(15);

    // 6. Télécharger le fichier xlsx converti
    log_message("Téléchargement du fichier xlsx converti...");
    $downloadxlsxRequest = new GroupDocs\Conversion\Model\Requests\DownloadFileRequest(
        $cloudOutputFileName,
        $storageName,
        null
    );

    $xlsxResponse = $fileApi->downloadFile($downloadxlsxRequest);
    log_message("Fichier xlsx téléchargé depuis le cloud avec succès");

    // 7. Enregistrer le fichier xlsx localement
    log_message("Enregistrement du fichier xlsx localement...");
    copy($xlsxResponse->getPathName(), $xlsxOutputPath);
    log_message("Fichier xlsx enregistré avec succès dans: " . $xlsxOutputPath);

    // 8. Vérifier si le fichier xlsx a été correctement enregistré
    if (file_exists($xlsxOutputPath)) {
        $xlsxSize = filesize($xlsxOutputPath);
        log_message("Taille du fichier xlsx: " . $xlsxSize . " octets");
        log_message("CONVERSION xlsx RÉUSSIE!");
    } else {
        log_message("ERREUR: Le fichier xlsx n'a pas été trouvé sur le disque local");
    }

} catch (Exception $e) {
    log_message("ERREUR: " . $e->getMessage());

    // Afficher des détails supplémentaires sur l'erreur
    if (method_exists($e, 'getCode')) {
        log_message("Code d'erreur: " . $e->getCode());
    }

    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        log_message("Détails de la réponse: " . $e->getResponse()->getBody()->getContents());
    }
}

log_message("Fin du processus de conversion");
?>