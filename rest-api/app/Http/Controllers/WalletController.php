<?php

namespace App\Http\Controllers;

use App\Services\SoapClientService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    protected $soapService;

    /**
     * Constructor del controlador
     */
    public function __construct(SoapClientService $soapService)
    {
        $this->soapService = $soapService;
    }

    /**
     * Registrar un nuevo cliente
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registerClient(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code' => 400,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->soapService->registerClient(
            $request->input('document'),
            $request->input('name'),
            $request->input('email'),
            $request->input('phone')
        );

        return $this->buildResponse($result);
    }

    /**
     * Recargar billetera
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function rechargeWallet(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|string',
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code' => 400,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->soapService->rechargeWallet(
            $request->input('document'),
            $request->input('phone'),
            $request->input('amount')
        );

        return $this->buildResponse($result);
    }

    /**
     * Iniciar proceso de pago
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function makePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|string',
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code' => 400,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->soapService->makePayment(
            $request->input('document'),
            $request->input('phone'),
            $request->input('amount')
        );

        return $this->buildResponse($result);
    }

    /**
     * Confirmar pago con token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'token' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code' => 400,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->soapService->confirmPayment(
            $request->input('session_id'),
            $request->input('token')
        );

        return $this->buildResponse($result);
    }

    /**
     * Consultar saldo
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBalance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|string',
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code' => 400,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->soapService->getBalance(
            $request->input('document'),
            $request->input('phone')
        );

        return $this->buildResponse($result);
    }

    /**
     * Construye una respuesta JSON estándar basada en el resultado del servicio SOAP
     *
     * @param array $result
     * @return JsonResponse
     */
    protected function buildResponse(array $result): JsonResponse
    {
        // Log para depuración
        error_log('Respuesta del servicio SOAP: ' . json_encode($result));
        
        // Asegurarse de que los campos requeridos estén presentes
        if (!isset($result['success'])) {
            $result['success'] = false;
        } else {
            // Convertir a booleano explícito
            $result['success'] = (bool)$result['success'];
        }
        
        if (!isset($result['code'])) {
            $result['code'] = 500;
        } else {
            // Asegurarse de que el código sea numérico
            $result['code'] = (int)$result['code'];
        }
        
        if (!isset($result['message'])) {
            $result['message'] = 'Respuesta sin mensaje';
        }
        
        $httpCode = $result['code'];
        
        // Mapear códigos de API a códigos HTTP
        if ($httpCode === 201) {
            $httpCode = 201; // Created
        } elseif ($httpCode === 200) {
            $httpCode = 200; // OK
        } elseif ($httpCode === 400) {
            $httpCode = 400; // Bad Request
        } elseif ($httpCode === 404) {
            $httpCode = 404; // Not Found
        } elseif ($httpCode === 409) {
            $httpCode = 409; // Conflict
        } else {
            $httpCode = 500; // Internal Server Error
        }
        
        // Log final
        error_log('Respuesta HTTP: ' . $httpCode . ' Datos: ' . json_encode($result));

        return response()->json($result, $httpCode);
    }
} 