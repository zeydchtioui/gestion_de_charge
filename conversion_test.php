<?php
// Utiliser le chemin relatif pour l'autoloader
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Script de test pour le stockage GroupDocs Cloud
 *
 * Ce script teste la connexion au stockage GroupDocs Cloud en:
 * 1. Téléchargeant un fichier MPP vers le cloud
 * 2. Téléchargeant immédiatement ce même fichier depuis le cloud
 *
 * Le but est de vérifier si le stockage fonctionne correctement sans tenter
 * de conversion, pour isoler les problèmes potentiels.
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
$outputFileName = "succes.mpp";
$outputFilePath = $outputFolder . "/" . $outputFileName;
$xlsxOutputPath = $outputFolder . "/" . pathinfo($outputFileName, PATHINFO_FILENAME) . ".xlsx";

// Vérifier que le fichier source existe
if (!file_exists($inputFilePath)) {
    log_message("ERREUR: Le fichier source n'existe pas: " . $inputFilePath);
    exit;
}

log_message("Démarrage du test de stockage GroupDocs Cloud");
log_message("Fichier source: " . $inputFilePath);
log_message("Destination après téléchargement: " . $outputFilePath);
log_message("Destination xlsx après conversion: " . $xlsxOutputPath);

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
    $cloudFileName = "TestConversion.mpp";
    $cloudOutputFileName = "succes.mpp";
    $cloudxlsxFileName = "succes.xlsx";

    log_message("Stockage cible: " . $storageName);
    log_message("Nom du fichier dans le cloud: " . $cloudFileName);

    // 2. Télécharger le fichier vers le cloud
    log_message("Téléchargement du fichier vers le cloud...");
    $uploadRequest = new GroupDocs\Conversion\Model\Requests\UploadFileRequest(
        $cloudFileName,
        $inputFilePath,
        $storageName
    );

    $uploadResult = $fileApi->uploadFile($uploadRequest);
    log_message("Fichier téléchargé avec succès vers le cloud");

    // 3. Télécharger immédiatement le même fichier depuis le cloud
    log_message("Préparation au téléchargement du fichier depuis le cloud...");
    $downloadRequest = new GroupDocs\Conversion\Model\Requests\DownloadFileRequest(
        $cloudFileName,
        $storageName,
        null
    );

    log_message("Téléchargement du fichier en cours...");
    $response = $fileApi->downloadFile($downloadRequest);
    log_message("Fichier téléchargé depuis le cloud avec succès");

    // 4. Enregistrer le fichier téléchargé localement
    log_message("Enregistrement du fichier localement...");
    copy($response->getPathName(), $outputFilePath);
    log_message("Fichier enregistré avec succès dans: " . $outputFilePath);

    // 5. Vérifier si le fichier a été correctement enregistré
    if (file_exists($outputFilePath)) {
        $sourceSize = filesize($inputFilePath);
        $downloadedSize = filesize($outputFilePath);

        log_message("Taille du fichier source: " . $sourceSize . " octets");
        log_message("Taille du fichier téléchargé: " . $downloadedSize . " octets");

        if ($sourceSize == $downloadedSize) {
            log_message("TEST RÉUSSI: Les fichiers source et téléchargé ont la même taille");
        } else {
            log_message("AVERTISSEMENT: Les fichiers source et téléchargé ont des tailles différentes");
        }
    } else {
        log_message("ERREUR: Le fichier téléchargé n'a pas été trouvé sur le disque local");
    }

    log_message("Test de stockage GroupDocs Cloud terminé avec succès!");

    // 6. Télécharger le fichier succes.mpp vers le cloud pour la conversion
    log_message("Téléchargement du fichier succes.mpp vers le cloud pour conversion...");
    $uploadSuccessRequest = new GroupDocs\Conversion\Model\Requests\UploadFileRequest(
        $cloudOutputFileName,
        $outputFilePath,
        $storageName
    );

    $uploadSuccessResult = $fileApi->uploadFile($uploadSuccessRequest);
    log_message("Fichier succes.mpp téléchargé avec succès vers le cloud");

    // 7. Configurer les paramètres pour la conversion en xlsx
    log_message("Configuration des paramètres de conversion en xlsx...");
    $settings = new GroupDocs\Conversion\Model\ConvertSettings();
    $settings->setStorageName($storageName);
    $settings->setFilePath($cloudOutputFileName);
    $settings->setFormat("xlsx");
    $settings->setOutputPath("succes.xlsx");

    // 8. Lancer la conversion
    log_message("Démarrage de la conversion en xlsx...");
    $result = $convertApi->convertDocument(
        new GroupDocs\Conversion\Model\Requests\ConvertDocumentRequest($settings)
    );
    log_message("Conversion en xlsx terminée");

    // 9. Attendre que la conversion soit terminée
    log_message("Attente de 15 secondes pour s'assurer que la conversion est terminée...");
    sleep(15);

    // 10. Télécharger le fichier xlsx converti
    log_message("Téléchargement du fichier xlsx converti...");
    $downloadxlsxRequest = new GroupDocs\Conversion\Model\Requests\DownloadFileRequest(
        $cloudxlsxFileName,
        $storageName,
        null
    );

    $xlsxResponse = $fileApi->downloadFile($downloadxlsxRequest);
    log_message("Fichier xlsx téléchargé depuis le cloud avec succès");

    // 11. Enregistrer le fichier xlsx localement
    log_message("Enregistrement du fichier xlsx localement...");
    copy($xlsxResponse->getPathName(), $xlsxOutputPath);
    log_message("Fichier xlsx enregistré avec succès dans: " . $xlsxOutputPath);

    // 12. Vérifier si le fichier xlsx a été correctement enregistré
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

log_message("Fin du test de stockage et de conversion");
?>