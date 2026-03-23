<?php
namespace modules\blog\models;
use PDO;
use PDOException;

/**
 * Classe LoginModel
 *
 * Cette classe gère les opérations de connexion des utilisateurs.
 */
class LoginModel {
    /**
     * @var \PDO $db Connexion à la base de données
     */
    private $db;

    /**
     * Constructeur de la classe LoginModel
     *
     * Initialise la connexion à la base de données via SingletonModel.
     */
    public function __construct()
    {
        // Connexion à la base de données via SingletonModel
        $this->db = SingletonModel::getInstance()->getConnection();
    }

    /**
     * Tester le mot de passe d'un utilisateur
     *
     * Vérifie si l'utilisateur existe et si le mot de passe correspond.
     *
     * @param string $identifiant Identifiant de l'utilisateur
     * @param string $password Mot de passe de l'utilisateur
     * @return array|false Les informations de l'utilisateur si le login réussit, sinon false
     */
    public function test_Pass($identifiant, $password)
    {
        // Vérifier l'utilisateur dans la table Utilisateurs
        $stmt = $this->db->prepare("SELECT * FROM Utilisateurs WHERE id_util = :identifiant");
        $stmt->bindParam(':identifiant', $identifiant);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si utilisateur trouvé, vérifier le mot de passe
        if ($result) {
            // Essayer d'abord password_verify (pour mots de passe hashés)
            if (password_verify($password, $result['mdp'])) {
                return $result;
            }

            // Si échec, essayer comparaison directe (pour mots de passe en clair)
            if ($password === $result['mdp']) {
                return $result;
            }
        }

        // Aucune correspondance trouvée
        return false;
    }
}
?>