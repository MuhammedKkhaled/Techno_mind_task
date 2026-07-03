<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('refresh', 'refresh');
    Route::post('logout', 'logout')->middleware(['auth:sanctum', 'abilities:access-api', 'tenant']);
});

Route::prefix('tasks')->middleware(['auth:sanctum', 'abilities:access-api', 'tenant'])->controller(TaskController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('/{task}', 'show');
    Route::match(['put', 'patch'], '/{task}', 'update');
    Route::delete('/{task}', 'destroy');
});
