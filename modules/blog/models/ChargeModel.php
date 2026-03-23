<?php
namespace modules\blog\models;

/**
 * Classe ChargeModel
 *
 * Cette classe gère l'analyse de la charge de travail à partir des données
 * récupérées depuis la base de données via ImportModel.
 *
 * Les graphiques affichent maintenant des moyennes par semaine
 */
class ChargeModel {
    /**
     * @var ImportModel $importModel Instance pour récupérer les données
     */
    private $importModel;

    /**
     * Constructeur de ChargeModel
     */
    public function __construct() {
        // Utiliser le Singleton pour ImportModel - MÊME instance que DashboardModel !
        $this->importModel = ImportModel::getInstance();
    }

    /**
     * Analyser les données de la base de données pour obtenir la charge par période
     *
     * @return array Données de charge analysées
     */
    public function analyserChargeParPeriode() {
        // Récupérer les données depuis ImportModel (même instance que Dashboard)
        $donneesDb = $this->importModel->getAllData();

        if (empty($donneesDb)) {
            return [
                'error' => "Aucune donnée disponible dans la base de données."
            ];
        }

        // Filtrer pour ne garder que les jours présents et futurs
        $donneesFiltrees = $this->filterFutureAndTodayData($donneesDb);

        if (empty($donneesFiltrees)) {
            return [
                'error' => "Aucune donnée disponible pour les jours présents et futurs."
            ];
        }

        // Convertir les données filtrées en format utilisable pour l'analyse
        $chargeParJour = $this->convertDbDataToChargeData($donneesFiltrees);

        // Calculer la charge par processus et par semaine (données filtrées)
        $chargeParSemaine = $this->calculateWeeklyChargeByProcess($donneesFiltrees);

        // Trouver la date de début et de fin globale du projet
        $dates = array_keys($chargeParJour);
        sort($dates);
        $dateDebut = new \DateTime(reset($dates));
        $dateFin = new \DateTime(end($dates));

        return [
            'chargeParJour' => array_values($chargeParJour),
            'chargeParSemaine' => $chargeParSemaine,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin
        ];
    }

    /**
     *Récupère les données quotidiennes pour une période libre
     *
     * @param string $dateDebut Date de début de la période (format Y-m-d)
     * @param string $dateFin Date de fin de la période (format Y-m-d)
     * @return array Données formatées pour cette période
     */
    public function getDailyDataForPeriod($dateDebut, $dateFin) {
        echo "<script>console.log('[ChargeModel] === RÉCUPÉRATION DONNÉES POUR PÉRIODE LIBRE ===');</script>";
        echo "<script>console.log('[ChargeModel] Période demandée: " . addslashes($dateDebut) . " → " . addslashes($dateFin) . "');</script>";

        try {
            // Validation et création des objets DateTime
            $debutPeriode = new \DateTime($dateDebut);
            $finPeriode = new \DateTime($dateFin);

            // Validation : début doit être antérieur à la fin
            if ($debutPeriode > $finPeriode) {
                echo "<script>console.log('[ChargeModel] Erreur: Date début postérieure à date fin');</script>";
                return ['error' => 'La date de début doit être antérieure à la date de fin.'];
            }

            // Calcul du nombre de jours dans la période
            $nombreJours = $this->calculateWorkingDaysBetween($debutPeriode, $finPeriode);
            echo "<script>console.log('[ChargeModel] Jours ouvrés dans la période: " . count($nombreJours) . "');</script>";

            if (empty($nombreJours)) {
                return ['error' => 'Aucun jour ouvré trouvé dans cette période.'];
            }

        } catch (\Exception $e) {
            echo "<script>console.log('[ChargeModel] Erreur parsing dates: " . addslashes($e->getMessage()) . "');</script>";
            return ['error' => 'Format de date invalide: ' . $e->getMessage()];
        }

        // Récupérer toutes les données depuis ImportModel
        $donneesDb = $this->importModel->getAllData();

        if (empty($donneesDb)) {
            return ['error' => 'Aucune donnée disponible dans la base de données.'];
        }

        // Filtrer les données pour cette période uniquement (en excluant les weekends)
        $donneesPeriode = [];
        foreach ($donneesDb as $donnee) {
            try {
                $dateDonnee = new \DateTime($donnee['Date']);

                // Vérifier si la date est dans la période ET que c'est un jour ouvré
                if ($dateDonnee >= $debutPeriode && $dateDonnee <= $finPeriode && $this->isWorkingDay($dateDonnee)) {
                    $donneesPeriode[] = $donnee;
                }
            } catch (\Exception $e) {
                echo "<script>console.log('[ChargeModel] Erreur parsing date donnée: " . addslashes($e->getMessage()) . "');</script>";
                continue;
            }
        }

        echo "<script>console.log('[ChargeModel] Données trouvées pour cette période: " . count($donneesPeriode) . "');</script>";

        //Convertir en format graphique par semaines au lieu de jours
        $graphiquesData = $this->preparePeriodGraphicsDataWeekly($donneesPeriode, $debutPeriode, $finPeriode);

        return [
            'graphiquesData' => $graphiquesData,
            'debutPeriode' => $debutPeriode,
            'finPeriode' => $finPeriode,
            'donneesCount' => count($donneesPeriode),
            'nombreJoursOuvres' => count($nombreJours)
        ];
    }

