<?php
namespace modules\blog\controllers;

use modules\blog\models\LoginModel;
use modules\blog\views\LoginView;

/**
 * Classe LoginController
 *
 * Cette classe gère les opérations de connexion.
 */
class LoginController {
    private $model;
    private $view;

    /**
     * Constructeur du LoginController
     */
    public function __construct() {
        $this->model = new LoginModel();
        $this->view = new LoginView();
    }

    /**
     * Gère les actions liées à la connexion
     *
     * @param string $action Action à exécuter
     */
    public function handleRequest($action = '') {
        switch ($action) {
            case 'login':
                $this->handleLogin();
                break;
            case 'logout':
                $this->handleLogout();
                break;
            default:
                $this->showLoginPage();
                break;
        }
    }

    /**
     * Affiche la page de connexion
     *
     * @param string|null $error Message d'erreur à afficher
     */
    public function showLoginPage($error = null) {
        echo $this->view->showLoginForm($error);
    }

    /**
     * Gère la soumission du formulaire de connexion
     */
    private function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $identifiant = $_POST['identifiant'] ?? '';
            $password = $_POST['password'] ?? '';

            if ($this->processLogin($identifiant, $password)) {
                // Redirection vers la page d'accueil après connexion réussie
                header('Location: index.php');
                exit;
            } else {
                $this->showLoginPage('Identifiant ou mot de passe incorrect.');
            }
        } else {
            $this->showLoginPage();
        }
    }

    /**
     * Gère la déconnexion
     */
    private function handleLogout() {
        session_start();
        session_destroy();
        header('Location: index.php');
        exit;
    }

    /**
     * Traite la tentative de connexion
     *
     * @param string $identifiant Identifiant de l'utilisateur
     * @param string $password Mot de passe
     * @return bool True si la connexion réussit, false sinon
     */
    private function processLogin($identifiant, $password) {
        $user = $this->model->test_Pass($identifiant, $password);

        if ($user) {
            // Démarrer la session et stocker les informations de l'utilisateur
            session_start();
            $_SESSION['user_id'] = $user['id_util'];

            return true;
        }

        return false;
    }
}