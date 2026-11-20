<?php

use App\Http\Controllers\Api\DteSandboxController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/dte')->group(function () {
    Route::post('/emit', [DteSandboxController::class, 'emit']);
    Route::post('/contingency/dispatch', [DteSandboxController::class, 'dispatchContingency']);
    Route::post('/{codigoGeneracion}/invalidate', [DteSandboxController::class, 'invalidate']);
    Route::get('/{codigoGeneracion}', [DteSandboxController::class, 'show']);
});