    /**
     *Prépare les données graphiques par semaines pour une période libre
     *
     * @param array $donneesPeriode Données de la période
     * @param \DateTime $debutPeriode Date de début
     * @param \DateTime $finPeriode Date de fin
     * @return array Données formatées pour JPGraph (moyennes par semaines)
     */
    private function preparePeriodGraphicsDataWeekly($donneesPeriode, $debutPeriode, $finPeriode) {
        echo "<script>console.log('[ChargeModel] === PRÉPARATION DONNÉES GRAPHIQUES PAR SEMAINES ===');</script>";

        $mappingProcessus = [
            'production' => ['CHAUDNQ', 'CHAUDQ', 'SOUDNQ', 'SOUDQ', 'CT'],
            'etude' => ['CALC', 'PROJ'],
            'methode' => ['METH'],
            'qualite' => ['QUAL', 'QUALS']
        ];

        echo "<script>console.log('[ChargeModel] ✅ MISE À JOUR: Production inclut maintenant CHAUDQ et SOUDQ');</script>";

        //ÉTAPE 1 : Calculer d'abord les données par jour (comme avant)
        $joursOuvres = $this->calculateWorkingDaysBetween($debutPeriode, $finPeriode);
        $nombreJours = count($joursOuvres);

        echo "<script>console.log('[ChargeModel] Jours ouvrés à traiter: " . $nombreJours . "');</script>";

        //INITIALISATION MISE À JOUR : 5 processus au lieu de 3 pour la production
        $donneesParJour = [
            'production' => [
                'CHAUDNQ' => array_fill(0, $nombreJours, 0),
                'CHAUDQ' => array_fill(0, $nombreJours, 0),
                'SOUDNQ' => array_fill(0, $nombreJours, 0),
                'SOUDQ' => array_fill(0, $nombreJours, 0),
                'CT' => array_fill(0, $nombreJours, 0)
            ],
            'etude' => [
                'CALC' => array_fill(0, $nombreJours, 0),
                'PROJ' => array_fill(0, $nombreJours, 0)
            ],
            'methode' => [
                'METH' => array_fill(0, $nombreJours, 0)
            ],
            'qualite' => [
                'QUAL' => array_fill(0, $nombreJours, 0),
                'QUALS' => array_fill(0, $nombreJours, 0)
            ]
        ];

        // Créer un mapping date → index pour un accès rapide
        $dateToIndexMap = [];
        foreach ($joursOuvres as $index => $jourObj) {
            $dateToIndexMap[$jourObj->format('Y-m-d')] = $index;
        }

        // Remplir les données jour par jour (comme avant)
        foreach ($donneesPeriode as $donnee) {
            $dateData = $donnee['Date'];
            $processus = $donnee['Processus'];
            $charge = floatval($donnee['Charge']);

            // Trouver l'index du jour
            if (!isset($dateToIndexMap[$dateData])) {
                echo "<script>console.log('[ChargeModel] Date non trouvée dans la période ou weekend: " . addslashes($dateData) . "');</script>";
                continue;
            }

            $indexJour = $dateToIndexMap[$dateData];

            // Trouver la catégorie du processus
            $categorieProcessus = null;
            foreach ($mappingProcessus as $categorie => $processusListe) {
                if (in_array($processus, $processusListe)) {
                    $categorieProcessus = $categorie;
                    break;
                }
            }

            if ($categorieProcessus && isset($donneesParJour[$categorieProcessus][$processus])) {
                $donneesParJour[$categorieProcessus][$processus][$indexJour] += $charge;
                echo "<script>console.log('[ChargeModel] Ajout: " . addslashes($processus) . " (" . addslashes($categorieProcessus) . ") jour " . $indexJour . " = +" . $charge . "');</script>";
            } else {
                echo "<script>console.log('[ChargeModel] Processus ignoré: " . addslashes($processus) . " (catégorie non trouvée)');</script>";
            }
        }

        //ÉTAPE 2 : Grouper les données par semaines et calculer les moyennes
        echo "<script>console.log('[ChargeModel] === GROUPEMENT PAR SEMAINES ===');</script>";

        $donneesParSemaine = $this->groupDataByWeeks($donneesParJour, $joursOuvres, $mappingProcessus);
        $semainesLabels = $this->generateWeeklyLabels($joursOuvres);

        echo "<script>console.log('[ChargeModel] Nombre de semaines générées: " . count($semainesLabels) . "');</script>";

        // Calculer la largeur dynamique basée sur le nombre de semaines
        $nombreSemaines = count($semainesLabels);
        $largeurGraphique = max(900, $nombreSemaines * 120); // 120px par semaine

        // Ajouter les labels et métadonnées aux données
        $graphiquesData = array_merge($donneesParSemaine, [
            'semaines_labels' => $semainesLabels, //Labels de semaines au lieu de jours
            'periode_info' => [
                'debut' => $debutPeriode->format('d/m/Y'),
                'fin' => $finPeriode->format('d/m/Y'),
                'nombre_jours' => $nombreJours,
                'nombre_semaines' => $nombreSemaines, //Ajout du nombre de semaines
                'largeur_graphique' => $largeurGraphique //Largeur adaptée aux semaines
            ]
        ]);

        // Log des totaux par catégorie (moyennes)
        foreach ($mappingProcessus as $categorie => $processusListe) {
            $totalCategorie = 0;
            foreach ($processusListe as $proc) {
                if (isset($donneesParSemaine[$categorie][$proc])) {
                    $totalProc = array_sum($donneesParSemaine[$categorie][$proc]);
                    $totalCategorie += $totalProc;
                    if ($totalProc > 0) {
                        echo "<script>console.log('[ChargeModel] Moyenne totale " . addslashes($proc) . ": " . $totalProc . "');</script>";
                    }
                }
            }
            echo "<script>console.log('[ChargeModel] Moyenne totale catégorie " . addslashes($categorie) . ": " . $totalCategorie . "');</script>";
        }

        echo "<script>console.log('[ChargeModel] Données graphiques par semaines préparées avec CHAUDQ/SOUDQ');</script>";

        return $graphiquesData;
    }

