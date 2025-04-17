import React from 'react';
import { BrowserRouter as Router, Routes, Route, Link } from 'react-router-dom';
import 'bootstrap/dist/css/bootstrap.min.css';
import './App.css';

// Componentes
import RegisterClient from './components/RegisterClient';
import RechargeWallet from './components/RechargeWallet';
import MakePayment from './components/MakePayment';
import CheckBalance from './components/CheckBalance';

function App() {
  return (
    <Router>
      <div className="App">
        <nav className="navbar navbar-expand-lg navbar-dark bg-primary">
          <div className="container">
            <Link className="navbar-brand" to="/">Billetera Virtual</Link>
            <button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
              <span className="navbar-toggler-icon"></span>
            </button>
            <div className="collapse navbar-collapse" id="navbarNav">
              <ul className="navbar-nav">
                <li className="nav-item">
                  <Link className="nav-link" to="/register">Registrar Cliente</Link>
                </li>
                <li className="nav-item">
                  <Link className="nav-link" to="/recharge">Recargar Billetera</Link>
                </li>
                <li className="nav-item">
                  <Link className="nav-link" to="/payment">Realizar Pago</Link>
                </li>
                <li className="nav-item">
                  <Link className="nav-link" to="/balance">Consultar Saldo</Link>
                </li>
              </ul>
            </div>
          </div>
        </nav>

        <div className="container mt-4">
          <Routes>
            <Route path="/" element={
              <div className="jumbotron text-center">
                <h1 className="display-4">Bienvenido a la Billetera Virtual</h1>
                <p className="lead">
                  Sistema para gestionar pagos electrónicos de manera segura y eficiente.
                </p>
                <hr className="my-4" />
                <p>
                  Registre clientes, recargue billeteras, realice pagos y consulte saldos en tiempo real.
                </p>
                <div className="mt-4">
                  <Link to="/register" className="btn btn-primary me-2">Registrar Cliente</Link>
                  <Link to="/recharge" className="btn btn-success me-2">Recargar Billetera</Link>
                  <Link to="/payment" className="btn btn-warning me-2">Realizar Pago</Link>
                  <Link to="/balance" className="btn btn-info">Consultar Saldo</Link>
                </div>
              </div>
            } />
            <Route path="/register" element={<RegisterClient />} />
            <Route path="/recharge" element={<RechargeWallet />} />
            <Route path="/payment" element={<MakePayment />} />
            <Route path="/balance" element={<CheckBalance />} />
          </Routes>
        </div>

        <footer className="mt-5 py-3 bg-light text-center">
          <div className="container">
            <p>&copy; 2025 Billetera Virtual - Todos los derechos reservados</p>
          </div>
        </footer>
      </div>
    </Router>
  );
}

export default App;
