<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AngelController;
use App\Http\Controllers\SymbolController;

Route::post('/login', [AngelController::class, 'login']);
Route::get('/history', [AngelController::class, 'history']);
Route::get('/ws-token', [AngelController::class, 'wsToken']); 
Route::get('/symbols', [SymbolController::class, 'index']);