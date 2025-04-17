# Sistema de Billetera Virtual

Este proyecto implementa una billetera virtual con arquitectura de microservicios utilizando SOAP y REST.

## Estructura del Proyecto

El proyecto está compuesto por tres componentes principales:

1. **Servicio SOAP (Laravel)** - Se encarga de las operaciones de base de datos y lógica de negocio principal.
2. **API REST (Lumen)** - Actúa como puente entre el cliente web y el servicio SOAP.
3. **Frontend (React)** - Interfaz de usuario para interactuar con el sistema.

## Funcionalidades

El sistema implementa las siguientes funcionalidades:

1. **Registro de Clientes** - Permite registrar nuevos usuarios con documento, nombre, email y celular.
2. **Recarga de Billetera** - Añade fondos a la billetera de un usuario existente.
3. **Proceso de Pago** - Permite realizar pagos usando un proceso de dos pasos con confirmación por token.
   - **Nota importante**: El token de confirmación no se muestra directamente en la interfaz. Para obtenerlo, abra las herramientas de desarrollador del navegador (F12), vaya a la pestaña "Network" (Red) y revise la respuesta JSON de la solicitud de pago.
4. **Confirmación de Pago** - Valida el token de confirmación para completar el pago.
5. **Consulta de Saldo** - Verifica el saldo disponible en la billetera.

## Requisitos del Sistema

- PHP 8.0 o superior
- Composer
- Node.js y npm
- MySQL/MariaDB
- Servidor web (Apache/Nginx)

## Configuración e Instalación

### Servicio SOAP (Laravel)

```bash
# Instalar dependencias
cd soap-service
composer install

# Configurar entorno
cp .env.example .env
php artisan key:generate

# Configurar base de datos en .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=wallet_db
# DB_USERNAME=root
# DB_PASSWORD=

# Ejecutar migraciones
php artisan migrate

# Iniciar servidor
php artisan serve --port=8000
```

### API REST (Lumen)

```bash
# Instalar dependencias
cd rest-api
composer install

# Configurar entorno
cp .env.example .env

# Configurar el endpoint del servicio SOAP en .env
# SOAP_ENDPOINT=http://localhost:8000/soap/wallet
# WSDL_ENDPOINT=http://localhost:8000/soap/wallet/wsdl

# Iniciar servidor
php -S localhost:8080 -t public
```

### Frontend (React)

```bash
# Instalar dependencias
cd frontend
npm install

# Iniciar servidor de desarrollo
npm start
```

## API REST Endpoints

### Registro de Cliente
- **POST** `/api/clients`
- **Parámetros**: `document`, `name`, `email`, `phone`

### Recargar Billetera
- **POST** `/api/wallets/recharge`
- **Parámetros**: `document`, `phone`, `amount`

### Iniciar Pago
- **POST** `/api/payments/start`
- **Parámetros**: `document`, `phone`, `amount`

### Confirmar Pago
- **POST** `/api/payments/confirm`
- **Parámetros**: `session_id`, `token`

### Consultar Saldo
- **GET** `/api/wallets/balance`
- **Parámetros**: `document`, `phone`

## SOAP Service Endpoints

- **Endpoint SOAP**: `http://localhost:8000/soap/wallet`
- **WSDL**: `http://localhost:8000/soap/wallet/wsdl`

### Métodos SOAP

1. `registerClient(document, name, email, phone)`
2. `rechargeWallet(document, phone, amount)`
3. `makePayment(document, phone, amount)`
4. `confirmPayment(sessionId, token)`
5. `getBalance(document, phone)`

## Tecnologías Utilizadas

- **Backend SOAP**: Laravel, Doctrine ORM, MySQL
- **Backend REST**: Lumen, GuzzleHTTP
- **Frontend**: React, React Router, Bootstrap, Axios

## Consideraciones de Seguridad

- Las contraseñas y datos sensibles deben cifrarse en un entorno de producción
- Se debe implementar autenticación por JWT u OAuth en un entorno real
- Los tokens de transacción no deben enviarse en respuestas API en producción
- En este entorno de desarrollo, el token de confirmación no se muestra en la interfaz por diseño. Debe ser recuperado desde las herramientas de desarrollador del navegador, simulando un segundo factor de autenticación.

## Autor

- [Leon Sosa]

## Licencia

Este proyecto está licenciado bajo la Licencia MIT. 