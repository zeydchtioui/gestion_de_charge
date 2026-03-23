<?php

namespace _assets\config;

/**
 * Classe Config
 *
 * Cette classe fournit la configuration de la base de données.
 */
class Config
{
    /**
     * Obtenir la configuration de la base de données.
     *
     * Cette méthode retourne un tableau contenant le Data Source Name (DSN),
     * le nom d'utilisateur et le mot de passe nécessaires pour se connecter à la base de données.
     *
     * @return array Le tableau de configuration de la base de données.
     */
    public function getDatabaseConfig(): array
    {
        return [
            'dsn' => 'mysql:host=mysql-thevenaut.alwaysdata.net;dbname=thevenaut_mosca;charset=utf8',
            'username' => 'thevenaut',
            'password' => 'Arceus2004',
        ];
    }
}