import axios from 'axios';

const API_URL = 'http://localhost:8080/api';

const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Configurar interceptores para mejor manejo de errores
api.interceptors.response.use(
  response => response,
  error => {
    console.error('Error en solicitud API:', error);
    if (error.response) {
      console.error('Respuesta de error:', error.response.data);
      // Si hay una respuesta de error del servidor, la pasamos
      return Promise.reject(error);
    } else if (error.request) {
      console.error('No hubo respuesta:', error.request);
      // Si no hay respuesta (problemas de red)
      return Promise.reject({
        response: {
          data: {
            success: false,
            code: 503,
            message: 'Error de conexión: no se obtuvo respuesta del servidor'
          }
        }
      });
    } else {
      console.error('Error de configuración:', error.message);
      // Error en la configuración de la solicitud
      return Promise.reject({
        response: {
          data: {
            success: false,
            code: 500,
            message: 'Error al crear la solicitud: ' + error.message
          }
        }
      });
    }
  }
);

// Servicio de API
const walletService = {
  // Registrar cliente
  registerClient: async (document, name, email, phone) => {
    try {
      console.log('Enviando solicitud registerClient:', { document, name, email, phone });
      const response = await api.post('/clients', {
        document,
        name,
        email,
        phone
      });
      console.log('Respuesta registerClient:', response.data);
      return response.data;
    } catch (error) {
      console.error('Error en registerClient:', error);
      if (error.response && error.response.data) {
        return error.response.data;
      }
      return {
        success: false,
        code: 500,
        message: error.message || 'Error de conexión con el servidor'
      };
    }
  },

  // Recargar billetera
  rechargeWallet: async (document, phone, amount) => {
    try {
      console.log('Enviando solicitud rechargeWallet:', { document, phone, amount });
      const response = await api.post('/wallets/recharge', {
        document,
        phone,
        amount: parseFloat(amount)
      });
      console.log('Respuesta rechargeWallet:', response.data);
      return response.data;
    } catch (error) {
      console.error('Error en rechargeWallet:', error);
      if (error.response && error.response.data) {
        return error.response.data;
      }
      return {
        success: false,
        code: 500,
        message: error.message || 'Error de conexión con el servidor'
      };
    }
  },

  // Iniciar pago
  makePayment: async (document, phone, amount) => {
    try {
      console.log('Enviando solicitud makePayment:', { document, phone, amount });
      const response = await api.post('/payments/start', {
        document,
        phone,
        amount: parseFloat(amount)
      });
      console.log('Respuesta makePayment:', response.data);
      return response.data;
    } catch (error) {
      console.error('Error en makePayment:', error);
      if (error.response && error.response.data) {
        return error.response.data;
      }
      return {
        success: false,
        code: 500,
        message: error.message || 'Error de conexión con el servidor'
      };
    }
  },

  // Confirmar pago
  confirmPayment: async (sessionId, token) => {
    try {
      console.log('Enviando solicitud confirmPayment:', { sessionId, token });
      const response = await api.post('/payments/confirm', {
        session_id: sessionId,
        token
      });
      console.log('Respuesta confirmPayment:', response.data);
      return response.data;
    } catch (error) {
      console.error('Error en confirmPayment:', error);
      if (error.response && error.response.data) {
        return error.response.data;
      }
      return {
        success: false,
        code: 500,
        message: error.message || 'Error de conexión con el servidor'
      };
    }
  },

  // Consultar saldo
  getBalance: async (document, phone) => {
    try {
      console.log('Enviando solicitud getBalance:', { document, phone });
      const response = await api.get('/wallets/balance', {
        params: {
          document,
          phone
        }
      });
      console.log('Respuesta getBalance:', response.data);
      return response.data;
    } catch (error) {
      console.error('Error en getBalance:', error);
      if (error.response && error.response.data) {
        return error.response.data;
      }
      return {
        success: false,
        code: 500,
        message: error.message || 'Error de conexión con el servidor'
      };
    }
  }
};

export default walletService; 