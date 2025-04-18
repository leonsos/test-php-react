<?php

namespace App\Doctrine\Repositories;

use App\Doctrine\Entities\Client;
use App\Services\DoctrineService;
use Doctrine\ORM\EntityRepository;

class ClientRepository
{
    private EntityRepository $repository;

    public function __construct()
    {
        $entityManager = DoctrineService::getEntityManager();
        $this->repository = $entityManager->getRepository(Client::class);
    }

    /**
     * Busca un cliente por su ID
     */
    public function find(int $id): ?Client
    {
        return $this->repository->find($id);
    }

    /**
     * Busca un cliente por su documento y teléfono
     */
    public function findByDocumentAndPhone(string $document, string $phone): ?Client
    {
        return $this->repository->findOneBy([
            'document' => $document,
            'phone' => $phone
        ]);
    }

    /**
     * Verifica si un cliente ya existe por documento, email o teléfono
     */
    public function exists(string $document, string $email, string $phone): bool
    {
        $entityManager = DoctrineService::getEntityManager();
        $queryBuilder = $entityManager->createQueryBuilder();
        
        $queryBuilder
            ->select('COUNT(c.id)')
            ->from(Client::class, 'c')
            ->where('c.document = :document')
            ->orWhere('c.email = :email')
            ->orWhere('c.phone = :phone')
            ->setParameter('document', $document)
            ->setParameter('email', $email)
            ->setParameter('phone', $phone);
        
        return (int)$queryBuilder->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Guarda un cliente en la base de datos
     */
    public function save(Client $client): void
    {
        $entityManager = DoctrineService::getEntityManager();
        
        if ($client->getId() === null) {
            $entityManager->persist($client);
        }
        
        $client->setUpdatedAt(new \DateTime());
        $entityManager->flush();
    }
} 