    /**
     *Groupe les données par semaines et calcule les moyennes
     *
     * @param array $donneesParJour Données organisées par jour
     * @param array $joursOuvres Liste des jours ouvrés
     * @param array $mappingProcessus Mapping des processus par catégorie
     * @return array Données groupées par semaines avec moyennes
     */
    private function groupDataByWeeks($donneesParJour, $joursOuvres, $mappingProcessus) {
        echo "<script>console.log('[ChargeModel] === GROUPEMENT PAR SEMAINES ===');</script>";

        // Organiser les jours par semaines
        $semainesData = [];
        $semaineActuelle = [];
        $numeroSemaine = 0;

        foreach ($joursOuvres as $index => $jourObj) {
            $jourSemaine = (int)$jourObj->format('N'); // 1=Lundi, 5=Vendredi

            // Si c'est lundi ET qu'on a déjà des jours dans la semaine, commencer une nouvelle semaine
            if ($jourSemaine == 1 && !empty($semaineActuelle)) {
                $semainesData[$numeroSemaine] = $semaineActuelle;
                $semaineActuelle = [];
                $numeroSemaine++;
            }

            $semaineActuelle[] = $index; // Stocker l'index du jour
        }

        // Ajouter la dernière semaine si elle contient des jours
        if (!empty($semaineActuelle)) {
            $semainesData[$numeroSemaine] = $semaineActuelle;
        }

        echo "<script>console.log('[ChargeModel] Nombre de semaines détectées: " . count($semainesData) . "');</script>";

        $donneesParSemaine = [
            'production' => [
                'CHAUDNQ' => [],
                'CHAUDQ' => [],
                'SOUDNQ' => [],
                'SOUDQ' => [],
                'CT' => []
            ],
            'etude' => [
                'CALC' => [],
                'PROJ' => []
            ],
            'methode' => [
                'METH' => []
            ],
            'qualite' => [
                'QUAL' => [],
                'QUALS' => []
            ]
        ];

        foreach ($semainesData as $numeroSem => $joursIndices) {
            echo "<script>console.log('[ChargeModel] Traitement semaine " . $numeroSem . " avec " . count($joursIndices) . " jours');</script>";

            foreach ($mappingProcessus as $categorie => $processusListe) {
                foreach ($processusListe as $processus) {
                    if (isset($donneesParJour[$categorie][$processus])) {
                        // Calculer la somme des charges pour cette semaine
                        $sommeSemaine = 0;
                        foreach ($joursIndices as $indexJour) {
                            $sommeSemaine += $donneesParJour[$categorie][$processus][$indexJour];
                        }

                        //DIVISION PAR 5 (toujours, même pour semaines incomplètes)
                        $moyenneSemaine = $sommeSemaine / 5;

                        $donneesParSemaine[$categorie][$processus][] = $moyenneSemaine;

                        if ($moyenneSemaine > 0) {
                            echo "<script>console.log('[ChargeModel] " . addslashes($processus) . " S" . $numeroSem . ": " . $sommeSemaine . " total ÷ 5 = " . $moyenneSemaine . " moyenne');</script>";
                        }
                    }
                }
            }
        }

        return $donneesParSemaine;
    }

