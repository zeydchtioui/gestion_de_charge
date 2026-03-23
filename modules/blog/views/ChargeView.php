<?php
namespace modules\blog\views;

/**
 * Classe ChargeView
 *
 * Cette classe gère l'affichage de l'analyse de charge avec sélection libre de période.
 *
 * VERSION REFACTORISÉE : Sélection libre date début → date fin avec affichage par semaines
 * Les graphiques affichent maintenant des moyennes par semaine au lieu de données par jour
 */
class ChargeView {
    /**
     * Affiche un message d'erreur
     *
     * @param string $message Message d'erreur à afficher
     * @return string Le contenu HTML généré
     */
    public function showErrorMessage($message) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erreur - Analyse de Charge</title>
            <link rel="stylesheet" href="_assets/css/dashboard.css">
        </head>
        <body>
        <div class="navbar">
            <a href="index.php?action=dashboard">Tableau de bord</a>
            <a href="index.php?action=analyse-charge">Analyse de Charge</a>
            <a href="index.php?action=logout">Déconnexion</a>
        </div>

        <div class="container">
            <h1>Erreur</h1>
            <div class="error-message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * 🆕 Affiche l'analyse de charge avec sélecteur libre de période (moyennes par semaines)
     *
     * @param array $userInfo Informations de l'utilisateur
     * @param string $fileName Nom du fichier Excel
     * @param array $resultats Résultats de l'analyse de charge (pour récapitulatif global)
     * @param array $periodData Données de la période sélectionnée (nouvelles données par semaines)
     * @param array $chartPaths Chemins des images de graphiques générées
     * @param array $dateRange Plage de dates disponibles dans les données
     * @return string Le contenu HTML généré
     */
    public function showChargeAnalysis($userInfo, $fileName, $resultats, $periodData = [], $chartPaths = [], $dateRange = []) {
        // Récupérer les dates actuellement sélectionnées depuis GET
        $dateDebut = $_GET['date_debut'] ?? '';
        $dateFin = $_GET['date_fin'] ?? '';

        // Valeurs par défaut si aucune sélection
        if (empty($dateDebut) || empty($dateFin)) {
            if (!empty($dateRange) && $dateRange['has_data']) {
                $dateDebut = $dateRange['date_min'];
                $dateFin = min($dateRange['date_max'], date('Y-m-d', strtotime($dateRange['date_min'] . ' +7 days')));
            } else {
                $dateDebut = date('Y-m-d');
                $dateFin = date('Y-m-d', strtotime('+7 days'));
            }
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Analyse de Charge - Gestion de Charge</title>
            <link rel="stylesheet" href="_assets/css/dashboard.css">
            <link rel="stylesheet" href="_assets/css/excel.css">
        </head>
        <body>
        <div class="navbar">
            <a href="index.php?action=dashboard">Tableau de bord</a>
            <a href="index.php?action=analyse-charge">Analyse de Charge</a>
            <a href="index.php?action=logout">Déconnexion</a>
        </div>

        <div class="container">
            <div class="card">
                <h1>Analyse de Charge - Gestion de Charge</h1>

                <div class="summary-box">
                    <div class="summary-title">Résumé de l'analyse</div>
                    <p>Période globale disponible: <?php echo htmlspecialchars($resultats['dateDebut'] ?? 'N/A'); ?> au <?php echo htmlspecialchars($resultats['dateFin'] ?? 'N/A'); ?></p>
                    <p>Fichier analysé: <?php echo htmlspecialchars($fileName); ?></p>
                </div>

                <!-- 🆕 NOUVEAU SÉLECTEUR DE PÉRIODE LIBRE -->
                <div class="period-selector-container">
                    <h2>📅 Sélection libre de la période d'analyse</h2>
                    <div class="period-selector">
                        <form action="index.php" method="GET" class="period-form" id="periodForm">
                            <input type="hidden" name="action" value="analyse-charge">

                            <div class="date-inputs">
                                <div class="form-group">
                                    <label for="date_debut">Date de début :</label>
                                    <input type="date"
                                           id="date_debut"
                                           name="date_debut"
                                           value="<?php echo htmlspecialchars($dateDebut); ?>"
                                           class="date-input"
                                           tabindex="1"
                                        <?php if (!empty($dateRange['date_min'])): ?>
                                            data-suggested-min="<?php echo htmlspecialchars($dateRange['date_min']); ?>"
                                        <?php endif; ?>
                                        <?php if (!empty($dateRange['date_max'])): ?>
                                            data-suggested-max="<?php echo htmlspecialchars($dateRange['date_max']); ?>"
                                        <?php endif; ?>
                                           required>
                                </div>

                                <div class="form-group">
                                    <label for="date_fin">Date de fin :</label>
                                    <input type="date"
                                           id="date_fin"
                                           name="date_fin"
                                           value="<?php echo htmlspecialchars($dateFin); ?>"
                                           class="date-input"
                                           tabindex="2"
                                        <?php if (!empty($dateRange['date_min'])): ?>
                                            data-suggested-min="<?php echo htmlspecialchars($dateRange['date_min']); ?>"
                                        <?php endif; ?>
                                        <?php if (!empty($dateRange['date_max'])): ?>
                                            data-suggested-max="<?php echo htmlspecialchars($dateRange['date_max']); ?>"
                                        <?php endif; ?>
                                           required>
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn-analyze-period" tabindex="3">
                                        🔄 Analyser cette période
                                    </button>
                                </div>
                            </div>

                            <!-- Informations sur la période sélectionnée -->
                            <div class="period-info">
                                <div id="period-summary" class="period-summary">
                                    <?php if (!empty($periodData)): ?>
                                        <span class="period-details">
                                            📊 Période analysée : <?php echo htmlspecialchars($periodData['debutPeriode']->format('d/m/Y')); ?>
                                            → <?php echo htmlspecialchars($periodData['finPeriode']->format('d/m/Y')); ?>
                                            (<?php echo $periodData['nombreJoursOuvres']; ?> jours ouvrés)
                                        </span>
                                        <span class="data-count">
                                            📈 <?php echo $periodData['donneesCount']; ?> entrée(s) de données trouvée(s)
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($dateRange) && $dateRange['has_data']): ?>
                                    <div class="available-range">
                                        <small>
                                            📋 Données disponibles du <?php echo htmlspecialchars($dateRange['date_min_formatted']); ?>
                                            au <?php echo htmlspecialchars($dateRange['date_max_formatted']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Section des graphiques pour la période sélectionnée -->
                <?php if (!empty($periodData) && !empty($chartPaths)): ?>
                    <div class="graphiques-container">
                        <h2>Évolution de la charge par semaine - Période sélectionnée</h2>

                        <div class="period-chart-info">
                            <p><strong>📅 Affichage :</strong> Moyennes de charge par semaine pour la période sélectionnée</p>
                            <p><strong>📊 Axe X :</strong> Semaines de la période</p>
                            <p><strong>📈 Axe Y :</strong> Moyenne de personnes par semaine</p>
                        </div>

                        <!-- BOUTONS DE SÉLECTION DES GRAPHIQUES -->
                        <div class="graphiques-tabs">
                            <button onclick="showChart('production')" id="btn-production" class="tab-button active">
                                🏭 Production
                            </button>
                            <button onclick="showChart('etude')" id="btn-etude" class="tab-button">
                                📊 Étude
                            </button>
                            <button onclick="showChart('methode')" id="btn-methode" class="tab-button">
                                🔧 Méthode
                            </button>
                            <button onclick="showChart('qualite')" id="btn-qualite" class="tab-button">
                                ✅ Qualité
                            </button>
                        </div>

                        <!-- CONTAINER AVEC DÉFILEMENT HORIZONTAL -->
                        <div class="charts-scroll-container">
                            <div class="charts-content">
                                <!-- GRAPHIQUE PRODUCTION (affiché par défaut) -->
                                <div id="chart-production" class="graphique-section chart-content">
                                    <h3>Production - Moyennes par semaine</h3>
                                    <?php if (!empty($chartPaths['production'])): ?>
                                        <img src="_assets/images/<?php echo htmlspecialchars($chartPaths['production']); ?>"
                                             alt="Graphique charge Production par semaine" class="chart-image">
                                        <p class="chart-description">Moyennes hebdomadaires : Chaudronnerie, Soudure et Contrôle</p>
                                    <?php else: ?>
                                        <div class="chart-placeholder">
                                            <p>Aucune donnée de production pour cette période</p>
                                            <small>Sélectionnez une autre période ou vérifiez les données</small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- GRAPHIQUE ÉTUDE (masqué par défaut) -->
                                <div id="chart-etude" class="graphique-section chart-content hidden">
                                    <h3>Étude - Moyennes par semaine</h3>
                                    <?php if (!empty($chartPaths['etude'])): ?>
                                        <img src="_assets/images/<?php echo htmlspecialchars($chartPaths['etude']); ?>"
                                             alt="Graphique charge Étude par semaine" class="chart-image">
                                        <p class="chart-description">Moyennes hebdomadaires : Calcul et Projet</p>
                                    <?php else: ?>
                                        <div class="chart-placeholder">
                                            <p>Aucune donnée d'étude pour cette période</p>
                                            <small>Sélectionnez une autre période ou vérifiez les données</small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- GRAPHIQUE MÉTHODE (masqué par défaut) -->
                                <div id="chart-methode" class="graphique-section chart-content hidden">
                                    <h3>Méthode - Moyennes par semaine</h3>
                                    <?php if (!empty($chartPaths['methode'])): ?>
                                        <img src="_assets/images/<?php echo htmlspecialchars($chartPaths['methode']); ?>"
                                             alt="Graphique charge Méthode par semaine" class="chart-image">
                                        <p class="chart-description">Moyennes hebdomadaires : Méthode</p>
                                    <?php else: ?>
                                        <div class="chart-placeholder">
                                            <p>Aucune donnée de méthode pour cette période</p>
                                            <small>Sélectionnez une autre période ou vérifiez les données</small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- GRAPHIQUE QUALITÉ (masqué par défaut) -->
                                <div id="chart-qualite" class="graphique-section chart-content hidden">
                                    <h3>Qualité - Moyennes par semaine</h3>
                                    <?php if (!empty($chartPaths['qualite'])): ?>
                                        <img src="_assets/images/<?php echo htmlspecialchars($chartPaths['qualite']); ?>"
                                             alt="Graphique charge Qualité par semaine" class="chart-image">
                                        <p class="chart-description">Moyennes hebdomadaires : Qualité et Qualité Spécialisée</p>
                                    <?php else: ?>
                                        <div class="chart-placeholder">
                                            <p>Aucune donnée de qualité pour cette période</p>
                                            <small>Sélectionnez une autre période ou vérifiez les données</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif (empty($periodData)): ?>
                    <!-- Message si aucune période sélectionnée -->
                    <div class="no-period-message">
                        <h3>📅 Sélectionnez une période pour visualiser les graphiques</h3>
                        <p>Choisissez une date de début et une date de fin, puis cliquez sur "Analyser cette période".</p>
                    </div>
                <?php endif; ?>


                <script>
                    // FONCTION POUR AFFICHER/MASQUER LES GRAPHIQUES
                    function showChart(chartType) {
                        console.log('Affichage du graphique:', chartType);

                        // Masquer tous les graphiques
                        const allCharts = document.querySelectorAll('.chart-content');
                        allCharts.forEach(chart => {
                            chart.classList.add('hidden');
                        });

                        // Désactiver tous les boutons
                        const allButtons = document.querySelectorAll('.tab-button');
                        allButtons.forEach(button => {
                            button.classList.remove('active');
                        });

                        // Afficher le graphique sélectionné
                        const selectedChart = document.getElementById('chart-' + chartType);
                        if (selectedChart) {
                            selectedChart.classList.remove('hidden');
                        }

                        // Activer le bouton sélectionné
                        const selectedButton = document.getElementById('btn-' + chartType);
                        if (selectedButton) {
                            selectedButton.classList.add('active');
                        }
                    }

                    // VALIDATION TEMPS RÉEL DES DATES (Version assouplie)
                    function updatePeriodInfo() {
                        const dateDebut = document.getElementById('date_debut').value;
                        const dateFin = document.getElementById('date_fin').value;
                        const summaryElement = document.getElementById('period-summary');

                        if (dateDebut && dateFin) {
                            const debut = new Date(dateDebut);
                            const fin = new Date(dateFin);

                            if (debut > fin) {
                                summaryElement.innerHTML = '<span class="error-text">⚠️ La date de début doit être antérieure à la date de fin</span>';
                                return;
                            }

                            // Calculer le nombre de jours (approximatif - sans exclusion précise des weekends)
                            const diffTime = Math.abs(fin - debut);
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                            const workingDaysApprox = Math.floor(diffDays * 5/7); // Approximation jours ouvrés
                            const weeksApprox = Math.ceil(workingDaysApprox / 5); // Approximation semaines

                            // 🆕 Vérification si la période dépasse les données disponibles
                            <?php if (!empty($dateRange) && $dateRange['has_data']): ?>
                            const dataMinDate = new Date('<?php echo $dateRange['date_min']; ?>');
                            const dataMaxDate = new Date('<?php echo $dateRange['date_max']; ?>');
                            const isOutsideRange = (debut < dataMinDate || fin > dataMaxDate);


                            <?php else: ?>
                            let warningText = '';
                            <?php endif; ?>

                            summaryElement.innerHTML = `
                                <span class="period-preview">
                                    📅 Période à analyser : ${debut.toLocaleDateString('fr-FR')} → ${fin.toLocaleDateString('fr-FR')}
                                    (≈ ${workingDaysApprox} jours ouvrés, ≈ ${weeksApprox} semaine(s))
                                </span>
                                ${warningText}
                            `;
                        }
                    }

                    // Initialisation au chargement de la page
                    document.addEventListener('DOMContentLoaded', function() {
                        console.log('Page chargée - Initialisation des graphiques par semaines et validation dates');

                        // 🛠️ DEBUGGING DES CALENDRIERS DE DATE
                        const dateDebut = document.getElementById('date_debut');
                        const dateFin = document.getElementById('date_fin');

                        console.log('Elements trouvés:', {
                            dateDebut: dateDebut ? 'OUI' : 'NON',
                            dateFin: dateFin ? 'OUI' : 'NON'
                        });

                        if (dateDebut) {
                            console.log('Date début - valeur:', dateDebut.value);
                            console.log('Date début - attributs:', {
                                type: dateDebut.type,
                                disabled: dateDebut.disabled,
                                readonly: dateDebut.readOnly,
                                style: dateDebut.style.cssText
                            });

                            // Test d'interaction
                            dateDebut.addEventListener('click', function(e) {
                                console.log('✅ Clic détecté sur date début');
                            });

                            dateDebut.addEventListener('change', function(e) {
                                console.log('✅ Changement détecté sur date début:', e.target.value);
                                updatePeriodInfo();
                            });

                            dateDebut.addEventListener('input', function(e) {
                                console.log('✅ Input détecté sur date début:', e.target.value);
                            });
                        }

                        if (dateFin) {
                            console.log('Date fin - valeur:', dateFin.value);

                            dateFin.addEventListener('click', function(e) {
                                console.log('✅ Clic détecté sur date fin');
                            });

                            dateFin.addEventListener('change', function(e) {
                                console.log('✅ Changement détecté sur date fin:', e.target.value);
                                updatePeriodInfo();
                            });

                            dateFin.addEventListener('input', function(e) {
                                console.log('✅ Input détecté sur date fin:', e.target.value);
                            });
                        }

                        // Test de support navigateur
                        const testInput = document.createElement('input');
                        testInput.type = 'date';
                        const supportsDate = testInput.type === 'date';
                        console.log('Support navigateur pour input[type="date"]:', supportsDate);

                        if (!supportsDate) {
                            console.warn('⚠️ Le navigateur ne supporte pas input[type="date"]');
                            // Fallback pour vieux navigateurs
                            if (dateDebut) dateDebut.type = 'text';
                            if (dateFin) dateFin.type = 'text';
                        }

                        // Afficher Production par défaut
                        showChart('production');

                        // Validation initiale
                        if (dateDebut && dateFin) {
                            updatePeriodInfo();
                        }

                        // 🛠️ FORCER L'INTERACTION SUR LES CALENDRIERS
                        setTimeout(function() {
                            if (dateDebut && dateFin) {
                                // Forcer focus/blur pour activer les calendriers
                                dateDebut.focus();
                                dateDebut.blur();
                                dateFin.focus();
                                dateFin.blur();
                                console.log('🔄 Focus/blur forcé sur les calendriers');
                            }
                        }, 500);

                        // Validation du formulaire avant soumission
                        const periodForm = document.getElementById('periodForm');
                        if (periodForm) {
                            periodForm.addEventListener('submit', function(e) {
                                console.log('📤 Soumission formulaire');

                                if (!dateDebut || !dateFin) {
                                    console.error('❌ Elements date non trouvés');
                                    return;
                                }

                                const debut = new Date(dateDebut.value);
                                const fin = new Date(dateFin.value);

                                console.log('Dates soumises:', {
                                    debut: dateDebut.value,
                                    fin: dateFin.value,
                                    debutValid: !isNaN(debut),
                                    finValid: !isNaN(fin)
                                });

                                if (debut > fin) {
                                    alert('⚠️ La date de début doit être antérieure à la date de fin.');
                                    e.preventDefault();
                                    return false;
                                }

                                // Avertissement pour les très longues périodes (> 90 jours)
                                const diffTime = Math.abs(fin - debut);
                                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                                if (diffDays > 90) {
                                    const confirm = window.confirm(
                                        `📊 Vous analysez une période de ${diffDays} jours.\n\n` +
                                        `Les graphiques seront organisés par semaines avec moyennes calculées.\n\n` +
                                        `Voulez-vous continuer ?`
                                    );
                                    if (!confirm) {
                                        e.preventDefault();
                                        return false;
                                    }
                                }
                            });
                        }
                    });
                </script>
            </div>
        </div>

        <style>
            /* 🆕 STYLES POUR LE SÉLECTEUR DE PÉRIODE LIBRE */
            .period-selector-container {
                margin: 30px 0;
                padding: 20px;
                border: 2px solid #2196F3;
                border-radius: 8px;
                background-color: #f8f9ff;
            }

            .period-selector-container h2 {
                color: #2196F3;
                margin-top: 0;
                margin-bottom: 20px;
                border-bottom: 1px solid #2196F3;
                padding-bottom: 10px;
            }

            .period-form {
                max-width: 100%;
            }

            .date-inputs {
                display: flex;
                align-items: end;
                gap: 20px;
                flex-wrap: wrap;
                margin-bottom: 15px;
            }

            .date-inputs .form-group {
                flex: 1;
                min-width: 200px;
            }

            .date-inputs .form-group label {
                display: block;
                font-weight: bold;
                color: #333;
                font-size: 14px;
                margin-bottom: 8px;
            }

            .date-inputs .form-group input[type="date"] {
                width: 100%;
                padding: 12px 15px;
                border: 2px solid #ddd;
                border-radius: 6px;
                background-color: white;
                font-size: 16px;
                cursor: pointer;
                transition: border-color 0.3s;
                position: relative;
                z-index: 1;
                pointer-events: auto;
            }

            .date-inputs .form-group input[type="date"]:focus {
                outline: none;
                border-color: #2196F3;
                box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
            }

            /* Force l'affichage du calendrier */
            .date-inputs .form-group input[type="date"]::-webkit-calendar-picker-indicator {
                background: transparent;
                bottom: 0;
                color: transparent;
                cursor: pointer;
                height: auto;
                left: 0;
                position: absolute;
                right: 0;
                top: 0;
                width: auto;
                pointer-events: auto;
            }

            .date-info {
                margin-top: 5px;
            }

            .date-info small {
                color: #666;
                font-size: 12px;
            }

            .btn-analyze-period {
                background-color: #2196F3;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s;
                white-space: nowrap;
                min-height: 48px;
            }

            .btn-analyze-period:hover {
                background-color: #1976D2;
                transform: translateY(-1px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            }

            .period-info {
                margin-top: 15px;
                padding: 15px;
                background-color: #e3f2fd;
                border-radius: 6px;
                border-left: 4px solid #2196F3;
            }

            .period-summary {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .period-details, .period-preview {
                font-weight: 500;
                color: #1976d2;
                font-size: 16px;
            }

            .data-count {
                font-size: 14px;
                color: #4CAF50;
                font-weight: 500;
            }

            .available-range {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #bbdefb;
            }

            .available-range small {
                color: #666;
                font-style: italic;
            }

            .error-text {
                color: #f44336 !important;
                font-weight: bold;
            }

            .info-text {
                color: #2196F3 !important;
                font-weight: 500;
                font-size: 14px;
            }

            /* 🆕 STYLES POUR LE DÉFILEMENT HORIZONTAL */
            .charts-scroll-container {
                width: 100%;
                overflow-x: auto;
                overflow-y: hidden;
                border: 2px solid #ddd;
                border-radius: 8px;
                background-color: #fafafa;
                margin-bottom: 20px;
            }

            .charts-content {
                min-width: fit-content;
                padding: 20px;
            }

            .chart-image {
                width: auto; /* Largeur variable selon la période */
                height: 450px; /* Hauteur fixe */
                max-width: none; /* Permettre de dépasser la largeur du container */
                border: 1px solid #ccc;
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            /* 🆕 STYLES POUR INFO GRAPHIQUES */
            .period-chart-info {
                margin: 20px 0;
                padding: 15px;
                background-color: #f5f5f5;
                border-radius: 6px;
                border-left: 4px solid #4CAF50;
            }

            .period-chart-info p {
                margin: 5px 0;
                font-size: 14px;
                color: #555;
            }

            /* Message si aucune période */
            .no-period-message {
                text-align: center;
                padding: 40px 20px;
                background-color: #f9f9f9;
                border: 2px dashed #ccc;
                border-radius: 8px;
                margin: 30px 0;
            }

            .no-period-message h3 {
                color: #666;
                margin-bottom: 15px;
            }

            .no-period-message p {
                color: #888;
                font-size: 16px;
            }

            /* Section récapitulatif global */
            .global-summary-section {
                margin-top: 50px;
                border-top: 3px solid #eee;
                padding-top: 30px;
            }

            .global-summary-section h2 {
                color: #666;
            }

            .summary-note {
                color: #888;
                font-style: italic;
                margin-bottom: 20px;
                padding: 10px;
                background-color: #f9f9f9;
                border-radius: 4px;
                border-left: 4px solid #FFC107;
            }

            /* Styles existants conservés */
            .weekly-summary-section {
                margin-top: 40px;
                border-top: 2px solid #eee;
                padding-top: 30px;
            }

            .weekly-summary {
                margin-bottom: 20px;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
                background-color: #fafafa;
            }

            .weekly-summary h4 {
                margin: 0 0 10px 0;
                color: #333;
                font-size: 16px;
            }

            .weekly-processes {
                width: 100%;
            }

            .process-charge {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }

            .process-charge:last-child {
                border-bottom: none;
            }

            .process-name {
                font-weight: 500;
                color: #555;
            }

            .charge-value {
                font-weight: bold;
                color: #2196F3;
            }

            /* Styles existants pour les graphiques */
            .graphiques-container {
                margin-top: 30px;
            }

            .graphiques-container h2 {
                color: #333;
                border-bottom: 2px solid #2196F3;
                padding-bottom: 10px;
                margin-bottom: 30px;
            }

            .graphique-section {
                margin-bottom: 20px;
                text-align: center;
            }

            .graphique-section h3 {
                color: #333;
                margin-top: 0;
                margin-bottom: 15px;
                font-size: 18px;
            }

            .chart-description {
                margin-top: 10px;
                color: #666;
                font-style: italic;
                font-size: 14px;
            }

            .chart-placeholder {
                height: 200px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                border: 2px dashed #ccc;
                border-radius: 8px;
                background-color: #f9f9f9;
                color: #666;
                font-style: italic;
            }

            .chart-placeholder small {
                margin-top: 5px;
                font-size: 12px;
                color: #999;
            }

            /* STYLES POUR LES ONGLETS DE GRAPHIQUES */
            .graphiques-tabs {
                display: flex;
                gap: 10px;
                margin-bottom: 20px;
                justify-content: center;
                flex-wrap: wrap;
            }

            .tab-button {
                background-color: #f5f5f5;
                color: #333;
                border: 2px solid #ddd;
                padding: 12px 20px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 16px;
                font-weight: bold;
                transition: all 0.3s;
            }

            .tab-button:hover {
                background-color: #e0e0e0;
                border-color: #bbb;
            }

            .tab-button.active {
                background-color: #2196F3;
                color: white;
                border-color: #2196F3;
            }

            .chart-content {
                display: block;
            }

            .chart-content.hidden {
                display: none;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .date-inputs {
                    flex-direction: column;
                    gap: 15px;
                }

                .date-inputs .form-group {
                    min-width: 100%;
                }

                .btn-analyze-period {
                    width: 100%;
                    padding: 15px;
                }

                .graphiques-tabs {
                    flex-direction: column;
                    gap: 5px;
                }

                .tab-button {
                    padding: 10px 15px;
                    font-size: 14px;
                }

                .period-summary {
                    font-size: 14px;
                }

                .charts-scroll-container {
                    border-radius: 4px;
                }

                .charts-content {
                    padding: 10px;
                }
            }
        </style>

        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}