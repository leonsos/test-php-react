<?php

namespace App\Services;

use GuzzleHttp\Client;
use Exception;

class SoapClientService
{
    protected $client;
    protected $soapEndpoint;
    protected $wsdlEndpoint;

    public function __construct()
    {
        $this->client = new Client();
        $this->soapEndpoint = 'http://localhost:8000/soap/wallet';
        $this->wsdlEndpoint = 'http://localhost:8000/soap/wallet/wsdl';
        error_log("SOAP Endpoint: " . $this->soapEndpoint);
        error_log("WSDL Endpoint: " . $this->wsdlEndpoint);
    }

    /**
     * Realiza una llamada SOAP mediante HTTP POST
     *
     * @param string $method Nombre del método a invocar
     * @param array $params Parámetros para el método
     * @return mixed
     */
    protected function call($method, $params = [])
    {
        try {
            // Construimos el cuerpo de la solicitud SOAP manualmente
            $soapEnvelope = $this->buildSoapEnvelope($method, $params);
            error_log("Enviando solicitud SOAP: " . $soapEnvelope);

            // Realizamos la solicitud HTTP
            $response = $this->client->post($this->soapEndpoint, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => $method
                ],
                'body' => $soapEnvelope,
                'http_errors' => false
            ]);

            $statusCode = $response->getStatusCode();
            $responseContent = $response->getBody()->getContents();
            
            // Debug: Guardar la respuesta en un log para inspeccionarla
            error_log("Respuesta SOAP ($statusCode): " . $responseContent);
            
            // Verificar si hay error HTTP
            if ($statusCode >= 400) {
                return [
                    'success' => false,
                    'code' => $statusCode,
                    'message' => 'Error HTTP al invocar el servicio SOAP: ' . $statusCode
                ];
            }
            
            // Verificar si la respuesta es XML válido
            if (!$this->isValidXml($responseContent)) {
                return [
                    'success' => false,
                    'code' => 500,
                    'message' => 'Respuesta no válida del servidor SOAP: ' . substr($responseContent, 0, 200)
                ];
            }
            
            // Configurar libxml para que capture errores
            libxml_use_internal_errors(true);
            