    /**
     *Génère les labels pour les semaines
     *
     * @param array $joursOuvres Liste des jours ouvrés
     * @return array Labels des semaines
     */
    private function generateWeeklyLabels($joursOuvres) {
        echo "<script>console.log('[ChargeModel] === GÉNÉRATION LABELS SEMAINES ===');</script>";

        $labels = [];
        $semaineActuelle = [];
        $numeroSemaine = 1;

        foreach ($joursOuvres as $jourObj) {
            $jourSemaine = (int)$jourObj->format('N'); // 1=Lundi, 5=Vendredi

            // Si c'est lundi ET qu'on a déjà des jours dans la semaine, finaliser la semaine précédente
            if ($jourSemaine == 1 && !empty($semaineActuelle)) {
                // Créer le label pour la semaine précédente
                $premierJour = reset($semaineActuelle);
                $dernierJour = end($semaineActuelle);
                $labels[] = $premierJour->format('d/m') . '-' . $dernierJour->format('d/m');

                // Commencer la nouvelle semaine
                $semaineActuelle = [$jourObj];
                $numeroSemaine++;
            } else {
                $semaineActuelle[] = $jourObj;
            }
        }

        // Ajouter la dernière semaine si elle contient des jours
        if (!empty($semaineActuelle)) {
            $premierJour = reset($semaineActuelle);
            $dernierJour = end($semaineActuelle);
            $labels[] = $premierJour->format('d/m') . '-' . $dernierJour->format('d/m');
        }

        echo "<script>console.log('[ChargeModel] Labels semaines générés: " . implode(', ', $labels) . "');</script>";

        return $labels;
    }

    /**
     *Calcule tous les jours ouvrés entre deux dates (exclut weekends)
     *
     * @param \DateTime $dateDebut Date de début
     * @param \DateTime $dateFin Date de fin
     * @return array Liste des objets DateTime des jours ouvrés
     */
    private function calculateWorkingDaysBetween($dateDebut, $dateFin) {
        $joursOuvres = [];
        $current = clone $dateDebut;

        while ($current <= $dateFin) {
            // Inclure seulement les jours ouvrés (Lundi=1 à Vendredi=5)
            if ($this->isWorkingDay($current)) {
                $joursOuvres[] = clone $current;
            }
            $current->add(new \DateInterval('P1D'));
        }

        return $joursOuvres;
    }

    /**
     *Vérifie si un jour est un jour ouvré (exclut samedi et dimanche)
     *
     * @param \DateTime $date Date à vérifier
     * @return bool True si jour ouvré, false si weekend
     */
    private function isWorkingDay($date) {
        $dayOfWeek = (int)$date->format('N'); // 1=Lundi, 7=Dimanche
        return $dayOfWeek >= 1 && $dayOfWeek <= 5; // Lundi à Vendredi
    }

