<?php
namespace modules\blog\views;

/**
 * Classe DashboardView
 *
 * Cette classe gère l'affichage du tableau de bord.
 * Adaptée pour les nouvelles méthodes du contrôleur refactorisé.
 * VERSION MISE À JOUR : Réorganisation des boutons d'action
 */
class DashboardView {
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
            <title>Erreur</title>
            <link rel="stylesheet" href="_assets/css/dashboard.css">
            <link rel="stylesheet" href="_assets/css/dashboard-files.css">
        </head>
        <body>
        <div class="navbar">
            <a href="index.php?action=dashboard">Tableau de bord</a>
            <a href="index.php?action=analyse-charge">Analyse de Charge</a>
            <a href="index.php?action=logout">Déconnexion</a>
        </div>

        <div class="container">
            <h1>Erreur</h1>
            <div class="message error">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <a href="index.php?action=dashboard" class="btn back-link">Retour au tableau de bord</a>
        </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Affiche le tableau de bord principal
     *
     * @param array $userInfo Informations de l'utilisateur
     * @param array $dashboardData Données du dashboard
     * @return string Le contenu HTML généré
     */
    public function showDashboard($userInfo, $dashboardData) {
        return $this->renderDashboard($userInfo, $dashboardData);
    }

    /**
     * Affiche le tableau de bord avec un résultat d'opération
     *
     * @param array $userInfo Informations de l'utilisateur
     * @param array $dashboardData Données du dashboard
     * @param array $result Résultat de l'opération
     * @return string Le contenu HTML généré
     */
    public function showDashboardWithResult($userInfo, $dashboardData, $result) {
        return $this->renderDashboard($userInfo, $dashboardData, $result);
    }

    /**
     * Affiche toutes les données
     *
     * @param array $userInfo Informations de l'utilisateur
     * @param array $allData Toutes les données
     * @return string Le contenu HTML généré
     */
    public function showAllData($userInfo, $allData) {
        return $this->renderDashboard($userInfo, $allData, null, true);
    }

    /**
     * Génère le HTML du tableau de bord (méthode commune)
     * 🔄 MISE À JOUR : Suppression affichage données et réorganisation boutons
     *
     * @param array $userInfo Informations de l'utilisateur
     * @param array $dashboardData Données du dashboard
     * @param array|null $result Résultat d'opération (optionnel)
     * @param bool $showAll Afficher toutes les données (OBSOLÈTE)
     * @return string Le contenu HTML généré
     */
    private function renderDashboard($userInfo, $dashboardData, $result = null, $showAll = false) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Tableau de bord - Gestion de Charge</title>
            <link rel="stylesheet" href="_assets/css/dashboard.css">
            <link rel="stylesheet" href="_assets/css/excel.css">
            <link rel="stylesheet" href="_assets/css/dashboard-files.css">
        </head>
        <body>
        <div class="navbar">
            <a href="index.php">gestion des données</a>
            <a href="index.php?action=analyse-charge">Analyse de Charge</a>
            <a href="index.php?action=logout">Déconnexion</a>
        </div>

        <div class="container">
            <div class="card">
                <h1>gestion des données d'entrée</h1>
                <p>Bienvenue <?php echo htmlspecialchars($userInfo['nom']); ?></p>


                <div class="menu-item">
                    <a href="index.php?action=analyse-charge">
                        <div class="icon">📊</div>
                        <h3>Analyse de charge</h3>
                        <p>Analyser la répartition de charge par période</p>
                    </a>
                </div>


                <!-- Section d'importation et conversion -->
                <div class="import-section">
                    <h2>Gestion des données</h2>

                    <!-- Affichage du résultat si présent -->
                    <?php if ($result): ?>
                        <div class="message <?php echo $result['success'] ? 'success' : 'error'; ?>">
                            <?php echo nl2br(htmlspecialchars($result['message'])); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Informations sur les fichiers -->
                    <?php if (isset($dashboardData['files_info'])): ?>
                        <div class="summary-box">
                            <div class="summary-title">Fichiers disponibles</div>
                            <p><strong>Fichiers MPP :</strong> <?php echo $dashboardData['files_info']['mpp_count']; ?> fichier(s)</p>
                            <p><strong>Fichiers XLSX :</strong> <?php echo $dashboardData['files_info']['xlsx_count']; ?> fichier(s)</p>

