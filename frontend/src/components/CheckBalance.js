import React, { useState } from 'react';
import walletService from '../services/api';

const CheckBalance = () => {
  const [formData, setFormData] = useState({
    document: '',
    phone: ''
  });
  const [balance, setBalance] = useState(null);
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
    setBalance(null);

    try {
      const { document, phone } = formData;
      const result = await walletService.getBalance(document, phone);
      
      if (result.success) {
        setBalance(result.data);
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

  return (
    <div className="card">
      <div className="card-header">
        <h3>Consultar Saldo</h3>
      </div>
      <div className="card-body">
        {message && (
          <div className={`alert alert-${message.type}`} role="alert">
            {message.text}
          </div>
        )}

        {balance && (
          <div className="card mb-3">
            <div className="card-body">
              <h5 className="card-title">{balance.name}</h5>
              <h6 className="card-subtitle mb-2 text-muted">Documento: {balance.document}</h6>
              <p className="card-text">
                <strong>Saldo Disponible:</strong> ${balance.balance}
              </p>
            </div>
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
          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? 'Consultando...' : 'Consultar Saldo'}
          </button>
        </form>
      </div>
    </div>
  );
};

export default CheckBalance; 