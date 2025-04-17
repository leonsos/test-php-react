<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

// Wallet API Routes
$router->group(['prefix' => 'api'], function () use ($router) {
    // Registro de cliente
    $router->post('clients', 'WalletController@registerClient');
    
    // Recargar billetera
    $router->post('wallets/recharge', 'WalletController@rechargeWallet');
    
    // Iniciar pago
    $router->post('payments/start', 'WalletController@makePayment');
    
    // Confirmar pago
    $router->post('payments/confirm', 'WalletController@confirmPayment');
    
    // Consultar saldo
    $router->get('wallets/balance', 'WalletController@getBalance');
});
