<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AngelController;
use App\Http\Controllers\ChartController;

Route::get('/dashboard', [AngelController::class, 'dashboard']);
Route::get('/', [ChartController::class, 'chart']);

// Route::get('/', function () {
//     return view('welcome');
// });
