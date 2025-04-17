# Documentación de la Colección Postman para Billetera Virtual API

Esta documentación proporciona instrucciones sobre cómo importar y utilizar la colección de Postman para probar la API REST de la Billetera Virtual.

## Contenido

- [Importar Colección y Entorno](#importar-colección-y-entorno)
- [Configuración del Entorno](#configuración-del-entorno)
- [Flujo de Pruebas Recomendado](#flujo-de-pruebas-recomendado)
- [Descripción de los Endpoints](#descripción-de-los-endpoints)
- [Tests Automáticos](#tests-automáticos)

## Importar Colección y Entorno

1. Abre Postman.
2. Haz clic en el botón "Import" en la parte superior izquierda.
3. Arrastra y suelta los archivos `billetera-virtual-api.postman_collection.json` y `billetera-virtual-environment.postman_environment.json` o haz clic en "Upload Files" y selecciona ambos archivos.
4. Confirma la importación haciendo clic en "Import".

## Configuración del Entorno

1. En la parte superior derecha de Postman, selecciona el entorno "Billetera Virtual - Desarrollo" del menú desplegable.
2. Asegúrate de que los servidores estén en ejecución:
   - API REST (Lumen): `http://localhost:8080`
   - Servicio SOAP (Laravel): `http://localhost:8000`

## Flujo de Pruebas Recomendado

Para probar la API de manera completa, sigue este flujo:

1. **Registrar un Cliente**
   - Endpoint: POST `/clients`
   - Actualiza los valores de `document`, `name`, `email` y `phone` según sea necesario.
   - Después de la respuesta exitosa, los valores de `document` y `phone` ya estarán guardados en las variables de entorno.

2. **Recargar la Billetera**
   - Endpoint: POST `/wallets/recharge`
   - Usa las variables de entorno `{{document}}` y `{{phone}}` que se configuraron en el paso anterior.
   - Ajusta el monto (`amount`) según lo necesites.

3. **Consultar Saldo**
   - Endpoint: GET `/wallets/balance`
   - Usa las variables de entorno `{{document}}` y `{{phone}}`.
   - Verifica que el saldo refleje la recarga realizada.

4. **Iniciar un Pago**
   - Endpoint: POST `/payments/start`
   - Usa las variables de entorno `{{document}}` y `{{phone}}`.
   - Especifica un monto (`amount`) menor o igual al saldo disponible.
   - ⚠️ **IMPORTANTE**: Después de ejecutar esta solicitud, debes capturar manualmente los valores de `session_id` y `token` de la respuesta y guardarlos en las variables de entorno correspondientes.

5. **Confirmar el Pago**
   - Endpoint: POST `/payments/confirm`
   - Usa las variables de entorno `{{session_id}}` y `{{token}}` que configuraste en el paso anterior.
   - Verifica que la respuesta indique éxito y muestre el nuevo saldo.

6. **Verificar el Nuevo Saldo**
   - Endpoint: GET `/wallets/balance`
   - Confirma que el saldo se haya reducido por el monto del pago.

## Descripción de los Endpoints

### Clientes

- **Registrar Cliente (POST `/clients`)**
  - Registra un nuevo cliente en el sistema.
  - Todos los campos son obligatorios: `document`, `name`, `email`, `phone`.
  - Devuelve un código 201 si el cliente se creó correctamente.
  - Devuelve un código 409 si el cliente ya existe.

### Billetera

- **Recargar Billetera (POST `/wallets/recharge`)**
  - Agrega fondos a la billetera de un cliente existente.
  - Requiere: `document`, `phone`, `amount`.
  - El monto debe ser positivo.

- **Consultar Saldo (GET `/wallets/balance`)**
  - Consulta el saldo disponible de un cliente.
  - Requiere: `document`, `phone`.
  - Ambos valores deben coincidir para el mismo cliente.

### Pagos

- **Iniciar Pago (POST `/payments/start`)**
  - Inicia un proceso de pago generando un token y un ID de sesión.
  - Requiere: `document`, `phone`, `amount`.
  - El monto debe ser positivo y no exceder el saldo disponible.
  - **Nota**: En un entorno de producción, el token se enviaría al correo del usuario. En este entorno de desarrollo, se muestra en la respuesta para facilitar las pruebas.

- **Confirmar Pago (POST `/payments/confirm`)**
  - Confirma un pago utilizando el ID de sesión y el token.
  - Requiere: `session_id`, `token`.
  - La transacción debe estar en estado pendiente.
  - Reduce el saldo del cliente por el monto de la transacción.

## Tests Automáticos

La colección incluye tests automáticos para verificar:

- Códigos de estado HTTP correctos
- Estructura de respuesta adecuada
- Datos coherentes

Para ejecutar todos los tests en secuencia:

1. Selecciona la carpeta principal "Billetera Virtual API".
2. Haz clic en el botón "Run" en la parte superior.
3. En la ventana "Collection Runner", asegúrate de que el entorno "Billetera Virtual - Desarrollo" esté seleccionado.
4. Haz clic en "Start Run". 