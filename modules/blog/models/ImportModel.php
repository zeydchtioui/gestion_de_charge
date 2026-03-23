<?php
namespace modules\blog\models;

/**
 * Classe ImportModel (Singleton)
 *
 * Cette classe gère uniquement la récupération des données depuis la table Donnees
 * et les stocke en mémoire pour être réutilisées par d'autres classes.
 * Pattern Singleton pour partager la même instance et les mêmes données en mémoire.
 */
class ImportModel {
    /**
     * @var ImportModel|null $instance Instance unique de la classe
     */
    private static $instance = null;

    /**
     * @var \PDO $db Connexion à la base de données
     */
    private $db;

    /**
     * @var array $donnees Données stockées en mémoire
     */
    private $donnees = [];

    /**
     * @var bool $dataLoaded Indique si les données ont été chargées
     */
    private $dataLoaded = false;

    /**
     * Constructeur privé pour le Singleton
     */
    private function __construct() {
        // Connexion à la base de données via SingletonModel
        $this->db = SingletonModel::getInstance()->getConnection();
    }

    /**
     * Obtenir l'instance unique de la classe
     *
     * @return ImportModel Instance unique de la classe
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Empêcher le clonage
     */
    private function __clone() {}

    /**
     * Empêcher la désérialisation
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize a singleton.");
    }

    /**
     * Charge toutes les données de la table Donnees en mémoire
     *
     * @param bool $forceReload Force le rechargement même si déjà chargé
     * @return bool Succès du chargement
     */
    public function loadAllData($forceReload = false) {
        // Si déjà chargé et pas de force reload, ne pas recharger
        if ($this->dataLoaded && !$forceReload) {
            return true;
        }

        try {
            $query = "SELECT Processus, Tache, Charge, Date FROM Donnees ORDER BY Date ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();

            $this->donnees = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->dataLoaded = true;

            echo "<script>console.log('[ImportModel] Données chargées: " . count($this->donnees) . " entrées');</script>";

            return true;
        } catch (\PDOException $e) {
            error_log("Erreur chargement données: " . $e->getMessage());
            echo "<script>console.log('[ImportModel] Erreur chargement: " . addslashes($e->getMessage()) . "');</script>";
            $this->donnees = [];
            $this->dataLoaded = false;
            return false;
        }
    }

    /**
     * Retourne toutes les données chargées
     *
     * @return array Toutes les données de la table Donnees
     */
    public function getAllData() {
        // Charger les données si pas encore fait
        if (!$this->dataLoaded) {
            $this->loadAllData();
        }

        return $this->donnees;
    }

    /**
     * Vide la table Donnees
     *
     * @return bool Succès de l'opération
     */
    public function clearTable() {
        try {
            $this->db->exec("TRUNCATE TABLE Donnees");

            // Vider aussi les données en mémoire
            $this->donnees = [];
            $this->dataLoaded = true; // Marquer comme chargé car maintenant vide

            echo "<script>console.log('[ImportModel] Table vidée et mémoire effacée');</script>";

            return true;
        } catch (\PDOException $e) {
            error_log("Erreur vidage table: " . $e->getMessage());
            echo "<script>console.log('[ImportModel] Erreur vidage: " . addslashes($e->getMessage()) . "');</script>";
            return false;
        }
    }

    /**
     * Indique si des données sont chargées en mémoire
     *
     * @return bool True si des données sont chargées
     */
    public function hasData() {
        // Si pas encore chargé, essayer de charger automatiquement
        if (!$this->dataLoaded) {
            $this->loadAllData();
        }

        $hasData = $this->dataLoaded && !empty($this->donnees);
        echo "<script>console.log('[ImportModel] hasData(): " . ($hasData ? 'true' : 'false') . " (" . count($this->donnees) . " entrées)');</script>";

        return $hasData;
    }

    /**
     * Force le rechargement des données depuis la base
     *
     * @return bool Succès du rechargement
     */
    public function refreshData() {
        echo "<script>console.log('[ImportModel] Force refresh des données');</script>";
        return $this->loadAllData(true);
    }

    /**
     * Retourne le nombre d'entrées en mémoire (pour debug)
     *
     * @return int Nombre d'entrées
     */
    public function getDataCount() {
        return count($this->donnees);
    }

    /**
     * Indique si les données sont chargées (pour debug)
     *
     * @return bool True si chargées
     */
    public function isDataLoaded() {
        return $this->dataLoaded;
    }
}