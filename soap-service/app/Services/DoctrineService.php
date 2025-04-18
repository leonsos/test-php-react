<?php

namespace App\Services;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Illuminate\Support\Facades\Config;

class DoctrineService
{
    private static ?EntityManager $entityManager = null;

    /**
     * Obtiene la instancia de EntityManager
     */
    public static function getEntityManager(): EntityManager
    {
        if (self::$entityManager === null) {
            $paths = [app_path('Doctrine/Entities')];
            $isDevMode = Config::get('app.debug', false);
            
            // Configuración básica
            $config = ORMSetup::createAttributeMetadataConfiguration(
                $paths,
                $isDevMode,
                null,
                null
            );
            
            // Configuración de la base de datos basada en la configuración de Laravel
            $connection = DriverManager::getConnection([
                'driver'   => 'pdo_mysql',
                'host'     => Config::get('database.connections.mysql.host'),
                'port'     => Config::get('database.connections.mysql.port'),
                'dbname'   => Config::get('database.connections.mysql.database'),
                'user'     => Config::get('database.connections.mysql.username'),
                'password' => Config::get('database.connections.mysql.password'),
                'charset'  => Config::get('database.connections.mysql.charset')
            ], $config);
            
            // Crear EntityManager
            self::$entityManager = new EntityManager($connection, $config);
        }
        
        return self::$entityManager;
    }

    /**
     * Cierra la conexión del EntityManager
     */
    public static function closeEntityManager(): void
    {
        if (self::$entityManager !== null) {
            self::$entityManager->close();
            self::$entityManager = null;
        }
    }
} 