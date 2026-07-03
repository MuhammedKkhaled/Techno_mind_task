<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('logout', 'logout')->middleware(['auth:sanctum', 'tenant']);
});

Route::prefix('tasks')->middleware(['auth:sanctum', 'tenant'])->controller(TaskController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{task}', 'show');
    Route::match(['put', 'patch'], '/{task}', 'update');
    Route::delete('/{task}', 'destroy');
});
