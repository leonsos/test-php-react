import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import walletService from '../services/api';

const RegisterClient = () => {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    document: '',
    name: '',
    email: '',
    phone: ''
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
      const { document, name, email, phone } = formData;
      console.log("Enviando datos:", { document, name, email, phone });
      
      const result = await walletService.registerClient(document, name, email, phone);
      console.log("Respuesta del servidor:", result);
      
      setMessage({
        type: result.success ? 'success' : 'danger',
        text: result.message
      });
      
      // Si el registro fue exitoso, redirigir a la página principal después de 2 segundos
      if (result.success) {
        setTimeout(() => {
          navigate('/');
        }, 2000);
      }
    } catch (error) {
      console.error("Error completo:", error);
      let errorMessage = 'Error al procesar la solicitud';
      
      if (error.response) {
        console.error("Datos del error:", error.response.data);
        errorMessage = error.response.data.message || errorMessage;
      } else if (error.message) {
        errorMessage = error.message;
      }
      
      setMessage({
        type: 'danger',
        text: errorMessage
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="card">
      <div className="card-header">
        <h3>Registro de Cliente</h3>
      </div>
      <div className="card-body">
        {message && (
          <div className={`alert alert-${message.type}`} role="alert">
            {message.text}
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
            <label htmlFor="name" className="form-label">Nombre Completo</label>
            <input
              type="text"
              className="form-control"
              id="name"
              name="name"
              value={formData.name}
              onChange={handleChange}
              required
            />
          </div>
          <div className="mb-3">
            <label htmlFor="email" className="form-label">Correo Electrónico</label>
            <input
              type="email"
              className="form-control"
              id="email"
              name="email"
              value={formData.email}
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
            {loading ? 'Procesando...' : 'Registrar Cliente'}
          </button>
        </form>
      </div>
    </div>
  );
};

export default RegisterClient; 