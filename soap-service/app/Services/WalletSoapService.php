<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WalletSoapService
{
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
            $exists = Client::where('document', $document)
                ->orWhere('email', $email)
                ->orWhere('phone', $phone)
                ->exists();

            if ($exists) {
                return [
                    'success' => false,
                    'code' => 409,
                    'message' => 'El cliente ya existe con el documento, email o teléfono proporcionado'
                ];
            }

            // Crear nuevo cliente
            $client = Client::create([
                'document' => $document,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'balance' => 0
            ]);

            return [
                'success' => true,
                'code' => 201,
                'message' => 'Cliente registrado con éxito',
                'data' => [
                    'client_id' => $client->id
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
            $client = Client::where('document', $document)
                ->where('phone', $phone)
                ->first();

            if (!$client) {
                return [
                    'success' => false,
                    'code' => 404,
                    'message' => 'Cliente no encontrado'
                ];
            }

            // Actualizar balance usando una transacción
            DB::beginTransaction();
            try {
                // Registrar transacción
                $transaction = Transaction::create([
                    'client_id' => $client->id,
                    'type' => 'deposit',
                    'amount' => $amount,
                    'status' => 'completed'
                ]);

                // Actualizar saldo
                $client->balance += $amount;
                $client->save();

                DB::commit();

                return [
                    'success' => true,
                    'code' => 200,
                    'message' => 'Recarga realizada con éxito',
                    'data' => [
                        'new_balance' => $client->balance,
                        'transaction_id' => $transaction->id
                    ]
                ];
            } catch (\Exception $e) {
                DB::rollBack();
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
            $client = Client::where('document', $document)
                ->where('phone', $phone)
                ->first();

            if (!$client) {
                return [
                    'success' => false,
                    'code' => 404,
                    'message' => 'Cliente no encontrado'
                ];
            }

            // Verificar saldo suficiente
            if ($client->balance < $amount) {
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'Saldo insuficiente para realizar el pago'
                ];
            }

            // Generar token y session_id
            $token = mt_rand(100000, 999999);
            $sessionId = Str::uuid()->toString();

            // Crear transacción pendiente
            $transaction = Transaction::create([
                'client_id' => $client->id,
                'type' => 'payment',
                'amount' => $amount,
                'session_id' => $sessionId,
                'token' => $token,
                'status' => 'pending'
            ]);

            // Aquí se enviaría el email con el token
            // En un entorno real, se usaría Mail::to($client->email)->send(new PaymentTokenMail($token));
            
            return [
                'success' => true,
                'code' => 200,
                'message' => 'Se ha enviado un token de confirmación al correo registrado',
                'data' => [
                    'session_id' => $sessionId,
                    'token' => $token // En producción real, no se debería devolver el token
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
     * Confirma un pago usando el token enviado
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
            $transaction = Transaction::where('session_id', $sessionId)
                ->where('token', $token)
                ->where('status', 'pending')
                ->first();

            if (!$transaction) {
                return [
                    'success' => false,
                    'code' => 404,
                    'message' => 'Transacción no encontrada o ya procesada'
                ];
            }

            // Obtener cliente
            $client = $transaction->client;

            // Verificar saldo suficiente (podría haber cambiado desde la solicitud inicial)
            if ($client->balance < $transaction->amount) {
                $transaction->status = 'cancelled';
                $transaction->save();
                
                return [
                    'success' => false,
                    'code' => 400,
                    'message' => 'Saldo insuficiente para realizar el pago'
                ];
            }

            // Procesar pago
            DB::beginTransaction();
            try {
                // Actualizar saldo
                $client->balance -= $transaction->amount;
                $client->save();

                // Marcar transacción como completada
                $transaction->status = 'completed';
                $transaction->save();

                DB::commit();

                return [
                    'success' => true,
                    'code' => 200,
                    'message' => 'Pago confirmado con éxito',
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'new_balance' => $client->balance
                    ]
                ];
            } catch (\Exception $e) {
                DB::rollBack();
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
            $client = Client::where('document', $document)
                ->where('phone', $phone)
                ->first();

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
                    'client_id' => $client->id,
                    'document' => $client->document,
                    'name' => $client->name,
                    'balance' => $client->balance
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