<?php

use App\Http\Controllers\Web\DteDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DteDashboardController::class, 'index'])->name('dte.dashboard');
Route::post('/dte/emit', [DteDashboardController::class, 'emit'])->name('dte.emit');
Route::post('/dte/contingency', [DteDashboardController::class, 'dispatchContingency'])->name('dte.contingency');
