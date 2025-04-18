<?php

namespace App\Console\Commands;

use App\Services\DoctrineService;
use Doctrine\ORM\EntityManager;
use Illuminate\Console\Command;

class TestDoctrine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'doctrine:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la conexión de Doctrine a la base de datos';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Probando conexión de Doctrine...');
        
        try {
            // Obtener EntityManager
            $entityManager = DoctrineService::getEntityManager();
            
            // Verificar conexión a la base de datos
            $connection = $entityManager->getConnection();
            
            // Probar consulta simple (esto también verifica la conexión)
            $result = $connection->executeQuery('SELECT 1 as test')->fetchAssociative();
            $this->info('✅ Conexión exitosa a la base de datos!');
            $this->info('Consulta de prueba: ' . json_encode($result));
            
            // Listar entidades registradas
            $this->info('Entidades registradas:');
            $metadataFactory = $entityManager->getMetadataFactory();
            $classes = $metadataFactory->getAllMetadata();
            
            foreach ($classes as $metadata) {
                $this->info("- " . $metadata->getName());
                
                // Mostrar tabla
                $tableName = $metadata->table['name'] ?? null;
                if ($tableName) {
                    $this->info("  Tabla: " . $tableName);
                }
                
                // Mostrar columnas
                $this->info("  Columnas:");
                foreach ($metadata->getFieldNames() as $fieldName) {
                    $fieldMappings = $metadata->fieldMappings ?? [];
                    if (isset($fieldMappings[$fieldName])) {
                        $type = $fieldMappings[$fieldName]['type'] ?? 'desconocido';
                        $this->info("    {$fieldName} -> {$type}");
                    } else {
                        $this->info("    {$fieldName}");
                    }
                }
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 