    /**
     *Obtient la plage de dates disponibles dans les données
     *
     * @return array Informations sur la plage de dates disponibles
     */
    public function getAvailableDateRange() {
        echo "<script>console.log('[ChargeModel] === RÉCUPÉRATION PLAGE DATES DISPONIBLES ===');</script>";

        // Récupérer toutes les données depuis ImportModel
        $donneesDb = $this->importModel->getAllData();

        if (empty($donneesDb)) {
            echo "<script>console.log('[ChargeModel] Aucune donnée disponible');</script>";
            return [
                'has_data' => false,
                'message' => 'Aucune donnée disponible'
            ];
        }

        // Extraire toutes les dates
        $dates = array_column($donneesDb, 'Date');
        sort($dates);

        $dateMin = new \DateTime(reset($dates));
        $dateMax = new \DateTime(end($dates));

        echo "<script>console.log('[ChargeModel] Plage disponible: " . addslashes($dateMin->format('Y-m-d')) . " → " . addslashes($dateMax->format('Y-m-d')) . "');</script>";

        return [
            'has_data' => true,
            'date_min' => $dateMin->format('Y-m-d'),
            'date_max' => $dateMax->format('Y-m-d'),
            'date_min_formatted' => $dateMin->format('d/m/Y'),
            'date_max_formatted' => $dateMax->format('d/m/Y'),
            'total_entries' => count($donneesDb)
        ];
    }

    /**
     * Filtre les données pour ne garder que les jours présents et futurs
     *
     * @param array $donneesDb Données de la base de données
     * @return array Données filtrées
     */
    private function filterFutureAndTodayData($donneesDb) {
        // Obtenir la date d'aujourd'hui à minuit pour la comparaison
        $aujourdhui = new \DateTime();
        $aujourdhui->setTime(0, 0, 0); // Minuit aujourd'hui

        $donneesFiltrees = [];

        foreach ($donneesDb as $donnee) {
            $dateDonnee = new \DateTime($donnee['Date']);
            $dateDonnee->setTime(0, 0, 0); // Minuit pour cette date

            // Garder seulement si la date est aujourd'hui ou dans le futur
            if ($dateDonnee >= $aujourdhui) {
                $donneesFiltrees[] = $donnee;
            }
        }

        return $donneesFiltrees;
    }

    /**
     * Calcule la charge par processus et par semaine
     *
     * @param array $donneesDb Données de la base de données
     * @return array Charge par semaine et par processus
     */
    private function calculateWeeklyChargeByProcess($donneesDb) {
        $chargeParSemaine = [];

        foreach ($donneesDb as $donnee) {
            $date = new \DateTime($donnee['Date']);
            $processus = $donnee['Processus'];
            $charge = floatval($donnee['Charge']);

            // Calculer le début de la semaine (lundi)
            $debutSemaine = clone $date;
            $jourSemaine = $date->format('N'); // 1 = lundi, 7 = dimanche
            $debutSemaine->sub(new \DateInterval('P' . ($jourSemaine - 1) . 'D'));

            // Calculer la fin de la semaine (dimanche)
            $finSemaine = clone $debutSemaine;
            $finSemaine->add(new \DateInterval('P6D'));

            // Créer la clé de semaine
            $cleSemaine = $debutSemaine->format('Y-m-d') . '_' . $finSemaine->format('Y-m-d');

            // Initialiser la semaine si nécessaire
            if (!isset($chargeParSemaine[$cleSemaine])) {
                $chargeParSemaine[$cleSemaine] = [
                    'debut' => $debutSemaine,
                    'fin' => $finSemaine,
                    'processus' => [],
                    'total' => 0
                ];
            }

            // Initialiser le processus si nécessaire
            if (!isset($chargeParSemaine[$cleSemaine]['processus'][$processus])) {
                $chargeParSemaine[$cleSemaine]['processus'][$processus] = 0;
            }

            // Ajouter la charge
            $chargeParSemaine[$cleSemaine]['processus'][$processus] += $charge;
            $chargeParSemaine[$cleSemaine]['total'] += $charge;
        }

        // Trier par date de début de semaine
        ksort($chargeParSemaine);

        return $chargeParSemaine;
    }

