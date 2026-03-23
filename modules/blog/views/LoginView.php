<?php
namespace modules\blog\views;

/**
 * Classe LoginView
 *
 * Cette classe gère l'affichage de la page de connexion moderne.
 */
class LoginView {

    /**
     * Affiche le formulaire de connexion moderne
     *
     * @param string|null $error Message d'erreur à afficher, le cas échéant
     * @return string Le contenu HTML généré
     */
    public function showLoginForm($error = null) {
        // Début de la mise en mémoire tampon
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Connexion - Gestion de Charge</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                    overflow: hidden;
                }

                /* Fond flou animé */
                body::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background:
                            radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                            radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                            radial-gradient(circle at 40% 40%, rgba(120, 200, 255, 0.3) 0%, transparent 50%);
                    animation: rotate 20s linear infinite;
                    filter: blur(40px);
                }

                @keyframes rotate {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                .login-container {
                    background: rgba(255, 255, 255, 0.15);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    border-radius: 20px;
                    padding: 40px;
                    width: 400px;
                    box-shadow: 0 25px 45px rgba(0, 0, 0, 0.1);
                    position: relative;
                    z-index: 10;
                }

                .logo-section {
                    text-align: center;
                    margin-bottom: 30px;
                }

                .logo-section h1 {
                    color: white;
                    font-size: 28px;
                    font-weight: 300;
                    margin-bottom: 10px;
                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                .logo-section .subtitle {
                    color: rgba(255, 255, 255, 0.8);
                    font-size: 16px;
                    font-weight: 300;
                }

                .login-form h2 {
                    text-align: center;
                    color: white;
                    margin-bottom: 30px;
                    font-weight: 400;
                    font-size: 24px;
                }

                .form-group {
                    margin-bottom: 25px;
                    position: relative;
                }

                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    color: rgba(255, 255, 255, 0.9);
                    font-weight: 500;
                    font-size: 14px;
                }

                .form-group input {
                    width: 100%;
                    padding: 15px 20px;
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    border-radius: 12px;
                    background: rgba(255, 255, 255, 0.1);
                    color: white;
                    font-size: 16px;
                    transition: all 0.3s ease;
                    backdrop-filter: blur(10px);
                }

                .form-group input::placeholder {
                    color: rgba(255, 255, 255, 0.6);
                }

                .form-group input:focus {
                    outline: none;
                    border-color: rgba(255, 255, 255, 0.6);
                    background: rgba(255, 255, 255, 0.2);
                    box-shadow: 0 0 20px rgba(255, 255, 255, 0.1);
                    transform: translateY(-2px);
                }

                .login-button {
                    width: 100%;
                    padding: 15px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    border-radius: 12px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    margin-top: 10px;
                    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
                }

                .login-button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
                }

                .login-button:active {
                    transform: translateY(0);
                }

                .error {
                    background: rgba(255, 82, 82, 0.15);
                    border: 1px solid rgba(255, 82, 82, 0.3);
                    color: #ff6b6b;
                    padding: 15px;
                    border-radius: 12px;
                    text-align: center;
                    margin-bottom: 20px;
                    backdrop-filter: blur(10px);
                    font-size: 14px;
                }

                .login-container {
                    animation: slideIn 0.6s ease-out;
                }

                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateY(50px) scale(0.9);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                }

                @media (max-width: 480px) {
                    .login-container {
                        width: 90%;
                        padding: 30px 20px;
                    }

                    .logo-section h1 {
                        font-size: 24px;
                    }

                    .login-form h2 {
                        font-size: 20px;
                    }
                }

                .floating-particles {
                    position: absolute;
                    width: 100%;
                    height: 100%;
                    overflow: hidden;
                    z-index: 1;
                }

                .particle {
                    position: absolute;
                    width: 4px;
                    height: 4px;
                    background: rgba(255, 255, 255, 0.5);
                    border-radius: 50%;
                    animation: float 6s infinite linear;
                }

                @keyframes float {
                    0% {
                        transform: translateY(100vh) scale(0);
                        opacity: 0;
                    }
                    10% {
                        opacity: 1;
                    }
                    90% {
                        opacity: 1;
                    }
                    100% {
                        transform: translateY(-100vh) scale(1);
                        opacity: 0;
                    }
                }
            </style>
        </head>
        <body>
        <!-- Particules flottantes -->
        <div class="floating-particles">
            <div class="particle" style="left: 10%; animation-delay: -2s;"></div>
            <div class="particle" style="left: 20%; animation-delay: -4s;"></div>
            <div class="particle" style="left: 30%; animation-delay: -6s;"></div>
            <div class="particle" style="left: 40%; animation-delay: -8s;"></div>
            <div class="particle" style="left: 50%; animation-delay: -10s;"></div>
            <div class="particle" style="left: 60%; animation-delay: -12s;"></div>
            <div class="particle" style="left: 70%; animation-delay: -14s;"></div>
            <div class="particle" style="left: 80%; animation-delay: -16s;"></div>
            <div class="particle" style="left: 90%; animation-delay: -18s;"></div>
        </div>

        <div class="login-container">
            <!-- Section logo/titre -->
            <div class="logo-section">
                <h1> Moscatelli </h1>
                <p class="subtitle">Gestion de Charge</p>
            </div>

            <!-- Formulaire de connexion -->
            <div class="login-form">
                <h2>Connexion</h2>

                <?php if ($error): ?>
                    <div class="error">
                        <i>⚠️</i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="index.php?action=login" method="post">
                    <div class="form-group">
                        <label for="identifiant">Identifiant</label>
                        <input type="text"
                               id="identifiant"
                               name="identifiant"
                               placeholder="Votre identifiant"
                               required
                               autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password"
                               id="password"
                               name="password"
                               placeholder="Votre mot de passe"
                               required
                               autocomplete="current-password">
                    </div>

                    <button type="submit" class="login-button">
                        Se connecter
                    </button>
                </form>
            </div>
        </div>

        <script>
            // Animation d'entrée pour les champs
            document.addEventListener('DOMContentLoaded', function() {
                const inputs = document.querySelectorAll('input');

                inputs.forEach((input, index) => {
                    input.style.opacity = '0';
                    input.style.transform = 'translateX(-20px)';

                    setTimeout(() => {
                        input.style.transition = 'all 0.4s ease';
                        input.style.opacity = '1';
                        input.style.transform = 'translateX(0)';
                    }, 300 + (index * 100));
                });

                // Focus automatique sur le premier champ
                document.getElementById('identifiant').focus();
            });
        </script>
        </body>
        </html>
        <?php
        // Retourne le contenu mis en mémoire tampon
        return ob_get_clean();
    }
}