<?php

namespace App\Services;

interface WalletServiceInterface
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
    public function registerClient($document, $name, $email, $phone);

    /**
     * Recarga la billetera del cliente
     *
     * @param string $document Documento de identidad
     * @param string $phone Número de teléfono
     * @param float $amount Monto a recargar
     * @return array
     */
    public function rechargeWallet($document, $phone, $amount);

    /**
     * Inicia un proceso de pago generando un token
     *
     * @param string $document Documento de identidad
     * @param string $phone Número de teléfono
     * @param float $amount Monto a pagar
     * @return array
     */
    public function makePayment($document, $phone, $amount);

    /**
     * Confirma un pago con un token
     *
     * @param string $sessionId ID de sesión del pago
     * @param string $token Token de confirmación
     * @return array
     */
    public function confirmPayment($sessionId, $token);

    /**
     * Consulta el saldo de un cliente
     *
     * @param string $document Documento de identidad
     * @param string $phone Número de teléfono
     * @return array
     */
    public function getBalance($document, $phone);
} 