    /**
     * Convertit les données de la base de données en format d'analyse de charge
     *
     * @param array $donneesDb Données de la base de données
     * @return array Données formatées pour l'analyse
     */
    private function convertDbDataToChargeData($donneesDb) {
        $chargeParJour = [];

        // Grouper les données par date
        foreach ($donneesDb as $donnee) {
            $date = $donnee['Date'];
            $processus = $donnee['Processus'];
            $tache = $donnee['Tache'];
            $charge = floatval($donnee['Charge']);

            // Initialiser le jour s'il n'existe pas
            if (!isset($chargeParJour[$date])) {
                $dateObj = new \DateTime($date);
                $jourSemaine = $dateObj->format('N'); // 1 (lundi) à 7 (dimanche)
                $estWeekend = ($jourSemaine == 6 || $jourSemaine == 7);

                $chargeParJour[$date] = [
                    'date' => $dateObj,
                    'charge' => 0,
                    'taches' => [],
                    'processus' => [],
                    'estWeekend' => $estWeekend
                ];
            }

            // Ajouter la charge et les informations
            $chargeParJour[$date]['charge'] += $charge;

            // Ajouter la tâche si pas déjà présente
            if (!in_array($tache, $chargeParJour[$date]['taches'])) {
                $chargeParJour[$date]['taches'][] = $tache;
            }

            // Ajouter le processus si pas déjà présent
            if (!in_array($processus, $chargeParJour[$date]['processus'])) {
                $chargeParJour[$date]['processus'][] = $processus;
            }
        }

        return $chargeParJour;
    }

    /**
     * Formater les résultats pour l'affichage
     *
     * @param array $resultatAnalyse Résultat de l'analyse de charge
     * @return array Données formatées pour l'affichage
     */
    public function formaterResultats($resultatAnalyse) {
        $donneesMensuellesFormat = [];
        $moisActuel = '';
        $donneesMois = [];

        // Définir les noms des jours de la semaine en français
        $joursEnFrancais = [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche'
        ];

        // Traiter tous les jours analysés
        foreach ($resultatAnalyse['chargeParJour'] as $jour) {
            $date = $jour['date'];
            $mois = $date->format('F Y'); // Nom du mois et année

            if ($mois !== $moisActuel) {
                if (!empty($donneesMois)) {
                    $donneesMensuellesFormat[$moisActuel] = $donneesMois;
                }
                $moisActuel = $mois;
                $donneesMois = [];
            }

            // Préparer les processus pour l'affichage
            $processusTexte = implode(', ', $jour['processus']);

            // Déterminer l'affichage en fonction du type de jour
            if ($jour['estWeekend']) {
                // Week-end - pas de charge
                $donneesMois[] = [
                    'date' => $date->format('d/m/Y'),
                    'charge' => '0',
                    'taches' => '',
                    'processus' => '',
                    'jour_semaine' => $joursEnFrancais[$date->format('N')],
                    'estWeekend' => true
                ];
            } else {
                // Jour de semaine - afficher la charge et les tâches
                $donneesMois[] = [
                    'date' => $date->format('d/m/Y'),
                    'charge' => number_format($jour['charge'], 2),
                    'taches' => implode(', ', $jour['taches']),
                    'processus' => $processusTexte,
                    'jour_semaine' => $joursEnFrancais[$date->format('N')],
                    'estWeekend' => false
                ];
            }
        }

        if (!empty($donneesMois)) {
            $donneesMensuellesFormat[$moisActuel] = $donneesMois;
        }

        // Formater les données de charge par semaine
        $chargeParSemaineFormatee = [];
        if (isset($resultatAnalyse['chargeParSemaine'])) {
            foreach ($resultatAnalyse['chargeParSemaine'] as $semaine) {
                $chargeParSemaineFormatee[] = [
                    'debut' => $semaine['debut']->format('d/m/Y'),
                    'fin' => $semaine['fin']->format('d/m/Y'),
                    'processus' => $semaine['processus'],
                    'total' => number_format($semaine['total'], 2)
                ];
            }
        }

        return [
            'donneesMensuelles' => $donneesMensuellesFormat,
            'chargeParSemaine' => $chargeParSemaineFormatee,
            'dateDebut' => $resultatAnalyse['dateDebut']->format('d/m/Y'),
            'dateFin' => $resultatAnalyse['dateFin']->format('d/m/Y')
        ];
    }
}