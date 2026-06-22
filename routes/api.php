<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PersonController;

Route::get('/dni/{dni}', [PersonController::class, 'searchByDni']);
Route::get('/ruc/{ruc}', [PersonController::class, 'searchByRuc']);
