<?php

use App\Http\Controllers\ClassificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ClassificationController::class, 'index']);
Route::post('/predict', [ClassificationController::class, 'predict']);
