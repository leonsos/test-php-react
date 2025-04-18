<?php

namespace App\Console\Commands;

use App\Services\DoctrineService;
use Doctrine\ORM\Tools\SchemaTool;
use Illuminate\Console\Command;

class DoctrineSchemaCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'doctrine:schema:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea el esquema de la base de datos a partir de las entidades Doctrine';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $entityManager = DoctrineService::getEntityManager();
        $metadataFactory = $entityManager->getMetadataFactory();
        $schemaTool = new SchemaTool($entityManager);
        
        $classes = $metadataFactory->getAllMetadata();
        
        if (empty($classes)) {
            $this->error('No se encontraron entidades Doctrine. Verifica que estén correctamente definidas y ubicadas en app/Doctrine/Entities/');
            return Command::FAILURE;
        }
        
        $this->info('Creando esquema de base de datos a partir de ' . count($classes) . ' entidades...');
        
        try {
            $schemaTool->createSchema($classes);
            $this->info('Esquema de base de datos creado correctamente.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error al crear el esquema de la base de datos: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 