<?php

namespace App\Services;

use App\Doctrine\Entities\Client;
use App\Doctrine\Repositories\ClientRepository;
use App\Doctrine\Repositories\TransactionRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletDoctrineService implements WalletServiceInterface
{
    private ClientRepository $clientRepository;
    private TransactionRepository $transactionRepository;

    public function __construct()
    {
        $this->clientRepository = new ClientRepository();
        $this->transactionRepository = new TransactionRepository();
    }

    /**
     * Registra un nuevo cliente en el sistema
     *
     * @param string $document Documento de identidad
     * @param string $name Nombre completo
     * @param string $email Correo electrónico
     * @param string $phone Número de teléfono
     * @return array
     */
    public function registerClient($document, $name, $email, $phone)
    {
        try {
            // Validar que todos los campos son requeridos
            if (empty($document) || empty($name) || empty($email) || empty($phone)) {
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'Todos los campos son requeridos'
                ];
            }

            // Verificar si el cliente ya existe
            if ($this->clientRepository->exists($document, $email, $phone)) {
                return [
                    'success' => false,
                    'code' => 409,
                    'message' => 'El cliente ya existe con el documento, email o teléfono proporcionado'
                ];
            }

            // Crear nuevo cliente
            $client = new Client();
            $client->setDocument($document)
                ->setName($name)
                ->setEmail($email)
                ->setPhone($phone)
                ->setBalance(0);
            
            $this->clientRepository->save($client);

            return [
                'success' => true,
                'code' => 201,
                'message' => 'Cliente registrado con éxito',
                'data' => [
                    'client_id' => $client->getId()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error al registrar cliente: ' . $e->getMessage());
            
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Recarga la billetera del cliente
     *
     * @param string $document Documento de identidad
     * @param string $phone Número de teléfono
     * @param float $amount Monto a recargar
     * @return array
     */
    public function rechargeWallet($document, $phone, $amount)
    {
        try {
            // Validar campos requeridos
            if (empty($document) || empty($phone) || empty($amount)) {
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'Todos los campos son requeridos'
                ];
            }

            // Validar monto positivo
            if ($amount <= 0) {
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'El monto debe ser mayor a cero'
                ];
            }

            // Buscar cliente
            $client = $this->clientRepository->findByDocumentAndPhone($document, $phone);

            if (!$client) {
                return [
                    'success' => false,
                    'code' => 404,
                    'message' => 'Cliente no encontrado'
                ];
            }

            // Iniciar transacción Doctrine
            $entityManager = DoctrineService::getEntityManager();
            $entityManager->beginTransaction();
            
            try {
                // Registrar transacción
                $transaction = $this->transactionRepository->createDeposit($client, $amount);

                // Actualizar saldo
                $client->setBalance($client->getBalance() + $amount);
                $this->clientRepository->save($client);

                $entityManager->commit();

                return [
                    'success' => true,
                    'code' => 200,
                    'message' => 'Recarga realizada con éxito',
                    'data' => [
                        'new_balance' => $client->getBalance(),
                        'transaction_id' => $transaction->getId()
                    ]
                ];
            } catch (\Exception $e) {
                $entityManager->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error al recargar billetera: ' . $e->getMessage());
            
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Inicia un proceso de pago generando un token
     *
     * @param string $document Documento de identidad
     * @param string $phone Número de teléfono
     * @param float $amount Monto a pagar
     * @return array
     */
    public function makePayment($document, $phone, $amount)
    {
        try {
            // Validar campos requeridos
            if (empty($document) || empty($phone) || empty($amount)) {
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'Todos los campos son requeridos'
                ];
            }

            // Validar monto positivo
            if ($amount <= 0) {
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'El monto debe ser mayor a cero'
                ];
            }

            // Buscar cliente
            $client = $this->clientRepository->findByDocumentAndPhone($document, $phone);

            if (!$client) {
                return [
                    'success' => false,
                    'code' => 404,
                    'message' => 'Cliente no encontrado'
                ];
            }

            // Verificar saldo suficiente
            if ($client->getBalance() < $amount) {
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'Saldo insuficiente para realizar el pago'
                ];
            }

            // Generar token y session_id
            $token = (string)mt_rand(100000, 999999);
            $sessionId = Str::uuid()->toString();

            // Registrar transacción pendiente
            $transaction = $this->transactionRepository->createPendingPayment($client, $amount, $sessionId, $token);

            // En un caso real, aquí se enviaría el token por email
            // Mail::to($client->getEmail())->send(new PaymentTokenMail($token));

            return [
                'success' => true,
                'code' => 200,
                'message' => 'Se ha enviado un token de confirmación al correo registrado',
                'data' => [
                    'session_id' => $sessionId,
                    'token' => $token // Solo para entorno de desarrollo
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error al iniciar pago: ' . $e->getMessage());
            
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Confirma un pago con un token
     *
     * @param string $sessionId ID de sesión del pago
     * @param string $token Token de confirmación
     * @return array
     */
    public function confirmPayment($sessionId, $token)
    {
        try {
            // Validar campos requeridos
            if (empty($sessionId) || empty($token)) {
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'Todos los campos son requeridos'
                ];
            }

            // Buscar transacción
            $transaction = $this->transactionRepository->findBySessionAndToken($sessionId, $token);

            if (!$transaction) {
                return [
                    'success' => false,
                    'code' => 404,
                    'message' => 'Transacción no encontrada o ya procesada'
                ];
            }

            // Obtener cliente
            $client = $transaction->getClient();

            // Verificar saldo suficiente (podría haber cambiado desde la solicitud inicial)
            if ($client->getBalance() < $transaction->getAmount()) {
                $transaction->setStatus('cancelled');
                $this->transactionRepository->save($transaction);
                
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'Saldo insuficiente para realizar el pago'
                ];
            }

            // Procesar pago
            $entityManager = DoctrineService::getEntityManager();
            $entityManager->beginTransaction();
            
            try {
                // Actualizar saldo
                $client->setBalance($client->getBalance() - $transaction->getAmount());
                $this->clientRepository->save($client);

                // Marcar transacción como completada
                $transaction->setStatus('completed');
                $this->transactionRepository->save($transaction);

                $entityManager->commit();

                return [
                    'success' => true,
                    'code' => 200,
                    'message' => 'Pago confirmado con éxito',
                    'data' => [
                        'transaction_id' => $transaction->getId(),
                        'new_balance' => $client->getBalance()
                    ]
                ];
            } catch (\Exception $e) {
                $entityManager->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error al confirmar pago: ' . $e->getMessage());
            
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta el saldo de un cliente
     *
     * @param string $document Documento de identidad
     * @param string $phone Número de teléfono
     * @return array
     */
    public function getBalance($document, $phone)
    {
        try {
            // Validar campos requeridos
            if (empty($document) || empty($phone)) {
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'Todos los campos son requeridos'
                ];
            }

            // Buscar cliente
            $client = $this->clientRepository->findByDocumentAndPhone($document, $phone);

            if (!$client) {
                return [
                    'success' => false,
                    'code' => 404,
                    'message' => 'Cliente no encontrado'
                ];
            }

            return [
                'success' => true,
                'code' => 200,
                'message' => 'Saldo consultado con éxito',
                'data' => [
                    'client_id' => $client->getId(),
                    'document' => $client->getDocument(),
                    'name' => $client->getName(),
                    'balance' => $client->getBalance()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error al consultar saldo: ' . $e->getMessage());
            
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ];
        }
    }
} 