<?php

namespace App\Doctrine\Repositories;

use App\Doctrine\Entities\Client;
use App\Doctrine\Entities\Transaction;
use App\Services\DoctrineService;
use Doctrine\ORM\EntityRepository;

class TransactionRepository
{
    private EntityRepository $repository;

    public function __construct()
    {
        $entityManager = DoctrineService::getEntityManager();
        $this->repository = $entityManager->getRepository(Transaction::class);
    }

    /**
     * Busca una transacción por su ID
     */
    public function find(int $id): ?Transaction
    {
        return $this->repository->find($id);
    }

    /**
     * Busca una transacción por session_id y token
     */
    public function findBySessionAndToken(string $sessionId, string $token): ?Transaction
    {
        return $this->repository->findOneBy([
            'session_id' => $sessionId,
            'token' => $token,
            'status' => 'pending'
        ]);
    }

    /**
     * Crea una nueva transacción de depósito
     */
    public function createDeposit(Client $client, float $amount): Transaction
    {
        $transaction = new Transaction();
        $transaction->setClient($client)
            ->setType('deposit')
            ->setAmount($amount)
            ->setStatus('completed');
        
        $this->save($transaction);
        
        return $transaction;
    }

    /**
     * Crea una transacción de pago pendiente
     */
    public function createPendingPayment(Client $client, float $amount, string $sessionId, string $token): Transaction
    {
        $transaction = new Transaction();
        $transaction->setClient($client)
            ->setType('payment')
            ->setAmount($amount)
            ->setSessionId($sessionId)
            ->setToken($token)
            ->setStatus('pending');
        
        $this->save($transaction);
        
        return $transaction;
    }

    /**
     * Guarda una transacción en la base de datos
     */
    public function save(Transaction $transaction): void
    {
        $entityManager = DoctrineService::getEntityManager();
        
        if ($transaction->getId() === null) {
            $entityManager->persist($transaction);
        }
        
        $transaction->setUpdatedAt(new \DateTime());
        $entityManager->flush();
    }
} 