                            <!-- 🆕 SECTION DÉTAILLÉE DES FICHIERS XLSX -->
                            <?php if (isset($dashboardData['files_info']['xlsx_files_detailed']) && !empty($dashboardData['files_info']['xlsx_files_detailed'])): ?>
                                <div class="xlsx-files-details">
                                    <h4>📋 Détail des fichiers XLSX convertis :</h4>
                                    <div class="xlsx-files-list">
                                        <?php foreach ($dashboardData['files_info']['xlsx_files_detailed'] as $file): ?>
                                            <div class="xlsx-file-item <?php echo $file['has_numero'] ? 'with-numero' : 'without-numero'; ?>">
                                                <div class="file-main-info">
                                                    <span class="file-icon">📄</span>
                                                    <div class="file-details">
                                                        <?php if ($file['has_numero']): ?>
                                                            <div class="file-numero-affaire">
                                                                <strong>N° Affaire :</strong>
                                                                <span class="numero-badge"><?php echo htmlspecialchars($file['numero_affaire']); ?></span>
                                                            </div>
                                                            <div class="file-nom-propre">
                                                                <strong>Nom :</strong> <?php echo htmlspecialchars($file['nom_propre']); ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="file-nom-complet">
                                                                <strong>Fichier :</strong> <?php echo htmlspecialchars(pathinfo($file['name'], PATHINFO_FILENAME)); ?>
                                                            </div>
                                                            <div class="file-note">
                                                                <small>⚠️ Numéro d'affaire non détecté</small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="file-meta-info">
                                                    <small class="file-size">
                                                        📦 <?php echo htmlspecialchars($file['size_formatted']); ?>
                                                    </small>
                                                    <small class="file-date">
                                                        🕒 <?php echo htmlspecialchars($file['modified_formatted']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="xlsx-files-empty">
                                    <p><em>Aucun fichier XLSX disponible. Convertissez d'abord des fichiers MPP.</em></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- 🔄 SECTION MISE À JOUR : Conversion + Suppression + Vidage -->
                    <div class="convert-by-number-section">
                        <h3>🎯 Gestion par numéro d'affaire</h3>
                        <div class="convert-form-container">
                            <p class="convert-description">
                                Convertissez un fichier MPP spécifique, supprimez un fichier converti, ou videz complètement la base de données.<br>
                                <small>Format attendu : <code>24-09_0009</code> (pour un fichier nommé "AFF24-09_0009 planning en cours.mpp")</small>
                            </p>

                            <form action="index.php" method="POST" class="convert-form" id="convertForm">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="numero_affaire">Numéro d'affaire :</label>
                                        <input type="text"
                                               id="numero_affaire"
                                               name="numero_affaire"
                                               placeholder="Ex: 24-09_0009"
                                               pattern="[0-9]{2}-[0-9]{2}_[0-9]{4}"
                                               title="Format attendu : XX-XX_XXXX (ex: 24-09_0009)">
                                        <small class="input-note">*(Requis uniquement pour conversion/suppression ciblée)</small>
                                    </div>
                                    <div class="form-group buttons-group">
                                        <button type="submit" class="btn-convert-selective" onclick="setConvertAction('convert_by_number')">
                                            🔄 Convertir et ajouter ce fichier
                                        </button>
                                        <button type="submit" class="btn-delete-selective" onclick="setConvertAction('delete_by_number')">
                                            🗑️ Supprimer le fichier converti
                                        </button>
                                        <!-- 🆕 BOUTON VIDAGE DÉPLACÉ ICI -->
                                        <button type="button" class="btn-clear-database" onclick="clearDatabase()">
                                            💥 Vider la base de données
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" name="action" id="convertActionField" value="convert_by_number">
                            </form>
                        </div>
                    </div>

                    <!-- 🗑️ SECTION SUPPRIMÉE : action-buttons (importer, voir toutes données, vider base) -->
                </div>

                <!-- 🗑️ SECTION SUPPRIMÉE : Affichage des données brutes -->

                <!-- SECTION AJOUT MANUEL DE CHARGE -->
                <div class="add-charge-section">
                    <h2>Ajouter ou supprimer une charge manuellement</h2>

                    <form action="index.php" method="POST" class="add-charge-form" id="chargeForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="processus">Compétence :</label>
                                <input type="text"
                                       id="processus"
                                       name="processus"
                                       placeholder="Ex: CHAUDNQ, SOUDNQ, CT..."
                                       list="processus-suggestions"
                                       required>
                                <datalist id="processus-suggestions">
                                    <!-- Les suggestions seront ajoutées ici par le contrôleur -->
                                    <?php if (isset($dashboardData['processus_suggestions'])): ?>
                                    <?php foreach ($dashboardData['processus_suggestions'] as $suggestion): ?>
                                    <option value="<?php echo htmlspecialchars($suggestion); ?>">
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                </datalist>
                            </div>

                            <div class="form-group">
                                <label for="tache">Projet :</label>
                                <input type="text"
                                       id="tache"
                                       name="tache"
                                       placeholder="Description du projet"
                                       required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="charge">Charge :</label>
                                <input type="number"
                                       id="charge"
                                       name="charge"
                                       step="1"
                                       min="0"
                                       placeholder="Ex: 1"
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="date">Date :</label>
                                <input type="date"
                                       id="date"
                                       name="date"
                                       value="<?php echo date('Y-m-d'); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-add-charge" onclick="setAction('add_charge')">
                                ➕ Ajouter la charge
                            </button>
                            <button type="submit" class="btn-delete-charge" onclick="setAction('delete_charge')">
                                🗑️ Supprimer la charge
                            </button>
                        </div>

                        <!-- Champ hidden pour l'action -->
                        <input type="hidden" name="action" id="actionField" value="add_charge">
                    </form>

                    <script>
                        // Fonction pour définir l'action du formulaire
                        function setAction(action) {
                            document.getElementById('actionField').value = action;

                            // Changer la couleur du bouton temporairement pour feedback visuel
                            if (action === 'delete_charge') {
                                console.log('🗑️ Action: Suppression de charge');
                            } else {
                                console.log('➕ Action: Ajout de charge');
                            }
                        }

                        // Confirmation pour la suppression
                        document.addEventListener('DOMContentLoaded', function() {
                            const deleteBtn = document.querySelector('.btn-delete-charge');
                            if (deleteBtn) {
                                deleteBtn.addEventListener('click', function(e) {
                                    const processus = document.getElementById('processus').value;
                                    const tache = document.getElementById('tache').value;
                                    const charge = document.getElementById('charge').value;
                                    const date = document.getElementById('date').value;

                                    if (!processus || !tache || !charge || !date) {
                                        alert('Veuillez remplir tous les champs avant de supprimer.');
                                        e.preventDefault();
                                        return;
                                    }

                                    const confirmMsg = `Êtes-vous sûr de vouloir supprimer cette charge ?\n\n` +
                                        `Processus: ${processus}\n` +
                                        `Tâche: ${tache}\n` +
                                        `Charge: ${charge}\n` +
                                        `Date: ${date}`;

                                    if (!confirm(confirmMsg)) {
                                        e.preventDefault();
                                    }
                                });
                            }
                        });
                    </script>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // 🔄 MISE À JOUR : Validation du formulaire de conversion
                        const convertForm = document.getElementById('convertForm');
                        if (convertForm) {
                            convertForm.addEventListener('submit', function(e) {
                                const action = document.getElementById('convertActionField').value;

                                // Pour le vidage de base, pas besoin de numéro d'affaire
                                if (action === 'clear_data') {
                                    return; // Laisser le formulaire se soumettre normalement
                                }

                                // Pour conversion/suppression, valider le numéro d'affaire
                                const numeroAffaire = document.getElementById('numero_affaire').value.trim();

                                if (!numeroAffaire) {
                                    alert('Veuillez saisir un numéro d\'affaire pour cette action.');
                                    e.preventDefault();
                                    return;
                                }

                                // Validation du format
                                const formatPattern = /^[0-9]{2}-[0-9]{2}_[0-9]{4}$/;
                                if (!formatPattern.test(numeroAffaire)) {
                                    alert('Format invalide. Utilisez le format XX-XX_XXXX (ex: 24-09_0009)');
                                    e.preventDefault();
                                    return;
                                }

                                let confirmMsg = '';
                                if (action === 'delete_by_number') {
                                    confirmMsg = `Supprimer le fichier XLSX converti contenant le numéro d'affaire "${numeroAffaire}" ?\n\n` +
                                        `Le système recherchera dans le dossier 'converted' et supprimera le fichier correspondant.`;
                                } else {
                                    confirmMsg = `Convertir le fichier avec le numéro d'affaire "${numeroAffaire}" ?\n\n` +
                                        `Le système recherchera un fichier contenant ce numéro dans le dossier uploads.`;
                                }

                                if (!confirm(confirmMsg)) {
                                    e.preventDefault();
                                }
                            });
                        }

                        // 🆕 Fonction pour définir l'action de conversion/suppression
                        window.setConvertAction = function(action) {
                            document.getElementById('convertActionField').value = action;
                            console.log('Action sélectionnée:', action);
                        };

                        // 🆕 Fonction pour vider la base de données
                        window.clearDatabase = function() {
                            const confirmMsg = `⚠️ ATTENTION : Cette action va supprimer TOUTES les données de la base !\n\n` +
                                `Cette action est irréversible. Êtes-vous absolument sûr de vouloir continuer ?`;

                            if (confirm(confirmMsg)) {
                                // Deuxième confirmation pour éviter les clics accidentels
                                const secondConfirm = confirm(`🚨 DERNIÈRE CONFIRMATION 🚨\n\n` +
                                    `Toutes les données de charge vont être supprimées définitivement.\n\n` +
                                    `Confirmer le vidage de la base ?`);

                                if (secondConfirm) {
                                    // Rediriger vers l'action de vidage
                                    window.location.href = 'index.php?subaction=clear_data';
                                }
                            }
                        };
                    });
                </script>
            </div>

        </body>
        </html>

        <style>
            /* 🆕 STYLES POUR LE NOUVEAU BOUTON DE VIDAGE - NOIR */
            .btn-clear-database {
                background-color: #333333 !important;  /* Noir avec !important pour forcer */
                color: white !important;               /* Texte blanc avec !important */
                border: none !important;
                padding: 12px 24px;
                border-radius: 6px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s;
                white-space: nowrap;
                min-width: 200px;
            }

            .btn-clear-database:hover {
                background-color: #1a1a1a !important; /* Noir plus foncé au hover */
                color: white !important;
                transform: translateY(-1px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            }
        </style>
        <?php
        return ob_get_clean();
    }
}