import React, { useState } from 'react';
import walletService from '../services/api';

const MakePayment = () => {
  const [step, setStep] = useState(1);
  const [formData, setFormData] = useState({
    document: '',
    phone: '',
    amount: '',
    sessionId: '',
    token: ''
  });
  const [message, setMessage] = useState(null);
  const [loading, setLoading] = useState(false);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({
      ...formData,
      [name]: value
    });
  };

  const handleStartPayment = async (e) => {
    e.preventDefault();
    setLoading(true);
    setMessage(null);

    try {
      const { document, phone, amount } = formData;
      const result = await walletService.makePayment(document, phone, amount);
      
      if (result.success) {
        setFormData({
          ...formData,
          sessionId: result.data.session_id,
          token: '' // En un entorno real, el token lo recibira por correo
        });
        setStep(2);
        setMessage({
          type: 'success',
          text: result.message
        });
      } else {
        setMessage({
          type: 'danger',
          text: result.message
        });
      }
    } catch (error) {
      setMessage({
        type: 'danger',
        text: 'Error al procesar la solicitud'
      });
    } finally {
      setLoading(false);
    }
  };

  const handleConfirmPayment = async (e) => {
    e.preventDefault();
    setLoading(true);
    setMessage(null);

    try {
      const { sessionId, token } = formData;
      const result = await walletService.confirmPayment(sessionId, token);
      
      if (result.success) {
        setStep(3);
        setMessage({
          type: 'success',
          text: result.message,
          data: result.data
        });
      } else {
        setMessage({
          type: 'danger',
          text: result.message
        });
      }
    } catch (error) {
      setMessage({
        type: 'danger',
        text: 'Error al procesar la solicitud'
      });
    } finally {
      setLoading(false);
    }
  };

  const resetForm = () => {
    setFormData({
      document: '',
      phone: '',
      amount: '',
      sessionId: '',
      token: ''
    });
    setMessage(null);
    setStep(1);
  };

  return (
    <div className="card">
      <div className="card-header">
        <h3>Realizar Pago</h3>
      </div>
      <div className="card-body">
        {message && (
          <div className={`alert alert-${message.type}`} role="alert">
            {message.text}
            {step === 3 && message.type === 'success' && message.data && (
              <div className="mt-2">
                <p><strong>Transacción ID:</strong> {message.data.transaction_id}</p>
                <p><strong>Saldo Restante:</strong> ${message.data.new_balance}</p>
              </div>
            )}
          </div>
        )}

        {step === 1 && (
          <form onSubmit={handleStartPayment}>
            <div className="mb-3">
              <label htmlFor="document" className="form-label">Documento</label>
              <input
                type="text"
                className="form-control"
                id="document"
                name="document"
                value={formData.document}
                onChange={handleChange}
                required
              />
            </div>
            <div className="mb-3">
              <label htmlFor="phone" className="form-label">Número de Celular</label>
              <input
                type="text"
                className="form-control"
                id="phone"
                name="phone"
                value={formData.phone}
                onChange={handleChange}
                required
              />
            </div>
            <div className="mb-3">
              <label htmlFor="amount" className="form-label">Monto a Pagar</label>
              <input
                type="number"
                min="0.01"
                step="0.01"
                className="form-control"
                id="amount"
                name="amount"
                value={formData.amount}
                onChange={handleChange}
                required
              />
            </div>
            <button type="submit" className="btn btn-primary" disabled={loading}>
              {loading ? 'Procesando...' : 'Iniciar Pago'}
            </button>
          </form>
        )}

        {step === 2 && (
          <form onSubmit={handleConfirmPayment}>
            <div className="mb-3">
              <label htmlFor="token" className="form-label">Ingrese el Token de Confirmación</label>
              <p className="text-muted">El token ha sido enviado a su correo electrónico.</p>
              <input
                type="text"
                className="form-control"
                id="token"
                name="token"
                value={formData.token}
                onChange={handleChange}
                required
              />
            </div>
            <button type="submit" className="btn btn-success me-2" disabled={loading}>
              {loading ? 'Confirmando...' : 'Confirmar Pago'}
            </button>
            <button type="button" className="btn btn-outline-secondary" onClick={resetForm}>
              Cancelar
            </button>
          </form>
        )}

        {step === 3 && (
          <div className="text-center mt-3">
            <p className="lead">¡Pago completado con éxito!</p>
            <button type="button" className="btn btn-primary" onClick={resetForm}>
              Realizar Otro Pago
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default MakePayment; 