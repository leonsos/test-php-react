<?php

namespace App\Http\Controllers;

use App\Services\WalletSoapService;
use Illuminate\Http\Request;

class SoapController extends Controller
{
    protected $walletService;

    public function __construct(WalletSoapService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Handle SOAP requests for the wallet service
     */
    public function handle(Request $request)
    {
        // Obtener el contenido de la solicitud
        $requestContent = $request->getContent();
        error_log("Solicitud SOAP recibida: " . $requestContent);
        
        // Guardar la solicitud completa para análisis
        if (!file_exists(storage_path('logs'))) {
            mkdir(storage_path('logs'), 0755, true);
        }
        
        // Analizar manualmente la solicitud SOAP
        $method = $this->extractSoapMethod($requestContent);
        
        // Si no se pudo extraer el método, intentar identificarlo por los parámetros
        $params = $this->extractSoapParams($requestContent, $method);
        
        // Si no se detectó el método pero hay parámetros, intentar adivinar el método
        if (!$method && count($params) > 0) {
            error_log("Intentando identificar método por parámetros: " . json_encode(array_keys($params)));
            
            // Verificar patrones de parámetros conocidos
            if (isset($params['document']) && isset($params['name']) && isset($params['email']) && isset($params['phone'])) {
                $method = 'registerClient';
                error_log("Método identificado por parámetros: registerClient");
            } elseif (isset($params['document']) && isset($params['phone']) && isset($params['amount'])) {
                if (count($params) == 3) {
                    // Podría ser rechargeWallet o makePayment, usar rechargeWallet por defecto
                    $method = 'rechargeWallet';
                    error_log("Método identificado por parámetros: rechargeWallet");
                }
            } elseif (isset($params['document']) && isset($params['phone']) && count($params) == 2) {
                // Si solo hay document y phone, casi seguramente es getBalance
                $method = 'getBalance';
                error_log("Método identificado por parámetros: getBalance");
            } elseif (isset($params['sessionId']) && isset($params['token'])) {
                $method = 'confirmPayment';
                error_log("Método identificado por parámetros: confirmPayment");
            }
        }
        
        error_log("Método final usado: '$method'");
        error_log("Parámetros extraídos: " . json_encode($params));
        
        // Llamar al método correspondiente en el servicio
        $result = null;
        if ($method && method_exists($this->walletService, $method)) {
            // Convertir los parámetros asociativos a posicionales para la llamada
            $methodParams = $this->getMethodParameters($method, $params);
            error_log("Llamando $method con parámetros: " . json_encode($methodParams));
            
            $result = call_user_func_array([$this->walletService, $method], $methodParams);
            
            // Asegurar que el campo success sea siempre un booleano
            if (is_array($result)) {
                if (!isset($result['success'])) {
                    $result['success'] = ($result['code'] < 400);
                } elseif ($result['success'] === '' || $result['success'] === null) {
                    $result['success'] = ($result['code'] < 400);
                } else {
                    $result['success'] = (bool)$result['success'];
                }
                
                // Asegurarnos de que haya un código
                if (!isset($result['code'])) {
                    $result['code'] = $result['success'] ? 200 : 400;
                }
                
                // Asegurarnos de que haya un mensaje
                if (!isset($result['message'])) {
                    $result['message'] = $result['success'] ? 'Operación completada' : 'Error en la operación';
                }
            }
        } else {
            $result = [
                'success' => false,
                'code' => 400,
                'message' => 'Método no encontrado: ' . ($method ?: 'método no detectado'),
                'request_method' => $method ?: 'unknown' // Guardar el método para la respuesta
            ];
        }
        
        // Construir respuesta SOAP
        $response = $this->buildSoapResponse($method ?: 'unknown', $result);
        
        return response($response, 200)->header('Content-Type', 'text/xml; charset=utf-8');
    }

    /**
     * Generate WSDL file for the wallet service
     */
    public function wsdl()
    {
        // Versión simplificada del WSDL
        $wsdlContent = '<?xml version="1.0" encoding="UTF-8"?>
<definitions name="WalletService" 
             targetNamespace="'.url('/soap/wallet').'"
             xmlns="http://schemas.xmlsoap.org/wsdl/"
             xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
             xmlns:tns="'.url('/soap/wallet').'"
             xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <types>
        <schema targetNamespace="'.url('/soap/wallet').'"
                xmlns="http://www.w3.org/2001/XMLSchema">
        </schema>
    </types>
    <portType name="WalletServicePort">
    </portType>
    <binding name="WalletServiceBinding" type="tns:WalletServicePort">
        <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>
    </binding>
    <service name="WalletService">
        <port name="WalletServicePort" binding="tns:WalletServiceBinding">
            <soap:address location="'.url('/soap/wallet').'"/>
        </port>
    </service>
</definitions>';
        
        return response($wsdlContent, 200)
            ->header('Content-Type', 'application/xml');
    }
    
    /**
     * Extrae el nombre del método de la solicitud SOAP
     */
    private function extractSoapMethod($soapRequest)
    {
        error_log("Extrayendo método de: " . substr($soapRequest, 0, 500));
        
        // Guardar el contenido original para depuración
        file_put_contents(storage_path('logs/soap_request.xml'), $soapRequest);
        
        // Comprobar específicamente métodos conocidos
        $knownMethods = ['registerClient', 'rechargeWallet', 'makePayment', 'confirmPayment', 'getBalance'];
        foreach ($knownMethods as $method) {
            if (strpos($soapRequest, "<ns1:$method>") !== false) {
                error_log("Método encontrado directamente en la solicitud: $method");
                return $method;
            }
        }
        
        // Primer intento: usando SimpleXML para extraer el método más confiablemente
        try {
            // Configurar libxml para que capture errores
            libxml_use_internal_errors(true);
            
            $xml = simplexml_load_string($soapRequest);
            if ($xml) {
                $namespaces = $xml->getNamespaces(true);
                error_log("Namespaces disponibles: " . json_encode($namespaces));
                
                // Extraer Body
                $body = $xml->children($namespaces['SOAP-ENV'] ?? 'http://schemas.xmlsoap.org/soap/envelope/')->Body;
                
                if ($body) {
                    // Buscar en todos los namespace posibles
                    foreach ($namespaces as $prefix => $uri) {
                        $children = $body->children($uri);
                        foreach ($children as $name => $node) {
                            error_log("Método encontrado con XML: $name");
                            return $name;
                        }
                    }
                    
                    // Si no hay namespace, intentar con hijos directos
                    foreach ($body->children() as $name => $node) {
                        error_log("Método encontrado sin namespace: $name");
                        return $name;
                    }
                }
            }
            
            $errors = libxml_get_errors();
            libxml_clear_errors();
            if (!empty($errors)) {
                error_log("Errores SimpleXML: " . count($errors));
                foreach ($errors as $error) {
                    error_log("  - " . $error->message);
                }
            }
        } catch (\Exception $e) {
            error_log("Error al parsear XML: " . $e->getMessage());
        }
        
        // Intentar con expresión regular más robusta
        $patterns = [
            // Patrón estándar
            '/<SOAP-ENV:Body.*?><ns1:([^>]+)>/s',
            // Patrón alternativo con diferentes prefijos de namespace
            '/<(?:\w+:)?Body[^>]*>\s*<(?:\w+:)?([^>\s]+)/s',
            // Patrón para manejar posibles espacios/saltos de línea
            '/<(?:\w+:)?Body[^>]*>\s*<[^:>]+:([^>\s]+)/s',
            // Patrón más simple sin considerar namespace
            '/<Body[^>]*>\s*<([^>\s:]+)/s'
        ];
        
        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $soapRequest, $matches)) {
                if (isset($matches[1])) {
                    $method = $matches[1];
                    error_log("Método SOAP extraído con patrón #$index: " . $method);
                    return $method;
                }
            }
        }
        
        // Como último recurso, buscar cualquier etiqueta dentro del Body
        if (preg_match('/<(?:\w+:)?Body[^>]*>(.*?)<\/(?:\w+:)?Body>/s', $soapRequest, $bodyMatches)) {
            $bodyContent = $bodyMatches[1];
            error_log("Contenido Body: " . $bodyContent);
            
            // Buscar primera etiqueta de apertura
            if (preg_match('/<(?:\w+:)?([^>\s]+)/s', $bodyContent, $tagMatches)) {
                $method = $tagMatches[1];
                error_log("Método extraído del Body como último recurso: " . $method);
                return $method;
            }
        }
        
        error_log("NO SE PUDO EXTRAER EL MÉTODO SOAP DE LA SOLICITUD");
        return null;
    }
    
    /**
     * Extrae los parámetros de la solicitud SOAP
     */
    private function extractSoapParams($soapRequest, $method = null)
    {
        $params = [];
        
        try {
            // Intentar primero con SimpleXML para mayor fiabilidad
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($soapRequest);
            
            if ($xml) {
                // Obtener todos los namespaces
                $namespaces = $xml->getNamespaces(true);
                $soapEnvNs = $namespaces['SOAP-ENV'] ?? 'http://schemas.xmlsoap.org/soap/envelope/';
                
                // Obtener el cuerpo SOAP
                $body = $xml->children($soapEnvNs)->Body;
                
                if ($body) {
                    // Si tenemos el método, buscar directamente sus parámetros
                    if ($method) {
                        // Intentar con diferentes namespace
                        foreach ($namespaces as $prefix => $uri) {
                            $methodNode = $body->children($uri)->$method;
                            if ($methodNode) {
                                foreach ($methodNode->children() as $name => $value) {
                                    $params[$name] = (string)$value;
                                }
                                if (count($params) > 0) {
                                    error_log("Parámetros encontrados con NS $prefix para método $method");
                                    break;
                                }
                            }
                        }
                        
                        // Intentar sin namespace
                        if (count($params) == 0) {
                            $methodNode = $body->$method;
                            if ($methodNode) {
                                foreach ($methodNode->children() as $name => $value) {
                                    $params[$name] = (string)$value;
                                }
                            }
                        }
                    }
                    
                    // Si no encontramos parámetros, buscar en el primer nodo del cuerpo
                    if (count($params) == 0) {
                        foreach ($namespaces as $prefix => $uri) {
                            $bodyChildren = $body->children($uri);
                            if (count($bodyChildren) > 0) {
                                $firstNode = reset($bodyChildren);
                                foreach ($firstNode->children() as $name => $value) {
                                    $params[$name] = (string)$value;
                                }
                                if (count($params) > 0) {
                                    error_log("Parámetros encontrados en el primer nodo con NS $prefix");
                                    break;
                                }
                            }
                        }
                        
                        // Intentar con hijos directos del body
                        if (count($params) == 0 && count($body->children()) > 0) {
                            $firstNode = reset($body->children());
                            foreach ($firstNode->children() as $name => $value) {
                                $params[$name] = (string)$value;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error al extraer parámetros con SimpleXML: " . $e->getMessage());
        }
        
        // Si SimpleXML falló, usar regex como fallback
        if (count($params) == 0 && $method) {
            $pattern = "/<ns1:$method>(.*?)<\/ns1:$method>/s";
            preg_match($pattern, $soapRequest, $methodContent);
            
            if (isset($methodContent[1])) {
                // Extraer cada parámetro
                preg_match_all('/<([^>]+)>(.*?)<\/\\1>/s', $methodContent[1], $paramMatches, PREG_SET_ORDER);
                
                foreach ($paramMatches as $match) {
                    $paramName = $match[1];
                    $paramValue = $match[2];
                    $params[$paramName] = html_entity_decode($paramValue);
                }
            }
        }
        
        // Si aún no hay parámetros, extraer de cualquier nodo
        if (count($params) == 0) {
            preg_match_all('/<([^>\/:]+)>(.*?)<\/\\1>/s', $soapRequest, $paramMatches, PREG_SET_ORDER);
            foreach ($paramMatches as $match) {
                if (strlen($match[1]) < 30) { // Filtrar nombres demasiado largos
                    $paramName = $match[1];
                    $paramValue = $match[2];
                    $params[$paramName] = html_entity_decode($paramValue);
                }
            }
        }
        
        return $params;
    }
    
    /**
     * Convierte los parámetros asociativos a un array posicional según los parámetros del método
     */
    private function getMethodParameters($method, $params)
    {
        $methodParams = [];
        
        // Método registerClient
        if ($method === 'registerClient') {
            $methodParams = [
                $params['document'] ?? '',
                $params['name'] ?? '',
                $params['email'] ?? '',
                $params['phone'] ?? ''
            ];
        }
        // Otros métodos
        elseif ($method === 'rechargeWallet' || $method === 'makePayment' || $method === 'getBalance') {
            $methodParams = [
                $params['document'] ?? '',
                $params['phone'] ?? '',
                $params['amount'] ?? 0
            ];
        }
        elseif ($method === 'confirmPayment') {
            $methodParams = [
                $params['sessionId'] ?? '',
                $params['token'] ?? ''
            ];
        }
        
        return $methodParams;
    }
    
    /**
     * Construye la respuesta SOAP
     */
    private function buildSoapResponse($method, $result)
    {
        $resultXml = $this->arrayToXml($result);
        
        // Evitar 'unknownResponse' si es posible
        if ($method === 'unknown' && isset($result['request_method'])) {
            $method = $result['request_method'];
        }
        
        // Asegurarse de que el método termine con 'Response'
        if (strpos($method, 'Response') === false) {
            $responseMethod = $method . 'Response';
        } else {
            $responseMethod = $method;
        }
        
        // Formato correcto de respuesta SOAP con el nombre del método
        error_log("Construyendo respuesta SOAP para método: $method (responseMethod: $responseMethod)");
        
        $response = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="'.url('/soap/wallet').'">
    <SOAP-ENV:Body>
        <ns1:'.$responseMethod.'>
            <return>'.$resultXml.'</return>
        </ns1:'.$responseMethod.'>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';
        
        error_log("Respuesta SOAP generada: " . $response);
        return $response;
    }
    
    /**
     * Convierte un array a XML para la respuesta SOAP
     */
    private function arrayToXml($array)
    {
        if (!is_array($array)) {
            return htmlspecialchars((string)$array, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }
        
        $xml = '';
        foreach ($array as $key => $value) {
            // Asegurarse de que la clave sea un nombre XML válido
            if (is_numeric($key)) {
                $key = 'item' . $key;
            }
            
            // Manejar campo success específicamente
            if ($key === 'success') {
                // Convertir a string booleano explícito
                $value = ($value) ? 'true' : 'false';
                $xml .= '<'.$key.'>'.$value.'</'.$key.'>';
                continue;
            }
            
            // Manejar caracteres especiales y arrays anidados
            if (is_array($value)) {
                $xml .= '<'.$key.'>'.$this->arrayToXml($value).'</'.$key.'>';
            } else {
                $value = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<'.$key.'>'.$value.'</'.$key.'>';
            }
        }
        return $xml;
    }
} 