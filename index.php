<?php

// Inclure l'autoloader
require_once 'vendor/autoload.php';

// Démarrer la session
session_start();

// Désactiver l'affichage des messages d'erreur de type deprecated
error_reporting(E_ALL & ~E_DEPRECATED);

// Récupérer l'action demandée
$action = $_GET['action'] ?? '';

// Actions publiques (accessibles sans connexion)
$publicActions = ['login', 'logout'];

// Si c'est une action liée au login, la traiter directement
if (in_array($action, $publicActions)) {
    $controller = new modules\blog\controllers\LoginController();
    $controller->handleRequest($action);
    exit;
}

// Pour toutes les autres actions, vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    // Utilisateur non connecté, rediriger vers le login
    $controller = new modules\blog\controllers\LoginController();
    $controller->showLoginPage();
    exit;
}

// Utilisateur connecté, router vers le contrôleur approprié en fonction de l'action
switch ($action) {
    case 'analyse-charge':
        // Afficher l'analyse de charge
        $controller = new modules\blog\controllers\ChargeController();
        $controller->handleRequest();
        break;
    case 'logout':
        // Déconnexion (déjà traitée plus haut, mais gardé pour cohérence)
        $controller = new modules\blog\controllers\LoginController();
        $controller->handleRequest('logout');
        break;
    default:
        // Afficher le tableau de bord par défaut
        $controller = new modules\blog\controllers\DashboardController();
        $controller->handleRequest();
        break;
}