            // Intenta parsear la respuesta XML
            $xmlResponse = simplexml_load_string($responseContent);
            if ($xmlResponse === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->message;
                }
                return [
                    'success' => false,
                    'code' => 500,
                    'message' => 'Error al parsear XML: ' . implode('; ', $errorMessages)
                ];
            }
            
            $namespaces = $xmlResponse->getNamespaces(true);
            error_log("Namespaces encontrados: " . json_encode($namespaces));
            
            // Extraemos el cuerpo de la respuesta SOAP
            if (!isset($namespaces['SOAP-ENV'])) {
                return [
                    'success' => false,
                    'code' => 500,
                    'message' => 'Formato SOAP no válido: falta namespace SOAP-ENV'
                ];
            }
            
            // Usar XPath para encontrar el elemento de respuesta es más robusto
            $body = $xmlResponse->children($namespaces['SOAP-ENV'])->Body;
            if (!$body) {
                return [
                    'success' => false,
                    'code' => 500,
                    'message' => 'No se encontró el elemento Body en la respuesta SOAP'
                ];
            }
            
            // Debug: Mostrar la estructura del cuerpo
            $bodyXml = $body->asXML();
            error_log("Body SOAP: " . $bodyXml);
            
            // Listar todos los nodos hijos del cuerpo
            error_log("Nodos hijos del Body:");
            foreach ($body->children() as $nodeName => $nodeValue) {
                error_log("  - Nodo: " . $nodeName);
            }
            
            // Buscar el elemento de respuesta del método
            $responseMethod = $method . 'Response';
            $returnData = null;
            $responseNode = null;
            
            error_log("Buscando nodo de respuesta: " . $responseMethod);
            
            // SOLUCIÓN DIRECTA: Extraer el contenido entre las etiquetas registerClientResponse
            $responsePattern = "/<ns1:$responseMethod>(.*?)<\/ns1:$responseMethod>/s";
            if (preg_match($responsePattern, $responseContent, $responseMatches)) {
                error_log("Encontrado contenido del nodo directamente con regex: " . substr($responseMatches[1], 0, 100));
                
                // Extraer el contenido entre <return></return>
                $returnPattern = "/<return>(.*?)<\/return>/s";
                if (preg_match($returnPattern, $responseMatches[1], $returnMatches)) {
                    error_log("Encontrado contenido de return directamente: " . substr($returnMatches[1], 0, 100));
                    
                    // Construir un XML para procesar
                    $returnXml = simplexml_load_string("<root>" . $returnMatches[1] . "</root>");
                    if ($returnXml !== false) {
                        error_log("XML de return parseado correctamente");
                        $returnData = $returnXml;
                    }
                }
            } else {
                // Intentar con un patrón más general si el específico falla
                error_log("No se encontró patrón específico, intentando con patrón general");
                $generalPattern = "/<ns1:(.*?)Response>(.*?)<\/ns1:(.*?)Response>/s";
                if (preg_match($generalPattern, $responseContent, $responseMatches)) {
                    $methodName = $responseMatches[1];
                    error_log("Encontrado método general: $methodName con contenido: " . substr($responseMatches[2], 0, 100));
                    
                    // Extraer el contenido entre <return></return>
                    $returnPattern = "/<return>(.*?)<\/return>/s";
                    if (preg_match($returnPattern, $responseMatches[2], $returnMatches)) {
                        error_log("Encontrado contenido de return en patrón general: " . substr($returnMatches[1], 0, 100));
                        
                        // Construir un XML para procesar
                        $returnXml = simplexml_load_string("<root>" . $returnMatches[1] . "</root>");
                        if ($returnXml !== false) {
                            error_log("XML de return parseado correctamente (patrón general)");
                            $returnData = $returnXml;
                        }
                    }
                }
            }
            
            // Si el enfoque directo falló, intentar con los métodos anteriores
            if (!$returnData) {
                error_log("Volviendo a intentar con el enfoque estándar");
                
                // Intentar diferentes enfoques para encontrar el nodo de respuesta
                
                // 1. Primero intentamos buscar en todos los nodos hijos directamente
                foreach ($body->children() as $childName => $childValue) {
                    error_log("Verificando nodo: " . $childName);
                    if (strpos($childName, $responseMethod) !== false) {
                        $responseNode = $childValue;
                        error_log("¡Nodo encontrado!: " . $childName);
                        break;
                    }
                }
                
                // 2. Luego intentamos con diferentes espacios de nombres
                if (!$responseNode) {
                    foreach ($namespaces as $prefix => $uri) {
                        error_log("Probando con namespace: $prefix => $uri");
                        $nsChildren = $body->children($uri);
                        foreach ($nsChildren as $childName => $childValue) {
                            error_log("Verificando nodo con NS $prefix: " . $childName);
                            if (strpos($childName, $responseMethod) !== false) {
                                $responseNode = $childValue;
                                error_log("¡Nodo encontrado con NS $prefix!: " . $childName);
                                break 2;
                            }
                        }
                    }
                }
                
                // 3. Intentar una búsqueda más flexible por el nombre del método sin 'Response'
                if (!$responseNode) {
                    $methodName = str_replace('Response', '', $responseMethod);
                    foreach ($body->children() as $childName => $childValue) {
                        error_log("Verificando nodo flexible: " . $childName);
                        if (stripos($childName, $methodName) !== false) {
                            $responseNode = $childValue;
                            error_log("Nodo flexible encontrado: " . $childName);
                            break;
                        }
                    }
                    
                    // 4. Buscar en todos los namespace con la búsqueda flexible
                    if (!$responseNode) {
                        foreach ($namespaces as $prefix => $uri) {
                            $nsChildren = $body->children($uri);
                            foreach ($nsChildren as $childName => $childValue) {
                                error_log("Verificando nodo flexible con NS $prefix: " . $childName);
                                if (stripos($childName, $methodName) !== false) {
                                    $responseNode = $childValue;
                                    error_log("¡Nodo flexible encontrado con NS $prefix!: " . $childName);
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                // 5. Como último recurso, intentar usar el primer nodo hijo del cuerpo
                if (!$responseNode && count($body->children()) > 0) {
                    $children = $body->children();
                    $firstChild = reset($children);
                    $responseNode = $firstChild;
                    error_log("Usando primer nodo hijo como respuesta: " . key($children));
                }
                
                if (!$responseNode) {
                    // ÚLTIMA OPORTUNIDAD: Procesar el texto directo del Body para construir una respuesta
                    $bodyXml = $body->asXML();
                    error_log("Extrayendo datos directamente del Body XML: " . substr($bodyXml, 0, 200));
                    
                    // Extraer cualquier elemento success, code y message en el XML
                    $successPattern = "/<success>(.*?)<\/success>/s";
                    $codePattern = "/<code>(.*?)<\/code>/s";
                    $messagePattern = "/<message>(.*?)<\/message>/s";
                    
                    $success = false;
                    $code = 500;
                    $message = "No se encontró el elemento '$responseMethod' en la respuesta SOAP";
                    
                    if (preg_match($successPattern, $bodyXml, $successMatches)) {
                        $success = trim($successMatches[1]);
                        $success = ($success === 'true' || $success === '1') ? true : false;
                    }
                    
                    if (preg_match($codePattern, $bodyXml, $codeMatches)) {
                        $code = (int)trim($codeMatches[1]);
                    }
                    
                    if (preg_match($messagePattern, $bodyXml, $messageMatches)) {
                        $message = trim($messageMatches[1]);
                    }
                    
                    return [
                        'success' => $success,
                        'code' => $code,
                        'message' => $message
                    ];
                }
                
                // Debug: Mostrar la estructura del nodo de respuesta
                $responseXml = $responseNode->asXML();
                error_log("Nodo de respuesta encontrado: " . $responseXml);
                
                // Buscar el elemento 'return' dentro del nodo de respuesta
                $foundReturn = false;
                foreach ($responseNode->children() as $child) {
                    error_log("Hijo en nodo de respuesta: " . $child->getName());
                    if ($child->getName() === 'return') {
                        $returnData = $child;
                        $foundReturn = true;
                        break;
                    }
                }
                
                // Si no encontramos directamente el 'return', intentar buscar recursivamente
                if (!$foundReturn) {
                    error_log("Buscando elemento 'return' recursivamente");
                    $returnData = $this->findNodeByName($responseNode, 'return');
                    
                    // Como último recurso, usar todo el nodo de respuesta
                    if (!$returnData) {
                        error_log("Usando todo el nodo de respuesta como datos de retorno");
                        $returnData = $responseNode;
                    }
                }
            }
            
            // Si después de todo aún no tenemos datos, extraer cualquier dato posible de la respuesta
            if (!$returnData) {
                error_log("NO SE ENCONTRÓ NINGÚN DATO DE RESPUESTA - Extrayendo manualmente");
                
                // Extraer etiquetas de la respuesta
                preg_match_all('/<([^>\/]+)>(.*?)<\/\1>/s', $responseContent, $tags, PREG_SET_ORDER);
                $manualResult = [];
                
                foreach ($tags as $tag) {
                    // Solo tomar etiquetas relevantes
                    $name = $tag[1];
                    if (in_array($name, ['success', 'code', 'message', 'data'])) {
                        $manualResult[$name] = $tag[2];
                    }
                }
                
                if (!empty($manualResult)) {
                    error_log("Datos extraídos manualmente: " . json_encode($manualResult));
                    return $manualResult;
                }
                
                return [
                    'success' => false,
                    'code' => 500,
                    'message' => "No se pudo procesar la respuesta SOAP para el método '$method'"
                ];
            }
            
            // Convertimos SimpleXMLElement a array PHP
            $resultArray = $this->xmlToArray($returnData);
            error_log("Resultado convertido a array: " . json_encode($resultArray));
            
            // Si no hay contenido en el resultado, devolver error
            if (empty($resultArray)) {
                return [
                    'success' => false,
                    'code' => 500,
                    'message' => "Respuesta vacía del servidor SOAP para el método '$method'"
                ];
            }
            
            // Si el resultado no tiene la estructura esperada (success, code, message),
            // y estamos usando el nodo completo, intentamos buscar estos elementos dentro del array
            if (!isset($resultArray['success']) && !$foundReturn) {
                error_log("Buscando campos success/code/message en el resultado");
                // Buscar en el primer nivel
                $success = $resultArray['success'] ?? null;
                $code = $resultArray['code'] ?? null;
                $message = $resultArray['message'] ?? null;
                
                // Si no están en el primer nivel, buscar dentro de posibles subnodos
                if ($success === null) {
                    foreach ($resultArray as $key => $value) {
                        if (is_array($value)) {
                            $success = $value['success'] ?? $success;
                            $code = $value['code'] ?? $code;
                            $message = $value['message'] ?? $message;
                            
                            // Si encontramos todos los campos, salimos del bucle
                            if ($success !== null && $code !== null && $message !== null) {
                                break;
                            }
                        }
                    }
                }
                
                // Si encontramos al menos el campo success, construimos una respuesta estándar
                if ($success !== null) {
                    return [
                        'success' => (bool)$success,
                        'code' => $code ?? 200,
                        'message' => $message ?? 'Operación completada',
                        'data' => $resultArray
                    ];
                }
            }
            
            // Manejar caso especial de elementos XML success vacíos con código de error
            if (isset($resultArray['success']) && $resultArray['success'] === '' && isset($resultArray['code'])) {
                // Determinar success basado en el código
                $isSuccess = ($resultArray['code'] < 400);
                $resultArray['success'] = $isSuccess;
                
                error_log("Campo success vacío detectado, se determinó success=$isSuccess basado en código " . $resultArray['code']);
            }
            
            return $resultArray;
        } catch (Exception $e) {
            // En caso de error, devolvemos un formato estándar de error
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Error al invocar el servicio SOAP: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Construye un sobre SOAP para la solicitud
     *
     * @param string $method Nombre del método
     * @param array $params Parámetros del método
     * @return string
     */
    protected function buildSoapEnvelope($method, $params)
    {
        $paramXml = '';
        foreach ($params as $key => $value) {
            // Escapar caracteres especiales en los valores XML
            $escapedValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $paramXml .= "<$key>$escapedValue</$key>";
        }

        return <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope 
    xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" 
    xmlns:ns1="{$this->soapEndpoint}">
    <SOAP-ENV:Body>
        <ns1:$method>
            $paramXml
        </ns1:$method>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
EOT;
    }

    /**
     * Verifica si una cadena es XML válido
     */
    private function isValidXml($xml) 
    {
        libxml_use_internal_errors(true);
        simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        return empty($errors);
    }

    /**
     * Busca recursivamente un nodo por su nombre
     *
     * @param \SimpleXMLElement $node El nodo donde buscar
     * @param string $name El nombre del nodo a buscar
     * @return \SimpleXMLElement|null El nodo encontrado o null
     */
    private function findNodeByName($node, $name)
    {
        // Verificar si el nodo actual tiene el nombre buscado
        if ($node->getName() === $name) {
            return $node;
        }
        
        // Buscar en los nodos hijos
        foreach ($node->children() as $child) {
            if ($child->getName() === $name) {
                return $child;
            }
            
            // Buscar recursivamente en los hijos
            $found = $this->findNodeByName($child, $name);
            if ($found) {
                return $found;
            }
        }
        
        // Buscar en los nodos hijos con todos los namespace conocidos
        $namespaces = $node->getNamespaces(true);
        foreach ($namespaces as $prefix => $uri) {
            $nsChildren = $node->children($uri);
            foreach ($nsChildren as $child) {
                if ($child->getName() === $name) {
                    return $child;
                }
                
                // Buscar recursivamente
                $found = $this->findNodeByName($child, $name);
                if ($found) {
                    return $found;
                }
            }
        }
        
        return null;
    }

    /**
     * Convierte un objeto SimpleXMLElement a un array PHP recursivamente
     *
     * @param SimpleXMLElement $xml El objeto XML a convertir
     * @return array El array resultante
     */
    protected function xmlToArray($xml)
    {
        $result = [];
        
        if ($xml instanceof \SimpleXMLElement) {
            // Obtener atributos
            $attributes = $xml->attributes();
            foreach ($attributes as $key => $value) {
                $result['@' . $key] = (string)$value;
            }
            
            // Obtener nodos hijos
            $children = $xml->children();
            if (!$children->count()) {
                $result['value'] = trim((string)$xml);
            } else {
                foreach ($children as $childName => $childValue) {
                    if (count($xml->$childName) > 1) {
                        // Múltiples elementos con el mismo nombre
                        if (!isset($result[$childName])) {
                            $result[$childName] = [];
                        }
                        $result[$childName][] = $this->xmlToArray($childValue);
                    } else {
                        // Un solo elemento
                        $result[$childName] = $this->xmlToArray($childValue);
                    }
                }
            }
            
            // Si solo hay un valor simple sin atributos
            if (count($result) === 1 && isset($result['value']) && count($attributes) === 0) {
                return $result['value'];
            }
            
            return $result;
        }
        
        // Fallback a método antiguo si algo falla
        return json_decode(json_encode($xml), true);
    }

    /**
     * Registra un nuevo cliente
     *
     * @param string $document Documento de identidad
     * @param string $name Nombre completo
     * @param string $email Correo electrónico
     * @param string $phone Número de teléfono
     * @return array
     */
    public function registerClient($document, $name, $email, $phone)
    {
        return $this->call('registerClient', [
            'document' => $document,
            'name' => $name,
            'email' => $email,
            'phone' => $phone
        ]);
    }

    /**
     * Recarga la billetera de un cliente
     *
     * @param string $document Documento de identidad
     * @param string $phone Número de teléfono
     * @param float $amount Monto a recargar
     * @return array
     */
    public function rechargeWallet($document, $phone, $amount)
    {
        return $this->call('rechargeWallet', [
            'document' => $document,
            'phone' => $phone,
            'amount' => $amount
        ]);
    }

    /**
     * Inicia un proceso de pago
     *
     * @param string $document Documento de identidad
     * @param string $phone Número de teléfono
     * @param float $amount Monto a pagar
     * @return array
     */
    public function makePayment($document, $phone, $amount)
    {
        return $this->call('makePayment', [
            'document' => $document,
            'phone' => $phone,
            'amount' => $amount
        ]);
    }

    /**
     * Confirma un pago con token
     *
     * @param string $sessionId ID de sesión del pago
     * @param string $token Token de confirmación
     * @return array
     */
    public function confirmPayment($sessionId, $token)
    {
        return $this->call('confirmPayment', [
            'sessionId' => $sessionId,
            'token' => $token
        ]);
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
        error_log("Consultando saldo para document: $document, phone: $phone");
        
        try {
            $result = $this->call('getBalance', [
                'document' => $document,
                'phone' => $phone
            ]);
            
            error_log("Resultado de getBalance: " . json_encode($result));
            return $result;
        } catch (Exception $e) {
            error_log("Error en getBalance: " . $e->getMessage());
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Error al consultar saldo: ' . $e->getMessage()
            ];
        }
    }
} 