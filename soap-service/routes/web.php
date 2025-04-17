<?php

use App\Http\Controllers\SoapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// SOAP Service Routes - Sin protección CSRF
Route::prefix('soap')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::get('/wallet/wsdl', [SoapController::class, 'wsdl']);
    Route::post('/wallet', [SoapController::class, 'handle']);
});