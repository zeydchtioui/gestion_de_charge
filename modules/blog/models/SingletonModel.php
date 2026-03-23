<?php

namespace modules\blog\models;

use _assets\config\Config;
use PDO;
use PDOException;

/**
 * Classe SingletonModel
 *
 * Cette classe implémente le modèle Singleton pour gérer une connexion unique à la base de données.
 */
class SingletonModel
{
    /**
     * @var SingletonModel|null $instance Instance unique de la classe
     */
    private static $instance = null;

    /**
     * @var \PDO $connection Connexion PDO
     */
    private $connection;

    /**
     * @var Config $config Configuration de la base de données
     */
    private $config;

    /**
     * Constructeur privé pour le Singleton
     *
     * Initialise la connexion à la base de données en utilisant les paramètres de configuration.
     */
    private function __construct()
    {
        // Charger la configuration depuis une fonction dédiée
        $this->config = new Config();
        $conn = $this->config->getDatabaseConfig();

        try {
            // Créer la connexion PDO avec les paramètres de configuration
            $this->connection = new PDO($conn['dsn'], $conn['username'], $conn['password']);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // Gérer les erreurs de connexion
            die('Erreur: ' . $e->getMessage());
        }
    }

    /**
     * Obtenir l'instance unique de la classe
     *
     * @return SingletonModel Instance unique de la classe
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Récupérer la connexion PDO
     *
     * @return \PDO Connexion PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }
}