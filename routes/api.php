<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\CalendarController;

// 5 attempts/minute per IP — cheap brute-force guard on the one endpoint
// that's reachable without a token.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load('role', 'company', 'agency');
    });

    Route::middleware('role.permission')->group(function () {
        Route::apiResource('companies', CompanyController::class);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('users', UserController::class);
        Route::apiResource('shifts', ShiftController::class);
    });
    Route::apiResource('agencies', AgencyController::class);
    Route::apiResource('zones', ZoneController::class);

    Route::get('/calendar', [CalendarController::class, 'index']);
});
