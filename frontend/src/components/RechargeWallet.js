import React, { useState } from 'react';
import walletService from '../services/api';

const RechargeWallet = () => {
  const [formData, setFormData] = useState({
    document: '',
    phone: '',
    amount: ''
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

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setMessage(null);

    try {
      const { document, phone, amount } = formData;
      const result = await walletService.rechargeWallet(document, phone, amount);
      
      setMessage({
        type: result.success ? 'success' : 'danger',
        text: result.message
      });

      if (result.success) {
        setFormData({
          ...formData,
          amount: ''
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

  return (
    <div className="card">
      <div className="card-header">
        <h3>Recargar Billetera</h3>
      </div>
      <div className="card-body">
        {message && (
          <div className={`alert alert-${message.type}`} role="alert">
            {message.text}
            {message.type === 'success' && message.data && (
              <div className="mt-2">
                <strong>Nuevo Saldo:</strong> ${message.data.new_balance}
              </div>
            )}
          </div>
        )}
        <form onSubmit={handleSubmit}>
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
            <label htmlFor="amount" className="form-label">Monto a Recargar</label>
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
            {loading ? 'Procesando...' : 'Recargar Billetera'}
          </button>
        </form>
      </div>
    </div>
  );
};

export default RechargeWallet; 